# EvaluaTech Architecture Decision Records (`ARCHITECTURE_DECISIONS.md`)

Maintained by the **Agency Project Manager** in alignment with [`AGENTS.md`](../AGENTS.md).

## ADR-001: Polyglot Systems + Machine Learning Separation (Safe Rust + Python `calamanCy` / `scikit-learn`)
- **Status**: Accepted (2026-11-27)
- **Context**: Memory-safe native binaries (`axum`, `rusqlite`, `wasm32-unknown-unknown`) excel at high-concurrency HTTP routing, cryptographic anonymity boundaries, and deterministic Likert arithmetic, whereas Python possesses first-class Filipino linguistic pipelines (`calamanCy` `tl_calamancy_md-0.2.0`), `spaCy` (`en_core_web_md`), `vaderSentiment`, and mature `scikit-learn` multi-estimator calibration.
- **Decision**: Enforce a clean polyglot architecture:
  1. **Safe Rust (`#![forbid(unsafe_code)]`)** owns `evaluatech-server` (Axum + SQLite WAL + 63 OWASP controls), `evaluatech-web` (compiled WebAssembly UI), and `evaluatech-nlp` (non-ML Likert $(\mu, \sigma^2)$ descriptive math, PII redaction, and CSV formula sanitization).
  2. **Python 3.12 (`nlp-python/`)** owns **100% of NLP and Machine Learning** (`calamanCy` + `spaCy` + `vaderSentiment` + `scikit-learn`), connected to `evaluatech-server` via a persistent, UTF-8 JSON-lines warm worker (`evaluatech-server/src/python_nlp.rs`).
- **Consequences**: Genuine pretrained Tagalog/English static embeddings and calibrated `scikit-learn` ensembles run natively in Python with zero warm-worker cold-start penalty, while student anonymity and web serving remain memory-safe in Rust.

## ADR-002: Pretrained Bilingual Word Vectors (`tl_calamancy_md-0.2.0` + `en_core_web_md`) + Calibrated Heterogeneous Ensemble
- **Status**: Accepted (2026-11-27)
- **Context**: Collegiate student evaluations in the Philippines code-switch fluidly across English, Tagalog, and Taglish, including comparative negations (`rather than`, `instead of`, `imbes na`), remedial positive practices (`common mistakes`, `reduce exam stress`), and hedged contrastive observations (`medyo ... pero ... naman`).
- **Decision**: Combine `calamanCy`-lemmatized & comparative-negation-scoped word n-grams `(1, 3)`, `char_wb` subword n-grams `(2, 5)`, **500-dimensional bilingual static embeddings** (`200d` `tl_calamancy_md-0.2.0` + `300d` `en_core_web_md`), and neuro-symbolic VADER + clause-contrast discourse features inside a calibrated `scikit-learn` ensemble (`LogisticRegression` + Platt-calibrated `LinearSVC`). All model selection is performed strictly via 5-Fold Stratified Cross-Validation on the development split with zero access to `docs/analysis/test_partition.csv`.
- **Consequences**: Strong generalization to unseen Tagalog, Taglish, and English synonyms with 100% auditable, unaltered holdout metrics in `nlp-python/models/model_metadata.json`.

## ADR-003: Role-Tailored Navigation — Plain Student Webpage vs. Institutional Staff Left Sidebar
- **Status**: Accepted (2026-11-27)
- **Context**: Students sign in once per term solely to evaluate their enrolled instructors without distraction, whereas Faculty, Department Chairs, College Deans, and Registrar Officers manage multi-view operational workflows. Furthermore, a global `Scale` button/modal cluttered administrative screens where Likert completion does not occur.
- **Decision**:
  1. Render a clean, **plain single-column webpage** (`aside.staff-sidebar` absent) for Public Visitors (`/`, `/login`) and Students (`/queue`, `/evaluate/:id`).
  2. Render a persistent **Institutional Left Sidebar (`aside.staff-sidebar`)** for Professors (`/faculty`), Department Chairs (`/chair`), College Deans (`/dean`), and Registrar Officers (`/admin`), complete with live action badges and cross-portal administrative links.
  3. Delete the global `Scale` navigation item and modal across all roles; display the `1–5` scale legend quietly inline inside Part 1 of the student evaluation stepper only.
