"""End-to-end Python NLP Engine: PII Redaction, calamanCy + VADER + scikit-learn Inference & Action Plans."""

from __future__ import annotations

import re
from functools import lru_cache
from typing import Any

from .calamancy_pipeline import (
    extract_calamancy_features,
    get_calamancy_nlp,
    lemmatize_calamancy_token,
)
from .lexicons import (
    CALAMANCY_REPO_URL,
    INSTITUTION_NAME,
    INSTITUTION_REFERENCE_URL,
    MAX_BATCH_SIZE,
    MAX_COMMENT_LENGTH,
    MAX_PROFESSOR_QUESTIONS,
    PEDAGOGICAL_TAGS,
    TAG_UNCLASSIFIED,
)
from .sentiment_ml import (
    classify_rule_domain,
    classify_valence,
    compute_vader_multilingual_polarity,
    load_sklearn_bundles,
    predict_sklearn_domain,
    predict_sklearn_sentiment,
)

_CONTROL_CHARS_RE = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]")
_PROMPT_INJECTION_RE = re.compile(
    r"<\|system\|>|<\|user\|>|<\|assistant\|>|<\|im_start\|>|<\|im_end\|>|\[inst\]|\[/inst\]",
    re.IGNORECASE,
)
_EMAIL_RE = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}")
_PH_PHONE_RE = re.compile(r"(?:\+639|09)\d{2}[-\s]?\d{3}[-\s]?\d{4}\b")
_STUDENT_ID_RE = re.compile(r"\b20\d{2}[-\s]\d{4,6}\b")


def scrub_pii(text: str) -> str:
    """Redact emails, Philippine mobile numbers, and student ID numbers prior to NLP processing."""
    if not text:
        return ""
    cleaned = _CONTROL_CHARS_RE.sub("", text)
    cleaned = _PROMPT_INJECTION_RE.sub("", cleaned)
    cleaned = _EMAIL_RE.sub("[REDACTED-EMAIL]", cleaned)
    cleaned = _PH_PHONE_RE.sub("[REDACTED-PHONE]", cleaned)
    cleaned = _STUDENT_ID_RE.sub("[REDACTED-ID]", cleaned)
    return cleaned.strip()[:MAX_COMMENT_LENGTH]


@lru_cache(maxsize=1)
def get_engine_provenance() -> dict[str, Any]:
    _, calamancy_ready, active_calamancy = get_calamancy_nlp()
    sent_bundle, dom_bundle, meta = load_sklearn_bundles()
    ml_loaded = sent_bundle is not None and dom_bundle is not None
    sent_meta = meta.get("sentiment_model", {})
    real_test = sent_meta.get("real_comment_test", {})
    dom_meta = meta.get("domain_model", {})
    dom_real = dom_meta.get("real_comment_test", {})

    return {
        "nlp_engine": "Python calamanCy + VADER + scikit-learn",
        "runtime_engine": "Python 3.12 (calamanCy + spaCy + vaderSentiment + scikit-learn Dual-Regime ML)",
        "language_runtime": "Python",
        "calamancy_loaded": calamancy_ready,
        "vader_loaded": True,
        "fallback_heuristic": not ml_loaded,
        "calamancy_model": active_calamancy,
        "calamancy_repo": CALAMANCY_REPO_URL,
        "ml_model_loaded": ml_loaded,
        "ml_engine": "Python scikit-learn Dual-Regime Ensemble (TF-IDF word 1-3 + char_wb 2-5 + VADER 13d Signals)",
        "ml_real_comment_cv_accuracy": sent_meta.get("real_comment_cv_accuracy", 0.0),
        "ml_real_comment_sentiment_accuracy": real_test.get("accuracy", 0.0),
        "ml_real_comment_sentiment_macro_f1": real_test.get("macro_f1", 0.0),
        "ml_real_comment_negative_recall": real_test.get("negative_recall", 0.0),
        "ml_real_comment_per_language": sent_meta.get("real_comment_per_language", {}),
        "ml_real_comment_domain_accuracy": dom_real.get("accuracy", 0.0),
        "ml_real_comment_domain_macro_f1": dom_real.get("macro_f1", 0.0),
        "ml_metadata": meta,
    }


