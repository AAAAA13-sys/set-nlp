# EvaluaTech Python NLP & Sentiment Analysis — Multi-Iteration Dev-CV and Blind Holdout Validation Report

**Maintained By:** Agency Python NLP & ML Engineer & QA Reality Checker  
**Date:** November 27, 2026  
**Runtime Stack:** Python 3.12 (`nlp-python/`: `tl_calamancy_md-0.2.0` + `en_core_web_md` + `vaderSentiment` + `scikit-learn` Calibrated 4-Head Ensemble)  
**Source of Truth:** [`nlp-python/models/model_metadata.json`](../../nlp-python/models/model_metadata.json)

---

## 1. Scientific Integrity & Zero-Leakage Governance

In strict compliance with [`AGENTS.md`](../../AGENTS.md) and [`.agents/rules/ml-scientific-integrity.md`](../../.agents/rules/ml-scientific-integrity.md):

1. **Frozen Blind Holdout (`docs/analysis/test_partition.csv`)**:
   - Contains `3,000` held-out rows (`228` authentic blind student comments with `ID <= 800` and `2,772` synthetic benchmark rows).
   - **Zero** rows, vocabularies, error analyses, or hyperparameter decisions ever touch `test_partition.csv`.
2. **Language-Stratified Zero-Leakage Training Augmentation (`nlp-python/data/expanded_training_corpus.csv`)**:
   - Generated deterministically by [`nlp-python/build_expanded_corpus.py`](../../nlp-python/build_expanded_corpus.py) (`1,185` unique naturalistic collegiate statements: `140 Positive`, `135 Negative`, and `120 Neutral` in **each** of English, Tagalog, and Taglish, enforcing zero language-sentiment mutual information $I(\text{Language}; \text{Sentiment}) = 0$).
   - Programmatically verified via normalized alphanumeric SHA-256 fingerprinting to have **0 overlapping hashes** (`test_overlap_hashes = 0`) against `test_partition.csv` and `dev_partition.csv`.
3. **Unaltered Empirical Reporting**:
   - Every architectural iteration below was selected **exclusively** via 5-Fold Stratified Out-of-Fold Cross-Validation on the `572` authentic student comments in `docs/analysis/dev_partition.csv`. Blind holdout numbers on `test_partition.csv` are reported **100% verbatim and unaltered**.

---

## 2. Progressive 5-Fold Out-of-Fold Cross-Validation Iterations (`dev_partition.csv`, `n = 572` Real Comments)

| Iteration | Feature & Estimator Architecture | Dev 5-Fold CV Accuracy | Dev CV Macro F1 | Dev CV Negative Recall |
| :---: | :--- | :---: | :---: | :---: |
| **Baseline** | Initial Python `LogisticRegression` (`TF-IDF 1-3` + `char_wb 2-5` + `13d` VADER signals) | `89.51%` | `0.8750` | `91.03%` |
| **Iter 1** | Comparative-Negation (`rather than` / `imbes na`) + Remedial Overrides + `24d` Clause-Contrast Discourse `LR` | `94.23%` | `0.9330` | `98.21%` |
| **Iter 2** | `+ 500d` Negation-Aware Pretrained Word Vectors (`tl_calamancy_md-0.2.0` `200d` + `en_core_web_md` `300d`) | `94.58%` | `0.9327` | `97.76%` |
| **Iter 3** | `+ 1,185`-Row Language-Stratified Zero-Leakage Corpus (`I(L; S) = 0`) | `95.45%` | `0.9430` | `99.10%` |
| **Iter 4 (Selected)** | **Calibrated 4-Head Ensemble (`LR` + Platt-Calibrated `LinearSVC` + `524d` `RBF-SVC` + Full-Regime `LR`)** | **`96.33%`** | **`0.9495`** | **`99.10%`** |

---

## 3. Unaltered Blind Holdout Results (`docs/analysis/test_partition.csv`)

### 3.1 Authentic Blind Student Comments (`n = 228`, `ID <= 800`)

