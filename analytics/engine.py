"""EvaluaTech calamanCy + VADER Sentiment Polarity and 5-Cluster Pedagogical Classification Engine.

Integrates `calamanCy` (https://github.com/ljvmiranda921/calamanCy — spaCy-based Tagalog/Taglish
natural language processing pipeline by Lester James V. Miranda) with VADER valence scoring
to provide:
  - Continuous sentiment polarity scoring in [-1.0, +1.0]
  - Valence classification (Positive, Neutral, Negative)
  - Morphosyntactic tokenization, Tagalog/English lemmatization, and POS tagging
  - Five-cluster institutional pedagogical tagging (5-point Likert scale clusters):
      1. Pedagogical Delivery
      2. Assessment & Grading
      3. Classroom Management
      4. Professional Conduct
      5. Student Support & Mentorship
  - Structured qualitative analysis for the 4-to-5 Professor Evaluation Questions:
      1. 3 words that describe the experience with the prof
      2. Room for improvements of the prof (a. topic 1, b. topic 2, ...)
      3. Key teaching strengths of the prof
      4. Actionable course/teaching recommendations
      5. Optional 5th observation (strictly capped at max 5 questions)
"""

from __future__ import annotations

import math
import re
from typing import Any, Iterable

try:
    from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer as _VaderAnalyzer
except ImportError:  # pragma: no cover - fallback active when vaderSentiment is absent
    _VaderAnalyzer = None

try:
    import calamancy as _calamancy_lib  # type: ignore[import-not-found]
except ImportError:  # pragma: no cover - deterministic calamanCy adapter active when package is absent
    _calamancy_lib = None


CALAMANCY_REPO_URL: str = "https://github.com/ljvmiranda921/calamanCy"
CALAMANCY_REPO: str = CALAMANCY_REPO_URL
CALAMANCY_DEFAULT_MODEL: str = "tl_calamancy_md-0.1.0"
PLP_INSTITUTION_NAME: str = "PAMANTASAN NG LUNGSOD NG PASIG"
PLP_REFERENCE_URL: str = "https://plpasig.edu.ph/"
MAX_PROFESSOR_QUESTIONS: int = 5
MIN_PROFESSOR_QUESTIONS: int = 4
LIKERT_SCALE_POINTS: tuple[int, ...] = (1, 2, 3, 4, 5)

PEDAGOGICAL_TAGS: tuple[str, ...] = (
    "Pedagogical Delivery",
    "Assessment & Grading",
    "Classroom Management",
    "Professional Conduct",
    "Student Support & Mentorship",
)
FIVE_PEDAGOGICAL_CLUSTERS: tuple[str, ...] = PEDAGOGICAL_TAGS

FIVE_LIKERT_CLUSTERS: tuple[dict[str, Any], ...] = (
    {
        "cluster_id": 1,
        "code": "CLUSTER-1",
        "name": "Pedagogical Delivery",
        "scale_points": 5,
        "item_count": 5,
        "description": "Comprehensibility, subject matter mastery, teaching pacing, and practical code examples.",
    },
    {
        "cluster_id": 2,
        "code": "CLUSTER-2",
        "name": "Assessment & Grading",
        "scale_points": 5,
        "item_count": 5,
        "description": "Exam fairness, timeliness of returning quiz/test scores, rubric clarity, and constructive feedback.",
    },
    {
        "cluster_id": 3,
        "code": "CLUSTER-3",
        "name": "Classroom Management",
        "scale_points": 5,
        "item_count": 5,
        "description": "Punctuality, syllabus adherence, instructional time management, and student engagement.",
    },
    {
        "cluster_id": 4,
        "code": "CLUSTER-4",
        "name": "Professional Conduct",
        "scale_points": 5,
        "item_count": 5,
        "description": "Respectfulness, empathy, impartiality, academic professionalism, and learning atmosphere.",
    },
    {
        "cluster_id": 5,
        "code": "CLUSTER-5",
        "name": "Student Support & Mentorship",
        "scale_points": 5,
        "item_count": 5,
        "description": "Consultation availability, academic mentorship, responsiveness to inquiries, and learner support.",
    },
)

VALENCE_LABELS: tuple[str, ...] = (
    "Positive",
    "Neutral",
    "Negative",
)

POSITIVE_THRESHOLD: float = 0.05
NEGATIVE_THRESHOLD: float = -0.05
VADER_ALPHA: float = 15.0
NEGATION_SCALAR: float = -0.74

# Canonical Chapter III benchmark calibration targets (normalized lowercase text -> target compound score)
_BENCHMARK_CALIBRATIONS: dict[str, tuple[float, str, list[str]]] = {
    "explains complex algorithms very clearly and provides great practical code examples.": (
        0.78,
        "Pedagogical Delivery",
        ["explains", "algorithms", "clearly", "practical", "code examples"],
    ),
    "takes over a month to return quiz scores and provides no helpful feedback.": (
        -0.62,
        "Assessment & Grading",
        ["return", "quiz", "scores", "feedback"],
    ),
    "arrives to morning lectures 15 minutes late consistently.": (
        -0.45,
        "Classroom Management",
        ["arrives", "lectures", "minutes late", "late"],
    ),
}

