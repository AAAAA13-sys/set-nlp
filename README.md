# School AAAAA13 — EvaluaTech

> **Automated Student Evaluation of Teachers (SET) Platform Combining Memory-Safe Rust Systems (`evaluatech-server` + `evaluatech-web` WASM) with Python Multilingual NLP & Machine Learning (`calamanCy` + `vaderSentiment` + `scikit-learn`), Role-Tailored Navigation, and Dual-Token Cryptographic Decoupling.**

[![Systems: 100% Safe Rust](https://img.shields.io/badge/Systems-Safe%20Rust%20(%23!%5Bforbid(unsafe__code)%5D)-dea584.svg?style=flat-square)](Cargo.toml)
[![NLP & ML: Python calamanCy + scikit-learn](https://img.shields.io/badge/NLP%20%26%20ML-Python%20(calamanCy%20%2B%20VADER%20%2B%20scikit--learn)-3776ab.svg?style=flat-square)](nlp-python/)
[![Frontend: WebAssembly](https://img.shields.io/badge/Frontend-Rust%20%2B%20WebAssembly-654ff0.svg?style=flat-square)](evaluatech-web/)
[![Security: 63/63 OWASP Controls](https://img.shields.io/badge/OWASP%20Controls-63%2F63%20ENFORCED-blueviolet.svg?style=flat-square)](evaluatech-server/src/security.rs)
[![Accessibility: WCAG 2.2 AAA](https://img.shields.io/badge/Accessibility-WCAG%202.2%20AAA-informational.svg?style=flat-square)](web-dist/styles.css)

---

## 1. Overview & Polyglot Architecture

**EvaluaTech** separates systems engineering from machine learning using the right language for each domain:
- **Safe Rust (`evaluatech-server`, `evaluatech-web`, `evaluatech-nlp`)**: Memory-safe HTTP/SQLite persistence, cryptographic Dual-Token student anonymity, 63 OWASP controls, Likert $(\mu, \sigma^2)$ descriptive arithmetic, and WebAssembly UI rendering.
- **Python (`nlp-python/`)**: Genuine **`calamanCy` + `spaCy`** Tagalog/Taglish morphological tokenization & lemmatization, **`vaderSentiment`** multilingual polarity scoring, and **`scikit-learn`** Dual-Regime Logistic Regression sentiment & 5-cluster pedagogical classification.

| Component | Language / Target | Responsibility |
| :--- | :--- | :--- |
| [`nlp-python`](nlp-python/) | Python 3.12 (`calamanCy`, `spaCy`, `vaderSentiment`, `scikit-learn`, `FastAPI`) | Multilingual English / Tagalog / Taglish sentiment & pedagogical cluster ML pipelines (`models/*.joblib`), `calamanCy` lemmatization, VADER valence, batch summarization, and FastAPI / warm JSON-RPC worker execution. |
| [`evaluatech-web`](evaluatech-web/) | Rust `cdylib` (`wasm32-unknown-unknown`) | Role-tailored institutional SPA: **Plain distraction-free webpage** for Students (`/queue`, `/evaluate/:id`) with zero global scale clutter, paired with a structured **Institutional Left Sidebar** for Professors, Department Chairs, College Deans, and University Registrar Officers (`/faculty`, `/chair`, `/dean`, `/admin`). |
| [`evaluatech-server`](evaluatech-server/) | Safe Rust Binary (`evaluatech-server`) | Standalone **Axum + Tokio + SQLite WAL** server bridging to `nlp-python` and enforcing `EVT.*` HMAC-SHA256 Bearer auth, one-time temporary credentials (`TMP-*`), Dual-Token Cryptographic Decoupling, `FT-02` idempotency, Double-Blind faculty grade locks, and AACCUP Level III dossier sealing (`AAAAA13-AACCUP3-*`). |
| [`evaluatech-nlp`](evaluatech-nlp/) | Safe Rust `rlib` | Shared non-ML Likert 25-item descriptive statistics, CSV formula sanitization, and defense-in-depth PII scrubbing. |

---

## 2. Institutional Portal Walkthrough

| University Home Portal (`/`) | Student Profile Dashboard (`/queue`) |
| :---: | :---: |
| ![Home Portal](docs/showcase/screenshots/01_home_portal.png) | ![Student Subjects](docs/showcase/screenshots/03_student_subjects.png) |

| 2-Part Evaluation Form (`/evaluate/:id`) | College Dean Portal (`/dean`) |
| :---: | :---: |
| ![Part 1 Ratings](docs/showcase/screenshots/04_evaluation_part1_ratings.png) | ![College Dean Summary](docs/showcase/screenshots/09_dean_summary.png) |

| Registrar Administration (`/admin`) | Mobile `375×812` Card-Stacked Layout |
| :---: | :---: |
| ![Registrar Schedule](docs/showcase/screenshots/11_registrar_administration.png) | ![Mobile Portal](docs/showcase/screenshots/12_mobile_portal.png) |

---

## 3. Core Institutional & Cryptographic Invariants

- **Dual-Token Cryptographic Decoupling**: Enrollment completion records (`evaluation_clearances`) are physically separated from anonymized evaluation sheets (`evaluation_vault`). `evaluation_vault` stores **zero** `student_id`, `user_id`, `clearance_id`, IP address, session ID, or sub-second correlation timestamps.
- **FT-02 Concurrency & Idempotency**: Submissions verify `submission_key` inside atomic SQLite transactions, ensuring retries or double clicks return the original receipt (`CLR-*`) without inserting duplicate responses.
- **Double-Blind Faculty Release Gate**: Instructors receive `HTTP 403 DOUBLE_BLIND_LOCKED` until both `term_grades_submitted = true` and `registrar_lock = true` are satisfied.
- **Scoped Academic Follow-Up Containment**: Scoped participation endpoints (`GET /api/{chair,dean,admin}/participation`) list pending students strictly from enrollment and completion ledgers for polite academic follow-up without touching `evaluation_vault`.
- **One-Time Temporary Credentials**: Roster imports issue CSPRNG temporary passwords (`TMP-*`) stored strictly as bcrypt with `must_change_password = 1`, pinning accounts to `/account/password` until rotated.

---

## 4. Multilingual Machine Learning Benchmarks (`evaluatech-nlp`)

Trained on 10,000 collegiate statements across English, Tagalog, and Taglish (`800` authentic student comments + `9,200` synthetic statements, split 70/30 into [`dev_partition.csv`](docs/analysis/dev_partition.csv) and [`test_partition.csv`](docs/analysis/test_partition.csv)). Headline performance is reported **strictly on the 227 blind real held-out student comments** ([`MODEL_COMPARISON_REPORT.md`](docs/analysis/MODEL_COMPARISON_REPORT.md)):

| Metric (`n = 227` Blind Real Student Comments) | Score |
| :--- | :---: |
| **Real-Comment 5-Fold CV Accuracy (`n = 572`)** | **92.66%** (`0.9193` Macro F1) |
| **Blind Held-Out Sentiment Accuracy (`n = 227`)** | **92.51%** (`210 / 227`, `0.9019` Macro F1) |
| **Negative Sentiment Recall (`n = 89` neg)** | **94.38%** (`84 / 89`) |
| **Taglish / Tagalog / English Accuracy** | **95.00%** / **94.44%** / **89.25%** |
| **Pedagogical Cluster Accuracy (5 Clusters + Unclassified)** | **88.55%** (`0.7340` CV Macro F1) |
| **Pure-Rust Inference Latency** | **~15 µs / comment** |

---

## 5. Quick Start & Verification

### Run the Full-Stack Single-Binary Server (`http://127.0.0.1:8080`)
```powershell
cargo run -p evaluatech-server --release
```
*(Or double-click `START-EVALUATECH.bat` on Windows. When `APP_ENV=local` and the SQLite database is empty, demo accounts for `student`, `faculty`, `chair`, `dean`, and `admin` are seeded automatically.)*

### Rebuild the WebAssembly Frontend Bundle
```powershell
cargo build -p evaluatech-web --target wasm32-unknown-unknown --release
wasm-bindgen "target/wasm32-unknown-unknown/release/evaluatech_web.wasm" --out-dir "web-dist/pkg" --target web --no-typescript
```

### Run Strict Zero-Unsafe Lints & Automated Test Suite
```powershell
cargo fmt --all --check
cargo clippy --workspace --all-targets --all-features -- -D warnings
cargo test --workspace --all-features
```

---

## 6. Documentation

- [Institutional System Architecture](docs/INSTITUTIONAL_SYSTEM_ARCHITECTURE.md)
- [Architecture Decision Records (`ADR-001`–`ADR-008`)](docs/ARCHITECTURE_DECISIONS.md)
- [Domain-to-Rust Module Glossary](docs/GLOSSARY.md)
- [Production Deployment Guide](docs/PRODUCTION_DEPLOYMENT.md)
- [Managed Campus Laboratory Kiosk Guide](docs/MANAGED_KIOSK_DEPLOYMENT.md)
- [UI/UX Audit & Anti-Slop Modernization Specification](docs/ui_ux_audit.md)
- [Multilingual ML Model Comparison Report](docs/analysis/MODEL_COMPARISON_REPORT.md)
- [Dataset Annotation Guidelines](docs/analysis/ANNOTATION_GUIDELINES.md)
