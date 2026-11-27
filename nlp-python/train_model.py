"""Train and validate the Python calamanCy + VADER + scikit-learn Dual-Regime ML pipelines."""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import sklearn
from scipy import sparse
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, f1_score, recall_score
from sklearn.model_selection import StratifiedKFold

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(Path(__file__).resolve().parent))

from evaluatech_nlp_py.lexicons import ASPECT_TO_CLUSTER, TAG_UNCLASSIFIED  # noqa: E402
from evaluatech_nlp_py.sentiment_ml import (  # noqa: E402
    DOMAIN_MODEL_PATH,
    METADATA_PATH,
    MODELS_DIR,
    SENTIMENT_MODEL_PATH,
    extract_morphosyntactic_matrix,
    preprocess_for_word_tfidf,
)


def map_aspect(raw_aspect: str) -> str:
    return ASPECT_TO_CLUSTER.get(str(raw_aspect).strip(), TAG_UNCLASSIFIED)


def evaluate_dual_head(
    clf_full: LogisticRegression,
    clf_real: LogisticRegression,
    X: sparse.csr_matrix,
    y_true: np.ndarray,
    alpha_real: float,
) -> tuple[np.ndarray, float, float, float]:
    probs = (1.0 - alpha_real) * clf_full.predict_proba(X) + alpha_real * clf_real.predict_proba(X)
    preds = clf_full.classes_[np.argmax(probs, axis=1)]
    acc = float(accuracy_score(y_true, preds))
    mf1 = float(f1_score(y_true, preds, average="macro", zero_division=0))
    neg_mask = y_true == "Negative"
    neg_rec = float(recall_score(y_true == "Negative", preds == "Negative", zero_division=0)) if neg_mask.any() else 0.0
    return preds, acc, mf1, neg_rec