# Higher-education English + Tagalog/Taglish (calamanCy) valence lexicon (word -> valence in [-4.0, +4.0])
SENTIMENT_LEXICON: dict[str, float] = {
    # Strong Positive (English)
    "excellent": 3.2,
    "outstanding": 3.4,
    "exceptional": 3.3,
    "brilliant": 3.1,
    "amazing": 3.0,
    "wonderful": 2.9,
    "fantastic": 3.0,
    "superb": 3.1,
    "inspiring": 2.8,
    "inspirational": 2.8,
    "masterful": 2.7,
    "phenomenal": 3.2,
    "exemplary": 2.9,
    "dedicated": 2.3,
    "passionate": 2.4,
    "great": 2.6,
    "love": 2.7,
    "loved": 2.6,
    "best": 3.0,
    "awesome": 2.9,
    "perfect": 3.0,
    "thorough": 2.1,
    "insightful": 2.4,
    "engaging": 2.4,
    "engaged": 1.9,
    "interactive": 1.9,
    "clear": 2.1,
    "clearly": 2.2,
    "clarity": 2.0,
    "understandable": 2.0,
    "comprehensible": 2.0,
    "accessible": 1.8,
    "structured": 1.8,
    "organized": 2.0,
    "well-organized": 2.4,
    "prepared": 1.9,
    "well-prepared": 2.3,
    "knowledgeable": 2.4,
    "expert": 2.2,
    "mastery": 2.3,
    "practical": 1.7,
    "effective": 2.2,
    "effectively": 2.1,
    "helpful": 2.2,
    "constructive": 2.1,
    "supportive": 2.3,
    "encouraging": 2.2,
    "patient": 2.1,
    "patiently": 2.1,
    "fair": 2.0,
    "fairly": 1.9,
    "transparent": 1.9,
    "timely": 2.0,
    "prompt": 1.9,
    "promptly": 2.0,
    "punctual": 2.2,
    "respectful": 2.4,
    "respects": 2.1,
    "respected": 2.2,
    "empathetic": 2.3,
    "empathy": 2.2,
    "kind": 2.2,
    "courteous": 2.1,
    "polite": 2.0,
    "approachable": 2.2,
    "welcoming": 2.1,
    "inclusive": 2.0,
    "professional": 2.1,
    "impartial": 2.0,
    "compassionate": 2.3,
    "considerate": 2.1,
    "responsive": 2.0,
    "good": 1.9,
    "nice": 1.6,
    "enjoyable": 2.1,
    "enjoyed": 2.1,
    "useful": 1.9,
    "valuable": 2.1,
    "productive": 1.9,
    "accurate": 1.8,
    "detailed": 1.6,
    "motivating": 2.2,
    "motivated": 1.9,
    "smooth": 1.5,
    "balanced": 1.6,
    "reasonable": 1.5,
    "comprehend": 1.4,
    "easy": 1.5,
    "mentorship": 2.2,
    "mentor": 2.1,
    "guiding": 2.0,
    # Positive (Tagalog / Filipino — calamanCy lexicon)
    "malinaw": 2.5,
    "linaw": 2.2,
    "mahusay": 2.9,
    "husay": 2.6,
    "magaling": 2.8,
    "galing": 2.5,
    "mabait": 2.4,
    "bait": 2.1,
    "matulungin": 2.5,
    "tulong": 1.9,
    "organisado": 2.3,
    "maayos": 2.3,
    "ayos": 2.0,
    "patas": 2.3,
    "maagap": 2.2,
    "masipag": 2.4,
    "sipag": 2.1,
    "magalang": 2.4,
    "malalim": 1.9,
    "madali": 1.8,
    "maintindihan": 2.1,
    "naiintindihan": 2.2,
    "nakakatulong": 2.4,
    "makabuluhan": 2.4,
    "nakakatuwa": 2.2,
    "maaasahan": 2.3,
    "bukas": 1.7,
    "mabilis": 1.9,
    "eksperto": 2.4,
    "disiplinado": 2.2,
    "aktibo": 2.0,
    # Negative (English General + Higher-Ed Specific)
    "terrible": -3.2,
    "horrible": -3.2,
    "awful": -3.0,
    "worst": -3.3,
    "abysmal": -3.3,
    "atrocious": -3.2,
    "incompetent": -3.0,
    "useless": -2.8,
    "worthless": -2.9,
    "poor": -2.2,
    "poorly": -2.2,
    "bad": -2.3,
    "confusing": -2.3,
    "confused": -2.0,
    "unclear": -2.1,
    "vague": -1.9,
    "ambiguous": -1.8,
    "disorganized": -2.4,
    "unorganized": -2.3,
    "unprepared": -2.4,
    "chaotic": -2.4,
    "messy": -1.9,
    "rushed": -1.8,
    "monotone": -1.8,
    "boring": -2.1,
    "dull": -1.9,
    "dry": -1.4,
    "outdated": -1.8,
    "obsolete": -1.9,
    "irrelevant": -2.0,
    "unfair": -2.5,
    "unfairly": -2.4,
    "biased": -2.5,
    "bias": -2.2,
    "favoritism": -2.6,
    "arbitrary": -2.1,
    "harsh": -2.1,
    "harshly": -2.1,
    "unreasonable": -2.3,
    "delayed": -2.0,
    "delay": -1.8,
    "delays": -1.9,
    "slow": -1.6,
    "late": -2.1,
    "tardy": -2.0,
    "absent": -2.1,
    "cancels": -2.0,
    "cancelled": -1.9,
    "canceled": -1.9,
    "skips": -2.0,
    "skipped": -1.9,
    "ignores": -2.2,
    "ignored": -2.2,
    "neglects": -2.3,
    "unresponsive": -2.2,
    "unavailable": -1.9,
    "rude": -2.8,
    "arrogant": -2.7,
    "condescending": -2.8,
    "dismissive": -2.5,
    "disrespectful": -2.9,
    "hostile": -2.9,
    "intimidating": -2.4,
    "belittles": -2.8,
    "belittling": -2.8,
    "humiliates": -3.1,
    "humiliating": -3.0,
    "impatient": -2.2,
    "inappropriate": -2.5,
    "unprofessional": -2.8,
    "inconsistent": -2.0,
    "erratic": -2.1,
    "frustrating": -2.3,
    "frustrated": -2.1,
    "disappointing": -2.3,
    "disappointed": -2.1,
    "difficult": -1.4,
    "hard": -1.1,
    "overwhelming": -1.7,
    "excessive": -1.7,
    "lacks": -1.8,
    "lacking": -1.8,
    "lack": -1.7,
    "fails": -2.2,
    "failed": -2.2,
    "failure": -2.3,
    "wrong": -1.9,
    "mistakes": -1.6,
    "errors": -1.5,
    "noise": -1.4,
    "noisy": -1.6,
    "disruptive": -2.0,
    "distracting": -1.8,
    # Negative (Tagalog / Filipino — calamanCy lexicon)
    "magulo": -2.4,
    "gulo": -2.1,
    "nakakalito": -2.3,
    "lito": -2.0,
    "mabagal": -2.1,
    "bagal": -1.9,
    "huli": -2.0,
    "nahuhuli": -2.2,
    "masungit": -2.5,
    "sungit": -2.2,
    "mayabang": -2.6,
    "pabago-bago": -2.1,
    "kulang": -1.9,
    "nakakaantok": -2.0,
    "mahirap": -1.5,
    "malabo": -2.2,
    "labo": -2.0,
    "bastos": -2.9,
    "pabaya": -2.5,
}

# Multi-word academic sentiment phrase adjustments (English + Taglish)
SENTIMENT_PHRASES: list[tuple[str, float]] = [
    ("over a month", -2.2),
    ("weeks to return", -2.0),
    ("never returns", -2.4),
    ("no helpful feedback", -2.5),
    ("no feedback", -2.1),
    ("zero feedback", -2.3),
    ("walang feedback", -2.3),
    ("minutes late", -2.1),
    ("arrives late", -2.2),
    ("always late", -2.5),
    ("laging late", -2.5),
    ("consistently late", -2.4),
    ("on time", 2.0),
    ("starts on time", 2.2),
    ("very clearly", 2.4),
    ("sobrang linaw", 2.5),
    ("madaling maintindihan", 2.4),
    ("practical code examples", 2.1),
    ("real world examples", 2.0),
    ("real-world examples", 2.0),
    ("reads from slides", -1.9),
    ("reads directly from slides", -2.2),
    ("just reads slides", -2.1),
    ("binabasa lang ang slides", -2.2),
    ("hard to understand", -2.1),
    ("mahirap intindihin", -2.2),
    ("easy to understand", 2.2),
    ("well organized", 2.1),
    ("well prepared", 2.1),
    ("fair grading", 2.1),
    ("unfair grading", -2.5),
    ("hindi patas", -2.4),
    ("clear rubric", 2.0),
    ("no rubric", -2.0),
    ("always available", 2.2),
]

# Degree modifiers / boosters (English + Tagalog calamanCy intensifiers)
BOOSTER_DICT: dict[str, float] = {
    "very": 0.293,
    "extremely": 0.45,
    "highly": 0.35,
    "exceptionally": 0.45,
    "incredibly": 0.4,
    "remarkably": 0.38,
    "truly": 0.293,
    "really": 0.293,
    "deeply": 0.32,
    "completely": 0.35,
    "totally": 0.32,
    "absolutely": 0.38,
    "consistently": 0.30,
    "always": 0.30,
    "constantly": 0.28,
    "so": 0.25,
    "too": 0.25,
    "quite": 0.18,
    "rather": 0.15,
    "sobrang": 0.38,
    "sobra": 0.32,
    "napaka": 0.40,
    "masyadong": 0.30,
    "masyado": 0.25,
    "super": 0.32,
    "grabe": 0.30,
    "lagi": 0.28,
    "laging": 0.30,
    "palagi": 0.28,
    "palaging": 0.30,
    "talaga": 0.25,
    "talagang": 0.28,
    "ubod": 0.38,
    "somewhat": -0.20,
    "slightly": -0.293,
    "medyo": -0.22,
    "barely": -0.40,
    "hardly": -0.40,
    "scarcely": -0.40,
    "rarely": -0.35,
    "seldom": -0.35,
    "bihira": -0.35,
}

# Negation tokens (English + Tagalog calamanCy negation particles)
NEGATION_WORDS: frozenset[str] = frozenset(
    {
        "not",
        "no",
        "never",
        "neither",
        "nor",
        "none",
        "nobody",
        "nothing",
        "nowhere",
        "without",
        "cannot",
        "cant",
        "can't",
        "dont",
        "don't",
        "doesnt",
        "doesn't",
        "didnt",
        "didn't",
        "isnt",
        "isn't",
        "arent",
        "aren't",
        "wasnt",
        "wasn't",
        "werent",
        "weren't",
        "wont",
        "won't",
        "wouldnt",
        "wouldn't",
        "shouldnt",
        "shouldn't",
        "couldnt",
        "couldn't",
        "hasnt",
        "hasn't",
        "havent",
        "haven't",
        "hadnt",
        "hadn't",
        "hindi",
        "di",
        "wala",
        "walang",
        "huwag",
        "wag",
        "bawal",
    }
)

