"""Genuine Python calamanCy (`tl_calamancy_md-0.2.0`) + spaCy (`en_core_web_md`) Tagalog/Taglish/English
morphological tokenizer, POS tagger, lemmatizer, and 500d static semantic word embedding extractor."""

from __future__ import annotations

import os
import re
import warnings
from functools import lru_cache
from pathlib import Path
from typing import Any

os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS_WARNING", "1")
warnings.filterwarnings("ignore", message=r".*W094.*", category=UserWarning)
warnings.filterwarnings("ignore", message=r".*_register_pytree_node.*", category=FutureWarning)

import numpy as np
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
    COMPARATIVE_NEGATORS,
    MULTILINGUAL_VADER_LEXICON,
    NEGATION_WORDS,
)

_TOKEN_RE = re.compile(r"[A-Za-z0-9]+(?:['-][A-Za-z0-9]+)*")
_CLAUSE_SPLIT_RE = re.compile(r"[,;:.!?()]+")

_CONTRASTIVE_WORDS = frozenset({
    "but", "however", "though", "although", "yet", "pero", "kaso", "kaya", "ngunit", "subalit",
})

_FUNCTION_STOPWORDS = frozenset({
    "the", "a", "an", "of", "to", "in", "on", "for", "at", "by", "with", "from",
    "is", "are", "was", "were", "be", "been", "being", "that", "this", "these",
    "those", "it", "its", "as", "or", "and", "ang", "ng", "sa", "mga", "si",
    "ni", "kay", "para", "na", "ay", "yung", "ung", "ito", "iyan", "iyon", "po",
})

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
    "mistakes": "mistake",
    "errors": "error",
    "malinaw": "linaw",
    "mahusay": "husay",
    "magaling": "galing",
    "mabait": "bait",
    "matulungin": "tulong",
    "nakakatulong": "tulong",
    "tinutulungan": "tulong",
    "nagpapaliwanag": "paliwanag",
    "ipaliwanag": "paliwanag",
    "ipinaliwanag": "paliwanag",
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
    "makatwiran": "katwiran",
    "napapanahon": "panahon",
    "sumusunod": "sunod",
    "sumunod": "sunod",
    "naaayon": "ayon",
    "pagkakamali": "mali",
    "pagsasaulo": "saulo",
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
    "pero", "kaso", "naman", "lang", "lamang", "kapag", "yung", "imbes",
})

_ENGLISH_MARKERS: frozenset[str] = frozenset({
    "the", "and", "very", "with", "for", "quiz", "lectures", "explains",
    "provides", "she", "he", "his", "her", "our", "class", "course",
    "grading", "feedback", "during", "when", "exam", "syllabus",
})

_VERBS: frozenset[str] = frozenset({
    "explain", "explains", "explained", "teach", "teaches", "arrive",
    "arrives", "return", "returns", "takes", "provides", "nagtuturo",
    "magturo", "nagpapaliwanag", "dumadating", "tinutulungan", "pumasok",
})


def lemmatize_calamancy_token(token: str) -> str:
    """Morphological Tagalog/Taglish + English root/lemma canonicalizer aligned with calamanCy."""
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
    """Fallback Universal Dependencies coarse POS tag when running blank-tl pipeline."""
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


def _find_cached_calamancy_snapshot() -> Path | None:
    """Locate locally cached HuggingFace snapshot for `ljvmiranda921/tl_calamancy_md` to avoid network latency."""
    hf_home = Path(os.environ.get("HF_HOME", Path.home() / ".cache" / "huggingface"))
    hub_dir = hf_home / "hub"
    if not hub_dir.exists():
        return None
    for repo_dir in sorted(hub_dir.glob("models--ljvmiranda921--tl_calamancy_md*"), reverse=True):
        snaps_dir = repo_dir / "snapshots"
        if snaps_dir.exists():
            for snap in sorted(snaps_dir.iterdir(), reverse=True):
                if (snap / "config.cfg").exists():
                    return snap
                for sub in snap.iterdir():
                    if sub.is_dir() and (sub / "config.cfg").exists():
                        return sub
    return None


