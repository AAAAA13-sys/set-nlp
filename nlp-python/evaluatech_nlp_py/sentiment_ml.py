"""Python VADER Sentiment Polarity + calamanCy 500d Embeddings + scikit-learn Calibrated Ensemble (`sentiment_ml.py`)."""

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

from .calamancy_pipeline import (
    extract_bilingual_embedding_matrix,
    extract_contextual_tok2vec_matrix,
    find_tokens,
    lemmatize_calamancy_token,
)
from .lexicons import (
    BOOSTER_DICT,
    COMPARATIVE_NEGATORS,
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
    "but", "however", "though", "although", "yet", "while", "pero", "kaso", "kaya", "ngunit", "subalit", "bagama't",
})
_STRONG_CONTRAST_WORDS = frozenset({
    "but", "however", "yet", "pero", "kaso", "ngunit", "subalit",
})
_HEDGE_WORDS = frozenset({
    "medyo", "somewhat", "slightly", "fairly", "quite", "minsan", "occasionally", "mostly", "medyo", "bahagya",
})
_MITIGATOR_WORDS = frozenset({
    "naman", "still", "adequate", "acceptable", "manageable", "okay", "ok",
})
_CRITICAL_DEGREE_WORDS = frozenset({
    "too", "overly", "excessively", "severely", "masyadong", "masyado", "sobrang", "sobra",
    "napaka", "puro", "pawang", "biglang", "bigla", "sirang-sira",
})
_NEUTRAL_CUE_WORDS = frozenset({
    "standard", "typical", "normal", "reasonable", "moderate", "moderately",
    "average", "acceptable", "adequate", "sakto", "katamtaman", "karaniwan",
    "karaniwang", "okay", "naaayon", "matched", "aligned", "manageable",
})
_NORMATIVE_PHRASES = (
    "table of specifications", "talahanayan ng espisipikasyon", "sumusunod naman",
    "naaayon sa", "makatwiran naman", "sapat naman", "tama lang", "tama lamang",
    "karaniwang antas", "karaniwan lamang", "nagagamit pa naman", "pa rin naman",
    "typical for", "aligned with", "matched the", "followed standard",
    "still handle", "still function", "neither notably", "neither too",
)
_REMEDIAL_POSITIVE_CUES = (
    "common mistakes", "common quiz mistakes", "grading errors", "scoring errors",
    "grading miscalculations", "dispute quiz", "recheck", "reduce exam stress",
    "reduce student stress", "lower pre-exam anxiety", "nakakabawas ng",
    "without requiring obscure", "without requiring rote", "without relying on obscure",
    "over memorization", "allows revision", "draft revisions",
    "walang trick", "no trick questions", "walang sorpresa", "walang daya",
    "karaniwang pagkakamali", "itama ang pagkakamali", "mock defense",
    "nagpa-mock defense", "nag-curve", "curved the", "deadline extension",
    "reasonable extension", "palugit",
)
_COMPARATIVE_NEG_STRINGS = (
    "rather than", "instead of", "imbes na", "kesa sa", "kaysa sa", "sa halip na", "far from",
)
_TAGALOG_SIGNAL_WORDS = frozenset({
    "ang", "ng", "sa", "mga", "si", "ni", "hindi", "wala", "walang", "pero", "kaso", "naman", "yung", "kaya",
})
_ENGLISH_SIGNAL_WORDS = frozenset({
    "the", "and", "with", "for", "from", "that", "this", "though", "while", "rather", "instead", "course", "class",
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


def _score_token_span(lower_tokens: list[str], sia: SentimentIntensityAnalyzer, neutralized: set[str]) -> list[float]:
    lemma_tokens = [lemmatize_calamancy_token(t) for t in lower_tokens]
    comp_pairs = set(COMPARATIVE_NEGATORS)
    comp_neg_indices: set[int] = set()
    for i in range(len(lower_tokens) - 1):
        if (lower_tokens[i], lower_tokens[i + 1]) in comp_pairs:
            for k in range(i + 2, min(i + 7, len(lower_tokens))):
                if lower_tokens[k] in _CONTRASTIVE_WORDS or lower_tokens[k] == "and":
                    break
                comp_neg_indices.add(k)

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

            negated = idx in comp_neg_indices
            if not negated:
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
    return sentiments


def compute_vader_multilingual_polarity(text: str) -> float:
    """Compute hybrid Python VADER + Filipino/Taglish morphosyntactic compound polarity in [-1.0, +1.0]."""
    cleaned = " ".join(text.strip().split())
    if not cleaned:
        return 0.0

    lowered = cleaned.lower()
    strong_extremes = (
        "terror", "walang kwenta", "bastos", "hinihiya", "sirang-sira",
        "sobrang galing", "napakagaling", "walang natututunan",
    )
    if any(pat in lowered for pat in NEUTRAL_PATTERNS) and not any(ext in lowered for ext in strong_extremes):
        return 0.0

    raw_tokens = find_tokens(cleaned)
    if not raw_tokens:
        return 0.0

    sia = get_vader_analyzer()
    base_vader = sia.polarity_scores(cleaned)["compound"]
    lower_tokens = [t.lower() for t in raw_tokens]

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

    sentiments = _score_token_span(lower_tokens, sia, neutralized)
    but_idx = next((i for i, tok in enumerate(lower_tokens) if tok in _CONTRASTIVE_WORDS), None)

    if but_idx is not None:
        has_hedge = any(t in _HEDGE_WORDS for t in lower_tokens[:but_idx])
        has_mitigator = any(t in _MITIGATOR_WORDS for t in lower_tokens[but_idx + 1 :])
        # Balanced hedged contrast (e.g., "Medyo fast-paced ... pero approachable naman") dampens both sides toward Neutral
        if has_hedge and has_mitigator and not any(ext in lowered for ext in strong_extremes):
            for idx in range(len(sentiments)):
                sentiments[idx] *= 0.35
        elif lower_tokens[but_idx] in _STRONG_CONTRAST_WORDS:
            for idx in range(len(sentiments)):
                if idx < but_idx:
                    sentiments[idx] *= 0.5
                elif idx > but_idx:
                    sentiments[idx] *= 1.4

    total_val = sum(sentiments) + sum(w * 0.85 for w in matched_phrases)
    exclamations = min(cleaned.count("!"), 4)
    if exclamations > 0 and total_val != 0.0:
        p_boost = exclamations * 0.292
        total_val += p_boost if total_val > 0 else -p_boost

    morph_compound = total_val / math.sqrt(total_val * total_val + VADER_ALPHA) if total_val != 0.0 else 0.0
    # When comparative negation is present, rely more heavily on comparative-negation-aware morph_compound
    if any(cn in lowered for cn in _COMPARATIVE_NEG_STRINGS):
        blended = 0.90 * morph_compound + 0.10 * base_vader
    else:
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

    if facility_hits and (best_score < 2.2 or not matched[best_tag]):
        return TAG_UNCLASSIFIED, 0.85, facility_hits, scores
    if best_score <= 0.0 or not matched[best_tag]:
        return TAG_UNCLASSIFIED, 0.0, [], scores

    share = best_score / total_score if total_score > 0.0 else 1.0
    conf = round(max(0.50, min(0.99, 0.55 * share + 0.20 + min(best_score / 18.0, 0.30))), 4)
    return best_tag, conf, matched[best_tag], scores


def enhanced_multilingual_tokens(text: str) -> list[str]:
    """Clause-bounded comparative-negation-scoped & calamanCy-lemmatized feature tokens for scikit-learn TF-IDF."""
    if not text:
        return []
    lowered = text.lower()
    out: list[str] = []
    comp_pairs = set(COMPARATIVE_NEGATORS)

    if any(pat in lowered for pat in _NORMATIVE_PHRASES):
        out.append("cue:normative_adequacy")
    if any(pat in lowered for pat in _REMEDIAL_POSITIVE_CUES):
        out.append("cue:remedial_positive")
    if any(pat in lowered for pat in _COMPARATIVE_NEG_STRINGS):
        out.append("cue:comparative_neg")

    for seg in _CLAUSE_SPLIT_RE.split(lowered):
        if not seg.strip():
            continue
        words = find_tokens(seg)
        neg_rem = 0
        i = 0
        n = len(words)
        while i < n:
            w = words[i]
            if i + 1 < n and (w, words[i + 1]) in comp_pairs:
                out.append(f"compneg:{w}_{words[i + 1]}")
                neg_rem = 4
                i += 2
                continue

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
                i += 1
                continue
            if w in NEGATION_WORDS or w.endswith("n't"):
                neg_rem = 3
                out.append("cue:negator")
                i += 1
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
            if w in _HEDGE_WORDS:
                out.append("cue:hedge")
            if w in _MITIGATOR_WORDS:
                out.append("cue:mitigator")
            i += 1
    return out


def preprocess_for_word_tfidf(texts: list[str]) -> list[str]:
    return [" ".join(enhanced_multilingual_tokens(t)) for t in texts]


def extract_morphosyntactic_matrix(texts: list[str]) -> sparse.csr_matrix:
    """24-dimensional neuro-symbolic VADER + clause-contrastive + calamanCy discourse matrix."""
    sia = get_vader_analyzer()
    rows = np.zeros((len(texts), 24), dtype=np.float64)

    for i, text in enumerate(texts):
        lowered = text.lower()
        pol = compute_vader_multilingual_polarity(text)
        v_scores = sia.polarity_scores(text) if text.strip() else {"pos": 0.0, "neg": 0.0, "neu": 1.0}
        tag, _, _, d_scores = classify_rule_domain(text)
        toks = [t.lower() for t in find_tokens(text)]
        tok_set = set(toks)

        but_idx = next((idx for idx, tok in enumerate(toks) if tok in _CONTRASTIVE_WORDS), None)
        if but_idx is not None and 0 < but_idx < len(toks) - 1:
            s1 = sum(_score_token_span(toks[:but_idx], sia, set()))
            s2 = sum(_score_token_span(toks[but_idx + 1 :], sia, set()))
            pol1 = s1 / math.sqrt(s1 * s1 + VADER_ALPHA) if s1 != 0.0 else 0.0
            pol2 = s2 / math.sqrt(s2 * s2 + VADER_ALPHA) if s2 != 0.0 else 0.0
        else:
            pol1 = pol
            pol2 = 0.0

        has_hedge = any(w in _HEDGE_WORDS for w in toks)
        has_mitigator = any(w in _MITIGATOR_WORDS for w in toks)
        has_normative = any(p in lowered for p in _NORMATIVE_PHRASES) or any(w in _NEUTRAL_CUE_WORDS for w in toks)
        has_comp_neg = any(p in lowered for p in _COMPARATIVE_NEG_STRINGS)
        has_remedial = any(p in lowered for p in _REMEDIAL_POSITIVE_CUES)
        neg_cnt = sum(1 for w in toks if w in NEGATION_WORDS or w.endswith("n't"))
        balanced_tradeoff = (
            but_idx is not None
            and ((pol1 * pol2 < -0.015) or (has_hedge and has_mitigator))
            and abs(pol2) < 0.62
        )

        rows[i, 0] = pol * 1.8
        rows[i, 1] = max(pol, 0.0) * 1.6
        rows[i, 2] = max(-pol, 0.0) * 1.6
        rows[i, 3] = max(1.0 - abs(pol) * 2.5, 0.0) * 1.2
        rows[i, 4] = pol1 * 1.2
        rows[i, 5] = pol2 * 1.4
        rows[i, 6] = 1.35 if balanced_tradeoff else 0.0
        rows[i, 7] = 1.15 if has_normative else 0.0
        rows[i, 8] = 0.85 if has_hedge else 0.0
        rows[i, 9] = 1.25 if has_comp_neg else 0.0
        rows[i, 10] = 1.35 if has_remedial else 0.0
        rows[i, 11] = min(neg_cnt * 0.45, 1.35)
        rows[i, 12] = 0.75 if any(w in _CRITICAL_DEGREE_WORDS for w in toks) else 0.0
        # Keep rows[i, 13] domain-neutral so campus facility/org comments are never biased toward Negative!
        rows[i, 13] = 0.85 if ("mabilis magsalita" in lowered or "mabilis magturo" in lowered) else 0.0
        for idx, ptag in enumerate(PEDAGOGICAL_TAGS):
            rows[i, 14 + idx] = min(d_scores.get(ptag, 0.0) / 7.5, 1.2)
        rows[i, 19] = float(v_scores["pos"]) * 1.2
        rows[i, 20] = float(v_scores["neg"]) * 1.2
        rows[i, 21] = float(v_scores["neu"]) * 0.6
        rows[i, 22] = 0.5 if bool(tok_set & _TAGALOG_SIGNAL_WORDS) else 0.0
        rows[i, 23] = 0.5 if bool(tok_set & _ENGLISH_SIGNAL_WORDS) else 0.0

    return sparse.csr_matrix(rows)


def extract_domain_lexicon_matrix(texts: list[str]) -> sparse.csr_matrix:
    """7-dimensional non-sentiment domain lexicon & facility isolation matrix for 6-cluster domain classification."""
    rows = np.zeros((len(texts), 7), dtype=np.float64)
    for i, text in enumerate(texts):
        tag, conf, kw, d_scores = classify_rule_domain(text)
        for idx, ptag in enumerate(PEDAGOGICAL_TAGS):
            rows[i, idx] = min(d_scores.get(ptag, 0.0) / 4.0, 2.5)
        rows[i, 5] = 2.4 if (tag == TAG_UNCLASSIFIED and bool(kw)) else 0.0
        rows[i, 6] = float(conf)
    return sparse.csr_matrix(rows)


def extract_explainable_cues(text: str) -> list[str]:
    if not text.strip():
        return []
    cues: list[str] = []
    for tok in enhanced_multilingual_tokens(text):
        if tok.startswith(("neg:", "compneg:", "pfx:", "conj:")) or tok in (
            "cue:neutral_mod",
            "cue:normative_adequacy",
            "cue:remedial_positive",
            "cue:comparative_neg",
            "cue:critical_deg",
        ):
            if tok not in cues:
                cues.append(tok)
        if len(cues) >= 5:
            break
    pol = compute_vader_multilingual_polarity(text)
    if abs(pol) >= 0.15:
        cues.append(f"vader_anchor:{pol:+.2f}")
    return cues[:6]


def build_feature_matrix(bundle: dict[str, Any], texts: list[str]) -> sparse.csr_matrix:
    parts: list[sparse.spmatrix] = [
        bundle["word_tfidf"].transform(preprocess_for_word_tfidf(texts)),
        bundle["char_tfidf"].transform(texts),
    ]
    if bundle.get("use_embeddings", False):
        emb_scale = float(bundle.get("emb_scale", 0.85))
        X_emb = extract_bilingual_embedding_matrix(texts, scale=emb_scale)
        parts.append(sparse.csr_matrix(X_emb))
    if bundle.get("use_contextual_tok2vec", False):
        ctx_scale = float(bundle.get("ctx_scale", 0.85))
        X_ctx = extract_contextual_tok2vec_matrix(texts, scale=ctx_scale)
        parts.append(sparse.csr_matrix(X_ctx))
    if bundle.get("use_lex", False):
        parts.append(extract_morphosyntactic_matrix(texts))
    if bundle.get("use_domain_lex", False):
        parts.append(extract_domain_lexicon_matrix(texts))
    return sparse.hstack(parts, format="csr")


class DenseSliceProbabilityWrapper:
    """Adapter slicing trailing dense columns from the CSR matrix for non-linear probability estimators."""

    def __init__(self, clf: Any, start_col: int) -> None:
        self.clf = clf
        self.start_col = start_col
        self.classes_ = clf.classes_

    def predict_proba(self, X: sparse.csr_matrix) -> np.ndarray:
        X_dense = X[:, self.start_col :].toarray()
        return self.clf.predict_proba(X_dense)


class SvdDiscourseProbabilityWrapper:
    """Projects `(TF-IDF + 500d static + 352d contextual)` via `TruncatedSVD(64)` and fuses `24d` VADER discourse
    signals into a compact `88d` RBF-SVC classifier without high-dimensional variance."""

    def __init__(self, svd: Any, clf: Any, sem_end_col: int, lex_start_col: int, lex_scale: float = 0.8) -> None:
        self.svd = svd
        self.clf = clf
        self.sem_end_col = sem_end_col
        self.lex_start_col = lex_start_col
        self.lex_scale = lex_scale
        self.classes_ = clf.classes_

    def predict_proba(self, X: sparse.csr_matrix) -> np.ndarray:
        Z_sem = self.svd.transform(X[:, : self.sem_end_col])
        Z_lex = X[:, self.lex_start_col :].toarray() * self.lex_scale
        return self.clf.predict_proba(np.hstack([Z_sem, Z_lex]))


class NonContextualSliceWrapper:
    """Drops contextual `tok2vec` columns `[ctx_start:ctx_end]` so Full-Regime heads trained without `tok2vec`
    on template rows have zero train/inference covariate mismatch."""

    def __init__(self, clf: Any, ctx_start_col: int, ctx_end_col: int) -> None:
        self.clf = clf
        self.ctx_start_col = ctx_start_col
        self.ctx_end_col = ctx_end_col
        self.classes_ = clf.classes_

    def predict_proba(self, X: sparse.csr_matrix) -> np.ndarray:
        X_sub = sparse.hstack([X[:, : self.ctx_start_col], X[:, self.ctx_end_col :]], format="csr")
        return self.clf.predict_proba(X_sub)



def predict_bundle_probabilities(bundle: dict[str, Any], X: sparse.csr_matrix) -> tuple[np.ndarray, list[str], bool]:
    """Compute calibrated ensemble class probabilities from weighted estimator heads."""
    estimators: list[tuple[Any, float]] | None = bundle.get("estimators")
    if estimators:
        classes = list(estimators[0][0].classes_)
        total_w = sum(float(w) for _, w in estimators)
        probs = np.zeros((X.shape[0], len(classes)), dtype=np.float64)
        top_votes: list[np.ndarray] = []
        for est, w in estimators:
            p = est.predict_proba(X)
            probs += (float(w) / total_w) * p
            top_votes.append(np.argmax(p, axis=1))
        class_multipliers = bundle.get("class_multipliers")
        if class_multipliers is not None:
            mult = np.asarray(class_multipliers, dtype=np.float64)
            probs = probs * mult
            probs = probs / np.clip(probs.sum(axis=1, keepdims=True), 1e-12, None)
        agree = bool(all(int(v[0]) == int(top_votes[0][0]) for v in top_votes[1:])) if X.shape[0] == 1 else True
        return probs, classes, agree

    clf_full = bundle["clf_full"]
    clf_real = bundle.get("clf_real")
    alpha = float(bundle.get("alpha_real", 0.5))
    classes = list(clf_full.classes_)
    p_full = clf_full.predict_proba(X)
    if clf_real is not None and alpha > 0.0:
        p_real = clf_real.predict_proba(X)
        agree = bool(int(np.argmax(p_full[0])) == int(np.argmax(p_real[0]))) if X.shape[0] == 1 else True
        probs = (1.0 - alpha) * p_full + alpha * p_real
    else:
        agree = True
        probs = p_full
    return probs, classes, agree


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
    sent_bundle, _, meta = load_sklearn_bundles()
    if sent_bundle is None:
        return None

    X = build_feature_matrix(sent_bundle, [text])
    probs_mat, classes, agree = predict_bundle_probabilities(sent_bundle, X)
    probs = probs_mat[0]

    prob_map = {cls: round(float(probs[i]), 4) for i, cls in enumerate(classes)}
    best_idx = int(np.argmax(probs))
    pos_p = prob_map.get("Positive", 0.0)
    neg_p = prob_map.get("Negative", 0.0)
    polarity = round(max(-1.0, min(1.0, pos_p - neg_p)), 4)
    algo_desc = meta.get("sentiment_model", {}).get(
        "algorithm",
        "Python scikit-learn Calibrated Ensemble (tl_calamancy_md 200d + en_core_web_md 300d + TF-IDF + VADER 24d)",
    )

    return {
        "polarity": polarity,
        "valence": classes[best_idx],
        "confidence": round(float(probs[best_idx]), 4),
        "probabilities": prob_map,
        "head_agreement": agree,
        "cues": extract_explainable_cues(text),
        "algorithm": algo_desc,
    }


def predict_sklearn_domain(text: str) -> dict[str, Any] | None:
    if not text.strip():
        return None
    _, dom_bundle, _ = load_sklearn_bundles()
    if dom_bundle is None:
        return None

    X = build_feature_matrix(dom_bundle, [text])
    probs_mat, classes, agree = predict_bundle_probabilities(dom_bundle, X)
    probs = probs_mat[0]

    prob_map = {cls: round(float(probs[i]), 4) for i, cls in enumerate(classes)}
    best_idx = int(np.argmax(probs))
    return {
        "cluster": classes[best_idx],
        "confidence": round(float(probs[best_idx]), 4),
        "probabilities": prob_map,
        "head_agreement": agree,
    }