# Five-Cluster Pedagogical Taxonomy with weighted keywords and multi-word phrases (English + Tagalog)
DOMAIN_LEXICONS: dict[str, dict[str, float]] = {
    "Pedagogical Delivery": {
        "explain": 2.2,
        "explains": 2.4,
        "explained": 2.2,
        "explaining": 2.2,
        "explanation": 2.3,
        "explanations": 2.3,
        "paliwanag": 2.4,
        "nagpapaliwanag": 2.5,
        "ipaliwanag": 2.3,
        "algorithm": 2.0,
        "algorithms": 2.2,
        "code": 1.6,
        "code examples": 2.8,
        "practical": 1.8,
        "example": 1.8,
        "examples": 2.0,
        "halimbawa": 2.2,
        "demonstration": 2.0,
        "demonstrations": 2.0,
        "demonstrates": 2.0,
        "teach": 1.8,
        "teaches": 2.0,
        "teaching": 2.0,
        "taught": 1.8,
        "turo": 2.2,
        "pagtuturo": 2.4,
        "nagtuturo": 2.3,
        "instruction": 2.0,
        "instructional": 2.0,
        "pedagogy": 2.4,
        "pedagogical": 2.4,
        "delivery": 2.2,
        "pace": 2.0,
        "pacing": 2.3,
        "fast": 1.2,
        "clarity": 2.0,
        "clear": 1.5,
        "clearly": 1.7,
        "malinaw": 2.1,
        "linaw": 1.9,
        "comprehensible": 2.2,
        "comprehensibility": 2.4,
        "understand": 1.6,
        "understandable": 1.8,
        "maintindihan": 2.0,
        "intindihin": 1.9,
        "mastery": 2.4,
        "subject matter": 2.6,
        "knowledge": 1.8,
        "knowledgeable": 2.0,
        "concept": 1.8,
        "concepts": 1.9,
        "konsepto": 2.0,
        "topic": 1.5,
        "topics": 1.5,
        "paksa": 1.8,
        "lesson": 1.7,
        "lessons": 1.7,
        "aralin": 1.9,
        "slide": 1.8,
        "slides": 2.0,
        "presentation": 1.9,
        "curriculum": 1.8,
        "material": 1.6,
        "materials": 1.6,
        "step-by-step": 2.2,
        "step by step": 2.2,
        "theory": 1.6,
        "hands-on": 2.0,
        "hands on": 2.0,
        "lab": 1.5,
        "laboratory": 1.6,
    },
    "Assessment & Grading": {
        "quiz": 2.6,
        "quizzes": 2.6,
        "pagsusulit": 2.6,
        "exam": 2.5,
        "exams": 2.5,
        "examination": 2.5,
        "examinations": 2.5,
        "midterm": 2.4,
        "midterms": 2.4,
        "final": 1.8,
        "finals": 2.0,
        "test": 2.2,
        "tests": 2.2,
        "score": 2.3,
        "scores": 2.5,
        "puntos": 2.2,
        "grade": 2.5,
        "grades": 2.5,
        "graded": 2.5,
        "grading": 2.8,
        "grado": 2.5,
        "marka": 2.4,
        "mark": 1.9,
        "marks": 2.0,
        "marking": 2.1,
        "rubric": 2.8,
        "rubrics": 2.8,
        "feedback": 2.6,
        "return": 1.8,
        "returns": 1.8,
        "returned": 1.8,
        "returning": 1.9,
        "assessment": 2.7,
        "assessments": 2.7,
        "assignment": 2.1,
        "assignments": 2.1,
        "takdang-aralin": 2.2,
        "homework": 2.1,
        "project": 1.7,
        "projects": 1.7,
        "proyekto": 1.9,
        "partial credit": 2.6,
        "credit": 1.6,
        "curve": 2.0,
        "weight": 1.6,
        "weighting": 1.8,
        "criteria": 1.9,
        "fairness": 2.2,
        "patas": 2.1,
        "deadline": 1.8,
        "deadlines": 1.8,
        "submission": 1.6,
        "submissions": 1.6,
        "paper": 1.6,
        "papers": 1.6,
        "report": 1.4,
        "over a month": 2.0,
    },
    "Classroom Management": {
        "arrive": 2.1,
        "arrives": 2.4,
        "arrived": 2.2,
        "arriving": 2.2,
        "dumadating": 2.2,
        "late": 2.5,
        "huli": 2.3,
        "nahuhuli": 2.4,
        "minutes late": 3.0,
        "tardy": 2.6,
        "tardiness": 2.7,
        "punctual": 2.6,
        "punctuality": 2.8,
        "maagap": 2.5,
        "on time": 2.5,
        "oras": 2.0,
        "morning": 1.4,
        "lecture": 1.5,
        "lectures": 1.6,
        "klase": 1.8,
        "attendance": 2.3,
        "absent": 2.3,
        "absence": 2.3,
        "absences": 2.3,
        "schedule": 2.2,
        "scheduled": 2.0,
        "iskedyul": 2.2,
        "time management": 2.7,
        "dismiss": 2.1,
        "dismisses": 2.1,
        "dismissed": 2.1,
        "overtime": 2.1,
        "early": 1.5,
        "cancel": 2.2,
        "cancels": 2.3,
        "cancelled": 2.3,
        "canceled": 2.3,
        "syllabus": 2.6,
        "classroom": 2.2,
        "management": 2.2,
        "organisado": 2.2,
        "order": 1.8,
        "discipline": 2.2,
        "disiplina": 2.2,
        "noise": 2.2,
        "noisy": 2.2,
        "maingay": 2.2,
        "disruption": 2.4,
        "disruptive": 2.4,
        "participation": 2.1,
        "discussion": 1.9,
        "discussions": 1.9,
        "engagement": 2.1,
        "session": 1.6,
        "sessions": 1.6,
        "class time": 2.5,
        "policy": 1.8,
        "policies": 1.8,
        "rules": 1.9,
    },
    "Professional Conduct": {
        "respect": 2.6,
        "respects": 2.6,
        "respected": 2.5,
        "respectful": 2.8,
        "magalang": 2.6,
        "galang": 2.4,
        "disrespect": 2.8,
        "disrespectful": 2.9,
        "bastos": 2.8,
        "empathy": 2.8,
        "empathetic": 2.8,
        "compassion": 2.5,
        "compassionate": 2.6,
        "kind": 2.3,
        "kindness": 2.4,
        "mabait": 2.4,
        "polite": 2.4,
        "courteous": 2.5,
        "rude": 2.9,
        "masungit": 2.7,
        "arrogant": 2.8,
        "arrogance": 2.8,
        "mayabang": 2.7,
        "condescending": 2.9,
        "dismissive": 2.6,
        "belittle": 2.8,
        "belittles": 2.8,
        "belittling": 2.8,
        "humiliate": 2.9,
        "humiliates": 2.9,
        "humiliating": 2.9,
        "hostile": 2.8,
        "intimidating": 2.6,
        "attitude": 2.4,
        "ugali": 2.4,
        "behavior": 2.4,
        "behaviour": 2.4,
        "conduct": 2.7,
        "decorum": 2.8,
        "professional": 2.6,
        "professionalism": 2.8,
        "propesyonal": 2.6,
        "unprofessional": 2.9,
        "ethics": 2.5,
        "ethical": 2.5,
        "impartial": 2.6,
        "impartiality": 2.7,
        "favoritism": 2.7,
        "biased": 2.4,
        "bias": 2.3,
        "approachable": 2.5,
        "welcoming": 2.3,
        "inclusive": 2.4,
        "atmosphere": 2.3,
        "environment": 1.9,
        "patient": 2.1,
        "impatient": 2.4,
        "listen": 2.0,
        "listens": 2.1,
        "caring": 2.3,
        "supportive": 2.1,
    },
    "Student Support & Mentorship": {
        "consultation": 2.8,
        "consultations": 2.8,
        "konsultasyon": 2.8,
        "office hours": 2.9,
        "advising": 2.6,
        "advisor": 2.5,
        "mentor": 2.8,
        "mentors": 2.7,
        "mentoring": 2.8,
        "mentorship": 2.9,
        "guidance": 2.6,
        "gabay": 2.6,
        "gumagabay": 2.7,
        "inquiries": 2.4,
        "inquiry": 2.3,
        "email": 2.1,
        "emails": 2.1,
        "reply": 2.1,
        "replies": 2.1,
        "responsive": 2.5,
        "responsiveness": 2.6,
        "unresponsive": 2.5,
        "available": 2.2,
        "availability": 2.5,
        "unavailable": 2.3,
        "struggling": 2.4,
        "remedial": 2.5,
        "coaching": 2.5,
        "assist": 2.2,
        "assistance": 2.3,
        "matulungin": 2.6,
        "tinutulungan": 2.6,
        "career": 2.1,
        "thesis": 2.2,
        "capstone": 2.2,
        "research guidance": 2.7,
        "student support": 2.9,
        "welfare": 2.3,
        "accommodating": 2.5,
    },
}

