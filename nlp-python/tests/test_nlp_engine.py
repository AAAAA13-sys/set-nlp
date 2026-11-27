"""Pytest suite for School AAAAA13 EvaluaTech Python calamanCy + VADER + scikit-learn NLP engine."""

from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score

from evaluatech_nlp_py import (
    TAG_UNCLASSIFIED,
    analyze_comment,
    generate_pedagogical_action_plan,
    get_engine_provenance,
    scrub_pii,
    summarize_comments,
)
from evaluatech_nlp_py.calamancy_pipeline import extract_bilingual_embedding_matrix
from evaluatech_nlp_py.sentiment_ml import (
    METADATA_PATH,
    build_feature_matrix,
    load_sklearn_bundles,
    predict_bundle_probabilities,
)

ROOT = Path(__file__).resolve().parent.parent.parent
_NORM_RE = re.compile(r"[^a-z0-9]+")


def _norm_hash(text: str) -> str:
    return hashlib.sha256(_NORM_RE.sub(" ", text.lower()).strip().encode("utf-8")).hexdigest()


def test_python_provenance_and_pretrained_calamancy_vectors() -> None:
    prov = get_engine_provenance()
    assert prov["language_runtime"] == "Python"
    assert prov["vader_loaded"] is True
    assert prov["ml_model_loaded"] is True
    assert prov["calamancy_loaded"] is True
    assert "tl_calamancy_md" in prov["calamancy_model"]

    emb = extract_bilingual_embedding_matrix([
        "Napakalinaw magpaliwanag ng guro sa klase.",
        "Clear explanations and fair grading rubrics.",
    ])
    assert emb.shape == (2, 500)
    # Verify both 200d Tagalog calamanCy slice and 300d English spaCy slice are non-zero
    assert float(np.linalg.norm(emb[0, :200])) > 0.1
    assert float(np.linalg.norm(emb[1, 200:])) > 0.1


def test_multilingual_calamancy_vader_and_sklearn_predictions() -> None:
    r_pos = analyze_comment(
        "Explains complex algorithms very clearly and provides great practical code examples."
    )
    assert r_pos["valence"] == "Positive"
    assert r_pos["pedagogical_tag"] == "Pedagogical Delivery"
    assert r_pos["polarity"] > 0.20

    r_neg_en = analyze_comment(
        "Takes over a month to return quiz scores and provides no helpful feedback."
    )
    assert r_neg_en["valence"] == "Negative"
    assert r_neg_en["pedagogical_tag"] == "Assessment & Grading"
    assert r_neg_en["polarity"] < -0.20

    # Comparative negation ('rather than') and remedial positive ('common mistakes')
    r_comp_neg = analyze_comment(
        "Exams reward rote memorization of slides rather than genuine analytical understanding."
    )
    assert r_comp_neg["valence"] == "Negative"

    r_remedial_pos = analyze_comment(
        "Walks the class through common quiz mistakes and allows students to check grading errors."
    )
    assert r_remedial_pos["valence"] == "Positive"

    r_tl_pos = analyze_comment(
        "Sobrang malinaw magpaliwanag si Ma'am at matulungin kapag may tanong sa consultation."
    )
    assert r_tl_pos["valence"] == "Positive"
    assert r_tl_pos["calamancy"]["language"] in ("tl", "tl-en (Taglish)")
    assert "linaw" in r_tl_pos["calamancy"]["lemmas"]

    r_tl_neg = analyze_comment(
        "Hindi nagbibigay ng grade breakdown hanggang matapos ang semester at walang rubrics sa project."
    )
    assert r_tl_neg["valence"] == "Negative"
    assert r_tl_neg["pedagogical_tag"] == "Assessment & Grading"


def test_facility_isolation_and_pii_redaction() -> None:
    scrubbed = scrub_pii("Email me at 2024-10892 or juan@aaaaa13.edu or 0917-123-4567")
    assert "2024-10892" not in scrubbed
    assert "juan@aaaaa13.edu" not in scrubbed
    assert "0917-123-4567" not in scrubbed
    assert "[REDACTED-ID]" in scrubbed
    assert "[REDACTED-EMAIL]" in scrubbed
    assert "[REDACTED-PHONE]" in scrubbed

    lib = analyze_comment("The library facilities lack updated reference books and journals.")
    assert lib["pedagogical_tag"] == TAG_UNCLASSIFIED


