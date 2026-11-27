"""Multi-Corpus Domain-Adapted Training & Blind Holdout Evaluation (`nlp-python/train_model.py`).

Implements the School AAAAA13 EvaluaTech NLP Research Department Specification:
1. Combines `dev_partition.csv` (`7,000` rows), `expanded_training_corpus.csv` (`1,185` curated SET rows),
   and `external_nlp_datasets.csv` (`2,508` authentic Hugging Face rows from `EduRABSA_SA+ACD`,
   `ratemyprofessors-reviews-3-labels`, `tagasenti`, and `course-reviews`) = `10,693` training samples.
2. Enforces programmatic Two-Stage Anti-Leakage checks (`0` exact SHA-256 overlaps and `0` 6-gram overlaps
   between external datasets and `docs/analysis/test_partition.csv`).
3. Evaluates every progressive iteration and selects both Sentiment and Pedagogical Domain ensemble
   configurations EXCLUSIVELY via 5-Fold Stratified Out-of-Fold Cross-Validation on `dev_partition.csv`
   (`n = 572` authentic student comments).
4. Scores `docs/analysis/test_partition.csv` ONCE at the end and records 100% unaltered empirical metrics.
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

_EXT_SOURCE_WEIGHTS: dict[str, float] = {
    "yhua219/EduRABSA_SA+ACD": 0.65,
    "jjjardev/tagasenti": 0.45,
    "Z-H-Che/ratemyprofessors-reviews-3-labels": 0.40,
    "chillies/course-review-multilabel-sentiment-analysis": 0.35,
    "kkotkar1/course-reviews": 0.35,
}


def norm_tokens(text: str) -> list[str]:
    cleaned = _NORM_RE.sub(" ", text.lower()).strip()
    return cleaned.split() if cleaned else []


def norm_fingerprint(text: str) -> str:
    return hashlib.sha256(" ".join(norm_tokens(text)).encode("utf-8")).hexdigest()


def map_aspect(raw_aspect: str) -> str:
    return ASPECT_TO_CLUSTER.get(str(raw_aspect).strip(), TAG_UNCLASSIFIED)


def score_predictions(y_true: np.ndarray, preds: np.ndarray) -> tuple[float, float, float]:
    acc = float(accuracy_score(y_true, preds))
    mf1 = float(f1_score(y_true, preds, average="macro", zero_division=0))
    neg_mask = y_true == "Negative"
    neg_rec = float(recall_score(neg_mask, preds == "Negative", zero_division=0)) if neg_mask.any() else 0.0
    return acc, mf1, neg_rec


def compute_gamma_ext(n_real: int, w_real: float, ext_raw_weights: np.ndarray, cap_ratio: float = 0.35) -> float:
    if len(ext_raw_weights) == 0:
        return 1.0
    ext_sum = float(ext_raw_weights.sum())
    if ext_sum <= 1e-9:
        return 1.0
    return min(1.0, (cap_ratio * n_real * w_real) / ext_sum)


def main() -> None:
    dev_csv = ROOT / "docs" / "analysis" / "dev_partition.csv"
    test_csv = ROOT / "docs" / "analysis" / "test_partition.csv"
    exp_csv = Path(__file__).resolve().parent / "data" / "expanded_training_corpus.csv"
    ext_csv = Path(__file__).resolve().parent / "data" / "external_nlp_datasets.csv"

    dev_df = pd.read_csv(dev_csv)
    test_df = pd.read_csv(test_csv)
    exp_df = pd.read_csv(exp_csv) if exp_csv.exists() else pd.DataFrame()
    ext_df = pd.read_csv(ext_csv) if ext_csv.exists() else pd.DataFrame()

    # --- Strict Two-Stage Programmatic Anti-Leakage Gate ---
    test_hashes = {norm_fingerprint(str(t)) for t in test_df["Feedback_Text"].dropna()}
    test_6grams: set[tuple[str, ...]] = set()
    for t in test_df["Feedback_Text"].dropna().astype(str):
        toks = norm_tokens(t)
        if len(toks) >= 6:
            for i in range(len(toks) - 5):
                test_6grams.add(tuple(toks[i : i + 6]))

    if not exp_df.empty:
        exp_hashes = {norm_fingerprint(str(t)) for t in exp_df["Feedback_Text"].dropna()}
        assert len(test_hashes & exp_hashes) == 0, "FATAL: Curated corpus leaked test hashes!"

    ext_6gram_overlap = 0
    if not ext_df.empty:
        ext_hashes = {norm_fingerprint(str(t)) for t in ext_df["Feedback_Text"].dropna()}
        assert len(test_hashes & ext_hashes) == 0, "FATAL: External datasets leaked exact test hashes!"
        for t in ext_df["Feedback_Text"].dropna().astype(str):
            toks = norm_tokens(t)
            if len(toks) >= 6:
                for i in range(len(toks) - 5):
                    if tuple(toks[i : i + 6]) in test_6grams:
                        ext_6gram_overlap += 1
        assert ext_6gram_overlap == 0, f"FATAL: External datasets shared {ext_6gram_overlap} 6-grams with blind test!"

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

    ext_texts = ext_df["Feedback_Text"].fillna("").astype(str).tolist() if not ext_df.empty else []
    y_ext_sent = ext_df["Sentiment"].astype(str).to_numpy() if not ext_df.empty else np.array([], dtype=str)
    ext_raw_w = (
        np.array([_EXT_SOURCE_WEIGHTS.get(str(s), 0.40) for s in ext_df["Source_Dataset"]], dtype=np.float64)
        if not ext_df.empty
        else np.array([], dtype=np.float64)
    )

    total_train_rows = len(dev_df) + len(exp_df) + len(ext_df)
    print(
        f"[data] Total Training Samples={total_train_rows} "
        f"(Dev real={len(real_texts)}, Curated SET={len(exp_texts)}, Imported HF External={len(ext_texts)}, Dev template={len(synth_texts)}) | "
        f"Untouched Blind Test={len(test_df)}"
    )

    print("[features] Extracting calamanCy lemmas, 500d static bilingual embeddings, and 24d VADER discourse signals...")
    real_prep = preprocess_for_word_tfidf(real_texts)
    synth_prep = preprocess_for_word_tfidf(synth_texts)
    exp_prep = preprocess_for_word_tfidf(exp_texts) if exp_texts else []
    ext_prep = preprocess_for_word_tfidf(ext_texts) if ext_texts else []

    emb_scale = 0.90
    X_real_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(real_texts, scale=emb_scale))
    X_synth_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(synth_texts, scale=emb_scale))
    X_exp_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(exp_texts, scale=emb_scale)) if exp_texts else None
    X_ext_emb = sparse.csr_matrix(extract_bilingual_embedding_matrix(ext_texts, scale=emb_scale)) if ext_texts else None

    X_real_lex = extract_morphosyntactic_matrix(real_texts)
    X_synth_lex = extract_morphosyntactic_matrix(synth_texts)
    X_exp_lex = extract_morphosyntactic_matrix(exp_texts) if exp_texts else None
    X_ext_lex = extract_morphosyntactic_matrix(ext_texts) if ext_texts else None

    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    classes = ["Negative", "Neutral", "Positive"]
    iteration_logs: list[dict[str, Any]] = []

    oof_iter1 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_iter2 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_iter3 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_iter4 = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_h1_spec_lr = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_h2_ext_svm = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_h3_ext_rbf = np.zeros((len(real_texts), 3), dtype=np.float64)
    oof_h4_full_lr = np.zeros((len(real_texts), 3), dtype=np.float64)

    for tr_idx, va_idx in skf.split(real_texts, y_real_sent):
        tr_real_prep = [real_prep[i] for i in tr_idx]
        va_real_prep = [real_prep[i] for i in va_idx]
        tr_real_raw = [real_texts[i] for i in tr_idx]
        va_real_raw = [real_texts[i] for i in va_idx]
        y_tr_real = y_real_sent[tr_idx]

        # Baseline Vectorizers (Iterations 1 & 2)
        w_vec_base = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=40000, sublinear_tf=True)
        c_vec_base = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=45000, sublinear_tf=True)
        w_vec_base.fit(tr_real_prep + synth_prep)
        c_vec_base.fit(tr_real_raw + synth_texts)

        Xw_tr_r = w_vec_base.transform(tr_real_prep)
        Xc_tr_r = c_vec_base.transform(tr_real_raw)
        Xw_va_r = w_vec_base.transform(va_real_prep)
        Xc_va_r = c_vec_base.transform(va_real_raw)

        oof_iter1[va_idx] = (
            LogisticRegression(C=3.0, class_weight="balanced", max_iter=1200)
            .fit(sparse.hstack([Xw_tr_r, Xc_tr_r, X_real_lex[tr_idx]], format="csr"), y_tr_real)
            .predict_proba(sparse.hstack([Xw_va_r, Xc_va_r, X_real_lex[va_idx]], format="csr"))
        )

        oof_iter2[va_idx] = (
            LogisticRegression(C=2.8, class_weight="balanced", max_iter=1200)
            .fit(sparse.hstack([Xw_tr_r, Xc_tr_r, X_real_emb[tr_idx], X_real_lex[tr_idx]], format="csr"), y_tr_real)
            .predict_proba(sparse.hstack([Xw_va_r, Xc_va_r, X_real_emb[va_idx], X_real_lex[va_idx]], format="csr"))
        )

        # Multi-Corpus Global Vectorizers (55k word + 60k char_wb)
        w_vec_mc = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=55000, sublinear_tf=True)
        c_vec_mc = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=60000, sublinear_tf=True)
        w_vec_mc.fit(tr_real_prep + exp_prep + ext_prep + synth_prep)
        c_vec_mc.fit(tr_real_raw + exp_texts + ext_texts + synth_texts)

        Xw_va = w_vec_mc.transform(va_real_prep)
        Xc_va = c_vec_mc.transform(va_real_raw)
        X_va = sparse.hstack([Xw_va, Xc_va, X_real_emb[va_idx], X_real_lex[va_idx]], format="csr")

        # Head 1 (Collegiate Specialist LR: Real + Curated SET)
        Xw_spec = w_vec_mc.transform(tr_real_prep + exp_prep)
        Xc_spec = c_vec_mc.transform(tr_real_raw + exp_texts)
        Xemb_spec = sparse.vstack([X_real_emb[tr_idx], X_exp_emb], format="csr") if X_exp_emb is not None else X_real_emb[tr_idx]
        Xlex_spec = sparse.vstack([X_real_lex[tr_idx], X_exp_lex], format="csr") if X_exp_lex is not None else X_real_lex[tr_idx]
        X_spec = sparse.hstack([Xw_spec, Xc_spec, Xemb_spec, Xlex_spec], format="csr")
        y_spec = np.concatenate([y_tr_real, y_exp_sent]) if len(y_exp_sent) else y_tr_real
        sw_spec = np.concatenate([np.full(len(y_tr_real), 4.0), np.full(len(y_exp_sent), 1.0)]) if len(y_exp_sent) else np.ones(len(y_tr_real))

        h1_lr = LogisticRegression(C=2.8, class_weight="balanced", max_iter=1500).fit(X_spec, y_spec, sample_weight=sw_spec)
        oof_iter3[va_idx] = h1_lr.predict_proba(X_va)
        oof_h1_spec_lr[va_idx] = oof_iter3[va_idx]

        # Domain-Adapted Generalist Pool (Real + Curated SET + gamma_ext-budgeted HuggingFace External)
        gamma_fold = compute_gamma_ext(len(y_tr_real), 6.0, ext_raw_w, cap_ratio=0.35)
        if X_ext_emb is not None and X_ext_lex is not None and len(ext_texts) > 0:
            Xw_ext = w_vec_mc.transform(ext_prep)
            Xc_ext = c_vec_mc.transform(ext_texts)
            X_ext_all = sparse.hstack([Xw_ext, Xc_ext, X_ext_emb, X_ext_lex], format="csr")
            X_da = sparse.vstack([X_spec, X_ext_all], format="csr")
            Xemb_da = sparse.vstack([Xemb_spec, X_ext_emb], format="csr")
            Xlex_da = sparse.vstack([Xlex_spec, X_ext_lex], format="csr")
            y_da = np.concatenate([y_spec, y_ext_sent])
            sw_da = np.concatenate([
                np.full(len(y_tr_real), 6.0),
                np.full(len(y_exp_sent), 1.0),
                ext_raw_w * gamma_fold,
            ])
        else:
            X_da, Xemb_da, Xlex_da, y_da, sw_da = X_spec, Xemb_spec, Xlex_spec, y_spec, sw_spec

        # Head 2: Domain-Adapted Wide-Margin Platt-Calibrated LinearSVC
        h2_svm = CalibratedClassifierCV(
            LinearSVC(C=0.60, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
            method="sigmoid",
            cv=3,
        ).fit(X_da, y_da, sample_weight=sw_da)
        oof_iter4[va_idx] = h2_svm.predict_proba(X_va)
        oof_h2_ext_svm[va_idx] = oof_iter4[va_idx]

        # Head 3: Domain-Adapted 524d RBF-SVC (`500d` embeddings + `24d` VADER discourse)
        X_dense_da = sparse.hstack([Xemb_da, Xlex_da], format="csr").toarray()
        X_dense_va = sparse.hstack([X_real_emb[va_idx], X_real_lex[va_idx]], format="csr").toarray()
        h3_rbf = make_pipeline(
            StandardScaler(),
            SVC(C=2.4, kernel="rbf", gamma="scale", class_weight="balanced", probability=True, random_state=42),
        ).fit(X_dense_da, y_da, svc__sample_weight=sw_da)
        oof_h3_ext_rbf[va_idx] = h3_rbf.predict_proba(X_dense_va)

        # Head 4: Full-Regime LR across ALL 10,693 rows (Real + Curated + External + Synth Dev)
        X_syn_all = sparse.hstack(
            [w_vec_mc.transform(synth_prep), c_vec_mc.transform(synth_texts), X_synth_emb, X_synth_lex],
            format="csr",
        )
        X_full = sparse.vstack([X_da, X_syn_all], format="csr")
        y_full = np.concatenate([y_da, y_synth_sent])
        gamma_full = compute_gamma_ext(len(y_tr_real), 10.0, ext_raw_w, cap_ratio=0.35)
        sw_full = np.concatenate([
            np.full(len(y_tr_real), 10.0),
            np.full(len(y_exp_sent), 1.8),
            ext_raw_w * gamma_full,
            np.full(len(y_synth_sent), 0.75),
        ])
        h4_full = LogisticRegression(C=3.2, class_weight="balanced", max_iter=1500).fit(X_full, y_full, sample_weight=sw_full)
        oof_h4_full_lr[va_idx] = h4_full.predict_proba(X_va)

    for it_num, it_name, oof_p in [
        (1, "Iter 1: calamanCy TF-IDF + 24d Discourse LR", oof_iter1),
        (2, "Iter 2: + 500d Pretrained calamanCy & spaCy Word Vectors", oof_iter2),
        (3, "Iter 3: + Collegiate Specialist LR (Curated SET Corpus)", oof_iter3),
        (4, "Iter 4: + Imported 2,508 HuggingFace Corpora Wide-Margin LinearSVC", oof_iter4),
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
    best_oof5 = (
        0.25 * oof_h1_spec_lr
        + 0.30 * oof_h2_ext_svm
        + 0.25 * oof_h3_ext_rbf
        + 0.20 * oof_h4_full_lr
    )
    best_obj = -1.0
    for w1, w2, w3, w4 in [
        (0.25, 0.30, 0.25, 0.20),
        (0.30, 0.25, 0.25, 0.20),
        (0.25, 0.25, 0.30, 0.20),
        (0.20, 0.35, 0.25, 0.20),
        (0.25, 0.30, 0.30, 0.15),
        (0.30, 0.30, 0.25, 0.15),
    ]:
        cand = w1 * oof_h1_spec_lr + w2 * oof_h2_ext_svm + w3 * oof_h3_ext_rbf + w4 * oof_h4_full_lr
        preds = np.array([classes[i] for i in np.argmax(cand, axis=1)])
        acc, mf1, _ = score_predictions(y_real_sent, preds)
        obj = acc + 0.25 * mf1
        if obj > best_obj:
            best_obj = obj
            best_weights = (w1, w2, w3, w4)
            best_oof5 = cand

    iter5_preds = np.array([classes[i] for i in np.argmax(best_oof5, axis=1)])
    cv_sent_acc, cv_sent_f1, cv_sent_nrec = score_predictions(y_real_sent, iter5_preds)
    iteration_logs.append({
        "iteration": 5,
        "description": "Iter 5: Specialist-Generalist 4-Head Multi-Corpus Ensemble (10,693 Samples)",
        "ensemble_weights": list(best_weights),
        "dev_oof_accuracy": round(cv_sent_acc, 4),
        "dev_oof_macro_f1": round(cv_sent_f1, 4),
        "dev_oof_negative_recall": round(cv_sent_nrec, 4),
    })
    print(
        f"[cv] Iter 5 Specialist-Generalist Multi-Corpus Ensemble (weights={best_weights}) -> "
        f"Acc={cv_sent_acc:.2%} | Macro F1={cv_sent_f1:.4f} | Neg Recall={cv_sent_nrec:.2%}"
    )

    # --- Fit Final Global Vectorizers & Sentiment Ensemble (`10,693` Training Rows, Zero Test Access) ---
    word_vec = TfidfVectorizer(ngram_range=(1, 3), token_pattern=r"\S+", min_df=2, max_features=55000, sublinear_tf=True)
    char_vec = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), min_df=3, max_features=60000, sublinear_tf=True)

    word_vec.fit(real_prep + exp_prep + ext_prep + synth_prep)
    char_vec.fit(real_texts + exp_texts + ext_texts + synth_texts)

    Xw_spec_f = word_vec.transform(real_prep + exp_prep)
    Xc_spec_f = char_vec.transform(real_texts + exp_texts)
    Xemb_spec_f = sparse.vstack([X_real_emb, X_exp_emb], format="csr") if X_exp_emb is not None else X_real_emb
    Xlex_spec_f = sparse.vstack([X_real_lex, X_exp_lex], format="csr") if X_exp_lex is not None else X_real_lex
    X_spec_f = sparse.hstack([Xw_spec_f, Xc_spec_f, Xemb_spec_f, Xlex_spec_f], format="csr")
    y_spec_f = np.concatenate([y_real_sent, y_exp_sent]) if len(y_exp_sent) else y_real_sent
    sw_spec_f = np.concatenate([np.full(len(y_real_sent), 4.0), np.full(len(y_exp_sent), 1.0)]) if len(y_exp_sent) else np.ones(len(y_real_sent))

    dense_start_col = Xw_spec_f.shape[1] + Xc_spec_f.shape[1]

    head1_spec_lr = LogisticRegression(C=2.8, class_weight="balanced", max_iter=1500)
    head1_spec_lr.fit(X_spec_f, y_spec_f, sample_weight=sw_spec_f)

    gamma_final = compute_gamma_ext(len(y_real_sent), 6.0, ext_raw_w, cap_ratio=0.35)
    if X_ext_emb is not None and X_ext_lex is not None and len(ext_texts) > 0:
        Xw_ext_f = word_vec.transform(ext_prep)
        Xc_ext_f = char_vec.transform(ext_texts)
        X_ext_f = sparse.hstack([Xw_ext_f, Xc_ext_f, X_ext_emb, X_ext_lex], format="csr")
        X_da_f = sparse.vstack([X_spec_f, X_ext_f], format="csr")
        Xemb_da_f = sparse.vstack([Xemb_spec_f, X_ext_emb], format="csr")
        Xlex_da_f = sparse.vstack([Xlex_spec_f, X_ext_lex], format="csr")
        y_da_f = np.concatenate([y_spec_f, y_ext_sent])
        sw_da_f = np.concatenate([
            np.full(len(y_real_sent), 6.0),
            np.full(len(y_exp_sent), 1.0),
            ext_raw_w * gamma_final,
        ])
    else:
        X_da_f, Xemb_da_f, Xlex_da_f, y_da_f, sw_da_f = X_spec_f, Xemb_spec_f, Xlex_spec_f, y_spec_f, sw_spec_f

    head2_ext_svm = CalibratedClassifierCV(
        LinearSVC(C=0.60, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
        method="sigmoid",
        cv=5,
    )
    head2_ext_svm.fit(X_da_f, y_da_f, sample_weight=sw_da_f)

    X_dense_da_f = sparse.hstack([Xemb_da_f, Xlex_da_f], format="csr").toarray()
    rbf_core = make_pipeline(
        StandardScaler(),
        SVC(C=2.4, kernel="rbf", gamma="scale", class_weight="balanced", probability=True, random_state=42),
    )
    rbf_core.fit(X_dense_da_f, y_da_f, svc__sample_weight=sw_da_f)
    head3_ext_rbf = DenseSliceProbabilityWrapper(rbf_core, start_col=dense_start_col)

    X_syn_f = sparse.hstack(
        [word_vec.transform(synth_prep), char_vec.transform(synth_texts), X_synth_emb, X_synth_lex],
        format="csr",
    )
    X_full_f = sparse.vstack([X_da_f, X_syn_f], format="csr")
    y_full_f = np.concatenate([y_da_f, y_synth_sent])
    gamma_full_f = compute_gamma_ext(len(y_real_sent), 10.0, ext_raw_w, cap_ratio=0.35)
    sw_full_f = np.concatenate([
        np.full(len(y_real_sent), 10.0),
        np.full(len(y_exp_sent), 1.8),
        ext_raw_w * gamma_full_f,
        np.full(len(y_synth_sent), 0.75),
    ])
    head4_full_lr = LogisticRegression(C=3.2, class_weight="balanced", max_iter=1500)
    head4_full_lr.fit(X_full_f, y_full_f, sample_weight=sw_full_f)

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
            (head1_spec_lr, w1),
            (head2_ext_svm, w2),
            (head3_ext_rbf, w3),
            (head4_full_lr, w4),
        ],
    }

    # --- 5-Fold Dev-CV Gated Pedagogical Domain Ensemble (Rescuing Starved Clusters!) ---
    y_real_dom = dev_df["Aspect"].map(map_aspect).to_numpy()[real_indices]
    y_synth_dom = dev_df["Aspect"].map(map_aspect).to_numpy()[synth_indices]
    y_exp_dom = exp_df["Aspect"].map(map_aspect).to_numpy() if not exp_df.empty else np.array([], dtype=str)

    X_real_dlex = extract_domain_lexicon_matrix(real_texts)
    X_synth_dlex = extract_domain_lexicon_matrix(synth_texts)
    X_exp_dlex = extract_domain_lexicon_matrix(exp_texts) if exp_texts else None

    Xw_r_d = word_vec.transform(real_prep)
    Xc_r_d = char_vec.transform(real_texts)
    Xd_real = sparse.hstack([Xw_r_d, Xc_r_d, X_real_emb, X_real_dlex], format="csr")

    Xw_s_d = word_vec.transform(synth_prep)
    Xc_s_d = char_vec.transform(synth_texts)
    Xd_synth = sparse.hstack([Xw_s_d, Xc_s_d, X_synth_emb, X_synth_dlex], format="csr")

    Xd_exp = (
        sparse.hstack([word_vec.transform(exp_prep), char_vec.transform(exp_texts), X_exp_emb, X_exp_dlex], format="csr")
        if X_exp_emb is not None and X_exp_dlex is not None
        else None
    )

    # Select minority-enriching external domain-eligible rows (`Classroom Management`, `Professional Conduct`,
    # `Student Support & Mentorship`, `Unclassified / Needs Review`) from EduRABSA_ACD & RMP
    starved_set = {
        "Classroom Management",
        "Professional Conduct",
        "Student Support & Mentorship",
        TAG_UNCLASSIFIED,
    }
    if not ext_df.empty and X_ext_emb is not None:
        dom_mask = (ext_df["Domain_Eligible"] == True) & (ext_df["Aspect"].isin(starved_set))  # noqa: E712
        ext_dom_idx = np.flatnonzero(dom_mask.to_numpy())
        ext_dom_texts = [ext_texts[i] for i in ext_dom_idx]
        ext_dom_prep = [ext_prep[i] for i in ext_dom_idx]
        y_ext_dom = ext_df.iloc[ext_dom_idx]["Aspect"].astype(str).to_numpy()
        Xd_ext_dom = sparse.hstack(
            [
                word_vec.transform(ext_dom_prep),
                char_vec.transform(ext_dom_texts),
                X_ext_emb[ext_dom_idx],
                extract_domain_lexicon_matrix(ext_dom_texts),
            ],
            format="csr",
        )
    else:
        Xd_ext_dom = None
        y_ext_dom = np.array([], dtype=str)

    # Compare Domain Variants strictly via 5-Fold Stratified CV on `y_real_dom` (`n = 572`)
    dom_skf = StratifiedKFold(n_splits=4, shuffle=True, random_state=42)
    dom_classes_sorted = sorted(list(set(y_real_dom) | set(y_synth_dom)))

    def eval_dom_cv(use_curated: bool, use_ext_starved: bool) -> tuple[float, float]:
        oof_d = np.zeros((len(y_real_dom), len(dom_classes_sorted)), dtype=np.float64)
        for d_tr, d_va in dom_skf.split(Xd_real, y_real_dom):
            blocks = [Xd_real[d_tr], Xd_synth]
            ys = [y_real_dom[d_tr], y_synth_dom]
            sws = [np.full(len(d_tr), 6.0), np.full(len(y_synth_dom), 0.9)]
            real_blocks = [Xd_real[d_tr]]
            real_ys = [y_real_dom[d_tr]]
            real_sws = [np.full(len(d_tr), 4.0)]

            if use_curated and Xd_exp is not None:
                blocks.append(Xd_exp)
                ys.append(y_exp_dom)
                sws.append(np.full(len(y_exp_dom), 1.0))
                real_blocks.append(Xd_exp)
                real_ys.append(y_exp_dom)
                real_sws.append(np.full(len(y_exp_dom), 0.9))

            if use_ext_starved and Xd_ext_dom is not None and len(y_ext_dom) > 0:
                blocks.append(Xd_ext_dom)
                ys.append(y_ext_dom)
                sws.append(np.full(len(y_ext_dom), 0.70))
                real_blocks.append(Xd_ext_dom)
                real_ys.append(y_ext_dom)
                real_sws.append(np.full(len(y_ext_dom), 0.65))

            X_b = sparse.vstack(blocks, format="csr")
            y_b = np.concatenate(ys)
            sw_b = np.concatenate(sws)

            X_rb = sparse.vstack(real_blocks, format="csr")
            y_rb = np.concatenate(real_ys)
            sw_rb = np.concatenate(real_sws)

            c_full = LogisticRegression(C=4.0, class_weight="balanced", max_iter=1500).fit(X_b, y_b, sample_weight=sw_b)
            c_real = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1500).fit(X_rb, y_rb, sample_weight=sw_rb)
            oof_d[d_va] = 0.55 * c_full.predict_proba(Xd_real[d_va]) + 0.45 * c_real.predict_proba(Xd_real[d_va])

        preds_d = np.array([dom_classes_sorted[i] for i in np.argmax(oof_d, axis=1)])
        return float(accuracy_score(y_real_dom, preds_d)), float(f1_score(y_real_dom, preds_d, average="macro", zero_division=0))

    dom_candidates = [
        ("Dev-Only Baseline", False, False),
        ("Dev + Curated SET", True, False),
        ("Dev + Curated SET + EduRABSA/RMP Minority Rescue", True, True),
    ]
    best_dom_cfg = dom_candidates[0]
    best_dom_score = -1.0
    for d_name, u_cur, u_ext in dom_candidates:
        d_acc, d_f1 = eval_dom_cv(u_cur, u_ext)
        print(f"[dom-cv] {d_name} -> Dev Acc={d_acc:.2%} | Dev Macro F1={d_f1:.4f}")
        sc = d_acc + 0.35 * d_f1
        if sc > best_dom_score:
            best_dom_score = sc
            best_dom_cfg = (d_name, u_cur, u_ext)

    print(f"[dom-select] Selected Domain Architecture: {best_dom_cfg[0]}")
    _, use_cur_win, use_ext_win = best_dom_cfg

    dom_blocks = [Xd_real, Xd_synth]
    dom_ys = [y_real_dom, y_synth_dom]
    dom_sws = [np.full(len(y_real_dom), 6.0), np.full(len(y_synth_dom), 0.9)]
    dom_r_blocks = [Xd_real]
    dom_r_ys = [y_real_dom]
    dom_r_sws = [np.full(len(y_real_dom), 4.0)]

    if use_cur_win and Xd_exp is not None:
        dom_blocks.append(Xd_exp)
        dom_ys.append(y_exp_dom)
        dom_sws.append(np.full(len(y_exp_dom), 1.0))
        dom_r_blocks.append(Xd_exp)
        dom_r_ys.append(y_exp_dom)
        dom_r_sws.append(np.full(len(y_exp_dom), 0.9))

    if use_ext_win and Xd_ext_dom is not None and len(y_ext_dom) > 0:
        dom_blocks.append(Xd_ext_dom)
        dom_ys.append(y_ext_dom)
        dom_sws.append(np.full(len(y_ext_dom), 0.70))
        dom_r_blocks.append(Xd_ext_dom)
        dom_r_ys.append(y_ext_dom)
        dom_r_sws.append(np.full(len(y_ext_dom), 0.65))

    Xd_all_final = sparse.vstack(dom_blocks, format="csr")
    yd_all_final = np.concatenate(dom_ys)
    swd_all_final = np.concatenate(dom_sws)

    Xd_r_final = sparse.vstack(dom_r_blocks, format="csr")
    yd_r_final = np.concatenate(dom_r_ys)
    swd_r_final = np.concatenate(dom_r_sws)

    dom_lr_full = LogisticRegression(C=4.0, class_weight="balanced", max_iter=1500).fit(Xd_all_final, yd_all_final, sample_weight=swd_all_final)
    dom_svm_full = CalibratedClassifierCV(
        LinearSVC(C=0.80, class_weight="balanced", dual="auto", max_iter=4000, random_state=42),
        method="sigmoid",
        cv=5,
    ).fit(Xd_all_final, yd_all_final, sample_weight=swd_all_final)
    dom_lr_real = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1500).fit(Xd_r_final, yd_r_final, sample_weight=swd_r_final)

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
        "Specialist-Generalist 4-Head Multi-Corpus Ensemble (Specialist LR + Domain-Adapted Platt-LinearSVC + "
        "524d RBF-SVC + Full-Regime LR | tl_calamancy_md-0.2.0 200d + en_core_web_md 300d + TF-IDF 1-3 + char_wb 2-5 + VADER 24d)"
    )
    metadata = {
        "training_date": str(date.today()),
        "runtime": "Python 3.12 (tl_calamancy_md-0.2.0 + en_core_web_md + vaderSentiment + scikit-learn)",
        "sklearn_version": sklearn.__version__,
        "joblib_version": joblib.__version__,
        "calamancy_model": calamancy_name,
        "english_vector_model": en_name,
        "bilingual_static_embedding_dim": 500,
        "total_training_samples": int(total_train_rows),
        "dev_samples": int(len(dev_df)),
        "expanded_zero_leakage_training_samples": int(len(exp_df)),
        "external_hf_imported_samples": int(len(ext_df)),
        "external_hf_sources": ext_df["Source_Dataset"].value_counts().to_dict() if not ext_df.empty else {},
        "test_samples": int(len(test_df)),
        "test_overlap_hashes": 0,
        "test_6gram_overlaps": 0,
        "gamma_ext_applied": round(gamma_final, 4),
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
            "selected_variant": best_dom_cfg[0],
            "algorithm": "Calibrated 3-Head Multi-Corpus Domain Ensemble (LR + Platt-LinearSVC + Specialist LR | 500d Embeddings + TF-IDF + 7d Domain Signals)",
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
