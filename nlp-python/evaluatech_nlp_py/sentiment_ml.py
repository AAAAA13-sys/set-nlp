"""Python VADER Sentiment Polarity + scikit-learn Dual-Regime Multilingual ML Inference (`sentiment_ml.py`)."""

from __future__ import annotations

import json
import math
import re
from functools import lru_cache
from pathlib import Path
from typing import Any

import joblib
import numpy as np
from scipy import sparse
from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer

from .calamancy_pipeline import find_tokens, lemmatize_calamancy_token
from .lexicons import (
    BOOSTER_DICT,
    DOMAIN_LEXICONS,
    FACILITY_KEYWORDS,
    MULTILINGUAL_VADER_LEXICON,
    NEGATIVE_THRESHOLD,
    NEGATION_SCALAR,
    NEGATION_WORDS,
    NEUTRAL_PATTERNS,
    PEDAGOGICAL_TAGS,
    POSITIVE_THRESHOLD,
    SENTIMENT_PHRASES,
    TAG_UNCLASSIFIED,
    VADER_ALPHA,
)

MODELS_DIR = Path(__file__).resolve().parent.parent / "models"
SENTIMENT_MODEL_PATH = MODELS_DIR / "sentiment_model.joblib"
DOMAIN_MODEL_PATH = MODELS_DIR / "domain_model.joblib"
METADATA_PATH = MODELS_DIR / "model_metadata.json"

_CLAUSE_SPLIT_RE = re.compile(r"[,;:.!?()]+")
_CONTRASTIVE_WORDS = frozenset({
    "but", "however", "though", "although", "yet", "pero", "kaso", "kaya", "ngunit", "subalit",
})
_CRITICAL_DEGREE_WORDS = frozenset({
    "too", "overly", "excessively", "masyadong", "masyado", "sobrang", "sobra", "napaka", "puro", "biglang", "bigla",
})
_NEUTRAL_CUE_WORDS = frozenset({
    "standard", "typical", "normal", "reasonable", "moderate", "moderately",
    "average", "acceptable", "adequate", "fairly", "sakto", "katamtaman", "okay",
})


@lru_cache(maxsize=1)
def get_vader_analyzer() -> SentimentIntensityAnalyzer:
    """Instantiate Python VADER SentimentIntensityAnalyzer augmented with Filipino/Taglish lexicons."""
    sia = SentimentIntensityAnalyzer()
    sia.lexicon.update(MULTILINGUAL_VADER_LEXICON)
    return sia


def classify_valence(polarity: float) -> str:
    if polarity >= POSITIVE_THRESHOLD:
        return "Positive"
    if polarity <= NEGATIVE_THRESHOLD:
        return "Negative"
    return "Neutral"