def analyze_comment(raw: str) -> dict[str, Any]:
    """Full Python calamanCy + VADER + scikit-learn analysis for a single student evaluation comment."""
    scrubbed = scrub_pii(raw)
    vader_polarity = compute_vader_multilingual_polarity(scrubbed)
    rule_tag, rule_conf, keywords, domain_scores = classify_rule_domain(scrubbed)
    calamancy_feats = extract_calamancy_features(scrubbed)

    ml_sent = predict_sklearn_sentiment(scrubbed)
    ml_dom = predict_sklearn_domain(scrubbed)
    prov = get_engine_provenance()

    # Primary polarity & valence combine calibrated scikit-learn probabilities with VADER polarity
    if ml_sent is not None and scrubbed:
        ml_pol = float(ml_sent["polarity"])
        polarity = round(0.65 * ml_pol + 0.35 * vader_polarity, 4)
        valence = str(ml_sent["valence"])
        if valence == "Positive" and polarity < 0.05:
            polarity = max(polarity, 0.12)
        elif valence == "Negative" and polarity > -0.05:
            polarity = min(polarity, -0.12)
    else:
        polarity = vader_polarity
        valence = classify_valence(polarity)

    # Preserve strict facility isolation (`Unclassified / Needs Review`) while leveraging ML cluster predictions
    if rule_tag == TAG_UNCLASSIFIED and keywords:
        pedagogical_tag = TAG_UNCLASSIFIED
        confidence = rule_conf
    elif ml_dom is not None and scrubbed:
        pedagogical_tag = str(ml_dom["cluster"])
        confidence = float(ml_dom["confidence"])
    else:
        pedagogical_tag = rule_tag
        confidence = rule_conf

    out: dict[str, Any] = {
        "polarity": polarity,
        "vader_polarity": vader_polarity,
        "valence": valence,
        "pedagogical_tag": pedagogical_tag,
        "confidence": confidence,
        "keywords": keywords,
        "domain_scores": domain_scores,
        "scrubbed_preview": scrubbed,
        "nlp_engine": "Python calamanCy + VADER + scikit-learn",
        "runtime_engine": prov["runtime_engine"],
        "calamancy_loaded": prov["calamancy_loaded"],
        "fallback_heuristic": prov["fallback_heuristic"],
        "runtime_provenance": prov,
        "calamancy": calamancy_feats,
    }
    if ml_sent is not None:
        out["ml_sentiment"] = ml_sent
    if ml_dom is not None:
        out["ml_domain"] = ml_dom
    return out


def analyze_comment_item(item: Any) -> dict[str, Any]:
    if isinstance(item, str):
        return analyze_comment(item)
    if isinstance(item, dict):
        txt = str(item.get("comment") or item.get("text") or "")
        res = analyze_comment(txt)
        if item.get("id") is not None:
            res["id"] = item["id"]
        return res
    return analyze_comment("")


def summarize_comments(items: list[Any]) -> dict[str, Any]:
    tag_breakdown = {tag: 0 for tag in PEDAGOGICAL_TAGS}
    if not items:
        return {
            "total": 0,
            "positive_count": 0,
            "neutral_count": 0,
            "negative_count": 0,
            "positive_pct": 0.0,
            "neutral_pct": 0.0,
            "negative_pct": 0.0,
            "mean_polarity": 0.0,
            "primary_tag": "Pedagogical Delivery",
            "primary_negative_tag": "Pedagogical Delivery",
            "unclassified_count": 0,
            "tag_breakdown": tag_breakdown,
            "watchlist_flagged": False,
        }

    analyzed = [
        it if (isinstance(it, dict) and "polarity" in it and "valence" in it and "pedagogical_tag" in it)
        else analyze_comment_item(it)
        for it in items
    ]

    total = len(analyzed)
    pos = neu = neg = unclass = 0
    pol_sum = 0.0
    counts = {tag: 0 for tag in PEDAGOGICAL_TAGS}
    neg_counts = {tag: 0 for tag in PEDAGOGICAL_TAGS}

    for entry in analyzed:
        val = str(entry.get("valence") or "Neutral")
        tag = str(entry.get("pedagogical_tag") or "Pedagogical Delivery")
        pol_sum += float(entry.get("polarity") or 0.0)
        if tag in counts:
            counts[tag] += 1
            if val == "Negative":
                neg_counts[tag] += 1
        else:
            unclass += 1

        if val == "Positive":
            pos += 1
        elif val == "Negative":
            neg += 1
        else:
            neu += 1

    pos_pct = round((pos / total) * 100.0, 2)
    neu_pct = round((neu / total) * 100.0, 2)
    neg_pct = round((neg / total) * 100.0, 2)
    mean_pol = round(pol_sum / total, 4)

    primary_tag = max(PEDAGOGICAL_TAGS, key=lambda t: (counts[t], neg_counts[t]))
    primary_neg_tag = (
        max(PEDAGOGICAL_TAGS, key=lambda t: (neg_counts[t], counts[t]))
        if neg > 0
        else primary_tag
    )

    return {
        "total": total,
        "positive_count": pos,
        "neutral_count": neu,
        "negative_count": neg,
        "positive_pct": pos_pct,
        "neutral_pct": neu_pct,
        "negative_pct": neg_pct,
        "mean_polarity": mean_pol,
        "primary_tag": primary_tag,
        "primary_negative_tag": primary_neg_tag,
        "unclassified_count": unclass,
        "tag_breakdown": counts,
        "watchlist_flagged": neg_pct > 35.0,
    }