@lru_cache(maxsize=1)
def get_calamancy_nlp() -> tuple[Language, bool, str]:
    """Load pretrained `tl_calamancy_md-0.2.0` (200d TLUnified vectors + trainable lemmatizer + tagger)."""
    snap = _find_cached_calamancy_snapshot()
    if snap is not None:
        try:
            nlp = spacy.load(snap, disable=["parser", "ner"])
            return nlp, True, CALAMANCY_DEFAULT_MODEL
        except Exception:
            pass

    if HAS_CALAMANCY_PKG and calamancy is not None:
        for candidate in (CALAMANCY_DEFAULT_MODEL, "tl_calamancy_md-0.1.0", "tl_calamancy_lg-0.2.0"):
            try:
                nlp = calamancy.load(candidate, disable=["parser", "ner"])
                return nlp, True, candidate
            except Exception:
                pass

    nlp = spacy.blank("tl")
    if "evaluatech_tagalog_morph" not in nlp.pipe_names:
        nlp.add_pipe("evaluatech_tagalog_morph")
    model_lbl = f"{CALAMANCY_DEFAULT_MODEL} (fallback-blank-tl)" if HAS_CALAMANCY_PKG else "spacy-blank-tl"
    return nlp, HAS_CALAMANCY_PKG, model_lbl


@lru_cache(maxsize=1)
def get_english_nlp() -> tuple[Language | None, str]:
    """Load `en_core_web_md` (300d English static word vectors) if installed."""
    for candidate in ("en_core_web_md", "en_core_web_lg"):
        if spacy.util.is_package(candidate):
            try:
                nlp = spacy.load(candidate, disable=["parser", "ner", "attribute_ruler", "lemmatizer"])
                return nlp, candidate
            except Exception:
                pass
    return None, "none"


def find_tokens(text: str) -> list[str]:
    return _TOKEN_RE.findall(text)


def _lookup_vocab_vector(nlp: Language | None, word: str, dim: int) -> np.ndarray | None:
    if nlp is None or dim <= 0 or nlp.vocab.vectors_length != dim:
        return None
    for probe in (word, word.lower(), lemmatize_calamancy_token(word)):
        lex = nlp.vocab[probe]
        if lex.has_vector:
            return lex.vector
    return None


def extract_bilingual_embedding_matrix(texts: list[str], scale: float = 0.85) -> np.ndarray:
    """Pool negation-aware, content-weighted 200d `tl_calamancy_md-0.2.0` + 300d `en_core_web_md`
    static word vectors (`500d` L2-normalized bilingual semantic representation)."""
    tl_nlp, _, _ = get_calamancy_nlp()
    en_nlp, _ = get_english_nlp()

    tl_dim = int(tl_nlp.vocab.vectors_length) if tl_nlp is not None else 0
    en_dim = int(en_nlp.vocab.vectors_length) if en_nlp is not None else 0
    tl_out_dim = tl_dim if tl_dim > 0 else 200
    en_out_dim = en_dim if en_dim > 0 else 300
    total_dim = tl_out_dim + en_out_dim

    out = np.zeros((len(texts), total_dim), dtype=np.float32)
    comp_pairs = set(COMPARATIVE_NEGATORS)

    for row_idx, text in enumerate(texts):
        if not text or not text.strip():
            continue
        tl_acc = np.zeros(tl_out_dim, dtype=np.float32)
        en_acc = np.zeros(en_out_dim, dtype=np.float32)
        tl_wsum = 0.0
        en_wsum = 0.0

        for seg in _CLAUSE_SPLIT_RE.split(text):
            words = find_tokens(seg)
            if not words:
                continue
            lows = [w.lower() for w in words]
            neg_rem = 0
            after_contrast = False
            i = 0
            n = len(words)
            while i < n:
                low = lows[i]
                if i + 1 < n and (low, lows[i + 1]) in comp_pairs:
                    neg_rem = 4
                    i += 2
                    continue
                if low in _CONTRASTIVE_WORDS:
                    neg_rem = 0
                    if low in ("pero", "kaso", "but", "however", "yet", "ngunit", "subalit"):
                        after_contrast = True
                    i += 1
                    continue
                if low in NEGATION_WORDS or low.endswith("n't"):
                    neg_rem = 3
                    i += 1
                    continue
                if low in _FUNCTION_STOPWORDS:
                    i += 1
                    continue

                lex_val = abs(MULTILINGUAL_VADER_LEXICON.get(low, 0.0))
                if lex_val == 0.0:
                    lex_val = abs(MULTILINGUAL_VADER_LEXICON.get(lemmatize_calamancy_token(low), 0.0))
                weight = 1.0 + min(lex_val * 0.35, 1.0)
                if after_contrast:
                    weight *= 1.25

                sign = -0.55 if neg_rem > 0 else 1.0
                if neg_rem > 0:
                    neg_rem -= 1

                if tl_dim == tl_out_dim:
                    v_tl = _lookup_vocab_vector(tl_nlp, words[i], tl_dim)
                    if v_tl is not None:
                        tl_acc += (sign * weight) * v_tl
                        tl_wsum += weight

                if en_dim == en_out_dim:
                    v_en = _lookup_vocab_vector(en_nlp, words[i], en_dim)
                    if v_en is not None:
                        en_acc += (sign * weight) * v_en
                        en_wsum += weight

                i += 1

        if tl_wsum > 0.0:
            norm_tl = float(np.linalg.norm(tl_acc))
            if norm_tl > 1e-8:
                out[row_idx, :tl_out_dim] = (tl_acc / norm_tl) * scale
        if en_wsum > 0.0:
            norm_en = float(np.linalg.norm(en_acc))
            if norm_en > 1e-8:
                out[row_idx, tl_out_dim:] = (en_acc / norm_en) * scale

    return out


