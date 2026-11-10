# EvaluaTech dataset sentiment and NLP analysis

Source: `Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.csv`. 800 rows. Original file preserved.

## Result

Sentiment label agreement: **54.38%** (435/800). Macro F1: **0.5377**. Rows for review: **365**.

**Actual runtime:** custom educational rules blended with installed VADER, heuristic tokenization/lemmatization/POS, and keyword-based pedagogical tagging. The trained calamanCy model did not run.

| Sentiment | Reference count | Predicted count | Precision | Recall | F1 |
|---|---:|---:|---:|---:|---:|
| Positive | 356 | 246 | 86.99% | 60.11% | 0.7110 |
| Neutral | 132 | 423 | 24.82% | 79.55% | 0.3784 |
| Negative | 312 | 131 | 88.55% | 37.18% | 0.5237 |

## Agreement by supplied language

| Language | Rows | Correct | Agreement | Macro F1 |
|---|---:|---:|---:|---:|
| English | 323 | 199 | 61.61% | 0.5842 |
| Tagalog | 192 | 106 | 55.21% | 0.5448 |
| Taglish | 285 | 130 | 45.61% | 0.4717 |

## Confusion matrix

Rows are reference labels; columns are predictions.

| Reference / Predicted | Positive | Neutral | Negative |
|---|---:|---:|---:|
| Positive | 214 | 138 | 4 |
| Neutral | 16 | 105 | 11 |
| Negative | 16 | 180 | 116 |

## Pedagogical tagging

| Predicted domain | Rows |
|---|---:|
| Pedagogical Delivery | 351 |
| Classroom Management | 75 |
| Student Support & Mentorship | 26 |
| Assessment & Grading | 334 |
| Professional Conduct | 14 |

172 rows had no matched domain keywords. The engine still assigns a domain; inspect these before using the categories in reports.

## Selected mismatches

Examples are selected by reference/prediction pair, not presented as a random sample. Reference labels may also need review.

- Row 3 (Taglish): reference **Neutral**, predicted **Positive**, polarity 0.5944. Medyo fast-paced magturo si Ma'am pero approachable naman kapag nagtanong during consultation.
- Row 5 (Taglish): reference **Negative**, predicted **Neutral**, polarity 0.0. Terror teacher, grabe magpa-exam napakalayo sa mga na-discuss sa lecture.
- Row 13 (Taglish): reference **Positive**, predicted **Neutral**, polarity 0.0. Ang ganda ng discussion palagi kasi may real-world application at case studies.
- Row 16 (Taglish): reference **Neutral**, predicted **Negative**, polarity -0.3535. Ok lang naman, average instructor pero minsan mahirap ma-reach sa email.
- Row 163 (English): reference **Negative**, predicted **Positive**, polarity 0.0516. The coding practical test had multiple errors in the test suite provided.
- Row 200 (English): reference **Positive**, predicted **Negative**, polarity -0.3818. He walked the class through common mistakes made on the preliminary test.

## Data quality and limitations

- Dataset reference labels were supplied, not independently adjudicated.
- No model training or tuning occurred; this is a dataset agreement evaluation, not an independent holdout certification.
- calamanCy and spaCy are unavailable in this Python environment; trained-model tagging was not evaluated.
- The API engine label names calamanCy even when the heuristic fallback runs.
- Domain confidence is a heuristic match score, not calibrated sentiment confidence.
- Reference aspects have a different taxonomy from the five output domains; no domain accuracy is claimed.
- Library facilities and extracurricular feedback extend beyond teacher-focused domain coverage.
- Dataset row IDs are source row identifiers, not student identities.
- Timing measures one local in-process run, not production HTTP latency or a load benchmark.

Duplicate extra rows: 1. Exact hardcoded benchmark matches: 0. No blank or overlength comments.

## Reproducibility

Engine commit: `db6875b19d43a5d4b2e83af411a3fb99e54f14e0`. Engine SHA-256: `e88838bf406e8be66f6e07bb191419555cdcbff070ea75ae5fc03d7820206729`.
Source SHA-256: `a097f9dee4e1bb8bd7377c3cc7706efeba4c420cbe959f3be504072fc6b17d5b`.
VADER: 3.3.2; Python: 3.14.3. 4 endpoint batches; 3.629 seconds total.

Files: sentiment_results.csv (all predictions), sentiment_review_queue.csv (mismatches), sentiment_summary.json (full metrics).

## Engineering findings

- 420 rows received exactly zero polarity. On 110 of these, the installed VADER analyzer with the same extended lexicon returns a nonzero score. The custom scorer returns early before the VADER blend when its own total is zero. Review that control flow with regression examples. This comparison is diagnostic; primary scores remain unchanged.
- The trained calamanCy pipeline feeds token/lemma/POS metadata, while sentiment currently uses the custom lemmatizer. Installing the model alone should not be assumed to repair sentiment classification.
- Add explicit runtime provenance and a fallback flag to API responses. Do not describe heuristic POS and lemmas as model-generated output.
- Review the supplied labels, especially mixed positive/negative comments. Deduplicate before any future train/development/test split; keep the final test partition untouched during tuning.
- Expand or constrain the aspect taxonomy: facilities and extracurricular topics are not covered cleanly by five teacher-focused domains. Add an unclassified/needs-review outcome instead of forcing unsupported matches.
- Validate improvements on per-language macro F1 and negative recall, not only aggregate accuracy or hand-picked examples. Do not tune against all 800 labels and present the same-set result as held-out performance.