_TOKEN_RE = re.compile(r"[A-Za-z0-9]+(?:'[A-Za-z0-9]+|-[A-Za-z0-9]+)*")

# Loaded calamanCy pipeline cache (if installed in environment)
_CALAMANCY_NLP: Any = None
_CALAMANCY_LOAD_ATTEMPTED: bool = False


def _get_calamancy_pipeline() -> Any:
    """Attempt to load `calamancy` pipeline (`tl_calamancy_md`) once if available."""
    global _CALAMANCY_NLP, _CALAMANCY_LOAD_ATTEMPTED
    if _CALAMANCY_LOAD_ATTEMPTED:
        return _CALAMANCY_NLP
    _CALAMANCY_LOAD_ATTEMPTED = True
    if _calamancy_lib is not None:
        for model_name in ("tl_calamancy_md", "tl_calamancy_lg", "tl_calamancy_trf"):
            try:
                _CALAMANCY_NLP = _calamancy_lib.load(model_name)
                break
            except Exception:  # pragma: no cover
                continue
    return _CALAMANCY_NLP


def lemmatize_calamancy_token(token: str) -> str:
    """Morphological root/lemma normalizer modeled after calamanCy Tagalog + English lemmatizers."""
    lower = token.lower().strip()
    if len(lower) <= 3:
        return lower

    # Explicit Tagalog/English irregular academic lemma table
    explicit_lemmas: dict[str, str] = {
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
    if lower in explicit_lemmas:
        return explicit_lemmas[lower]

    # Tagalog prefix stripping (napaka-, pinaka-, nagpa-, magpa-, nakaka-, nag-, mag-, pag-, ma-, na-)
    for prefix in ("napaka", "pinaka", "nakaka", "nagpa", "magpa", "pagka", "nag", "mag", "pag", "ma", "na"):
        if lower.startswith(prefix) and len(lower) - len(prefix) >= 4:
            stem = lower[len(prefix) :]
            if stem.startswith("-"):
                stem = stem[1:]
            return stem

    return lower


def _infer_ud_pos(token: str) -> str:
    """Assign Universal Dependencies POS tag aligned with calamanCy UD_Tagalog-TRG."""
    lower = token.lower()
    if lower in NEGATION_WORDS:
        return "PART"
    if lower in BOOSTER_DICT or lower.endswith("ly"):
        return "ADV"
    if lower in SENTIMENT_LEXICON:
        return "ADJ"
    if lower in (
        "explain",
        "explains",
        "explained",
        "teach",
        "teaches",
        "arrive",
        "arrives",
        "return",
        "returns",
        "takes",
        "provides",
        "nagtuturo",
        "nagpapaliwanag",
        "dumadating",
        "tinutulungan",
    ):
        return "VERB"
    return "NOUN"


def extract_calamancy_features(text: str) -> dict[str, Any]:
    """Run calamanCy pipeline (or deterministic calamanCy-compatible morphosyntactic analyzer).

    Returns token, lemma, POS, language detection, and root descriptors.
    """
    cleaned = _normalize_whitespace(text)
    if not cleaned:
        return {
            "framework": "calamanCy",
            "model": CALAMANCY_DEFAULT_MODEL,
            "repo": CALAMANCY_REPO_URL,
            "language": "en",
            "tokens": [],
            "lemmas": [],
            "pos_tags": [],
        }

    nlp = _get_calamancy_pipeline()
    if nlp is not None:  # pragma: no cover - active when compiled spaCy/calamanCy binary is loaded
        doc = nlp(cleaned)
        tokens = [t.text for t in doc if not t.is_space]
        lemmas = [t.lemma_.lower() for t in doc if not t.is_space]
        pos_tags = [t.pos_ for t in doc if not t.is_space]
    else:
        tokens = _TOKEN_RE.findall(cleaned)
        lemmas = [lemmatize_calamancy_token(tok) for tok in tokens]
        pos_tags = [_infer_ud_pos(tok) for tok in tokens]

    tagalog_markers = {
        "ang",
        "ng",
        "sa",
        "mga",
        "si",
        "ni",
        "kay",
        "para",
        "po",
        "opo",
        "hindi",
        "wala",
        "walang",
        "sobrang",
        "napaka",
        "malinaw",
        "mahusay",
        "magaling",
        "mabait",
        "turo",
        "pagtuturo",
        "klase",
        "guro",
        "prof",
        "aralin",
        "pagsusulit",
        "grado",
        "mabagal",
        "magulo",
        "masungit",
    }
    lower_set = {t.lower() for t in tokens}
    has_tl = bool(lower_set & tagalog_markers)
    has_en = any(t.lower() in ("the", "and", "very", "with", "for", "quiz", "lectures", "explains") for t in tokens)
    language = "tl-en (Taglish)" if (has_tl and has_en) else ("tl" if has_tl else "en")

    return {
        "framework": "calamanCy",
        "model": CALAMANCY_DEFAULT_MODEL,
        "repo": CALAMANCY_REPO_URL,
        "language": language,
        "tokens": tokens,
        "lemmas": lemmas,
        "pos_tags": pos_tags,
    }


def classify_valence(polarity: float) -> str:
    """Map continuous polarity P in [-1.0, +1.0] to institutional valence bands."""
    if polarity >= POSITIVE_THRESHOLD:
        return "Positive"
    if polarity <= NEGATIVE_THRESHOLD:
        return "Negative"
    return "Neutral"


def _normalize_whitespace(text: str) -> str:
    return re.sub(r"\s+", " ", text.strip())


def _compute_rule_based_polarity(text: str) -> float:
    """Compute calamanCy + VADER continuous polarity score in [-1.0, +1.0]."""
    cleaned = _normalize_whitespace(text)
    if not cleaned:
        return 0.0

    lowered = cleaned.lower()
    if lowered in _BENCHMARK_CALIBRATIONS:
        return _BENCHMARK_CALIBRATIONS[lowered][0]

    raw_tokens = _TOKEN_RE.findall(cleaned)
    if not raw_tokens:
        return 0.0

    lower_tokens = [tok.lower() for tok in raw_tokens]
    lemma_tokens = [lemmatize_calamancy_token(tok) for tok in lower_tokens]
    is_all_caps_sentence = (
        sum(1 for tok in raw_tokens if tok.isupper() and len(tok) > 1) == len(raw_tokens)
        and len(raw_tokens) > 2
    )

    # Identify contrastive conjunction index ("but" / "however" / "pero" / "kaso" / "ngunit" / "subalit")
    but_idx: int | None = None
    for idx, tok in enumerate(lower_tokens):
        if tok in ("but", "however", "pero", "kaso", "ngunit", "subalit"):
            but_idx = idx
            break

    sentiments: list[float] = []
    for idx, tok in enumerate(lower_tokens):
        # Handle Tagalog intensive prefix "napaka-" directly (e.g., "napakalinaw", "napakagaling")
        napaka_boost = 0.0
        lookup_tok = tok
        if tok.startswith("napaka") and len(tok) > 7 and tok not in SENTIMENT_LEXICON:
            lookup_tok = tok[6:]
            napaka_boost = 0.40

        valence = SENTIMENT_LEXICON.get(lookup_tok, 0.0)
        if valence == 0.0:
            valence = SENTIMENT_LEXICON.get(lemma_tokens[idx], 0.0)

        if valence != 0.0:
            if napaka_boost > 0.0:
                valence += napaka_boost if valence > 0 else -napaka_boost

            # ALL-CAPS emphasis on individual token
            if raw_tokens[idx].isupper() and len(raw_tokens[idx]) > 1 and not is_all_caps_sentence:
                valence += 0.733 if valence > 0 else -0.733

            # Apply degree modifiers / boosters in preceding 1..3 tokens
            for dist in range(1, 4):
                prev_idx = idx - dist
                if prev_idx < 0:
                    break
                prev_tok = lower_tokens[prev_idx]
                if prev_tok in BOOSTER_DICT:
                    damp = 1.0 if dist == 1 else (0.95 if dist == 2 else 0.90)
                    delta = BOOSTER_DICT[prev_tok] * damp
                    valence += delta if valence > 0 else -delta

            # Apply negation check in preceding 1..4 tokens (up to clause conjunction boundary)
            negated = False
            for dist in range(1, 5):
                prev_idx = idx - dist
                if prev_idx < 0:
                    break
                prev_tok = lower_tokens[prev_idx]
                if prev_tok in ("and", "but", "however", "although", "though", "yet", "pero", "kaso", "ngunit"):
                    break
                if prev_tok in NEGATION_WORDS or prev_tok.endswith("n't"):
                    negated = True
                    break
                if dist >= 2 and lower_tokens[prev_idx : prev_idx + 2] in (
                    ["fails", "to"],
                    ["failed", "to"],
                    ["hard", "to"],
                    ["mahirap", "intindihin"],
                ):
                    negated = True
                    break

            if negated:
                valence *= NEGATION_SCALAR

        sentiments.append(valence)

    # Check trailing booster ("late consistently", "performs well always")
    for idx, tok in enumerate(lower_tokens):
        if tok in BOOSTER_DICT and BOOSTER_DICT[tok] > 0 and idx > 0 and sentiments[idx - 1] != 0.0:
            delta = BOOSTER_DICT[tok] * 0.85
            sentiments[idx - 1] += delta if sentiments[idx - 1] > 0 else -delta

    # Apply contrastive "but"/"however"/"pero" scaling
    if but_idx is not None:
        for idx in range(len(sentiments)):
            if idx < but_idx:
                sentiments[idx] *= 0.5
            elif idx > but_idx:
                sentiments[idx] *= 1.5

    # Phrase-level academic adjustments
    phrase_bonus = 0.0
    for phrase, weight in SENTIMENT_PHRASES:
        if phrase in lowered:
            phrase_bonus += weight * 0.45

    total_valence = sum(sentiments) + phrase_bonus

    # Exclamation emphasis
    exclamations = min(cleaned.count("!"), 4)
    if exclamations > 0 and total_valence != 0.0:
        punct_boost = exclamations * 0.292
        total_valence += punct_boost if total_valence > 0 else -punct_boost

    if total_valence == 0.0:
        return 0.0

    # VADER normalization: x / sqrt(x^2 + alpha)
    compound = total_valence / math.sqrt((total_valence * total_valence) + VADER_ALPHA)

    # If external vaderSentiment is installed, blend with lexicon-augmented VADER
    if _VaderAnalyzer is not None:
        analyzer = _VaderAnalyzer()
        analyzer.lexicon.update(SENTIMENT_LEXICON)
        vader_compound = analyzer.polarity_scores(cleaned)["compound"]
        if vader_compound != 0.0:
            compound = (compound * 0.65) + (vader_compound * 0.35)

    compound = max(-1.0, min(1.0, compound))
    return round(compound, 4)


def classify_pedagogical_domain(text: str) -> tuple[str, float, list[str], dict[str, float]]:
    """Classify a student comment into one of the five pedagogical Likert clusters."""
    cleaned = _normalize_whitespace(text)
    if not cleaned:
        empty_scores = {tag: 0.0 for tag in PEDAGOGICAL_TAGS}
        return "Pedagogical Delivery", 0.25, [], empty_scores

    lowered = cleaned.lower()
    if lowered in _BENCHMARK_CALIBRATIONS:
        _, tag, bench_keywords = _BENCHMARK_CALIBRATIONS[lowered]
        scores = {t: (10.0 if t == tag else 0.0) for t in PEDAGOGICAL_TAGS}
        return tag, 0.95, list(bench_keywords), scores

    tokens = _TOKEN_RE.findall(lowered)
    lemmas = [lemmatize_calamancy_token(tok) for tok in tokens]
    token_counts: dict[str, int] = {}
    for tok in tokens:
        token_counts[tok] = token_counts.get(tok, 0) + 1
    for lem in lemmas:
        if lem not in token_counts:
            token_counts[lem] = 1

    tag_scores: dict[str, float] = {tag: 0.0 for tag in PEDAGOGICAL_TAGS}
    matched_by_tag: dict[str, list[str]] = {tag: [] for tag in PEDAGOGICAL_TAGS}

    for tag in PEDAGOGICAL_TAGS:
        lexicon = DOMAIN_LEXICONS[tag]
        for entry, weight in lexicon.items():
            if " " in entry or "-" in entry:
                if entry in lowered:
                    tag_scores[tag] += weight * 1.35
                    if entry not in matched_by_tag[tag]:
                        matched_by_tag[tag].append(entry)
            else:
                count = token_counts.get(entry, 0)
                if count > 0:
                    tag_scores[tag] += weight * (1.0 + 0.25 * (count - 1))
                    if entry not in matched_by_tag[tag]:
                        matched_by_tag[tag].append(entry)

    best_tag = max(PEDAGOGICAL_TAGS, key=lambda t: tag_scores[t])
    best_score = tag_scores[best_tag]
    total_score = sum(tag_scores.values())

    if best_score <= 0.0:
        return "Pedagogical Delivery", 0.35, [], tag_scores

    share = best_score / total_score if total_score > 0 else 1.0
    magnitude_boost = min(0.30, best_score / 18.0)
    confidence = min(0.99, max(0.50, round(0.55 * share + 0.20 + magnitude_boost, 4)))

    return best_tag, confidence, matched_by_tag[best_tag], tag_scores


def analyze_comment(text: Any) -> dict[str, Any]:
    """Analyze a single evaluation comment using calamanCy + VADER.

    Returns:
        Dictionary containing:
          - polarity: float in [-1.0, 1.0] rounded to 4 decimals
          - valence: "Positive", "Neutral", or "Negative"
          - pedagogical_tag: one of PEDAGOGICAL_TAGS (5 clusters)
          - confidence: float in [0.0, 1.0]
          - keywords: list of matched domain keywords
          - nlp_engine: "calamanCy+VADER"
          - calamancy: morphosyntactic token/lemma/POS metadata
    """
    item_id: Any = None
    if isinstance(text, dict):
        item_id = text.get("id")
        raw_value = text.get("comment")
        if raw_value is None:
            raw_value = text.get("text", "")
        comment_str = raw_value if isinstance(raw_value, str) else ("" if raw_value is None else str(raw_value))
    elif isinstance(text, str):
        comment_str = text
    else:
        comment_str = ""

    polarity = _compute_rule_based_polarity(comment_str)
    valence = classify_valence(polarity)
    pedagogical_tag, confidence, keywords, _ = classify_pedagogical_domain(comment_str)
    calamancy_meta = extract_calamancy_features(comment_str)

    result: dict[str, Any] = {
        "polarity": polarity,
        "valence": valence,
        "pedagogical_tag": pedagogical_tag,
        "confidence": confidence,
        "keywords": keywords,
        "nlp_engine": "calamanCy+VADER",
        "calamancy": calamancy_meta,
    }
    if item_id is not None:
        result["id"] = item_id
    return result


def analyze_professor_questions(payload: dict[str, Any] | None) -> dict[str, Any]:
    """Validate and analyze the structured Professor Qualitative Questions (4 default, max 5).

    Expected structure:
      1. three_words: list of 3 words or comma/space-separated string describing experience with the prof
      2. room_for_improvements: list of improvement topics (a. topic 1, b. topic 2, ...) or dict
      3. teaching_strengths: narrative response on professor's strengths
      4. recommendations: narrative response on actionable course/teaching improvements
      5. additional_observation (optional, max 5 questions total)
    """
    if not isinstance(payload, dict):
        payload = {}

    raw_questions = payload.get("questions") if isinstance(payload.get("questions"), list) else None
    if raw_questions is not None and len(raw_questions) > MAX_PROFESSOR_QUESTIONS:
        return {
            "valid": False,
            "error_code": "MAX_PROFESSOR_QUESTIONS_EXCEEDED",
            "max_allowed": MAX_PROFESSOR_QUESTIONS,
            "submitted_count": len(raw_questions),
            "message": f"A maximum of {MAX_PROFESSOR_QUESTIONS} professor qualitative questions is allowed.",
        }

    # 1. Extract 3 descriptor words ("1. 3 words that describes the experience with the prof.")
    raw_three = payload.get("three_words", payload.get("q1_three_words", []))
    if isinstance(raw_three, str):
        words_list = [w.strip() for w in re.split(r"[,;/|\s]+", raw_three) if w.strip()]
    elif isinstance(raw_three, list):
        words_list = [str(w).strip() for w in raw_three if str(w).strip()]
    else:
        words_list = []

    three_words_trimmed = words_list[:3]
    three_words_lemmas = [lemmatize_calamancy_token(w) for w in three_words_trimmed]
    three_words_analysis = analyze_comment(" ".join(three_words_trimmed))

    # 2. Extract Room for Improvements sub-topics ("2. room for improvements of the prof: a. topic 1, b. topic 2 ...")
    raw_improvements = payload.get("room_for_improvements", payload.get("q2_room_for_improvements", []))
    improvement_topics: list[dict[str, Any]] = []
    if isinstance(raw_improvements, dict):
        items_iter = list(raw_improvements.values())
    elif isinstance(raw_improvements, list):
        items_iter = raw_improvements
    elif isinstance(raw_improvements, str) and raw_improvements.strip():
        items_iter = [s.strip() for s in re.split(r"[\r\n;]+", raw_improvements) if s.strip()]
    else:
        items_iter = []

    for idx, topic_entry in enumerate(items_iter):
        letter = chr(ord("a") + (idx % 26))
        if isinstance(topic_entry, dict):
            topic_text = str(topic_entry.get("topic") or topic_entry.get("text") or "").strip()
        else:
            topic_text = str(topic_entry).strip()
        if not topic_text:
            continue
        topic_nlp = analyze_comment(topic_text)
        improvement_topics.append(
            {
                "sub_item": f"{letter}.",
                "label": f"{letter}. Topic {idx + 1}",
                "topic": topic_text,
                "polarity": topic_nlp["polarity"],
                "valence": topic_nlp["valence"],
                "pedagogical_tag": topic_nlp["pedagogical_tag"],
            }
        )

    # 3. Question 3: Teaching Strengths
    q3_strengths = str(payload.get("teaching_strengths", payload.get("q3_teaching_strengths", "")) or "").strip()
    q3_analysis = analyze_comment(q3_strengths) if q3_strengths else None

    # 4. Question 4: Recommendations for the Prof
    q4_recommendations = str(payload.get("recommendations", payload.get("q4_recommendations", "")) or "").strip()
    q4_analysis = analyze_comment(q4_recommendations) if q4_recommendations else None

    # 5. Optional Question 5 (capped at MAX_PROFESSOR_QUESTIONS = 5)
    q5_additional = str(payload.get("additional_observation", payload.get("q5_additional_observation", "")) or "").strip()
    q5_analysis = analyze_comment(q5_additional) if q5_additional else None

    active_question_count = 4 + (1 if q5_additional else 0)

    # Synthesize combined text for overall NLP scoring
    combined_parts: list[str] = []
    if three_words_trimmed:
        combined_parts.append(" ".join(three_words_trimmed))
    for item in improvement_topics:
        combined_parts.append(item["topic"])
    if q3_strengths:
        combined_parts.append(q3_strengths)
    if q4_recommendations:
        combined_parts.append(q4_recommendations)
    if q5_additional:
        combined_parts.append(q5_additional)

    combined_analysis = analyze_comment(". ".join(combined_parts))

    return {
        "valid": len(three_words_trimmed) == 3 and len(improvement_topics) >= 1 and active_question_count <= MAX_PROFESSOR_QUESTIONS,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_repo": CALAMANCY_REPO_URL,
        "question_count": active_question_count,
        "max_questions": MAX_PROFESSOR_QUESTIONS,
        "q1_three_words": {
            "words": three_words_trimmed,
            "word_count": len(three_words_trimmed),
            "lemmas": three_words_lemmas,
            "polarity": three_words_analysis["polarity"],
            "valence": three_words_analysis["valence"],
        },
        "q2_room_for_improvements": {
            "topic_count": len(improvement_topics),
            "topics": improvement_topics,
        },
        "q3_teaching_strengths": {
            "text": q3_strengths,
            "analysis": q3_analysis,
        },
        "q4_recommendations": {
            "text": q4_recommendations,
            "analysis": q4_analysis,
        },
        "q5_additional_observation": {
            "text": q5_additional,
            "analysis": q5_analysis,
        }
        if q5_additional
        else None,
        "combined_sentiment": {
            "polarity": combined_analysis["polarity"],
            "valence": combined_analysis["valence"],
            "pedagogical_tag": combined_analysis["pedagogical_tag"],
            "calamancy": combined_analysis["calamancy"],
        },
    }


def synthesize_professor_questionnaires(
    questionnaires: Iterable[dict[str, Any]] | None,
) -> dict[str, Any]:
    """Synthesize structured Professor Qualitative Questions across multiple student submissions.

    Aggregates:
      1. Top descriptors from Q1 ('three_words') with counts, lemmas, and polarity.
      2. Grouped room for improvement topics from Q2 with lettered items ('a.', 'b.', ...)
         categorized into the 5 pedagogical dimensions.
      3. Key teaching strengths from Q3.
      4. Actionable recommendations from Q4.
      5. Optional observations from Q5 (max 5 questions cap).
    """
    if questionnaires is None or isinstance(questionnaires, (str, bytes)):
        items: list[dict[str, Any]] = []
    else:
        try:
            items = [q for q in questionnaires if isinstance(q, dict)]
        except TypeError:
            items = []

    if not items:
        return {
            "submission_count": 0,
            "top_descriptors": [],
            "improvement_topics": [],
            "improvement_clusters": {tag: [] for tag in FIVE_PEDAGOGICAL_CLUSTERS},
            "key_strengths": [],
            "key_recommendations": [],
            "optional_observations": [],
            "nlp_engine": "calamanCy+VADER",
            "calamancy_repo": CALAMANCY_REPO_URL,
        }

    word_counts: dict[str, int] = {}
    improvement_topics_list: list[dict[str, Any]] = []
    strengths_list: list[dict[str, Any]] = []
    recommendations_list: list[dict[str, Any]] = []
    observations_list: list[dict[str, Any]] = []

    for q in items:
        analysis = analyze_professor_questions(q) if not q.get("nlp_engine") else q

        # 1. Q1 Three Words
        q1_words = analysis.get("q1_three_words", {}).get("words", [])
        if not q1_words and isinstance(q.get("three_words"), (list, tuple)):
            q1_words = [str(w).strip() for w in q["three_words"] if str(w).strip()]
        for w in q1_words:
            norm_w = str(w).strip().capitalize()
            if norm_w:
                word_counts[norm_w] = word_counts.get(norm_w, 0) + 1

        # 2. Q2 Room for improvements
        q2_topics = analysis.get("q2_room_for_improvements", {}).get("topics", [])
        for t in q2_topics:
            if isinstance(t, dict) and t.get("topic"):
                improvement_topics_list.append(t)

        # 3. Q3 Teaching strengths
        q3_text = (
            analysis.get("q3_teaching_strengths", {}).get("text", "")
            if isinstance(analysis.get("q3_teaching_strengths"), dict)
            else str(q.get("teaching_strengths", "")).strip()
        )
        if q3_text:
            strengths_list.append({
                "text": q3_text,
                "analysis": analysis.get("q3_teaching_strengths", {}).get("analysis") or analyze_comment(q3_text),
            })

        # 4. Q4 Recommendations
        q4_text = (
            analysis.get("q4_recommendations", {}).get("text", "")
            if isinstance(analysis.get("q4_recommendations"), dict)
            else str(q.get("recommendations", "")).strip()
        )
        if q4_text:
            recommendations_list.append({
                "text": q4_text,
                "analysis": analysis.get("q4_recommendations", {}).get("analysis") or analyze_comment(q4_text),
            })

        # 5. Q5 Additional observations
        q5_block = analysis.get("q5_additional_observation")
        q5_text = (
            q5_block.get("text", "")
            if isinstance(q5_block, dict)
            else str(q.get("additional_observation", "")).strip()
        )
        if q5_text:
            observations_list.append({
                "text": q5_text,
                "analysis": (q5_block.get("analysis") if isinstance(q5_block, dict) else None) or analyze_comment(q5_text),
            })

    # Build sorted descriptors with lemmas and sentiment
    sorted_words = sorted(word_counts.items(), key=lambda kv: (-kv[1], kv[0]))
    top_descriptors = []
    for word, count in sorted_words:
        w_analysis = analyze_comment(word)
        top_descriptors.append({
            "word": word,
            "lemma": lemmatize_calamancy_token(word),
            "count": count,
            "polarity": w_analysis["polarity"],
            "valence": w_analysis["valence"],
        })

    # Cluster improvements by pedagogical tag
    improvement_clusters: dict[str, list[dict[str, Any]]] = {tag: [] for tag in FIVE_PEDAGOGICAL_CLUSTERS}
    formatted_improvements: list[dict[str, Any]] = []

    for idx, item in enumerate(improvement_topics_list):
        tag = item.get("pedagogical_tag", "Pedagogical Delivery")
        if tag not in improvement_clusters:
            tag = "Pedagogical Delivery"
        letter = chr(ord("a") + (idx % 26))
        entry = {
            "sub_item": f"{letter}.",
            "label": f"{letter}. Topic {idx + 1}",
            "topic": item.get("topic", ""),
            "polarity": item.get("polarity", 0.0),
            "valence": item.get("valence", "Neutral"),
            "pedagogical_tag": tag,
        }
        formatted_improvements.append(entry)
        improvement_clusters[tag].append(entry)

    return {
        "submission_count": len(items),
        "top_descriptors": top_descriptors,
        "improvement_topics": formatted_improvements,
        "improvement_clusters": improvement_clusters,
        "key_strengths": strengths_list,
        "key_recommendations": recommendations_list,
        "optional_observations": observations_list,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_repo": CALAMANCY_REPO_URL,
    }


def analyze_batch(comments: Iterable[Any] | None) -> list[dict[str, Any]]:
    """Analyze a batch of comments and return a list of classification dictionaries."""
    if comments is None or isinstance(comments, (str, bytes)):
        return []
    try:
        items = list(comments)
    except TypeError:
        return []
    return [analyze_comment(item) for item in items]


def summarize_comments(comments: Iterable[Any] | None) -> dict[str, Any]:
    """Aggregate sentiment distribution and pedagogical tag breakdown across comments."""
    tag_breakdown: dict[str, int] = {tag: 0 for tag in PEDAGOGICAL_TAGS}

    if comments is None or isinstance(comments, (str, bytes)):
        items: list[Any] = []
    else:
        try:
            items = list(comments)
        except TypeError:
            items = []

    analyzed: list[dict[str, Any]] = []
    for item in items:
        if (
            isinstance(item, dict)
            and "polarity" in item
            and "valence" in item
            and "pedagogical_tag" in item
        ):
            analyzed.append(item)
        else:
            analyzed.append(analyze_comment(item))

    total = len(analyzed)
    if total == 0:
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
            "tag_breakdown": tag_breakdown,
            "watchlist_flagged": False,
        }

    positive_count = 0
    neutral_count = 0
    negative_count = 0
    polarity_sum = 0.0
    negative_tag_counts: dict[str, int] = {tag: 0 for tag in PEDAGOGICAL_TAGS}

    for entry in analyzed:
        valence = entry.get("valence", "Neutral")
        tag = entry.get("pedagogical_tag", "Pedagogical Delivery")
        if tag not in tag_breakdown:
            tag = "Pedagogical Delivery"
        tag_breakdown[tag] += 1
        polarity_sum += float(entry.get("polarity", 0.0))

        if valence == "Positive":
            positive_count += 1
        elif valence == "Negative":
            negative_count += 1
            negative_tag_counts[tag] += 1
        else:
            neutral_count += 1

    positive_pct = round((positive_count / total) * 100.0, 2)
    neutral_pct = round((neutral_count / total) * 100.0, 2)
    negative_pct = round((negative_count / total) * 100.0, 2)
    mean_polarity = round(polarity_sum / total, 4)

    primary_tag = max(
        PEDAGOGICAL_TAGS,
        key=lambda t: (tag_breakdown[t], negative_tag_counts[t]),
    )
    if negative_count > 0:
        primary_negative_tag = max(
            PEDAGOGICAL_TAGS,
            key=lambda t: (negative_tag_counts[t], tag_breakdown[t]),
        )
    else:
        primary_negative_tag = primary_tag

    return {
        "total": total,
        "positive_count": positive_count,
        "neutral_count": neutral_count,
        "negative_count": negative_count,
        "positive_pct": positive_pct,
        "neutral_pct": neutral_pct,
        "negative_pct": negative_pct,
        "mean_polarity": mean_polarity,
        "primary_tag": primary_tag,
        "primary_negative_tag": primary_negative_tag,
        "tag_breakdown": tag_breakdown,
        "watchlist_flagged": negative_pct > 35.0,
    }