- **Consequences**: Zero navigation clutter for students, clear multi-module orientation for academic officers, and zero irrelevant global modals.

## ADR-004: Zero-Permits Policy & Removal of Receipt Verification Friction
- **Status**: Accepted (2026-11-27)
- **Context**: Gating examination permits behind SET completion or requiring manual receipt-verification forms creates coercive friction without improving feedback quality.
- **Decision**: Enforce the **Zero Permits Policy** institution-wide. Track evaluation completion internally per enrolled section and surface live progress directly on the post-login Student Profile Dashboard (`/queue`) and scoped participation trackers (`GET /api/{chair,dean,admin}/participation`) without ever linking `evaluation_clearances` to `evaluation_vault`.
- **Consequences**: Streamlined TOPCIT-inspired task flow with zero exam-permit gating or manual token lookup forms.

## ADR-005: Non-Negotiable Dual-Token Cryptographic Decoupling & Zero-PII Vault
- **Status**: Accepted (2026-11-26)
- **Context**: Student evaluation compliance must be tracked under RA 10173 without allowing any actor—including System Administrators—to correlate a student with their ratings or narrative comments.
- **Decision**: `evaluation_clearances` stores `(student_id, course_section_id, submission_key, clearance_token)` while `evaluation_vault` stores only a random UUID v4 `id`, coarse `recorded_date` (`YYYY-MM-DD`), PII-scrubbed `comment_text`, salted HMAC-SHA256 `comment_hash`, Likert ratings, population variance $\sigma^2$, and Python NLP/ML classifications inside a single `BEGIN IMMEDIATE` SQLite WAL transaction.
- **Consequences**: Mathematically and structurally impossible to join or timestamp-correlate a student identity to a vault response, while `FT-02` `submission_key` guarantees idempotent retries.

## ADR-006: Stateless `EVT.*` HMAC-SHA256 Auth, Bcrypt & One-Time Temporary Credentials
- **Status**: Accepted (2026-11-26)
- **Context**: Authentication must be stateless, constant-time, rate-limited, free of shared default passwords in production, and ephemeral in browser storage.
- **Decision**: Sign `EVT.<payload>.<sig>` tokens with HMAC-SHA256 stored in `sessionStorage`, verify passwords with constant-time `bcrypt` (including dummy hash verification on unknown accounts), enforce 8-attempt/60s `auth_rate_limits` (`429`), support SHA-256 token revocation on sign-out, and issue CSPRNG one-time temporary credentials (`TMP-*`, `must_change_password = 1`, `403 PASSWORD_CHANGE_REQUIRED`).
- **Consequences**: Hardened authentication lifecycle across personal devices and shared campus laboratories.

## ADR-007: 63-Control OWASP & AI Security Middleware (`evaluatech-server/src/security.rs`)
- **Status**: Accepted (2026-11-26)
- **Context**: Every HTTP request and response must satisfy EvaluaTech's 63 institutional security controls.
- **Decision**: Enforce OWASP response headers (`Content-Security-Policy` with `'wasm-unsafe-eval'`, `Strict-Transport-Security`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `X-Robots-Tag`), `.env`/`.sqlite`/`.map` `403` blocks, `/graphql` `404`, WebSocket `426`, cross-origin `403`, 2 MB body cap `413`, `$gt`/`__proto__` operator rejection `422`, prompt-injection token stripping, and CSV formula neutralization.
- **Consequences**: `63 / 63 ENFORCED` defense-in-depth posture natively in Axum.

## ADR-008: WCAG 2.2 AAA Contrast & Responsive `44×44px` Touch Ergonomics
- **Status**: Accepted (2026-11-27)
- **Context**: Institutional interfaces must remain effortlessly readable under bright laboratory lighting and operable via keyboard or mobile touchscreens down to `320px`.
- **Decision**: Standardize on the TOPCIT Institutional Navy & Slate token system (`>= 7.0:1` WCAG 2.2 AAA body text contrast in both Light and Dark themes), enforce `>= 44×44px` interactive tap targets, consolidate `A+ Large Text` and `Dark View` into the header Settings menu, and stack tables into clean cards on mobile viewports.
- **Consequences**: Zero horizontal overflow and full keyboard/screen-reader accessibility across desktop, tablet, and mobile devices.
