"""EvaluaTech Scikit-Learn & NLTK Machine Learning Pipeline Integration.

Provides production inference for:
  - Multilingual sentiment classification (Positive, Neutral, Negative)
  - Continuous polarity scoring in [-1.0, +1.0] derived from calibrated probabilities
  - Pedagogical Likert cluster classification (5 clusters + Unclassified)
"""

from __future__ import annotations

import json
import logging
import os
import re
from typing import Any

logger = logging.getLogger(__name__)

_TOKEN_RE = re.compile(r"[A-Za-z0-9]+(?:'[A-Za-z0-9]+|-[A-Za-z0-9]+)*")

def nltk_tokenizer(text: str) -> list[str]:
    """Multilingual tokenization using NLTK word_tokenize with regex fallback."""
    if not text:
        return []
    try:
        from nltk.tokenize import word_tokenize  # type: ignore[import-not-found]
        return word_tokenize(text.lower())
    except Exception:
        return [tok.lower() for tok in _TOKEN_RE.findall(text)]

_MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")
_SENTIMENT_MODEL_PATH = os.path.join(_MODELS_DIR, "sentiment_pipeline.joblib")
_DOMAIN_MODEL_PATH = os.path.join(_MODELS_DIR, "domain_pipeline.joblib")
_METADATA_PATH = os.path.join(_MODELS_DIR, "model_metadata.json")

_SENTIMENT_PIPELINE: Any = None
_DOMAIN_PIPELINE: Any = None
_METADATA: dict[str, Any] = {}
_INITIALIZED: bool = False

def init_ml_models() -> bool:
    """Load the joblib serialized scikit-learn models if present."""
    global _SENTIMENT_PIPELINE, _DOMAIN_PIPELINE, _METADATA, _INITIALIZED
    if _INITIALIZED:
        return _SENTIMENT_PIPELINE is not None

    _INITIALIZED = True
    try:
        import joblib  # type: ignore[import-not-found]
    except ImportError:
        logger.warning("joblib not installed; ML models disabled.")
        return False

    if os.path.exists(_SENTIMENT_MODEL_PATH):
        try:
            _SENTIMENT_PIPELINE = joblib.load(_SENTIMENT_MODEL_PATH)
            logger.info("Loaded sentiment ML pipeline from %s", _SENTIMENT_MODEL_PATH)
        except Exception as exc:
            logger.warning("Failed to load sentiment ML model: %s", exc)
            _SENTIMENT_PIPELINE = None

    if os.path.exists(_DOMAIN_MODEL_PATH):
        try:
            _DOMAIN_PIPELINE = joblib.load(_DOMAIN_MODEL_PATH)
            logger.info("Loaded domain ML pipeline from %s", _DOMAIN_MODEL_PATH)
        except Exception as exc:
            logger.warning("Failed to load domain ML model: %s", exc)
            _DOMAIN_PIPELINE = None

    if os.path.exists(_METADATA_PATH):
        try:
            with open(_METADATA_PATH, encoding="utf-8") as f:
                _METADATA = json.load(f)
        except Exception as exc:
            logger.warning("Failed to load ML metadata: %s", exc)
            _METADATA = {}

    return _SENTIMENT_PIPELINE is not None

def is_ml_active() -> bool:
    """Check if the scikit-learn ML pipeline is loaded and active."""
    if not _INITIALIZED:
        init_ml_models()
    return _SENTIMENT_PIPELINE is not None

def predict_ml_sentiment(text: str) -> dict[str, Any] | None:
    """Predict valence and continuous polarity score in [-1.0, 1.0]."""
    if not is_ml_active() or _SENTIMENT_PIPELINE is None or not text.strip():
        return None

    try:
        # Get class probabilities
        probs = _SENTIMENT_PIPELINE.predict_proba([text])[0]
        classes = [str(c) for c in _SENTIMENT_PIPELINE.classes_]
        prob_dict = {str(cls_name): round(float(probs[i]), 4) for i, cls_name in enumerate(classes)}

        pos_p = prob_dict.get("Positive", 0.0)
        neg_p = prob_dict.get("Negative", 0.0)
        neu_p = prob_dict.get("Neutral", 0.0)

        # Polarity: difference between positive and negative probabilities
        # Yields continuous score in [-1.0, 1.0]
        raw_polarity = pos_p - neg_p
        polarity = round(max(-1.0, min(1.0, raw_polarity)), 4)

        # Valence prediction
        pred_valence = str(_SENTIMENT_PIPELINE.predict([text])[0])
        confidence = round(float(max(probs)), 4)

        return {
            "polarity": polarity,
            "valence": pred_valence,
            "confidence": confidence,
            "probabilities": prob_dict,
            "algorithm": "scikit-learn LogisticRegression (word 1-2 + char 2-5 n-gram TF-IDF)",
        }
    except Exception as exc:
        logger.warning("ML sentiment prediction failed: %s", exc)
        return None

def predict_ml_domain(text: str) -> dict[str, Any] | None:
    """Predict pedagogical cluster among the 5 Likert clusters + Unclassified."""
    if not is_ml_active() or _DOMAIN_PIPELINE is None or not text.strip():
        return None

    try:
        pred_cluster = str(_DOMAIN_PIPELINE.predict([text])[0])
        probs = _DOMAIN_PIPELINE.predict_proba([text])[0]
        classes = [str(c) for c in _DOMAIN_PIPELINE.classes_]
        prob_dict = {str(cls_name): round(float(probs[i]), 4) for i, cls_name in enumerate(classes)}
        confidence = round(float(max(probs)), 4)

        return {
            "cluster": pred_cluster,
            "confidence": confidence,
            "probabilities": prob_dict,
        }
    except Exception as exc:
        logger.warning("ML domain prediction failed: %s", exc)
        return None

def get_ml_provenance() -> dict[str, Any]:
    """Return provenance metadata about the scikit-learn models."""
    active = is_ml_active()
    return {
        "ml_engine": "scikit-learn+NLTK",
        "ml_model_loaded": active,
        "sentiment_pipeline_loaded": _SENTIMENT_PIPELINE is not None,
        "domain_pipeline_loaded": _DOMAIN_PIPELINE is not None,
        "metadata": _METADATA,
    }

def get_model_metadata() -> dict[str, Any]:
    """Return serialized model metadata dictionary."""
    if not _INITIALIZED:
        init_ml_models()
    return _METADATA

def classify_text(text: str) -> dict[str, Any]:
    """Convenience alias for sentiment classification."""
    res = predict_ml_sentiment(text)
    if res is None:
        return {"polarity": 0.0, "valence": "Neutral", "confidence": 0.0, "probabilities": {}}
    return res

def classify_domain(text: str) -> dict[str, Any]:
    """Convenience alias for pedagogical domain classification."""
    res = predict_ml_domain(text)
    if res is None:
        return {"cluster": "Unclassified / Needs Review", "confidence": 0.0, "probabilities": {}}
    return res