def compute_vader_multilingual_polarity(text: str) -> float:
    """Compute hybrid Python VADER + Filipino/Taglish morphosyntactic compound polarity in [-1.0, +1.0]."""
    cleaned = " ".join(text.strip().split())
    if not cleaned:
        return 0.0

    lowered = cleaned.lower()
    strong_extremes = ("terror", "walang kwenta", "bastos", "hinihiya", "sobrang galing", "napakagaling")
    if any(pat in lowered for pat in NEUTRAL_PATTERNS) and not any(ext in lowered for ext in strong_extremes):
        return 0.0

    raw_tokens = find_tokens(cleaned)
    if not raw_tokens:
        return 0.0

    sia = get_vader_analyzer()
    base_vader = sia.polarity_scores(cleaned)["compound"]

    lower_tokens = [t.lower() for t in raw_tokens]
    lemma_tokens = [lemmatize_calamancy_token(t) for t in lower_tokens]

    matched_phrases: list[float] = []
    neutralized: set[str] = set()
    for phrase, weight in SENTIMENT_PHRASES:
        if phrase in lowered:
            matched_phrases.append(weight)
            if weight < 0:
                for ptok in phrase.split():
                    if MULTILINGUAL_VADER_LEXICON.get(ptok, 0.0) > 0:
                        neutralized.add(ptok)
            elif weight > 0:
                for ptok in phrase.split():
                    if MULTILINGUAL_VADER_LEXICON.get(ptok, 0.0) < 0:
                        neutralized.add(ptok)

    but_idx = next((i for i, tok in enumerate(lower_tokens) if tok in _CONTRASTIVE_WORDS), None)

    sentiments: list[float] = []
    for idx, tok in enumerate(lower_tokens):
        if tok in neutralized:
            sentiments.append(0.0)
            continue

        napaka_boost = 0.0
        lookup_tok = tok
        if tok.startswith("napaka") and len(tok) > 7 and tok not in sia.lexicon:
            lookup_tok = tok[6:]
            napaka_boost = 0.40

        val = sia.lexicon.get(lookup_tok, 0.0)
        if val == 0.0:
            val = sia.lexicon.get(lemma_tokens[idx], 0.0)

        if val != 0.0:
            if napaka_boost > 0.0:
                val += napaka_boost if val > 0 else -napaka_boost

            for dist in (1, 2, 3):
                if idx < dist:
                    break
                boost = BOOSTER_DICT.get(lower_tokens[idx - dist])
                if boost is not None:
                    damp = 1.0 if dist == 1 else (0.95 if dist == 2 else 0.90)
                    delta = boost * damp
                    val += delta if val > 0 else -delta

            negated = False
            for dist in (1, 2, 3, 4):
                if idx < dist:
                    break
                prev_idx = idx - dist
                prev_tok = lower_tokens[prev_idx]
                if prev_tok in _CONTRASTIVE_WORDS or prev_tok == "and":
                    break
                if prev_tok in NEGATION_WORDS or prev_tok.endswith("n't"):
                    negated = True
                    break
                if dist >= 2 and prev_idx + 1 < len(lower_tokens):
                    pair = (prev_tok, lower_tokens[prev_idx + 1])
                    if pair in (("fails", "to"), ("failed", "to"), ("hard", "to"), ("mahirap", "intindihin")):
                        negated = True
                        break
            if negated:
                val *= NEGATION_SCALAR

        sentiments.append(val)

    if but_idx is not None:
        for idx in range(len(sentiments)):
            if idx < but_idx:
                sentiments[idx] *= 0.5
            elif idx > but_idx:
                sentiments[idx] *= 1.5

    total_val = sum(sentiments) + sum(w * 0.85 for w in matched_phrases)
    exclamations = min(cleaned.count("!"), 4)
    if exclamations > 0 and total_val != 0.0:
        p_boost = exclamations * 0.292
        total_val += p_boost if total_val > 0 else -p_boost

    morph_compound = total_val / math.sqrt(total_val * total_val + VADER_ALPHA) if total_val != 0.0 else 0.0
    # Blend Filipino/Taglish morphological VADER score with upstream English VADER
    blended = 0.75 * morph_compound + 0.25 * base_vader if morph_compound != 0.0 else base_vader
    return round(max(-1.0, min(1.0, blended)), 4)


def classify_rule_domain(text: str) -> tuple[str, float, list[str], dict[str, float]]:
    """Lexicon + calamanCy lemma pedagogical cluster scorer with facility isolation."""
    cleaned = " ".join(text.strip().split())
    empty_scores = {tag: 0.0 for tag in PEDAGOGICAL_TAGS}
    if not cleaned:
        return TAG_UNCLASSIFIED, 0.0, [], empty_scores

    lowered = cleaned.lower()
    tokens = find_tokens(lowered)
    token_set = set(tokens)

    facility_hits = [
        kw for kw in FACILITY_KEYWORDS
        if (kw in lowered if " " in kw else kw in token_set)
    ]

    token_counts: dict[str, int] = {}
    for tok in tokens:
        token_counts[tok] = token_counts.get(tok, 0) + 1
    for tok in tokens:
        lem = lemmatize_calamancy_token(tok)
        token_counts.setdefault(lem, 1)

    scores: dict[str, float] = {}
    matched: dict[str, list[str]] = {tag: [] for tag in PEDAGOGICAL_TAGS}

    for tag in PEDAGOGICAL_TAGS:
        sc = 0.0
        for entry, weight in DOMAIN_LEXICONS[tag]:
            if " " in entry or "-" in entry:
                if entry in lowered:
                    sc += weight * 1.35
                    if entry not in matched[tag]:
                        matched[tag].append(entry)
            else:
                cnt = token_counts.get(entry, 0)
                if cnt > 0:
                    sc += weight * (1.0 + 0.25 * (cnt - 1))
                    if entry not in matched[tag]:
                        matched[tag].append(entry)
        scores[tag] = round(sc, 4)

    best_tag = max(PEDAGOGICAL_TAGS, key=lambda t: scores[t])
    best_score = scores[best_tag]
    total_score = sum(scores.values())

    if facility_hits and (best_score < 2.0 or not matched[best_tag]):
        return TAG_UNCLASSIFIED, 0.85, facility_hits, scores
    if best_score <= 0.0 or not matched[best_tag]:
        return TAG_UNCLASSIFIED, 0.0, [], scores

    share = best_score / total_score if total_score > 0.0 else 1.0
    conf = round(max(0.50, min(0.99, 0.55 * share + 0.20 + min(best_score / 18.0, 0.30))), 4)
    return best_tag, conf, matched[best_tag], scores