def main() -> None:
    dev_csv = ROOT / "docs" / "analysis" / "dev_partition.csv"
    test_csv = ROOT / "docs" / "analysis" / "test_partition.csv"
    dev_df = pd.read_csv(dev_csv)
    test_df = pd.read_csv(test_csv)

    dev_texts = dev_df["Feedback_Text"].fillna("").astype(str).tolist()
    test_texts = test_df["Feedback_Text"].fillna("").astype(str).tolist()

    dev_is_real = (dev_df["ID"] <= 800).to_numpy()
    test_is_real = (test_df["ID"] <= 800).to_numpy()

    print(f"[train] Loaded dev={len(dev_df)} (real={dev_is_real.sum()}) | test={len(test_df)} (real={test_is_real.sum()})")

    # 1. Shared calamanCy-lemmatized + negation-scoped TF-IDF + char_wb + VADER 13d features
    word_vec = TfidfVectorizer(
        ngram_range=(1, 3),
        token_pattern=r"\S+",
        min_df=2,
        max_features=35000,
        sublinear_tf=True,
    )
    char_vec = TfidfVectorizer(
        analyzer="char_wb",
        ngram_range=(2, 5),
        min_df=3,
        max_features=40000,
        sublinear_tf=True,
    )

    X_dev_w = word_vec.fit_transform(preprocess_for_word_tfidf(dev_texts))
    X_dev_c = char_vec.fit_transform(dev_texts)
    X_dev_lex = extract_morphosyntactic_matrix(dev_texts)
    X_dev = sparse.hstack([X_dev_w, X_dev_c, X_dev_lex], format="csr")

    X_test_w = word_vec.transform(preprocess_for_word_tfidf(test_texts))
    X_test_c = char_vec.transform(test_texts)
    X_test_lex = extract_morphosyntactic_matrix(test_texts)
    X_test = sparse.hstack([X_test_w, X_test_c, X_test_lex], format="csr")

    # 2. Sentiment Model: 5-fold CV on authentic student comments + Dual-Regime fit
    y_dev_sent = dev_df["Sentiment"].astype(str).to_numpy()
    y_test_sent = test_df["Sentiment"].astype(str).to_numpy()

    X_dev_real = X_dev[dev_is_real]
    y_dev_real_sent = y_dev_sent[dev_is_real]

    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    cv_preds = np.empty_like(y_dev_real_sent)
    for tr_idx, va_idx in skf.split(X_dev_real, y_dev_real_sent):
        fold_clf = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1000)
        fold_clf.fit(X_dev_real[tr_idx], y_dev_real_sent[tr_idx])
        cv_preds[va_idx] = fold_clf.predict(X_dev_real[va_idx])

    cv_sent_acc = round(float(accuracy_score(y_dev_real_sent, cv_preds)), 4)
    cv_sent_f1 = round(float(f1_score(y_dev_real_sent, cv_preds, average="macro")), 4)

    sample_weights = np.where(dev_is_real, 8.0, 1.0)
    clf_sent_full = LogisticRegression(C=4.0, class_weight="balanced", max_iter=1000)
    clf_sent_full.fit(X_dev, y_dev_sent, sample_weight=sample_weights)

    clf_sent_real = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1000)
    clf_sent_real.fit(X_dev_real, y_dev_real_sent)

    alpha_sent = 0.55
    _, all_sent_acc, all_sent_f1, _ = evaluate_dual_head(clf_sent_full, clf_sent_real, X_test, y_test_sent, alpha_sent)
    real_preds, real_sent_acc, real_sent_f1, real_neg_rec = evaluate_dual_head(
        clf_sent_full, clf_sent_real, X_test[test_is_real], y_test_sent[test_is_real], alpha_sent
    )

    real_test_df = test_df[test_is_real].copy()
    real_test_df["Pred"] = real_preds
    per_lang: dict[str, dict[str, float | int]] = {}
    for lang in ("English", "Tagalog", "Taglish"):
        sub = real_test_df[real_test_df["Language"] == lang]
        if len(sub) > 0:
            yt = sub["Sentiment"].to_numpy()
            yp = sub["Pred"].to_numpy()
            per_lang[lang] = {
                "count": int(len(sub)),
                "accuracy": round(float(accuracy_score(yt, yp)), 4),
                "macro_f1": round(float(f1_score(yt, yp, average="macro", zero_division=0)), 4),
                "negative_recall": round(float(recall_score(yt == "Negative", yp == "Negative", zero_division=0)), 4),
            }

    # 3. Pedagogical Domain Model (5 Likert Clusters + Unclassified)
    y_dev_dom = dev_df["Aspect"].map(map_aspect).to_numpy()
    y_test_dom = test_df["Aspect"].map(map_aspect).to_numpy()
    y_dev_real_dom = y_dev_dom[dev_is_real]

    X_dev_dom = sparse.hstack([X_dev_w, X_dev_c], format="csr")
    X_test_dom = sparse.hstack([X_test_w, X_test_c], format="csr")

    clf_dom_full = LogisticRegression(C=4.0, class_weight="balanced", max_iter=1000)
    clf_dom_full.fit(X_dev_dom, y_dev_dom, sample_weight=np.where(dev_is_real, 6.0, 1.0))

    clf_dom_real = LogisticRegression(C=3.0, class_weight="balanced", max_iter=1000)
    clf_dom_real.fit(X_dev_dom[dev_is_real], y_dev_real_dom)

    alpha_dom = 0.45
    _, all_dom_acc, all_dom_f1, _ = evaluate_dual_head(clf_dom_full, clf_dom_real, X_test_dom, y_test_dom, alpha_dom)
    _, real_dom_acc, real_dom_f1, _ = evaluate_dual_head(
        clf_dom_full, clf_dom_real, X_test_dom[test_is_real], y_test_dom[test_is_real], alpha_dom
    )

    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    joblib.dump(
        {
            "word_tfidf": word_vec,
            "char_tfidf": char_vec,
            "use_lex": True,
            "alpha_real": alpha_sent,
            "clf_full": clf_sent_full,
            "clf_real": clf_sent_real,
        },
        SENTIMENT_MODEL_PATH,
        compress=3,
    )
    joblib.dump(
        {
            "word_tfidf": word_vec,
            "char_tfidf": char_vec,
            "use_lex": False,
            "alpha_real": alpha_dom,
            "clf_full": clf_dom_full,
            "clf_real": clf_dom_real,
        },
        DOMAIN_MODEL_PATH,
        compress=3,
    )

    metadata = {
        "training_date": str(date.today()),
        "runtime": "Python (calamanCy + spaCy + vaderSentiment + scikit-learn)",
        "sklearn_version": sklearn.__version__,
        "joblib_version": joblib.__version__,
        "total_samples": int(len(dev_df) + len(test_df)),
        "dev_samples": int(len(dev_df)),
        "test_samples": int(len(test_df)),
        "sentiment_model": {
            "algorithm": "Dual-Regime LogisticRegression + calamanCy TF-IDF (1,3) + char_wb (2,5) + VADER 13d Signals",
            "vocab_size": int(len(word_vec.vocabulary_) + len(char_vec.vocabulary_)),
            "real_comment_cv_accuracy": cv_sent_acc,
            "real_comment_cv_macro_f1": cv_sent_f1,
            "real_comment_test": {
                "count": int(test_is_real.sum()),
                "accuracy": round(real_sent_acc, 4),
                "macro_f1": round(real_sent_f1, 4),
                "negative_recall": round(real_neg_rec, 4),
            },
            "real_comment_per_language": per_lang,
            "overall_3000_test_accuracy": round(all_sent_acc, 4),
            "overall_3000_test_macro_f1": round(all_sent_f1, 4),
            "classes": list(clf_sent_full.classes_),
        },
        "domain_model": {
            "algorithm": "Dual-Regime LogisticRegression + calamanCy TF-IDF (1,3) + char_wb (2,5)",
            "real_comment_test": {
                "count": int(test_is_real.sum()),
                "accuracy": round(real_dom_acc, 4),
                "macro_f1": round(real_dom_f1, 4),
            },
            "overall_3000_test_accuracy": round(all_dom_acc, 4),
            "overall_3000_test_macro_f1": round(all_dom_f1, 4),
            "classes": list(clf_dom_full.classes_),
        },
    }
    METADATA_PATH.write_text(json.dumps(metadata, indent=2), encoding="utf-8")

    print(
        f"[train] Blind Real-Comment Test (n={test_is_real.sum()}): "
        f"Sentiment Acc={real_sent_acc:.2%} | Macro F1={real_sent_f1:.4f} | Neg Recall={real_neg_rec:.2%} | "
        f"Domain Acc={real_dom_acc:.2%}"
    )


if __name__ == "__main__":
    main()