def extract_contextual_tok2vec_matrix(texts: list[str], scale: float = 0.85) -> np.ndarray:
    """Extract 352d mean-pooled contextual CNN embeddings from `tl_calamancy_md-0.2.0` (`256d`)
    and `en_core_web_md` (`96d`)."""
    tl_nlp, _, _ = get_calamancy_nlp()
    en_nlp, _ = get_english_nlp()

    tl_t2v = tl_nlp.get_pipe("tok2vec") if (tl_nlp is not None and "tok2vec" in tl_nlp.pipe_names) else None
    en_t2v = en_nlp.get_pipe("tok2vec") if (en_nlp is not None and "tok2vec" in en_nlp.pipe_names) else None

    out = np.zeros((len(texts), 256 + 96), dtype=np.float32)
    if not texts:
        return out

    clean_texts = [" ".join(t.strip().split()) if t else "" for t in texts]

    if tl_t2v is not None and tl_nlp is not None:
        tl_docs = list(tl_t2v.pipe((tl_nlp.make_doc(t) for t in clean_texts), batch_size=256))
        for i, doc in enumerate(tl_docs):
            if doc.tensor.shape[0] > 0 and doc.tensor.shape[1] == 256:
                vec = doc.tensor.mean(axis=0)
                nrm = float(np.linalg.norm(vec))
                if nrm > 1e-8:
                    out[i, :256] = (vec / nrm) * scale

    if en_t2v is not None and en_nlp is not None:
        en_docs = list(en_t2v.pipe((en_nlp.make_doc(t) for t in clean_texts), batch_size=256))
        for i, doc in enumerate(en_docs):
            if doc.tensor.shape[0] > 0 and doc.tensor.shape[1] == 96:
                vec = doc.tensor.mean(axis=0)
                nrm = float(np.linalg.norm(vec))
                if nrm > 1e-8:
                    out[i, 256:] = (vec / nrm) * scale

    return out



def extract_calamancy_features(text: str) -> dict[str, Any]:
    """Run Python `tl_calamancy_md-0.2.0` pipeline to extract tokens, lemmas, UD POS tags, vector status & language."""
    cleaned = " ".join(text.strip().split())
    nlp, calamancy_ready, active_model = get_calamancy_nlp()
    _, en_model = get_english_nlp()
    vec_dim = int(nlp.vocab.vectors_length) if nlp is not None else 0

    if not cleaned:
        return {
            "framework": "calamanCy (Python)",
            "model": active_model,
            "english_vector_model": en_model,
            "vector_dim": vec_dim,
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
        "english_vector_model": en_model,
        "vector_dim": vec_dim,
        "repo": CALAMANCY_REPO_URL,
        "calamancy_installed": calamancy_ready,
        "language": language,
        "tokens": tokens,
        "lemmas": lemmas,
        "pos_tags": pos_tags,
    }
