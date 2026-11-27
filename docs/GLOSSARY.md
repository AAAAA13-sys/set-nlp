# EvaluaTech Polyglot Domain Glossary (`GLOSSARY.md`)

Maintained by the **Agency Project Manager** so Rust systems crates and Python ML modules share one canonical domain vocabulary.

| Domain Term | Meaning in School AAAAA13 EvaluaTech | Implementation Module / Table |
| :--- | :--- | :--- |
| **Pedagogical Cluster** | One of the 5 institutional Likert evaluation dimensions (`Pedagogical Delivery`, `Assessment & Grading`, `Classroom Management`, `Professional Conduct`, `Student Support & Mentorship`) plus `Unclassified / Needs Review` | `nlp-python/evaluatech_nlp_py/lexicons.py` & `evaluatech_nlp::PEDAGOGICAL_TAGS` |
| **Sentiment Valence** | Polarity band mapped from $P \in [-1.0, +1.0]$ and calibrated class probabilities: `Positive`, `Neutral`, `Negative` | `nlp-python/evaluatech_nlp_py/sentiment_ml.py` |
| **calamanCy Pipeline** | Pretrained Tagalog morphological analyzer, POS tagger, lemmatizer, and 200-dim static word vectors (`tl_calamancy_md-0.2.0`) paired with English `en_core_web_md` (300-dim) | `nlp-python/evaluatech_nlp_py/calamancy_pipeline.py` |
| **Python Dual-Regime ML Ensemble** | Calibrated `scikit-learn` probability ensemble combining `calamanCy` TF-IDF `(1,3)`, `char_wb` `(2,5)`, 500d bilingual word embeddings, and neuro-symbolic VADER + discourse features | `nlp-python/evaluatech_nlp_py/sentiment_ml.py` & `nlp-python/train_model.py` |
| **Warm Python NLP Bridge** | Persistent UTF-8 JSON-lines stdio worker managed by `evaluatech-server` for low-latency Rust-to-Python inference | `evaluatech-server/src/python_nlp.rs` & `nlp-python/evaluatech_nlp_py/worker.py` |
| **Dual-Token Decoupling** | Atomic SQLite WAL transaction recording student participation in `evaluation_clearances` and stripped anonymous feedback in `evaluation_vault` with zero join keys | `evaluatech-server/src/services.rs` |
| **Submission Idempotency (`FT-02`)** | Client-supplied `submission_key` ensuring network retries return the original completion receipt (`IDEMPOTENT_REPLAY`) without duplicate vault inserts | `evaluation_clearances.submission_key` |
| **Straight-Lining ($\sigma^2 = 0$)** | Population variance $\sigma^2 = 0$ across all 25 Likert items; prompts once (`ZERO_VARIANCE_CONFIRMATION_REQUIRED`) then sets `is_straight_lined = 1` on confirmation | `evaluatech_nlp::compute_likert_stats` |
| **Double-Blind Lock** | Server guard blocking faculty access to section SET reports until `term_grades_submitted == 1 && registrar_lock == 1` | `evaluatech-server/src/auth.rs` |
| **Role-Tailored Workspace** | Plain single-column webpage for Students (`/queue`, `/evaluate/:id`) vs. Institutional Left Sidebar (`aside.staff-sidebar`) for Faculty, Chairs, Deans, and Registrar (`/faculty`–`/admin`) | `evaluatech-web/src/lib.rs` & `web-dist/styles.css` |
| **Exception Watchlist** | Early-warning support flag triggered when faculty `mean_score < 3.50` OR `negative_pct > 35.0%` | `evaluatech-server/src/services.rs` & `nlp-python/evaluatech_nlp_py/engine.py` |
| **Bearer Session Token (`EVT.*`)** | Stateless HMAC-SHA256 signed token `EVT.<base64url(claims)>.<base64url(hmac)>` stored ephemerally in browser `sessionStorage` | `evaluatech-server/src/auth.rs` |
| **AACCUP Level III Seal** | SHA-256 cryptographic accreditation dossier seal (`AAAAA13-AACCUP3-*`) | `evaluatech-server/src/services.rs` |