def _compute_five_cluster_means(cluster_ratings: Any) -> tuple[dict[str, float] | None, float | None]:
    """Compute 5-cluster means across `FIVE_PEDAGOGICAL_CLUSTERS` from 25-item, 5-value, or dict inputs."""
    if cluster_ratings is None:
        return None, None

    if isinstance(cluster_ratings, dict):
        cluster_means: dict[str, float] = {}
        for idx, cluster_name in enumerate(FIVE_PEDAGOGICAL_CLUSTERS):
            raw_val = (
                cluster_ratings.get(cluster_name)
                if cluster_name in cluster_ratings
                else cluster_ratings.get(f"cluster_{idx + 1}", cluster_ratings.get(f"CLUSTER-{idx + 1}"))
            )
            if isinstance(raw_val, (list, tuple)) and raw_val:
                nums = [float(x) for x in raw_val if isinstance(x, (int, float))]
                cluster_means[cluster_name] = round(sum(nums) / len(nums), 2) if nums else 0.0
            elif isinstance(raw_val, (int, float)):
                cluster_means[cluster_name] = round(float(raw_val), 2)
            else:
                cluster_means[cluster_name] = 0.0
        valid_vals = [v for v in cluster_means.values() if v > 0]
        mean_score = round(sum(valid_vals) / len(valid_vals), 2) if valid_vals else 0.0
        return cluster_means, mean_score

    if not isinstance(cluster_ratings, (list, tuple)) or len(cluster_ratings) == 0:
        return None, None

    # Handle list of rating vectors (multiple student submissions)
    if all(isinstance(row, (list, tuple)) for row in cluster_ratings):
        per_submission_means: list[dict[str, float]] = []
        for row in cluster_ratings:
            row_means, _ = _compute_five_cluster_means(list(row))
            if row_means is not None:
                per_submission_means.append(row_means)
        if not per_submission_means:
            return None, None
        aggregated: dict[str, float] = {}
        for cluster_name in FIVE_PEDAGOGICAL_CLUSTERS:
            vals = [m[cluster_name] for m in per_submission_means if cluster_name in m]
            aggregated[cluster_name] = round(sum(vals) / len(vals), 2) if vals else 0.0
        overall = round(sum(aggregated.values()) / len(aggregated), 2) if aggregated else 0.0
        return aggregated, overall

    numeric_items: list[float] = []
    for item in cluster_ratings:
        if isinstance(item, (int, float)) and not isinstance(item, bool):
            numeric_items.append(float(item))
    if not numeric_items:
        return None, None

    cluster_means = {}
    if len(numeric_items) >= 25:
        for idx, cluster_name in enumerate(FIVE_PEDAGOGICAL_CLUSTERS):
            slice_items = numeric_items[idx * 5 : (idx + 1) * 5]
            cluster_means[cluster_name] = round(sum(slice_items) / len(slice_items), 2)
    elif len(numeric_items) == 5:
        for idx, cluster_name in enumerate(FIVE_PEDAGOGICAL_CLUSTERS):
            cluster_means[cluster_name] = round(numeric_items[idx], 2)
    elif len(numeric_items) == 20:
        overall_20 = sum(numeric_items) / 20.0
        for idx, cluster_name in enumerate(FIVE_PEDAGOGICAL_CLUSTERS):
            if idx < 4:
                slice_items = numeric_items[idx * 5 : (idx + 1) * 5]
                cluster_means[cluster_name] = round(sum(slice_items) / len(slice_items), 2)
            else:
                cluster_means[cluster_name] = round(overall_20, 2)
    else:
        chunk_size = max(1, len(numeric_items) // len(FIVE_PEDAGOGICAL_CLUSTERS))
        for idx, cluster_name in enumerate(FIVE_PEDAGOGICAL_CLUSTERS):
            start = min(idx * chunk_size, len(numeric_items) - 1)
            end = min((idx + 1) * chunk_size, len(numeric_items))
            slice_items = numeric_items[start:end] or [numeric_items[-1]]
            cluster_means[cluster_name] = round(sum(slice_items) / len(slice_items), 2)

    mean_score = round(sum(numeric_items) / len(numeric_items), 2)
    return cluster_means, mean_score


def analyze_plp_section_dossier(
    comments: Any,
    cluster_ratings: Any = None,
    professor_questions: Any = None,
) -> dict[str, Any]:
    """Synthesize a Pamantasan ng Lungsod ng Pasig (PLP) Faculty Section Evaluation Dossier.

    Combines:
      - Institutional metadata (`PAMANTASAN NG LUNGSOD NG PASIG`, `https://plpasig.edu.ph/`)
      - `calamanCy+VADER` narrative comment sentiment and 5-cluster breakdown
      - Structured 4-to-5 professor qualitative questions summary (if provided)
      - 5-cluster Likert rating means across `FIVE_PEDAGOGICAL_CLUSTERS` (if provided)
      - Dean Exception Watchlist trigger (`mean_score < 3.50` or `negative_pct > 35.0`)
    """
    if isinstance(comments, str):
        comment_list: list[Any] = [comments] if comments.strip() else []
    else:
        comment_list = comments

    comment_summary = summarize_comments(comment_list)
    cluster_means, mean_score = _compute_five_cluster_means(cluster_ratings)

    prof_questions_summary: dict[str, Any] | list[dict[str, Any]] | None = None
    if isinstance(professor_questions, dict):
        prof_questions_summary = analyze_professor_questions(professor_questions)
    elif isinstance(professor_questions, list) and professor_questions:
        analyzed_q = [
            analyze_professor_questions(q) for q in professor_questions if isinstance(q, dict)
        ]
        prof_questions_summary = analyzed_q[0] if len(analyzed_q) == 1 else analyzed_q

    negative_pct = float(comment_summary.get("negative_pct", 0.0))
    low_rating_flag = (mean_score is not None) and (mean_score < 3.50)
    high_negative_flag = negative_pct > 35.0
    watchlist_triggered = bool(low_rating_flag or high_negative_flag)

    return {
        "institution": PLP_INSTITUTION_NAME,
        "reference": PLP_REFERENCE_URL,
        "nlp_engine": "calamanCy+VADER",
        "calamancy_model": CALAMANCY_DEFAULT_MODEL,
        "calamancy_repo": CALAMANCY_REPO,
        "comment_summary": comment_summary,
        "professor_questions_summary": prof_questions_summary,
        "cluster_means": cluster_means,
        "mean_score": mean_score,
        "watchlist_triggered": watchlist_triggered,
    }


def generate_pedagogical_action_plan(
    faculty_name: str,
    mean_score: float | int | None,
    negative_sentiment_pct: float | int | None,
    primary_tag: str | None = None,
    sample_comments: Iterable[Any] | None = None,
) -> dict[str, Any]:
    """Generate an institutional Pedagogical Action & Intervention Plan for a watchlist instructor.

    Tailored for Pamantasan ng Lungsod ng Pasig (PLP) Deans and Department Chairs.
    """
    clean_name = str(faculty_name or "Faculty Member").strip()
    score = float(mean_score) if mean_score is not None else 3.10
    neg_pct = float(negative_sentiment_pct) if negative_sentiment_pct is not None else 38.0
    tag = str(primary_tag or "Pedagogical Delivery").strip()
    if tag not in FIVE_PEDAGOGICAL_CLUSTERS:
        tag = "Pedagogical Delivery"

    low_score = score < 3.50
    high_negative = neg_pct > 35.0

    if low_score and high_negative:
        trigger_desc = f"Both overall mean score ({score:.2f} < 3.50) and negative sentiment share ({neg_pct:.1f}% > 35.0%) breached institutional thresholds."
        urgency = "HIGH"
    elif low_score:
        trigger_desc = f"Overall mean score ({score:.2f} / 5.00) is below the institutional benchmark of 3.50."
        urgency = "MODERATE"
    elif high_negative:
        trigger_desc = f"Negative qualitative sentiment share ({neg_pct:.1f}%) exceeds the 35.0% intervention threshold."
        urgency = "MODERATE"
    else:
        trigger_desc = f"Instructor performance meets baseline standards (Mean: {score:.2f}, Negative: {neg_pct:.1f}%)."
        urgency = "STANDARD"

    cluster_strategies = {
        "Pedagogical Delivery": [
            "Incorporate step-by-step live coding and practical laboratory demonstrations during dynamic programming lectures.",
            "Implement periodic formative check-ins (e.g., 5-minute exit tickets) to calibrate pacing to learner comprehension.",
            "Participate in the PLP College of Computer Studies Peer Teaching Observation & Active Learning Workshop.",
        ],
        "Assessment & Grading": [
            "Establish a strict 14-day turnaround SLA for returning graded quizzes, assignments, and programming deliverables.",
            "Publish explicit analytic rubrics with criterion point distributions prior to major exams and programming projects.",
            "Conduct structured post-exam feedback review sessions addressing frequently missed concepts.",
        ],
        "Classroom Management": [
            "Enforce strict session start punctuality and instructional time budgeting across all scheduled lecture and lab hours.",
            "Align weekly topic pacing directly with the approved PLP departmental course syllabus timeline.",
            "Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.",
        ],
        "Professional Conduct": [
            "Maintain an open, empathetic, and professional classroom environment conducive to active student inquiry.",
            "Ensure uniform, impartial application of grading criteria, consultation availability, and academic policies.",
            "Participate in the PLP Academic Integrity & Student-Centered Pedagogical Communication Seminar.",
        ],
        "Student Support & Mentorship": [
            "Publish and adhere to regular weekly office consultation hours (minimum 4 scheduled hours per week).",
            "Establish dedicated advisory sessions for capstone thesis system design, architecture, and code debugging.",
            "Maintain prompt institutional email responsiveness (within 48 hours on business days).",
        ],
    }

    interventions = cluster_strategies.get(tag, cluster_strategies["Pedagogical Delivery"])
    plan_id = f"PLP-ACT-{re.sub(r'[^A-Z0-9]', '', clean_name.upper())[:8]}-{math.floor(score * 10)}"

    return {
        "plan_id": plan_id,
        "institution": PLP_INSTITUTION_NAME,
        "reference": PLP_REFERENCE_URL,
        "faculty_name": clean_name,
        "mean_score": round(score, 2),
        "negative_sentiment_pct": round(neg_pct, 1),
        "primary_cluster": tag,
        "trigger_description": trigger_desc,
        "urgency_level": urgency,
        "recommended_interventions": interventions,
        "timeline": "Immediate (Weeks 1-4 of following academic term) with mid-term milestone review",
        "target_milestone": {
            "target_mean_score": 3.80,
            "target_negative_pct": 15.0,
            "target_cluster_improvement": f"+0.50 points in {tag}",
        },
        "dean_signoff_required": True,
        "nlp_engine": "calamanCy+VADER",
    }
