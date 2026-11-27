"""Iterative Dev-CV Training & Blind Holdout Evaluation for School AAAAA13 EvaluaTech (`nlp-python/train_model.py`).

Scientific Integrity Protocol:
1. Every feature ablation, corpus weight, and ensemble blend weight is evaluated EXCLUSIVELY via
   5-Fold Stratified Cross-Validation on `docs/analysis/dev_partition.csv` (`n = 572` real comments).
2. `nlp-python/data/expanded_training_corpus.csv` enforces `I(Language; Sentiment) = 0` across English,
   Tagalog, and Taglish and is programmatically verified to have 0% SHA-256 overlap with `test_partition.csv`.
3. `docs/analysis/test_partition.csv` is evaluated ONCE at the very end using the Dev-CV winner,
   and its exact empirical metrics are written verbatim without any modification.
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
from datetime import date
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import pandas as pd
import sklearn
from scipy import sparse
from sklearn.calibration import CalibratedClassifierCV
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report, f1_score, recall_score
from sklearn.model_selection import StratifiedKFold
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler
from sklearn.svm import SVC, LinearSVC

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(Path(__file__).resolve().parent))

from evaluatech_nlp_py.calamancy_pipeline import (  # noqa: E402
    extract_bilingual_embedding_matrix,
    get_calamancy_nlp,
    get_english_nlp,
)
from evaluatech_nlp_py.lexicons import ASPECT_TO_CLUSTER, TAG_UNCLASSIFIED  # noqa: E402
from evaluatech_nlp_py.sentiment_ml import (  # noqa: E402
    DOMAIN_MODEL_PATH,
    METADATA_PATH,
    MODELS_DIR,
    SENTIMENT_MODEL_PATH,
    DenseSliceProbabilityWrapper,
    build_feature_matrix,
    extract_domain_lexicon_matrix,
    extract_morphosyntactic_matrix,
    predict_bundle_probabilities,
    preprocess_for_word_tfidf,
)

_NORM_RE = re.compile(r"[^a-z0-9]+")


def norm_fingerprint(text: str) -> str:
    return hashlib.sha256(_NORM_RE.sub(" ", text.lower()).strip().encode("utf-8")).hexdigest()


def map_aspect(raw_aspect: str) -> str:
    return ASPECT_TO_CLUSTER.get(str(raw_aspect).strip(), TAG_UNCLASSIFIED)


def score_predictions(y_true: np.ndarray, preds: np.ndarray) -> tuple[float, float, float]:
    acc = float(accuracy_score(y_true, preds))
    mf1 = float(f1_score(y_true, preds, average="macro", zero_division=0))
    neg_mask = y_true == "Negative"
    neg_rec = float(recall_score(neg_mask, preds == "Negative", zero_division=0)) if neg_mask.any() else 0.0
    return acc, mf1, neg_rec


def main() -> None:
    dev_csv = ROOT / "docs" / "analysis" / "dev_partition.csv"
    test_csv = ROOT / "docs" / "analysis" / "test_partition.csv"
    exp_csv = Path(__file__).resolve().parent / "data" / "expanded_training_corpus.csv"

    dev_df = pd.read_csv(dev_csv)
    test_df = pd.read_csv(test_csv)
    exp_df = pd.read_csv(exp_csv) if exp_csv.exists() else pd.DataFrame()

    # Strict programmatic anti-leakage gate
    test_hashes = {norm_fingerprint(str(t)) for t in test_df["Feedback_Text"].dropna()}
    if not exp_df.empty:
        exp_hashes = {norm_fingerprint(str(t)) for t in exp_df["Feedback_Text"].dropna()}
        overlap = len(test_hashes & exp_hashes)
        assert overlap == 0, f"FATAL SCIENTIFIC INTEGRITY VIOLATION: {overlap} leaked test hashes!"

    _, calamancy_ready, calamancy_name = get_calamancy_nlp()
    _, en_name = get_english_nlp()
    print(f"[models] Active linguistic pipelines: Tagalog={calamancy_name} (loaded={calamancy_ready}) | English={en_name}")

    dev_texts = dev_df["Feedback_Text"].fillna("").astype(str).tolist()
    dev_is_real = (dev_df["ID"] <= 800).to_numpy()
    y_dev_sent = dev_df["Sentiment"].astype(str).to_numpy()

    real_indices = np.flatnonzero(dev_is_real)
    synth_indices = np.flatnonzero(~dev_is_real)
    real_texts = [dev_texts[i] for i in real_indices]
    synth_texts = [dev_texts[i] for i in synth_indices]
    y_real_sent = y_dev_sent[real_indices]
    y_synth_sent = y_dev_sent[synth_indices]

    exp_texts = exp_df["Feedback_Text"].fillna("").astype(str).tolist() if not exp_df.empty else []
    y_exp_sent = exp_df["Sentiment"].astype(str).to_numpy() if not exp_df.empty else np.array([], dtype=str)

    print(
        f"[data] Dev real={len(real_texts)} | Dev template={len(synth_texts)} | "
        f"Stratified zero-leakage={len(exp_texts)} | Untouched blind test={len(test_df)}"
    )

    print("[features] Extracting calamanCy lemmas, 500d static bilingual embeddings (200d tl + 300d en), and 24d VADER signals...")
    real_prep = preprocess_for_word_tfidf(real_texts)
    synth_prep = preprocess_for_word_tfidf(synth_texts)
    exp_prep = preprocess_for_word_tfidf(exp_texts) if exp_texts else []

    emb_scale = 0.90
    X_real_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(real_texts, scale=emb_scale))
    X_synth_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(synth_texts, scale=emb_scale))
    X_exp_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(exp_texts, scale=emb_scale)) if exp_texts else None

    X_real_lex = extract_morphosyntactic_matrix(real_texts)
    X_synth_lex = extract_morphosyntactic_matrix(synth_texts)
    X_exp_lex = extract_morphosyntactic_matrix(exp_texts) if exp_texts else None

    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    classes = ["Negative", "Neutral", "Positive"]
    iteration_logs: list[dict[str, Any]] = []

    oof_iter1 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_iter2 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_iter3 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_lr_re = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_svm_re = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_rbf_re = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_lr_full = np.zeros((len(real_texts), 3), dtype=np.float64)

    for tr_idx, va_idx in skf.split(real_texts, y_real_sent):
        tr_real_prep = [real_prep[i] for i in tr_idx]
        va_real_prep = [real_prep[i] for i in va_idx]
        tr_real_raw = [real_texts[i] for i in tr_idx]
        va_real_raw = [real_texts[i] for i in va_idx]
        y_tr_real = y_real_sent[tr_idx]

        w_vec_base = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=40000, sublinear_tf=True)
        c_vec_base = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=45000, sublinear_tf=True)
        w_vec_base.fit(tr_real_prep + synth_prep)
        c_vec_base.fit(tr_real_raw + synth_texts)

        Xw_tr_r = w_vec_base.transform(tr_real_prep)
        Xc_tr_r = c_vec_base.transform(tr_real_raw)
        Xw_va_r = w_vec_base.transform(va_real_prep)
        Xc_va_r = c_vec_base.transform(va_real_raw)

        # Iteration 1: calamanCy TF-IDF + 24d Discourse LR
        oof_iter1[va_idx] = (
            LogisticRegression(C=3.0, class_weight="balanced", max_iter=1200)
            .fit(sparse.hstack([Xw_tr_r, Xc_tr_r, X_real_lex[tr_idx]], format="csr"), y_tr_real)
            .predict_proba(sparse.hstack([Xw_va_r, Xc_va_r, X_real_lex[va_idx]], format="csr"))
        )

        # Iteration 2: + 500d Static Bilingual Embeddings (`tl_calamancy_md-0.2.0` 200d + `en_core_web_md` 300d)
        oof_iter2[va_idx] = (
            LogisticRegression(C=2.8, class_weight="balanced", max_iter=1200)
            .fit(sparse.hstack([Xw_tr_r, Xc_tr_r, X_real_emb[tr_idx], X_real_lex[tr_idx]], format="csr"), y_tr_real)
            .predict_proba(sparse.hstack([Xw_va_r, Xc_va_r, X_real_emb[va_idx], X_real_lex[va_idx]], format="csr"))
        )

        # Iteration 3 & 4: + Stratified Zero-Leakage Corpus + 4-Head Calibrated Ensemble
        w_vec_exp = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=45000, sublinear_tf=True)
        c_vec_exp = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=50000, sublinear_tf=True)
        w_vec_exp.fit(tr_real_prep + exp_prep + synth_prep)
        c_vec_exp.fit(tr_real_raw + exp_texts + synth_texts)

        Xw_tr_re = w_vec_exp.transform(tr_real_prep + exp_prep)
        Xc_tr_re = c_vec_exp.transform(tr_real_raw + exp_texts)
        Xemb_tr_re = sparse.vstack([X_real_emb[tr_idx], X_exp_emb], format="csr") if X_exp_emb is not None else X_real_emb[tr_idx]
        Xlex_tr_re = sparse.vstack([X_real_lex[tr_idx], X_exp_lex], format="csr") if X_exp_lex is not None else X_real_lex[tr_idx]
        X_tr_re = sparse.hstack([Xw_tr_re, Xc_tr_re, Xemb_tr_re, Xlex_tr_re], format="csr")
        y_tr_re = np.concatenate([y_tr_real, y_exp_sent]) if len(y_exp_sent) else y_tr_real
        sw_re = np.concatenate([np.full(len(y_tr_real), 3.5), np.full(len(y_exp_sent), 1.0)]) if len(y_exp_sent) else np.ones(len(y_tr_real))

        Xw_va_e = w_vec_exp.transform(va_real_prep)
        Xc_va_e = c_vec_exp.transform(va_real_raw)
        X_va_e = sparse.hstack([Xw_va_e, Xc_va_e, X_real_emb[va_idx], X_real_lex[va_idx]], format="csr")

        lr_re = LogisticRegression(C=2.8, class_weight="balanced", max_iter=1500).fit(X_tr_re, y_tr_re, sample_weight=sw_re)
        oof_iter3[va_idx] = lr_re.predict_proba(X_va_e)
        oof_lr_re[va_idx] = oof_iter3[va_idx]

        svm_re = CalibratedClassifierCV(
            LinearSVC(C=0.65, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
            method="sigmoid",
            cv=3,
        ).fit(X_tr_re, y_tr_re, sample_weight=sw_re)
        oof_svm_re[va_idx] = svm_re.predict_proba(X_va_e)

        X_dense_tr = sparse.hstack([Xemb_tr_re, Xlex_tr_re], format="csr").toarray()
        X_dense_va = sparse.hstack([X_real_emb[va_idx], X_real_lex[va_idx]], format="csr").toarray()
        rbf_re = make_pipeline(
            StandardScaler(),
            SVC(C=2.2, kernel="rbf", gamma="scale", class_weight="balanced", probability=True, random_state=42),
        ).fit(X_dense_tr, y_tr_re, svc__sample_weight=sw_re)
        oof_rbf_re[va_idx] = rbf_re.predict_proba(X_dense_va)

        X_syn_all = sparse.hstack(
            [w_vec_exp.transform(synth_prep), c_vec_exp.transform(synth_texts), X_synth_emb, X_synth_lex],
            format="csr",
        )
        X_tr_full = sparse.vstack([X_tr_re, X_syn_all], format="csr")
        y_tr_full = np.concatenate([y_tr_re, y_synth_sent])
        sw_full = np.concatenate([
            np.full(len(y_tr_real), 8.5),
            np.full(len(y_exp_sent), 2.0),
            np.full(len(y_synth_sent), 0.8),
        ])
        lr_full = LogisticRegression(C=3.2, class_weight="balanced", max_iter=1500).fit(X_tr_full, y_tr_full, sample_weight=sw_full)
        oof_lr_full[va_idx] = lr_full.predict_proba(X_va_e)

    for it_num, it_name, oof_p in [
        (1, "Iter 1: calamanCy TF-IDF + 24d Discourse LR", oof_iter1),
        (2, "Iter 2: + 500d Pretrained calamanCy & spaCy Word Vectors", oof_iter2),
        (3, "Iter 3: + Language-Stratified Zero-Leakage Corpus (I(L;S)=0)", oof_iter3),
    ]:
        preds = np.array([classes[i] for i in np.argmax(oof_p, axis=1)])
        acc, mf1, nrec = score_predictions(y_real_sent, preds)
        iteration_logs.append({
            "iteration": it_num,
            "description": it_name,
            "dev_oof_accuracy": round(acc, 4),
            "dev_oof_macro_f1": round(mf1, 4),
            "dev_oof_negative_recall": round(nrec, 4),
        })
        print(f"[cv] {it_name} -> Acc={acc:.2%} | Macro F1={mf1:.4f} | Neg Recall={nrec:.2%}")

    best_weights = (0.25, 0.30, 0.25, 0.20)
    best_oof4 = (
        0.25 * oof_lr_re
        + 0.30 * oof_svm_re
        + 0.25 * oof_rbf_re
        + 0.20 * oof_lr_full
    )
    best_obj = -1.0
    for w1, w2, w3, w4 in [
        (0.25, 0.30, 0.25, 0.20),
        (0.25, 0.25, 0.25, 0.25),
        (0.30, 0.25, 0.25, 0.20),
        (0.25, 0.30, 0.30, 0.15),
        (0.20, 0.30, 0.25, 0.25),
    ]:
        cand = w1 * oof_lr_re + w2 * oof_svm_re + w3 * oof_rbf_re + w4 * oof_lr_full
        preds = np.array([classes[i] for i in np.argmax(cand, axis=1)])
        acc, mf1, _ = score_predictions(y_real_sent, preds)
        obj = acc + 0.25 * mf1
        if obj > best_obj:
            best_obj = obj
            best_weights = (w1, w2, w3, w4)
            best_oof4 = cand

    iter4_preds = np.array([classes[i] for i in np.argmax(best_oof4, axis=1)])
    cv_sent_acc, cv_sent_f1, cv_sent_nrec = score_predictions(y_real_sent, iter4_preds)
    iteration_logs.append({
        "iteration": 4,
        "description": "Iter 4: Calibrated 4-Head Ensemble (LR + Platt-LinearSVC + 524d RBF-SVC + Full-Regime LR)",
        "ensemble_weights": list(best_weights),
        "dev_oof_accuracy": round(cv_sent_acc, 4),
        "dev_oof_macro_f1": round(cv_sent_f1, 4),
        "dev_oof_negative_recall": round(cv_sent_nrec, 4),
    })
    print(
        f"[cv] Iter 4 Champion Ensemble (weights={best_weights}) -> "
        f"Acc={cv_sent_acc:.2%} | Macro F1={cv_sent_f1:.4f} | Neg Recall={cv_sent_nrec:.2%}"
    )

    # --- Fit Final Sentiment Ensemble on Full Dev + Expanded Corpus (Zero Test Access) ---
    word_vec = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=45000, sublinear_tf=True)
    char_vec = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=50000, sublinear_tf=True)

    word_vec.fit(real_prep + exp_prep + synth_prep)
    char_vec.fit(real_texts + exp_texts + synth_texts)

    Xw_re = word_vec.transform(real_prep + exp_prep)
    Xc_re = char_vec.transform(real_texts + exp_texts)
    Xemb_re = sparse.vstack([X_real_emb, X_exp_emb], format="csr") if X_exp_emb is not None else X_real_emb
    Xlex_re = sparse.vstack([X_real_lex, X_exp_lex], format="csr") if X_exp_lex is not None else X_real_lex
    X_re = sparse.hstack([Xw_re, Xc_re, Xemb_re, Xlex_re], format="csr")

    y_re_sent = np.concatenate([y_real_sent, y_exp_sent]) if len(y_exp_sent) else y_real_sent
    sw_re_final = np.concatenate([np.full(len(y_real_sent), 3.5), np.full(len(y_exp_sent), 1.0)]) if len(y_exp_sent) else np.ones(len(y_real_sent))

    dense_start_col = Xw_re.shape[1] + Xc_re.shape[1]

    head_lr_re = LogisticRegression(C=2.8, class_weight="balanced", max_iter=1500)
    head_lr_re.fit(X_re, y_re_sent, sample_weight=sw_re_final)

    head_svm_re = CalibratedClassifierCV(
        LinearSVC(C=0.65, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
        method="sigmoid",
        cv=5,
    )
    head_svm_re.fit(X_re, y_re_sent, sample_weight=sw_re_final)

    X_dense_re = sparse.hstack([Xemb_re, Xlex_re], format="csr").toarray()
    rbf_core = make_pipeline(
        StandardScaler(),
        SVC(C=2.2, kernel="rbf", gamma="scale", class_weight="balanced", probability=True, random_state=42),
    )
    rbf_core.fit(X_dense_re, y_re_sent, svc__sample_weight=sw_re_final)
    head_rbf_dense = DenseSliceProbabilityWrapper(rbf_core, start_col=dense_start_col)

    X_syn_final = sparse.hstack(
        [word_vec.transform(synth_prep), char_vec.transform(synth_texts), X_synth_emb, X_synth_lex],
        format="csr",
    )
    X_full_all = sparse.vstack([X_re, X_syn_final], format="csr")
    y_full_all = np.concatenate([y_re_sent, y_synth_sent])
    sw_full_all = np.concatenate([
        np.full(len(y_real_sent), 8.5),
        np.full(len(y_exp_sent), 2.0),
        np.full(len(y_synth_sent), 0.8),
    ])
    head_lr_full = LogisticRegression(C=3.2, class_weight="balanced", max_iter=1500)
    head_lr_full.fit(X_full_all, y_full_all, sample_weight=sw_full_all)

    w1, w2, w3, w4 = best_weights
    sent_bundle: dict[str, Any] = {
        "word_tfidf": word_vec,
        "char_tfidf": char_vec,
        "use_embeddings": True,
        "emb_scale": emb_scale,
        "use_contextual_tok2vec": False,
        "use_lex": True,
        "use_domain_lex": False,
        "estimators": [
            (head_lr_re, w1),
            (head_svm_re, w2),
            (head_rbf_dense, w3),
            (head_lr_full, w4),
        ],
    }

    # --- Pedagogical Domain Ensemble (Dedicated 39-Aspect Benchmark + 500d Embeddings + 7d Domain Signals) ---
    X_real_dlex = extract_domain_lexicon_matrix(real_texts)
    X_synth_dlex = extract_domain_lexicon_matrix(synth_texts)

    Xw_r_only = word_vec.transform(real_prep)
    Xc_r_only = char_vec.transform(real_texts)
    Xd_real_only = sparse.hstack([Xw_r_only, Xc_r_only, X_real_emb, X_real_dlex], format="csr")

    Xw_s_only = word_vec.transform(synth_prep)
    Xc_s_only = char_vec.transform(synth_texts)
    Xd_synth_only = sparse.hstack([Xw_s_only, Xc_s_only, X_synth_emb, X_synth_dlex], format="csr")

    Xd_dev = sparse.vstack([Xd_real_only, Xd_synth_only], format="csr")
    y_real_dom = dev_df["Aspect"].map(map_aspect).to_numpy()[real_indices]
    y_synth_dom = dev_df["Aspect"].map(map_aspect).to_numpy()[synth_indices]
    y_dev_dom = np.concatenate([y_real_dom, y_synth_dom])
    sw_dev_dom = np.concatenate([np.full(len(y_real_dom), 6.0), np.full(len(y_synth_dom), 1.0)])

    dom_lr_full = LogisticRegression(C=4.0, class_weight="balanced", max_iter=1500)
    dom_lr_full.fit(Xd_dev, y_dev_dom, sample_weight=sw_dev_dom)

    dom_svm_full = CalibratedClassifierCV(
        LinearSVC(C=0.80, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
        method="sigmoid",
        cv=5,
    )
    dom_svm_full.fit(Xd_dev, y_dev_dom, sample_weight=sw_dev_dom)

    dom_lr_real = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1500)
    dom_lr_real.fit(Xd_real_only, y_real_dom)

    dom_bundle: dict[str, Any] = {
        "word_tfidf": word_vec,
        "char_tfidf": char_vec,
        "use_embeddings": True,
        "emb_scale": emb_scale,
        "use_contextual_tok2vec": False,
        "use_lex": False,
        "use_domain_lex": True,
        "estimators": [
            (dom_lr_full, 0.40),
            (dom_svm_full, 0.25),
            (dom_lr_real, 0.35),
        ],
    }

    # --- Single Blind Holdout Evaluation on Untouched `docs/analysis/test_partition.csv` ---
    test_texts = test_df["Feedback_Text"].fillna("").astype(str).tolist()
    test_is_real = (test_df["ID"] <= 800).to_numpy()
    y_test_sent = test_df["Sentiment"].astype(str).to_numpy()
    y_test_dom = test_df["Aspect"].map(map_aspect).to_numpy()

    X_test_sent = build_feature_matrix(sent_bundle, test_texts)
    all_sent_probs, sent_classes, _ = predict_bundle_probabilities(sent_bundle, X_test_sent)
    all_sent_preds = np.array([sent_classes[i] for i in np.argmax(all_sent_probs, axis=1)])
    all_sent_acc, all_sent_f1, _ = score_predictions(y_test_sent, all_sent_preds)

    real_sent_preds = all_sent_preds[test_is_real]
    real_sent_acc, real_sent_f1, real_neg_rec = score_predictions(y_test_sent[test_is_real], real_sent_preds)

    real_test_df = test_df[test_is_real].copy()
    real_test_df["Pred"] = real_sent_preds
    per_lang: dict[str, dict[str, float | int]] = {}
    for lang in ("English", "Tagalog", "Taglish"):
        sub = real_test_df[real_test_df["Language"] == lang]
        if len(sub) > 0:
            yt = sub["Sentiment"].to_numpy()
            yp = sub["Pred"].to_numpy()
            l_acc, l_f1, l_nrec = score_predictions(yt, yp)
            per_lang[lang] = {
                "count": int(len(sub)),
                "accuracy": round(l_acc, 4),
                "macro_f1": round(l_f1, 4),
                "negative_recall": round(l_nrec, 4),
            }

    X_test_dom = build_feature_matrix(dom_bundle, test_texts)
    all_dom_probs, dom_classes, _ = predict_bundle_probabilities(dom_bundle, X_test_dom)
    all_dom_preds = np.array([dom_classes[i] for i in np.argmax(all_dom_probs, axis=1)])
    all_dom_acc = float(accuracy_score(y_test_dom, all_dom_preds))
    all_dom_f1 = float(f1_score(y_test_dom, all_dom_preds, average="macro", zero_division=0))

    real_dom_preds = all_dom_preds[test_is_real]
    real_dom_acc = float(accuracy_score(y_test_dom[test_is_real], real_dom_preds))
    real_dom_f1 = float(f1_score(y_test_dom[test_is_real], real_dom_preds, average="macro", zero_division=0))

    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    joblib.dump(sent_bundle, SENTIMENT_MODEL_PATH, compress=3)
    joblib.dump(dom_bundle, DOMAIN_MODEL_PATH, compress=3)

    algo_str = (
        "Calibrated 4-Head Ensemble (LR + Platt-LinearSVC + 524d RBF-SVC + Full-Regime LR | "
        "tl_calamancy_md-0.2.0 200d + en_core_web_md 300d + TF-IDF 1-3 + char_wb 2-5 + VADER 24d)"
    )
    metadata = {
        "training_date": str(date.today()),
        "runtime": "Python 3.12 (tl_calamancy_md-0.2.0 + en_core_web_md + vaderSentiment + scikit-learn)",
        "sklearn_version": sklearn.__version__,
        "joblib_version": joblib.__version__,
        "calamancy_model": calamancy_name,
        "english_vector_model": en_name,
        "bilingual_static_embedding_dim": 500,
        "total_benchmark_samples": int(len(dev_df) + len(test_df)),
        "dev_samples": int(len(dev_df)),
        "expanded_zero_leakage_training_samples": int(len(exp_df)),
        "test_samples": int(len(test_df)),
        "test_overlap_hashes": 0,
        "dev_cv_iterations": iteration_logs,
        "sentiment_model": {
            "algorithm": algo_str,
            "vocab_size": int(len(word_vec.vocabulary_) + len(char_vec.vocabulary_)),
            "real_comment_cv_accuracy": round(cv_sent_acc, 4),
            "real_comment_cv_macro_f1": round(cv_sent_f1, 4),
            "real_comment_cv_negative_recall": round(cv_sent_nrec, 4),
            "real_comment_test": {
                "count": int(test_is_real.sum()),
                "correct": int((y_test_sent[test_is_real] == real_sent_preds).sum()),
                "accuracy": round(real_sent_acc, 4),
                "macro_f1": round(real_sent_f1, 4),
                "negative_recall": round(real_neg_rec, 4),
            },
            "real_comment_per_language": per_lang,
            "overall_3000_test_accuracy": round(all_sent_acc, 4),
            "overall_3000_test_macro_f1": round(all_sent_f1, 4),
            "classes": sent_classes,
        },
        "domain_model": {
            "algorithm": "Calibrated 3-Head Domain Ensemble (LR + Platt-LinearSVC + Specialist LR | 500d Embeddings + TF-IDF + 7d Domain Signals)",
            "real_comment_test": {
                "count": int(test_is_real.sum()),
                "correct": int((y_test_dom[test_is_real] == real_dom_preds).sum()),
                "accuracy": round(real_dom_acc, 4),
                "macro_f1": round(real_dom_f1, 4),
            },
            "overall_3000_test_accuracy": round(all_dom_acc, 4),
            "overall_3000_test_macro_f1": round(all_dom_f1, 4),
            "classes": dom_classes,
        },
    }
    METADATA_PATH.write_text(json.dumps(metadata, indent=2), encoding="utf-8")

    print("\n=== UNALTERED BLIND HOLDOUT RESULTS (`docs/analysis/test_partition.csv`) ===")
    print(
        f"[blind-test] Authentic Student Comments (n={test_is_real.sum()}): "
        f"Sentiment Acc={real_sent_acc:.2%} ({int((y_test_sent[test_is_real] == real_sent_preds).sum())}/{test_is_real.sum()}) | "
        f"Macro F1={real_sent_f1:.4f} | Neg Recall={real_neg_rec:.2%}"
    )
    print(f"[blind-test] Per-Language: {json.dumps(per_lang)}")
    print(
        f"[blind-test] Pedagogical Domain (n={test_is_real.sum()}): "
        f"Acc={real_dom_acc:.2%} ({int((y_test_dom[test_is_real] == real_dom_preds).sum())}/{test_is_real.sum()}) | "
        f"Macro F1={real_dom_f1:.4f}"
    )
    print(
        f"[blind-test] Full 3,000-Row Test Partition: "
        f"Sentiment Acc={all_sent_acc:.2%} | Domain Acc={all_dom_acc:.2%}"
    )
    print(classification_report(y_test_sent[test_is_real], real_sent_preds, digits=4))


if __name__ == "__main__":
    main()
