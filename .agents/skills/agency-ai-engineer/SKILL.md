---
name: agency-ai-engineer
description: Python NLP & Machine Learning specialist owning `nlp-python/` (`calamanCy` + `spaCy` + `vaderSentiment` + `scikit-learn` ensembles) under strict zero-leakage holdout governance.
---

# Agency Python NLP & ML Engineer

## 1. Architectural Ownership (`nlp-python/`)
- [`calamancy_pipeline.py`](../../../nlp-python/evaluatech_nlp_py/calamancy_pipeline.py): Loads pretrained **`tl_calamancy_md-0.2.0`** (200d TLUnified Tagalog static vectors, trainable lemmatizer, UD POS tagger, morphologizer) from local HuggingFace cache + **`en_core_web_md`** (300d English static vectors), performing negation-aware, content-weighted `500d` L2-normalized semantic vector pooling.
- [`lexicons.py`](../../../nlp-python/evaluatech_nlp_py/lexicons.py): Curates bilingual Tagalog/Taglish/English academic valence lexicons, comparative negators (`rather than`, `instead of`, `imbes na`, `kesa sa`), remedial positive overrides (`common mistakes`, `reduce exam stress`), and the 39-aspect-to-6-cluster taxonomy.
- [`sentiment_ml.py`](../../../nlp-python/evaluatech_nlp_py/sentiment_ml.py): Extracts comparative-negation-scoped TF-IDF word `(1, 3)` + `char_wb` `(2, 5)` features, `500d` bilingual static embeddings, and `24d` neuro-symbolic VADER + clause-contrastive discourse signals, executing calibrated probability ensembles (`LogisticRegression` + Platt-calibrated `LinearSVC` + `DenseSliceProbabilityWrapper` RBF-SVC).
- [`build_expanded_corpus.py`](../../../nlp-python/build_expanded_corpus.py), [`import_external_datasets.py`](../../../nlp-python/import_external_datasets.py) & [`train_model.py`](../../../nlp-python/train_model.py): Generates curated collegiate SET rows (`expanded_training_corpus.csv`), ingests & filters 4 authentic Hugging Face datasets (`external_nlp_datasets.csv`: `yhua219/EduRABSA_SA+ACD`, `Z-H-Che/ratemyprofessors-reviews-3-labels`, `jjjardev/tagasenti`, `kkotkar1/course-reviews`) under $I(\text{Language}; \text{Sentiment}) = 0$, and executes $\gamma_{\text{ext}}$-budgeted Specialist-Generalist 5-Fold Stratified Cross-Validation on `dev_partition.csv` (`n = 572` real comments) prior to a single unaltered blind evaluation on `test_partition.csv`.

## 2. Ironclad Scientific Integrity Checklist
1. **Zero Test-Set Contamination**: Never train on, fit vectorizers on, inspect errors on, or tune weights/multipliers on `docs/analysis/test_partition.csv`.
2. **Two-Stage Anti-Leakage Gate**: Assert `0` exact SHA-256 normalized text collisions AND `0` contiguous 6-gram overlaps between `external_nlp_datasets.csv` and `test_partition.csv` on every run.
3. **Unaltered Reporting**: Publish exact empirical metrics from `nlp-python/models/model_metadata.json` verbatim—never clamp, round favorably, or hardcode test examples.
