# EvaluaTech Pure-Rust Full-Stack Architecture Decision Records (`DECISIONS.md`)

Synthesized by **Agency Senior Project Manager** from `rust/research/01..03.md` per the `rust-anything` whole-app protocol.

## ADR-001: Multi-Crate Pure-Rust Full-Stack Workspace Alongside Reference Stack
- **Status**: Accepted (2026-11-26)
- **Context**: EvaluaTech requires both (a) full-stack pure-Rust execution from multilingual NLP and 10k ML sentiment inference up to the HTTP/SQLite backend and WebAssembly web application, and (b) 100% preservation of the existing 187-unit verification baseline (`scripts/verify-all.ps1`) with the original files retained as ground-truth reference per `rust-anything` `whole-app.md`.
- **Decision**: Organize the standalone Rust platform as a Cargo workspace in `rust/` (`evaluatech-nlp`, `evaluatech-web`, `evaluatech-server`) while keeping `analytics/rust/{ml-rs,engine-rs}` enabled via PyO3 for the Python reference tier.
- **Consequences**: `evaluatech-server.exe` runs as a single self-contained native binary serving the Rust/WASM web app and REST API, while all 187 existing PHP/Vue/Python/Playwright tests remain 100% green.

## ADR-002: Portable Zero-Unsafe `evaluatech-nlp` Shared Between Server and Browser WASM
- **Status**: Accepted (2026-11-26)
- **Context**: Both the backend submission/analytics endpoints and the browser evaluation stepper benefit from deterministic sub-millisecond `calamanCy + VADER` scoring, 5-cluster pedagogical classification, PII scrubbing, and 10,000-sample ML inference.
- **Decision**: Enforce `#![forbid(unsafe_code)]` and zero OS-specific dependencies in the core `evaluatech-nlp` library so it compiles cleanly to both `x86_64` native code and `wasm32-unknown-unknown`, with an optional `server` feature providing the standalone `evaluatech-nlp-server` (`127.0.0.1:5001`) Axum binary.
- **Consequences**: Identical polarity, valence, cluster, and PII redaction math runs in both the browser WASM preview and the backend transaction engine.

## ADR-003: Version-Independent Pure-Rust 10,000-Sample TF-IDF + LogisticRegression Inference
- **Status**: Accepted (2026-11-26)
- **Context**: Python `.joblib` pickles break across minor `scikit-learn` versions (`1.9.1` vs `1.4.2`). The underlying mathematical model is a `FeatureUnion` of word `(1, 2)` and `char_wb` `(2, 5)` sublinear TF-IDF vectorizers feeding multinomial `LogisticRegression` (`C=10.0` sentiment, `C=4.0` domain, real student comments weighted `x5`).
- **Decision**: Export trained TF-IDF vocabularies, IDF weights, class intercepts, and coefficient matrices from the 10,000-comment partition into a deterministic JSON model artifact embedded in `evaluatech-nlp`, executing sparse dot-product + softmax inference directly in pure Rust.
- **Consequences**: Eliminates Python/pickle runtime dependencies, preserves the empirical ~87.2% real-comment accuracy benchmark, and cuts per-comment ML latency from ~1.54 ms to ~20 microseconds.

## ADR-004: Axum + Bundled Rusqlite Single-Binary Backend with Schema Compatibility
- **Status**: Accepted (2026-11-26)
- **Context**: EvaluaTech's standalone runtime uses SQLite with 13 normalized tables (`DatabaseSchemaManager.php`) and pessimistic write serialization.
- **Decision**: Build `evaluatech-server` on `axum` + `tokio` + `rusqlite` (`bundled` SQLite 3 compiled via `llvm-mingw` UCRT), using the exact 13-table DDL and `BEGIN IMMEDIATE` write locks for atomic dual-token submissions.
- **Consequences**: Zero external database daemon required; full ACID isolation and `FT-02` `submission_key` idempotency under burst concurrency.

## ADR-005: Non-Negotiable Dual-Token Cryptographic Decoupling & Zero-PII Vault
- **Status**: Accepted (2026-11-26)
- **Context**: Student evaluation compliance must be tracked without ever allowing student-to-response correlation.
- **Decision**: `evaluation_clearances` stores `(student_id, course_section_id, submission_key, clearance_token)` while `evaluation_vault` stores only a random UUID v4 `id`, coarse `recorded_date` (`YYYY-MM-DD`), PII-scrubbed `comment_text`, salted HMAC-SHA256 `comment_hash`, ratings, variance, and NLP/ML tags.
- **Consequences**: Mathematically and structurally impossible for any role—including System Admin—to join or timestamp-correlate a student to a vault record.

## ADR-006: Stateless `EVT.*` HMAC-SHA256 Auth, Bcrypt & One-Time Temporary Credentials
- **Status**: Accepted (2026-11-26)
- **Context**: Authentication must be stateless, constant-time, rate-limited, free of hardcoded production secrets or default user passwords, and compatible with `EVT.<payload>.<sig>` tokens.
- **Decision**: Implement `EVT.*` HS256 token signing/verification, constant-time `bcrypt` verification (with dummy hash fallback), 8-attempt/60s `auth_rate_limits` (`429`), SHA-256 logout revocation, and random CSPRNG one-time passwords (`must_change_password = 1`, `403 PASSWORD_CHANGE_REQUIRED`).
- **Consequences**: Full parity with all authentication, password-rotation, and anti-enumeration security invariants.

## ADR-007: 63-Control Security Maxxxxxing Tower Layer
- **Status**: Accepted (2026-11-26)
- **Context**: Every HTTP response and incoming request must enforce EvaluaTech's 63 OWASP & AI security controls.
- **Decision**: Implement a dedicated Axum middleware layer enforcing OWASP headers (`CSP` with `'wasm-unsafe-eval'` for WASM execution, `HSTS`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, `X-Robots-Tag`), path traversal & `.env`/`.sqlite`/`.map` `403` blocks, `/graphql` `404`, WebSocket `426`, cross-origin `403`, 2 MB body cap `413`, recursive `$gt`/`__proto__` operator rejection `422`, and CSV formula neutralization.
- **Consequences**: `63 / 63 ENFORCED` security posture natively in Rust.

## ADR-008: 100% Pure-Rust WebAssembly (`wasm32-unknown-unknown`) Coolors Periwinkle-Blue Web App
- **Status**: Accepted (2026-11-26)
- **Context**: The user requested opting everything into Rust up to the website/web app itself while honoring institutional UX and anti-slop standards.
- **Decision**: Build `evaluatech-web` in pure Rust (`wasm-bindgen` + `web-sys`) compiled to WebAssembly and embedded directly into `evaluatech-server`, implementing the Coolors Periwinkle-Blue palette (`#edf2fb`–`#abc4ff`), `Public Sans` + `JetBrains Mono`, WCAG 2.2 AAA contrast, single top-bar navigation (zero sidebars), zero demo/cheat buttons, and all 5 role workspaces + public landing + split login + clearance verifier.
- **Consequences**: Zero JS framework bloat, instant native WASM client validation/NLP preview, and single-binary deployment (`evaluatech-server.exe`).