| Metric | Previous Python Baseline | Selected Iteration 4 Ensemble | Net Gain |
| :--- | :---: | :---: | :---: |
| **Sentiment Accuracy (`n = 228`)** | `91.23%` (`208 / 228`) | **`92.54%` (`211 / 228`)** | **`+1.31%`** |
| **Sentiment Macro F1** | `0.8967` | **`0.9126`** | **`+0.0159`** |
| **Negative Sentiment Recall (`n = 89`)** | `92.13%` (`82 / 89`) | **`93.26%` (`83 / 89`)** | **`+1.13%`** |
| **Pedagogical Domain Macro F1 (`6` Classes)** | `0.6627` (`89.91%` Acc) | **`0.7107` (`88.60%` Acc)** | **`+0.0480` Macro F1** |

### 3.2 Per-Class Blind Holdout Performance (`n = 228` Authentic Student Comments)

| Valence Class | Precision | Recall | F1-Score | Support |
| :--- | :---: | :---: | :---: | :---: |
| **`Negative`** | **`96.51%`** | **`93.26%`** | **`0.9486`** | `89` |
| **`Neutral`** | **`83.78%`** | **`88.57%`** | **`0.8611`** | `35` |
| **`Positive`** | **`92.38%`** | **`93.27%`** | **`0.9282`** | `104` |
| **Macro Average** | **`90.89%`** | **`91.70%`** | **`0.9126`** | **`228`** |
| **Weighted Average** | **`92.67%`** | **`92.54%`** | **`0.9259`** | **`228`** |

### 3.3 Per-Language Blind Holdout Breakdown (`n = 228` Authentic Student Comments)

| Language | Blind Test Count | Previous Accuracy | Selected Ensemble Accuracy | Macro F1 | Negative Recall |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Tagalog** | `54` | `92.59%` | **`94.44%`** | **`0.9258`** | **`94.74%`** |
| **Taglish** | `80` | `92.50%` | **`93.75%`** | **`0.9216`** | **`97.62%`** |
| **English** | `94` | `89.36%` | **`90.43%`** | **`0.8929`** | **`85.71%`** |
| **Combined Real Holdout** | **`228`** | `91.23%` | **`92.54%`** | **`0.9126`** | **`93.26%`** |

### 3.4 Full `3,000`-Row Benchmark Partition Summary

- **Full `3,000`-Row Sentiment Accuracy**: **`94.67%`** (`0.9317` Macro F1)
- **Full `3,000`-Row Pedagogical Domain Accuracy (`5` Likert Clusters + `Unclassified / Needs Review`)**: **`99.13%`** (`0.9776` Macro F1)

---

## 4. Key Linguistic Capabilities Driving Generalization

1. **Pretrained Bilingual Static Word Embeddings (`500d`)**:
   - `tl_calamancy_md-0.2.0` (`200d` TLUnified vectors by LJ Miranda) + `en_core_web_md` (`300d` English vectors) bridge out-of-vocabulary synonyms (*"napapanahon"*, *"makatuturan"*, *"sirang-sira"*, *"obscure"*, *"proctoring"*) with negation-flipped vector pooling.
2. **Comparative Negation Scoping**:
   - Tokens following *"rather than"*, *"instead of"*, *"imbes na"*, and *"kesa sa"* receive explicit `compneg:*` and `neg:*` scope inversion so critiques like *"tests rote memorization rather than analytical understanding"* are classified as `Negative`.
3. **Remedial Pedagogical Context Overrides**:
   - Constructive instructor behaviors (*"walks through common mistakes"*, *"open to disputing grading errors"*, *"reduces exam stress"*, *"over memorization"*) neutralize surface negative nouns (*"mistakes"*, *"errors"*, *"stress"*).
4. **Hedged Contrastive Tradeoff Detection**:
   - Clause-1 vs. Clause-2 polarity decomposition distinguishes decisive negative shifts (*"Mabait sana kaso walang natututunan"*) from balanced hedged observations (*"Medyo fast-paced pero approachable naman"* -> `Neutral`).
