# EvaluaTech NLP & Sentiment Analysis — Model Comparison and Holdout Validation Report

**Author:** Technical Lead & NLP / ML Engineering<br>
**Date:** November 11, 2026<br>
**Status:** Approved for Institutional Release<br>
**Reference Directive:** User Directive — 10,000 Dataset Expansion and Scikit-Learn / NLTK / SpaCy ML Algorithm Training<br>
**Dataset:** 10,000-Row Multilingual Higher-Ed Student Evaluation of Teachers (SET) Corpus<br>
**Partitions:** 70% Development (7,000 samples) / 30% Untouched Held-Out Test (3,000 samples), Stratified across 9 Strata (Language $\times$ Sentiment)

> **Correction (2026-11-11): read this before any number below.**
> Only 800 of the 10,000 rows are real student comments; the other 9,200 were generated from templates by `scripts/generate_10k_dataset.py`. Of the 3,000 held-out test rows, 2,773 are synthetic and only 227 are real. The model scores 100% on the synthetic rows because it learns the generator's patterns, which inflated the overall figures below (98.80% accuracy, 99.07% negative recall). **On the 227 real comments, that Model D scored 84.14% accuracy and 86.52% negative recall.**
>
> The model was remodeled on 2026-11-11 (word 1-2 + character 2-5 n-grams, real comments weighted x5, selected by cross-validation on real comments only; `scripts/train_ml_model.py`). Current results on the 227 real held-out comments: **87.22% sentiment accuracy, 0.854 macro F1, 88.76% negative recall; topic accuracy 89.87%**. Source of truth: `analytics/models/model_metadata.json`. The sections below are kept as the historical record of the earlier evaluation.

---

## Executive Summary

Pursuant to the user mandate for un-altered, non-tampered empirical machine learning benchmarks with a 10,000-line statement corpus, NLP Engineering trained and evaluated production-grade machine learning models using **`scikit-learn`**, **`nltk`**, and **`spacy`**. The dataset was expanded to **exactly 10,000 completely unique student evaluation comments** spanning English (3,390), Tagalog (3,258), and Taglish (3,352), covering all five pedagogical Likert clusters (*Pedagogical Delivery*, *Assessment & Grading*, *Classroom Management*, *Professional Conduct*, *Student Support & Mentorship*) alongside non-teaching facility and extracurricular feedback (*Unclassified / Needs Review*).

### Key Empirical Findings on the 3,000-Sample Held-Out Test Partition:
1. **Multilingual Dataset Scale (10,000 rows):** Partitioned into **7,000 Development (70%)** and **3,000 Held-Out Test (30%)** with deterministic stratified splitting (`seed = 42`) and zero test leakage.
2. **Machine Learning Architecture:**
   - **Pre-processing & Tokenization:** Multilingual NLTK word tokenization and normalization preserving Tagalog/Taglish morphology.
   - **Feature Space:** `TfidfVectorizer(ngram_range=(1, 3), min_df=2, max_features=35000, sublinear_tf=True)` generating an 11,006 n-gram feature vocabulary.
   - **Estimator:** Class-balanced calibrated `LogisticRegression(C=2.5, max_iter=1000)` outputting continuous polarity scores in $[-1.0, +1.0]$ derived from calibrated class probabilities ($P = P(\text{Positive}) - P(\text{Negative})$).
3. **Empirical Benchmark on 3,000 Untouched Test Samples:**
   - **Overall Accuracy:** Reached **$98.80\%$** ($2,964 / 3,000$ correct), surpassing rule-based baselines ($89.23\%$) by **$+9.57$ percentage points**.
   - **Negative Sentiment Recall:** **$99.07\%$** ($1,281 / 1,293$ detected), crucial for institutional Dean/Chair early-warning intervention.
   - **Macro F1-Score:** **$0.9875$** across Positive ($0.9872$), Neutral ($0.9856$), and Negative ($0.9896$).
   - **Multilingual Consistency:**
     - **English Accuracy:** **$98.33\%$** ($1,000 / 1,017$)
     - **Tagalog Accuracy:** **$99.28\%$** ($970 / 977$)
     - **Taglish Accuracy:** **$98.81\%$** ($994 / 1,006$)
   - **Pedagogical Domain Classification:** **$98.57\%$ Accuracy**, **$0.9606$ Macro F1** across the 5 Likert clusters + Unclassified.
   - **Inference Latency:** Sub-millisecond pipeline execution ($0.209\text{ ms}$ raw vectorizer+classifier, $2.39\text{ ms}$ full end-to-end multi-tier pipeline).

---

## 1. Experimental Methodology and Dataset Partitions

### 1.1 Stratified Partitioning Protocol
The 10,000-sample corpus was partitioned with a deterministic random seed (`seed = 42`) across all 9 strata ($3\text{ Languages} \times 3\text{ Sentiments}$):
- **Development Partition (70% — 7,000 samples):** Used for 5-fold cross-validation, feature extraction, and model fitting.
- **Untouched Held-Out Test Partition (30% — 3,000 samples):** Preserved untouched until final blind benchmark evaluation.
- **Deduplication:** All rows verified unique by lowercase normalized text ($10,000$ unique sample texts, zero duplicates).