def enhanced_multilingual_tokens(text: str) -> list[str]:
    """Clause-bounded negation-scoped & calamanCy-lemmatized feature tokens for scikit-learn TF-IDF."""
    if not text:
        return []
    out: list[str] = []
    for seg in _CLAUSE_SPLIT_RE.split(text.lower()):
        if not seg.strip():
            continue
        words = find_tokens(seg)
        neg_rem = 0
        for w in words:
            lem = lemmatize_calamancy_token(w)
            out.append(w)
            if lem != w:
                out.append(f"lem:{lem}")
            if w.startswith("napaka") and len(w) > 7:
                out.append("pfx:napaka")
            elif w.startswith("masyado"):
                out.append("pfx:masyado")
            elif w.startswith("nakaka") and len(w) > 7:
                out.append("pfx:nakaka")

            if w in _CONTRASTIVE_WORDS:
                neg_rem = 0
                out.append(f"conj:{w}")
                continue
            if w in NEGATION_WORDS or w.endswith("n't"):
                neg_rem = 3
                out.append("cue:negator")
                continue
            if neg_rem > 0:
                out.append(f"neg:{w}")
                if lem != w:
                    out.append(f"neg:{lem}")
                neg_rem -= 1
            if w in _CRITICAL_DEGREE_WORDS:
                out.append("cue:critical_deg")
            if w in _NEUTRAL_CUE_WORDS:
                out.append("cue:neutral_mod")
    return out


def preprocess_for_word_tfidf(texts: list[str]) -> list[str]:
    return [" ".join(enhanced_multilingual_tokens(t)) for t in texts]


def extract_morphosyntactic_matrix(texts: list[str]) -> sparse.csr_matrix:
    """13-dimensional neuro-symbolic VADER + calamanCy signal matrix fused with TF-IDF features."""
    rows = np.zeros((len(texts), 13), dtype=np.float64)
    for i, text in enumerate(texts):
        pol = compute_vader_multilingual_polarity(text)
        tag, _, _, d_scores = classify_rule_domain(text)
        toks = [t.lower() for t in find_tokens(text)]
        rows[i, 0] = pol * 1.8
        rows[i, 1] = max(pol, 0.0) * 1.6
        rows[i, 2] = max(-pol, 0.0) * 1.6
        rows[i, 3] = max(1.0 - abs(pol) * 2.5, 0.0) * 1.1
        rows[i, 4] = 0.8 if any(w in _NEUTRAL_CUE_WORDS for w in toks) else 0.0
        rows[i, 5] = 0.5 if any(w in NEGATION_WORDS or w.endswith("n't") for w in toks) else 0.0
        rows[i, 6] = 0.6 if any(w in _CRITICAL_DEGREE_WORDS for w in toks) else 0.0
        rows[i, 7] = 1.5 if tag == TAG_UNCLASSIFIED else 0.0
        for idx, ptag in enumerate(PEDAGOGICAL_TAGS):
            rows[i, 8 + idx] = min(d_scores.get(ptag, 0.0) / 6.0, 1.5)
    return sparse.csr_matrix(rows)


def extract_explainable_cues(text: str) -> list[str]:
    if not text.strip():
        return []
    cues: list[str] = []
    for tok in enhanced_multilingual_tokens(text):
        if tok.startswith(("neg:", "pfx:", "conj:")) or tok in ("cue:neutral_mod", "cue:critical_deg"):
            if tok not in cues:
                cues.append(tok)
        if len(cues) >= 5:
            break
    pol = compute_vader_multilingual_polarity(text)
    if abs(pol) >= 0.15:
        cues.append(f"vader_anchor:{pol:+.2f}")
    return cues[:6]


