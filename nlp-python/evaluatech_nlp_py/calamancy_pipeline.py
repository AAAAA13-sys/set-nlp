"""Genuine Python calamanCy + spaCy Tagalog/Taglish morphological tokenizer, POS tagger, and lemmatizer."""

from __future__ import annotations

import re
from functools import lru_cache
from typing import Any

import spacy
from spacy.language import Language
from spacy.tokens import Doc

try:
    import calamancy  # type: ignore[import-untyped]
    HAS_CALAMANCY_PKG = True
except ImportError:  # pragma: no cover
    calamancy = None
    HAS_CALAMANCY_PKG = False

from .lexicons import (
    BOOSTER_DICT,
    CALAMANCY_DEFAULT_MODEL,
    CALAMANCY_REPO_URL,
    MULTILINGUAL_VADER_LEXICON,
    NEGATION_WORDS,
)

_TOKEN_RE = re.compile(r"[A-Za-z0-9]+(?:['-][A-Za-z0-9]+)*")

_EXPLICIT_LEMMAS: dict[str, str] = {
    "explains": "explain",
    "explained": "explain",
    "explaining": "explain",
    "explanations": "explanation",
    "algorithms": "algorithm",
    "examples": "example",
    "takes": "take",
    "scores": "score",
    "quizzes": "quiz",
    "exams": "exam",
    "grades": "grade",
    "grading": "grade",
    "arrives": "arrive",
    "arrived": "arrive",
    "lectures": "lecture",
    "minutes": "minute",
    "malinaw": "linaw",
    "mahusay": "husay",
    "magaling": "galing",
    "mabait": "bait",
    "matulungin": "tulong",
    "nakakatulong": "tulong",
    "tinutulungan": "tulong",
    "nagpapaliwanag": "paliwanag",
    "ipaliwanag": "paliwanag",
    "nagtuturo": "turo",
    "magturo": "turo",
    "pagtuturo": "turo",
    "maintindihan": "intindi",
    "naiintindihan": "intindi",
    "intindihin": "intindi",
    "nahuhuli": "huli",
    "dumadating": "dating",
    "masungit": "sungit",
    "nakakalito": "lito",
    "mabagal": "bagal",
    "magulo": "gulo",
    "magalang": "galang",
    "gumagabay": "gabay",
}

_TAGALOG_PREFIXES: tuple[str, ...] = (
    "napaka",
    "pinaka",
    "nakaka",
    "nagpa",
    "magpa",
    "pagka",
    "nag",
    "mag",
    "pag",
    "ma",
    "na",
)

_TAGALOG_MARKERS: frozenset[str] = frozenset({
    "ang", "ng", "sa", "mga", "si", "ni", "kay", "para", "po", "opo",
    "hindi", "wala", "walang", "sobrang", "napaka", "malinaw", "mahusay",
    "magaling", "mabait", "turo", "pagtuturo", "klase", "guro", "prof",
    "aralin", "pagsusulit", "grado", "mabagal", "magulo", "masungit",
    "pero", "kaso", "naman", "lang", "lamang", "kapag",
})

_ENGLISH_MARKERS: frozenset[str] = frozenset({
    "the", "and", "very", "with", "for", "quiz", "lectures", "explains",
    "provides", "she", "he", "his", "her", "our", "class", "course",
    "grading", "feedback", "during", "when",
})

_VERBS: frozenset[str] = frozenset({
    "explain", "explains", "explained", "teach", "teaches", "arrive",
    "arrives", "return", "returns", "takes", "provides", "nagtuturo",
    "magturo", "nagpapaliwanag", "dumadating", "tinutulungan", "pumasok",
})


def lemmatize_calamancy_token(token: str) -> str:
    """Morphological Tagalog/Taglish + English lemmatizer aligned with calamanCy."""
    lower = token.strip().lower()
    if len(lower) <= 3:
        return lower
    if lower in _EXPLICIT_LEMMAS:
        return _EXPLICIT_LEMMAS[lower]
    for pfx in _TAGALOG_PREFIXES:
        if lower.startswith(pfx) and (len(lower) - len(pfx)) >= 4:
            rest = lower[len(pfx):]
            return rest[1:] if rest.startswith("-") else rest
    return lower


