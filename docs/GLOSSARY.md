# EvaluaTech Pure-Rust Domain Glossary (`GLOSSARY.md`)

Maintained by **Agency Senior Project Manager** so every Rust crate uses identical domain terminology.

| Domain Term | Meaning in School AAAAA13 EvaluaTech | Rust Type / Function / Table |
|---|---|---|
| **Pedagogical Cluster** | One of the 5 institutional Likert evaluation dimensions (`Pedagogical Delivery`, `Assessment & Grading`, `Classroom Management`, `Professional Conduct`, `Student Support & Mentorship`) plus `Unclassified / Needs Review` | `evaluatech_nlp::PedagogicalCluster` |
| **Sentiment Valence** | Polarity band mapped from $P \in [-1.0, +1.0]$: `Positive` ($P \ge 0.05$), `Negative` ($P \le -0.05$), `Neutral` ($-0.05 < P < 0.05$) | `evaluatech_nlp::Valence` |
| **Empirical 10k ML Engine** | Word `(1,2)` + `char_wb` `(2,5)` sublinear TF-IDF + multinomial LogisticRegression trained on the 10,000-comment English/Tagalog/Taglish dataset (`x5` real-comment weighting) | `evaluatech_nlp::ml::RustMlModels` |
| **Dual-Token Decoupling** | Atomic transaction recording student compliance in `evaluation_clearances` and stripped anonymous feedback in `evaluation_vault` with zero join keys | `evaluatech_server::services::submit_evaluation` |
| **Submission Idempotency (`FT-02`)** | Unique client-supplied `submission_key` ensuring retries return the original `clearance_token` (`IDEMPOTENT_REPLAY`) without duplicate vault inserts | `evaluation_clearances.submission_key` |
| **Straight-Lining ($\sigma^2 = 0$)** | Population variance $\sigma^2 = 0$ across all 20/25 Likert items; challenges once (`ZERO_VARIANCE_CONFIRMATION_REQUIRED`) then sets `is_straight_lined = 1` on confirmation | `evaluatech_nlp::compute_likert_stats` |
| **Double-Blind Lock** | Server guard blocking faculty access to section SET reports until `term_grades_submitted == 1 && registrar_lock == 1` | `evaluatech_server::auth::ensure_double_blind_unlocked` |
| **Exception Watchlist** | Administrative diagnostic flag triggered when faculty `mean_score < 3.50` OR `negative_pct > 35.0%` | `evaluatech_server::services::compute_watchlist` |
| **Bearer Session Token (`EVT.*`)** | Stateless HMAC-SHA256 signed token `EVT.<base64url(claims)>.<base64url(hmac)>` stored in browser `sessionStorage` | `evaluatech_server::auth::{issue_token, verify_token}` |
| **Clearance Certificate (`EVT.CLR.*`)** | Cryptographic participation receipt proving course SET completion under the Zero Permits Policy | `evaluatech_server::services::verify_clearance_token` |
| **AACCUP Level III Seal** | SHA-256 cryptographic accreditation dossier seal (`AAAAA13-AACCUP3-*`) | `evaluatech_server::services::compile_aaccup_dossier` |