| Stratum | Total Corpus | Dev Partition (70%) | Held-Out Test (30%) | Test Share |
|---|---:|---:|---:|---:|
| **English $\times$ Negative** | 1,446 | 1,012 | 434 | 14.47% |
| **English $\times$ Neutral** | 455 | 319 | 136 | 4.53% |
| **English $\times$ Positive** | 1,489 | 1,042 | 447 | 14.90% |
| **Tagalog $\times$ Negative** | 1,395 | 977 | 418 | 13.93% |
| **Tagalog $\times$ Neutral** | 453 | 317 | 136 | 4.53% |
| **Tagalog $\times$ Positive** | 1,410 | 987 | 423 | 14.10% |
| **Taglish $\times$ Negative** | 1,469 | 1,028 | 441 | 14.70% |
| **Taglish $\times$ Neutral** | 484 | 339 | 145 | 4.83% |
| **Taglish $\times$ Positive** | 1,399 | 979 | 420 | 14.00% |
| **Total** | **10,000** | **7,000** | **3,000** | **100.0%** |

---

## 2. Models Compared

1. **Model A (Baseline Uncorrected):** Legacy rule-based engine before bug fixes and phrase weighting.
2. **Model B (Corrected Baseline):** Premature zero return removed; VADER evaluates when rule valence is zero, without phrase token neutralization.
3. **Model C (Enhanced Hybrid calamanCy + VADER):** Calibrated rule-based morphosyntactic parser with Tagalog negation/booster lexicons and phrase neutralization.
4. **Model D (Scikit-Learn ML Model — 10k Dataset):** Production `scikit-learn` pipeline with NLTK multilingual tokenization, sublinear TF-IDF $(1, 3)$ n-grams, and class-balanced logistic regression trained on 7,000 samples and serialized via `joblib`.

---

## 3. Empirical Results on Untouched Held-Out Test Partition (3,000 Samples)

### 3.1 Primary Performance Metrics

| Model | Accuracy | Macro Precision | Macro Recall | Macro F1 | Negative Recall |
|---|---:|---:|---:|---:|---:|
| **Model A: Baseline Uncorrected** | 89.23% (2,677/3,000) | 84.74% | 83.22% | 0.8388 | 91.80% |
| **Model B: Corrected Baseline** | 86.70% (2,601/3,000) | 80.12% | 77.36% | 0.7831 | 91.42% |
| **Model C: Enhanced Hybrid Rule+VADER** | 89.23% (2,677/3,000) | 84.74% | 83.22% | 0.8388 | 91.80% |
| **Model D: Scikit-Learn ML Model (10k)** | **98.80% (2,964/3,000)** | **98.80%** | **98.69%** | **0.9875** | **99.07%** |
| *Net Gain (Model D vs Model C)* | *+9.57%* | *+14.06%* | *+15.47%* | *+0.1487* | *+7.27%* |

### 3.2 Per-Class Detailed Performance (Model D — Scikit-Learn on 3,000 Test Samples)

| Class | Precision | Recall | F1-Score | Support (Actual) | Predicted |
|---|---:|---:|---:|---:|---:|
| **Positive** | 98.76% | 98.68% | 0.9872 | 1,290 | 1,289 |
| **Neutral** | 98.80% | 98.32% | 0.9856 | 417 | 415 |
| **Negative** | 98.84% | 99.07% | 0.9896 | 1,293 | 1,296 |
| **Macro Average** | **98.80%** | **98.69%** | **0.9875** | **3,000** | **3,000** |

### 3.3 Confusion Matrix (Model D on 3,000 Test Samples)

```
Reference \ Predicted   Positive    Neutral     Negative
Positive                 1,273          4           13
Neutral                      5        410            2
Negative                    11          1        1,281
```

### 3.4 Per-Language Performance Breakdown (3,000 Test Samples)

| Language | Test Samples | Model A Accuracy | Model B Accuracy | Model C Accuracy | Model D (ML) Accuracy |
|---|---:|---:|---:|---:|---:|
| **English** | 1,017 | 89.38% (909) | 88.59% (901) | 89.38% (909) | **98.33% (1,000/1,017)** |
| **Tagalog** | 977 | 86.18% (842) | 84.03% (821) | 86.18% (842) | **99.28% (970/977)** |
| **Taglish** | 1,006 | 92.05% (926) | 87.38% (879) | 92.05% (926) | **98.81% (994/1,006)** |
| **Overall** | **3,000** | **89.23%** | **86.70%** | **89.23%** | **98.80% (2,964/3,000)** |

---

## 4. Pedagogical Domain Classification Benchmark

In addition to sentiment valence and polarity, the scikit-learn pipeline was trained to classify comments into the 5 institutional Likert clusters + Unclassified:
- **Algorithm:** `TfidfVectorizer(ngram_range=(1, 2), min_df=2, max_features=25000)` + `LogisticRegression(C=1.5, balanced)`
- **Test Accuracy:** **$98.57\%$** ($2,957 / 3,000$)
- **Macro F1-Score:** **$0.9606$** across all 6 targets:
  1. *Pedagogical Delivery*
  2. *Assessment & Grading*
  3. *Classroom Management*
  4. *Professional Conduct*
  5. *Student Support & Mentorship*
  6. *Unclassified / Needs Review* (non-teaching facilities and extracurriculars)

---

## 5. Architectural Invariants Preserved

1. **Deterministic Fallback & Circuit Breaker:** If `scikit-learn` or `joblib` model binaries are ever unavailable, the system automatically falls back to the calibrated `calamanCy` + VADER heuristic rule engine without failing student submissions.
2. **Dual-Token Decoupling:** Neither the ML model training nor inference logs ever touch student identities, user IDs, or clearance tokens.
3. **Zero Permits Policy:** Student evaluations track completion progress across colleges and sections without gating examination permits.
4. **63 OWASP Security Controls:** Rate limiting, payload quotas ($2,000$ max chars, $250$ batch size), SSRF loopback protection, and constant-time verification remain fully intact.
