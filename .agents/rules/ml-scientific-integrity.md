# Rule: Machine Learning & `calamanCy` Scientific Integrity

1. **Python-Only NLP & ML Execution**:
   - All linguistic preprocessing (`calamanCy` `tl_calamancy_md-0.2.0` for Tagalog/Taglish + `spaCy` `en_core_web_md` for English), lexicon valence (`vaderSentiment`), feature vectorization, and supervised classification (`scikit-learn`) MUST reside in `nlp-python/`.
   - Rust crates (`evaluatech-nlp`, `evaluatech-server`, `evaluatech-web`) must never embed ML weights or reimplement sentiment/morphological classifiers.

2. **Strict Holdout Isolation (`docs/analysis/test_partition.csv`)**:
   - `docs/analysis/test_partition.csv` (`3,000` rows total; `228` authentic student comments with `ID <= 800`) is a **blind holdout benchmark**.
   - Never modify `docs/analysis/test_partition.csv` or `docs/analysis/dev_partition.csv`.
   - Every training script (`nlp-python/train_model.py`) MUST assert programmatic disjointness between any expanded training CSV (`nlp-python/data/*.csv`) and `docs/analysis/test_partition.csv` using normalized alphanumeric text hashes before fitting any vectorizer or estimator.
   - Never inspect `test_partition.csv` rows for error analysis, lexicon crafting, or hyperparameter grid search. Error analysis is permitted **only** on out-of-fold predictions from `docs/analysis/dev_partition.csv`.

3. **Unaltered Empirical Metrics**:
   - Never hardcode test comment strings, clamp/inflate reported accuracy numbers, or cherry-pick subsets of the `228` blind real test comments.
   - Report both:
     1. **5-Fold Stratified Cross-Validation** on the `572` authentic student comments in `dev_partition.csv`, and
     2. **Blind Holdout Evaluation** on the `228` authentic student comments in `test_partition.csv` (overall accuracy, macro F1, negative sentiment recall, per-language breakdown, and 6-class pedagogical domain accuracy), alongside the full `3,000`-row test partition metrics.

4. **Pedagogical Domain Isolation**:
   - Comments concerning non-teaching campus infrastructure (library, Wi-Fi, air conditioning, projectors, restrooms, student clubs) must map to `Unclassified / Needs Review` so instructors are never penalized for physical plant issues.