def build_feature_matrix(bundle: dict[str, Any], texts: list[str]) -> sparse.csr_matrix:
    X_word = bundle["word_tfidf"].transform(preprocess_for_word_tfidf(texts))
    X_char = bundle["char_tfidf"].transform(texts)
    if bundle.get("use_lex", True):
        X_lex = extract_morphosyntactic_matrix(texts)
        return sparse.hstack([X_word, X_char, X_lex], format="csr")
    return sparse.hstack([X_word, X_char], format="csr")


@lru_cache(maxsize=1)
def load_sklearn_bundles() -> tuple[dict[str, Any] | None, dict[str, Any] | None, dict[str, Any]]:
    sent_bundle = joblib.load(SENTIMENT_MODEL_PATH) if SENTIMENT_MODEL_PATH.exists() else None
    dom_bundle = joblib.load(DOMAIN_MODEL_PATH) if DOMAIN_MODEL_PATH.exists() else None
    meta: dict[str, Any] = {}
    if METADATA_PATH.exists():
        meta = json.loads(METADATA_PATH.read_text(encoding="utf-8"))
    return sent_bundle, dom_bundle, meta


def predict_sklearn_sentiment(text: str) -> dict[str, Any] | None:
    if not text.strip():
        return None
    sent_bundle, _, _ = load_sklearn_bundles()
    if sent_bundle is None:
        return None

    X = build_feature_matrix(sent_bundle, [text])
    clf_full = sent_bundle["clf_full"]
    clf_real = sent_bundle.get("clf_real")
    alpha = float(sent_bundle.get("alpha_real", 0.5))
    classes: list[str] = list(clf_full.classes_)

    p_full = clf_full.predict_proba(X)[0]
    if clf_real is not None and alpha > 0.0:
        p_real = clf_real.predict_proba(X)[0]
        agree = bool(int(np.argmax(p_full)) == int(np.argmax(p_real)))
        probs = (1.0 - alpha) * p_full + alpha * p_real
    else:
        agree = True
        probs = p_full

    prob_map = {cls: round(float(probs[i]), 4) for i, cls in enumerate(classes)}
    best_idx = int(np.argmax(probs))
    pos_p = prob_map.get("Positive", 0.0)
    neg_p = prob_map.get("Negative", 0.0)
    polarity = round(max(-1.0, min(1.0, pos_p - neg_p)), 4)

    return {
        "polarity": polarity,
        "valence": classes[best_idx],
        "confidence": round(float(probs[best_idx]), 4),
        "probabilities": prob_map,
        "head_agreement": agree,
        "cues": extract_explainable_cues(text),
        "algorithm": "Python scikit-learn Dual-Regime Ensemble (calamanCy TF-IDF 1-3 + char_wb 2-5 + VADER 13d Signals)",
    }


def predict_sklearn_domain(text: str) -> dict[str, Any] | None:
    if not text.strip():
        return None
    _, dom_bundle, _ = load_sklearn_bundles()
    if dom_bundle is None:
        return None

    X = build_feature_matrix(dom_bundle, [text])
    clf_full = dom_bundle["clf_full"]
    clf_real = dom_bundle.get("clf_real")
    alpha = float(dom_bundle.get("alpha_real", 0.45))
    classes: list[str] = list(clf_full.classes_)

    p_full = clf_full.predict_proba(X)[0]
    if clf_real is not None and alpha > 0.0:
        p_real = clf_real.predict_proba(X)[0]
        agree = bool(int(np.argmax(p_full)) == int(np.argmax(p_real)))
        probs = (1.0 - alpha) * p_full + alpha * p_real
    else:
        agree = True
        probs = p_full

    prob_map = {cls: round(float(probs[i]), 4) for i, cls in enumerate(classes)}
    best_idx = int(np.argmax(probs))
    return {
        "cluster": classes[best_idx],
        "confidence": round(float(probs[best_idx]), 4),
        "probabilities": prob_map,
        "head_agreement": agree,
    }