def analyze_professor_questions(payload: dict[str, Any]) -> dict[str, Any]:
    qs = payload.get("questions")
    if isinstance(qs, list) and len(qs) > MAX_PROFESSOR_QUESTIONS:
        return {
            "valid": False,
            "error_code": "MAX_PROFESSOR_QUESTIONS_EXCEEDED",
            "max_allowed": MAX_PROFESSOR_QUESTIONS,
            "submitted_count": len(qs),
        }

    raw_words = payload.get("three_words") or payload.get("q1_three_words") or []
    if isinstance(raw_words, str):
        words = [w.strip() for w in re.split(r"[,;/|\s]+", raw_words) if w.strip()][:3]
    elif isinstance(raw_words, list):
        words = [str(w).strip() for w in raw_words if str(w).strip()][:3]
    else:
        words = []

    lemmas = [lemmatize_calamancy_token(w) for w in words]
    w_nlp = analyze_comment(" ".join(words))

    raw_topics = payload.get("room_for_improvements") or payload.get("q2_room_for_improvements") or []
    if isinstance(raw_topics, str):
        topic_strs = [t.strip() for t in re.split(r"[\r\n;]+", raw_topics) if t.strip()]
    elif isinstance(raw_topics, list):
        topic_strs = [str(t).strip() for t in raw_topics if str(t).strip()]
    else:
        topic_strs = []

    topics = []
    for idx, t in enumerate(topic_strs):
        letter = chr(ord("a") + (idx % 26))
        t_nlp = analyze_comment(t)
        topics.append({
            "sub_item": f"{letter}.",
            "label": f"{letter}. Topic {idx + 1}",
            "topic": t,
            "polarity": t_nlp["polarity"],
            "valence": t_nlp["valence"],
            "pedagogical_tag": t_nlp["pedagogical_tag"],
        })

    q3 = str(payload.get("teaching_strengths") or payload.get("q3_teaching_strengths") or "").strip()
    q4 = str(payload.get("recommendations") or payload.get("q4_recommendations") or "").strip()
    q5 = str(payload.get("additional_observation") or payload.get("q5_additional_observation") or "").strip()

    parts = [p for p in [" ".join(words), *topic_strs, q3, q4, q5] if p]
    combined = analyze_comment(". ".join(parts))

    return {
        "valid": len(words) == 3 and len(topics) > 0,
        "nlp_engine": "Python calamanCy + VADER + scikit-learn",
        "calamancy_repo": CALAMANCY_REPO_URL,
        "q1_three_words": {
            "words": words,
            "word_count": len(lemmas),
            "lemmas": lemmas,
            "polarity": w_nlp["polarity"],
            "valence": w_nlp["valence"],
        },
        "q2_room_for_improvements": {"topic_count": len(topics), "topics": topics},
        "q3_teaching_strengths": {"text": q3, "analysis": analyze_comment(q3) if q3 else None},
        "q4_recommendations": {"text": q4, "analysis": analyze_comment(q4) if q4 else None},
        "q5_additional_observation": {"text": q5, "analysis": analyze_comment(q5)} if q5 else None,
        "combined_sentiment": {
            "polarity": combined["polarity"],
            "valence": combined["valence"],
            "pedagogical_tag": combined["pedagogical_tag"],
            "calamancy": combined["calamancy"],
        },
    }