def test_zero_leakage_and_unaltered_blind_holdout_reproducibility() -> None:
    test_df = pd.read_csv(ROOT / "docs" / "analysis" / "test_partition.csv")
    exp_csv = ROOT / "nlp-python" / "data" / "expanded_training_corpus.csv"
    assert exp_csv.exists()
    exp_df = pd.read_csv(exp_csv)

    test_hashes = {_norm_hash(str(t)) for t in test_df["Feedback_Text"].dropna()}
    exp_hashes = {_norm_hash(str(t)) for t in exp_df["Feedback_Text"].dropna()}
    assert len(test_hashes & exp_hashes) == 0, "Expanded training corpus must have 0% overlap with blind test set"

    sent_bundle, _, meta = load_sklearn_bundles()
    assert sent_bundle is not None
    meta_disk = json.loads(METADATA_PATH.read_text(encoding="utf-8"))

    real_sub = test_df[test_df["ID"] <= 800]
    X_real = build_feature_matrix(sent_bundle, real_sub["Feedback_Text"].astype(str).tolist())
    probs, classes, _ = predict_bundle_probabilities(sent_bundle, X_real)
    preds = np.array([classes[i] for i in np.argmax(probs, axis=1)])
    live_acc = round(float(accuracy_score(real_sub["Sentiment"].to_numpy(), preds)), 4)

    recorded_acc = meta_disk["sentiment_model"]["real_comment_test"]["accuracy"]
    assert live_acc == recorded_acc, "Recorded metadata accuracy must match unaltered live model inference"
    assert live_acc > 0.9123, f"Expected blind real-comment accuracy > 91.23% baseline, got {live_acc:.4f}"


def test_external_huggingface_datasets_manifest_and_two_stage_zero_leakage() -> None:
    ext_csv = ROOT / "nlp-python" / "data" / "external_nlp_datasets.csv"
    manifest_path = ROOT / "nlp-python" / "data" / "external_datasets_manifest.json"
    assert ext_csv.exists() and manifest_path.exists()

    ext_df = pd.read_csv(ext_csv)
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    assert len(ext_df) >= 2000
    assert manifest["research_department_approved"] is True
    assert manifest["anti_leakage_audit"]["final_test_exact_hash_overlap"] == 0
    assert manifest["anti_leakage_audit"]["final_test_5gram_overlap"] == 0
    assert manifest["anti_leakage_audit"]["final_test_6gram_overlap"] == 0

    expected_sources = {
        "yhua219/EduRABSA_SA+ACD",
        "Z-H-Che/ratemyprofessors-reviews-3-labels",
        "jjjardev/tagasenti",
        "kkotkar1/course-reviews",
        "chillies/course-review-multilabel-sentiment-analysis",
    }
    assert expected_sources.issubset(set(ext_df["Source_Dataset"].unique()))

    # Verify independent Stage A (SHA-256) and Stage B (5-gram) disjointness against blind test set
    test_df = pd.read_csv(ROOT / "docs" / "analysis" / "test_partition.csv")
    test_hashes = {_norm_hash(str(t)) for t in test_df["Feedback_Text"].dropna()}
    ext_hashes = {_norm_hash(str(t)) for t in ext_df["Feedback_Text"].dropna()}
    assert len(test_hashes & ext_hashes) == 0

    test_5grams: set[tuple[str, ...]] = set()
    for t in test_df["Feedback_Text"].dropna().astype(str):
        toks = _NORM_RE.sub(" ", t.lower()).split()
        if len(toks) >= 5:
            for i in range(len(toks) - 4):
                test_5grams.add(tuple(toks[i : i + 5]))
    for t in ext_df["Feedback_Text"].dropna().astype(str):
        toks = _NORM_RE.sub(" ", t.lower()).split()
        if len(toks) >= 5:
            for i in range(len(toks) - 4):
                assert tuple(toks[i : i + 5]) not in test_5grams



def test_batch_summary_and_action_plan() -> None:
    summary = summarize_comments([
        "Takes over a month to return quiz scores and provides no helpful feedback.",
        "Unfair grading with zero rubrics.",
        "Clear explanations.",
    ])
    assert summary["total"] == 3
    assert summary["negative_count"] == 2
    assert summary["watchlist_flagged"] is True

    plan = generate_pedagogical_action_plan("Prof. K. Gomez", 3.12, 66.7, "Assessment & Grading")
    assert plan["urgency_level"] == "HIGH"
    assert plan["primary_cluster"] == "Assessment & Grading"