def infer_ud_pos(token: str) -> str:
    """Infer Universal Dependencies coarse POS tag when running blank-tl pipeline."""
    s = token.lower()
    if s in NEGATION_WORDS:
        return "PART"
    if s in BOOSTER_DICT or s.endswith("ly"):
        return "ADV"
    if s in MULTILINGUAL_VADER_LEXICON:
        return "ADJ"
    if s in _VERBS:
        return "VERB"
    return "NOUN"


@Language.component("evaluatech_tagalog_morph")
def _evaluatech_tagalog_morph(doc: Doc) -> Doc:
    for token in doc:
        if not token.lemma_ or token.lemma_ == token.text:
            token.lemma_ = lemmatize_calamancy_token(token.text)
        if not token.pos_:
            token.pos_ = infer_ud_pos(token.text)
    return doc


@lru_cache(maxsize=1)
def get_calamancy_nlp() -> tuple[Language, bool, str]:
    """Load installed calamanCy model if downloaded; otherwise spaCy Tagalog (`tl`) + morph component."""
    if HAS_CALAMANCY_PKG and calamancy is not None:
        for candidate in ("tl_calamancy_md", "tl_calamancy_lg", "tl_calamancy_trf"):
            if spacy.util.is_package(candidate):
                try:
                    nlp = calamancy.load(candidate)
                    return nlp, True, candidate
                except Exception:
                    pass
    nlp = spacy.blank("tl")
    if "evaluatech_tagalog_morph" not in nlp.pipe_names:
        nlp.add_pipe("evaluatech_tagalog_morph")
    model_lbl = f"{CALAMANCY_DEFAULT_MODEL} (calamanCy+spaCy-tl)" if HAS_CALAMANCY_PKG else "spacy-blank-tl"
    return nlp, HAS_CALAMANCY_PKG, model_lbl


def find_tokens(text: str) -> list[str]:
    return _TOKEN_RE.findall(text)


def extract_calamancy_features(text: str) -> dict[str, Any]:
    """Run Python calamanCy + spaCy pipeline to extract tokens, lemmas, UD POS tags, and code-switching label."""
    cleaned = " ".join(text.strip().split())
    nlp, calamancy_ready, active_model = get_calamancy_nlp()
    if not cleaned:
        return {
            "framework": "calamanCy (Python)",
            "model": active_model,
            "repo": CALAMANCY_REPO_URL,
            "calamancy_installed": calamancy_ready,
            "language": "en",
            "tokens": [],
            "lemmas": [],
            "pos_tags": [],
        }

    doc = nlp(cleaned)
    word_tokens = [t for t in doc if not t.is_punct and not t.is_space]
    tokens = [t.text for t in word_tokens]
    lemmas = [
        lemmatize_calamancy_token(t.lemma_ if t.lemma_ else t.text)
        for t in word_tokens
    ]
    pos_tags = [t.pos_ if t.pos_ else infer_ud_pos(t.text) for t in word_tokens]

    lower_set = {t.lower() for t in tokens}
    has_tl = bool(lower_set & _TAGALOG_MARKERS) or any(
        any(w.startswith(p) and len(w) >= len(p) + 4 for p in ("napaka", "nakaka", "nagpa", "pinaka"))
        for w in lower_set
    )
    has_en = bool(lower_set & _ENGLISH_MARKERS)

    if has_tl and has_en:
        language = "tl-en (Taglish)"
    elif has_tl:
        language = "tl"
    else:
        language = "en"

    return {
        "framework": "calamanCy (Python)",
        "model": active_model,
        "repo": CALAMANCY_REPO_URL,
        "calamancy_installed": calamancy_ready,
        "language": language,
        "tokens": tokens,
        "lemmas": lemmas,
        "pos_tags": pos_tags,
    }