def generate_pedagogical_action_plan(
    faculty_name: str,
    mean_score: float,
    negative_sentiment_pct: float,
    primary_tag: str | None = None,
) -> dict[str, Any]:
    clean_name = faculty_name.strip() or "Faculty Member"
    tag = primary_tag.strip() if primary_tag and primary_tag.strip() in PEDAGOGICAL_TAGS else "Pedagogical Delivery"

    low_score = mean_score < 3.50
    high_neg = negative_sentiment_pct > 35.0
    if low_score and high_neg:
        trigger_desc = (
            f"Both overall mean score ({mean_score:.2f} < 3.50) and negative sentiment share "
            f"({negative_sentiment_pct:.1f}% > 35.0%) breached institutional thresholds."
        )
        urgency = "HIGH"
    elif low_score:
        trigger_desc = f"Overall mean score ({mean_score:.2f} / 5.00) is below the institutional benchmark of 3.50."
        urgency = "MODERATE"
    elif high_neg:
        trigger_desc = f"Negative qualitative sentiment share ({negative_sentiment_pct:.1f}%) exceeds the 35.0% intervention threshold."
        urgency = "MODERATE"
    else:
        trigger_desc = f"Instructor performance meets baseline standards (Mean: {mean_score:.2f}, Negative: {negative_sentiment_pct:.1f}%)."
        urgency = "STANDARD"

    interventions_map: dict[str, list[str]] = {
        "Assessment & Grading": [
            "Establish a strict 14-day turnaround SLA for returning graded quizzes, assignments, and programming deliverables.",
            "Publish explicit analytic rubrics with criterion point distributions prior to major exams and programming projects.",
            "Conduct structured post-exam feedback review sessions addressing frequently missed concepts.",
        ],
        "Classroom Management": [
            "Enforce strict session start punctuality and instructional time budgeting across all scheduled lecture and lab hours.",
            "Align weekly topic pacing directly with the approved AAAAA13 departmental course syllabus timeline.",
            "Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.",
        ],
        "Professional Conduct": [
            "Maintain an open, empathetic, and professional classroom environment conducive to active student inquiry.",
            "Ensure uniform, impartial application of grading criteria, consultation availability, and academic policies.",
            "Participate in the AAAAA13 Academic Integrity & Student-Centered Pedagogical Communication Seminar.",
        ],
        "Student Support & Mentorship": [
            "Publish and adhere to regular weekly office consultation hours (minimum 4 scheduled hours per week).",
            "Establish dedicated advisory sessions for capstone thesis system design, architecture, and code debugging.",
            "Maintain prompt institutional email responsiveness (within 48 hours on business days).",
        ],
        "Pedagogical Delivery": [
            "Incorporate step-by-step live coding and practical laboratory demonstrations during dynamic programming lectures.",
            "Implement periodic formative check-ins (e.g., 5-minute exit tickets) to calibrate pacing to learner comprehension.",
            "Participate in the AAAAA13 College of Computer Studies Peer Teaching Observation & Active Learning Workshop.",
        ],
    }

    name_code = "".join(ch for ch in clean_name.upper() if ch.isalnum())[:8]
    bucket = int(mean_score * 10.0)

    return {
        "plan_id": f"AAAAA13-ACT-{name_code}-{bucket}",
        "institution": INSTITUTION_NAME,
        "reference": INSTITUTION_REFERENCE_URL,
        "faculty_name": clean_name,
        "mean_score": round(mean_score, 2),
        "negative_sentiment_pct": round(negative_sentiment_pct, 1),
        "primary_cluster": tag,
        "trigger_description": trigger_desc,
        "urgency_level": urgency,
        "recommended_interventions": interventions_map[tag],
        "timeline": "Immediate (Weeks 1-4 of following academic term) with mid-term milestone review",
        "target_milestone": {
            "target_mean_score": 3.80,
            "target_negative_pct": 15.0,
            "target_cluster_improvement": f"+0.50 points in {tag}",
        },
        "dean_signoff_required": True,
        "nlp_engine": "Python calamanCy + VADER + scikit-learn",
    }
