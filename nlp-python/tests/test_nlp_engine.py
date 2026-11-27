"""Pytest suite for School AAAAA13 EvaluaTech Python calamanCy + VADER + scikit-learn NLP engine."""

from __future__ import annotations

from evaluatech_nlp_py import (
    TAG_UNCLASSIFIED,
    analyze_comment,
    generate_pedagogical_action_plan,
    get_engine_provenance,
    scrub_pii,
    summarize_comments,
)


def test_python_provenance_and_calamancy_loaded() -> None:
    prov = get_engine_provenance()
    assert prov["language_runtime"] == "Python"
    assert prov["vader_loaded"] is True
    assert prov["ml_model_loaded"] is True
    assert prov["calamancy_loaded"] is True


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
