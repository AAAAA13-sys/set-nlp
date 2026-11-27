"""School AAAAA13 EvaluaTech — Python NLP & Machine Learning Package (`evaluatech_nlp_py`)."""

from .engine import (
    MAX_BATCH_SIZE,
    PEDAGOGICAL_TAGS,
    TAG_UNCLASSIFIED,
    analyze_comment,
    analyze_comment_item,
    analyze_professor_questions,
    generate_pedagogical_action_plan,
    get_engine_provenance,
    scrub_pii,
    summarize_comments,
)

__all__ = [
    "MAX_BATCH_SIZE",
    "PEDAGOGICAL_TAGS",
    "TAG_UNCLASSIFIED",
    "analyze_comment",
    "analyze_comment_item",
    "analyze_professor_questions",
    "generate_pedagogical_action_plan",
    "get_engine_provenance",
    "scrub_pii",
    "summarize_comments",
]
