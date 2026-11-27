# EvaluaTech Sentiment Annotation and Taxonomy Guidelines

**Document Version:** 2.0  
**Date:** November 27, 2026  
**Audience:** Academic Affairs, QA Reality Checkers, Python NLP & ML Engineers  
**Dataset Scope:** Student Evaluation of Teachers (SET) — Multilingual Corpus (English, Tagalog, Taglish)

---

## 1. Linguistic Principles of Philippine Collegiate SET Feedback

Student feedback in Philippine Higher Education Institutions (HEIs) exhibits distinct morphosyntactic and pragmatic patterns modeled directly by `nlp-python/`:

1. **Taglish Code-Switching & Tagalog Affixation**: Students combine English academic terms (*"syllabus"*, *"rubrics"*, *"table of specifications"*, *"proctoring"*, *"live coding"*) with Tagalog verbal/adjectival morphology (*"napakalinaw"*, *"nagpa-mock defense"*, *"nakakabawas"*, *"sirang-sira"*, *"nambabara"*), parsed via `calamanCy` (`tl_calamancy_md-0.2.0`).
2. **Remedial vs. Deficient Polarity Distinction**: Mentions of *"mistakes"*, *"errors"*, *"exam stress"*, or *"difficult topics"* are **Positive** when describing supportive instructor remediation (*"walks through common mistakes"*, *"open to disputing grading errors"*, *"reduces exam stress"*, *"without requiring obscure memorization"*).
3. **Comparative Negation**: Constructions using *"rather than"*, *"instead of"*, *"imbes na"*, or *"kesa sa"* (*"tests rote memorization rather than analytical understanding"*) negate the idealized quality after the comparative marker and express **Negative** evaluative sentiment.
4. **Contrastive Shift vs. Hedged Balance**:
   - **Strong Contrastive Shift**: Unhedged contrast where the second clause delivers a decisive verdict (*"Mabait naman sana kaso walang natututunan ang klase"*) takes the trailing clause's valence (**Negative**).
   - **Hedged Contrastive Tradeoff**: Softened observations balanced by *"naman"*, *"pa rin"*, *"though"*, or *"although"* (*"Medyo fast-paced mag-discuss pero approachable naman"*, *"Medyo luma ang lab PCs pero nagagamit pa naman"*) represent balanced **Neutral** assessments.

---

## 2. Three-Class Sentiment Valence Scheme

| Valence | Polarity Band | Linguistic & Evaluative Definition | Representative Cues |
| :--- | :---: | :--- | :--- |
| **`Positive`** | $P \ge +0.05$ | Clear instruction, fair/transparent rubrics, constructive error review, punctuality, respect, or responsive mentorship. | *malinaw, mahusay, makatwiran, napapanahon, walks through common mistakes, fair grading, approachable* |
| **`Neutral`** | $-0.05 < P < +0.05$ | Normative syllabus/TOS alignment, typical difficulty or workload, or hedged two-sided tradeoffs with no extreme defect. | *karaniwan lamang, tama lang, naaayon sa silabus, sumusunod naman, typical difficulty, medyo ... pero ... naman* |
| **`Negative`** | $P \le -0.05$ | Rote/trick exams (`rather than`), unreturned grades, unannounced absences, schedule conflicts, disrespect, or broken facilities. | *magulo, walang rubrics, sirang-sira, nambabara, rather than, imbes na, conflict, two months late* |

---

## 3. Pedagogical Cluster Taxonomy & Non-Teaching Isolation

| Cluster | Institutional Domain | Scope | Representative Indicators |
| :---: | :--- | :--- | :--- |
| **1** | **Pedagogical Delivery** | Clarity, subject mastery, pacing, examples, live demonstrations | *explain, paliwanag, malinaw, talakayan, slides, examples, kurikulum* |
| **2** | **Assessment & Grading** | Exam alignment, rubrics, grading turnaround, proctoring, feedback | *exam, pagsusulit, rubric, grado, pagmamarka, proctoring, table of specifications* |
| **3** | **Classroom Management** | Punctuality, schedule coordination, attendance, workload pacing | *on time, maagap, late, nahuhuli, syllabus, schedule, workload, free cut* |
| **4** | **Professional Conduct** | Respect, impartiality, patience, classroom decorum, temperament | *respectful, magalang, impartial, walang kinikilingan, masungit, namamahiya* |
| **5** | **Student Support & Mentorship** | Consultation availability, thesis/capstone coaching, responsiveness | *consultation, konsultasyon, thesis, capstone, mock defense, gabay, palugit* |
| **N/A** | **Unclassified / Needs Review** | Campus facilities (Wi-Fi, AC, lab hardware, library) & student org funding | *library, silid-aklatan, wifi, aircon, laboratory PCs, student orgs, facilities* |

> **Governance Rule**: Facility and extracurricular complaints (`Unclassified / Needs Review`) are never folded into an instructor's personal Likert or sentiment standing; they are routed separately to Campus Physical Plant or Student Affairs.

---

## 4. Partition Architecture & Zero-Leakage Protocol

1. **Benchmark Partitions (`10,000` rows, `seed = 42`)**:
   - `docs/analysis/dev_partition.csv` (`7,000` rows, including `572` authentic student comments `ID <= 800`): Used for 5-Fold Stratified Cross-Validation, feature engineering, and hyperparameter selection.
   - `docs/analysis/test_partition.csv` (`3,000` rows, including `228` authentic student comments `ID <= 800`): **Strictly frozen blind holdout**. Never used for training, vocabulary discovery, threshold tuning, or corpus generation.
2. **Expanded Naturalistic Training Corpus (`nlp-python/data/expanded_training_corpus.csv`)**:
   - Balanced across all 9 `(Language × Sentiment)` strata (`ID >= 20001`) and programmatically verified via normalized alphanumeric SHA-256 fingerprinting to have **zero** text overlap (`overlap == 0`) with `test_partition.csv` and `dev_partition.csv`.
