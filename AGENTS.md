# AGENTS.md — School AAAAA13 EvaluaTech Engineering Agency Charter

**Institution:** School AAAAA13
**Platform:** EvaluaTech — Automated Student Evaluation of Teachers (SET) & Multilingual Sentiment Analytics
**Architecture:** Safe Rust Systems (`evaluatech-server`, `evaluatech-web` WASM, `evaluatech-nlp`) + Python Machine Learning (`nlp-python/`: `tl_calamancy_md-0.2.0` + `en_core_web_md` + `vaderSentiment` + `scikit-learn` Calibrated Ensemble)

---

## 1. Executive Memo from the Agency Project Manager

Every agent operating in this repository works under a strict division of concerns, anti-slop craftsmanship, and scientific honesty:

1. **Right Tool for Each Domain (Polyglot Separation — `ADR-001`)**:
   - **Safe Rust (`#![forbid(unsafe_code)]`)** handles memory-safe HTTP routing (`axum`), ACID relational persistence (`rusqlite` WAL), cryptographic Dual-Token decoupling, 63 OWASP security controls, Likert $(\mu, \sigma^2)$ descriptive arithmetic, and compiled WebAssembly UI rendering.
   - **Python 3.12 (`nlp-python/`)** handles **100% of Natural Language Processing and Machine Learning**:
     - **`calamanCy` (`tl_calamancy_md-0.2.0`)**: Pretrained Tagalog/Taglish morphological tokenizer, trainable lemmatizer, UD POS tagger, morphologizer, and **200-dimensional TLUnified static word vectors**.
     - **`spaCy` (`en_core_web_md`)**: **300-dimensional English static word vectors**.
     - **`vaderSentiment`**: Multilingual polarity engine augmented with Filipino/Taglish academic lexicons, comparative negation (`rather than`, `instead of`, `imbes na`), remedial positive overrides (`common mistakes`, `reduce exam stress`), and contrastive clause balance (`medyo ... pero ... naman`).
     - **`scikit-learn`**: Calibrated multi-estimator probability ensembles (`LogisticRegression` + Platt-calibrated `LinearSVC` + `DenseSliceProbabilityWrapper` RBF-SVC) bridged to Rust via a persistent warm JSON-RPC worker (`evaluatech-server/src/python_nlp.rs`).
   - Never reimplement ML classifiers or Tagalog morphological models in Rust.

2. **Ironclad ML Scientific Integrity (Zero Result Tampering — `ADR-002`)**:
   - `docs/analysis/test_partition.csv` (`3,000` held-out test rows, including `228` authentic blind student comments) is **strictly read-only for final blind holdout scoring**.
   - Never train on, fit TF-IDF vocabularies on, inspect errors on, tune hyperparameters against, or hand-code rules from `test_partition.csv`.
   - All training corpus expansions live in `nlp-python/data/expanded_training_corpus.csv` (`build_expanded_corpus.py`) and must pass automated normalized SHA-256 disjointness checks (`test_overlap_hashes == 0`) against `test_partition.csv` prior to fitting.
   - Feature ablations, regularization parameters, ensemble head weights, and class prior multipliers are selected **exclusively via 5-Fold Stratified Out-of-Fold Cross-Validation on `docs/analysis/dev_partition.csv` (`n = 572` real comments)**. Whatever accuracy, macro F1, and negative recall the selected model achieves on `test_partition.csv` must be reported **100% verbatim and unaltered**.

3. **Role-Tailored Institutional UX (Zero AI Slop — `ADR-003`, `ADR-004`)**:
   - **Students (`/queue`, `/evaluate/:id`) & Public Visitors (`/`, `/login`)**: Render a calm, **plain single-column webpage**. Students land immediately on their User Profile Dashboard (`Evaluations Completed: X / Y`) with direct `Evaluate` buttons. Zero sidebars, zero marketing fluff, zero receipt-verification forms, zero exam-permit gating.
   - **Professors up to Administrative Officers (`/faculty`, `/chair`, `/dean`, `/admin`)**: Render a persistent **Institutional Left Sidebar (`aside.staff-sidebar`)** with officer identity, live action badges, module navigation, Display Preferences, and cross-portal administrative switchers (for Deans and the University Registrar).
   - **No Global Scale Page or Button**: Never expose a global `Scale` link or modal in the header or staff sidebars. The `1–5` legend appears quietly inline inside Part 1 of the student evaluation form only.

4. **Repository & Git Hygiene**:
   - Sole git author and committer: `AAAAA13-sys <108907185+AAAAA13-sys@users.noreply.github.com>`. Never append `Co-Authored-By` trailers or AI attribution.
   - All text files must be UTF-8 without BOM and pass `git diff --cached --check`.

---

## 2. Agency Roster & Specialist Playbooks

| Specialist Agent | Playbook | Primary Ownership |
| :--- | :--- | :--- |
| **Agency Project Manager** | [`.agents/skills/agency-project-manager/SKILL.md`](.agents/skills/agency-project-manager/SKILL.md) | Polyglot architecture governance, `ADR-001`–`ADR-008`, anti-leakage oversight, release sign-off |
| **Python NLP & ML Engineer** | [`.agents/skills/agency-ai-engineer/SKILL.md`](.agents/skills/agency-ai-engineer/SKILL.md) | `nlp-python/` (`tl_calamancy_md-0.2.0`, `en_core_web_md`, `vaderSentiment`, `scikit-learn` ensembles, Dev-CV iterations) |
| **Rust Backend Architect** | [`.agents/skills/agency-backend-architect/SKILL.md`](.agents/skills/agency-backend-architect/SKILL.md) | `evaluatech-server/` (`axum`, `rusqlite` WAL, `python_nlp.rs` warm IPC worker), `evaluatech-nlp/` non-ML math |
| **Security & Anonymity Guardian** | [`.agents/skills/agency-security-guardian/SKILL.md`](.agents/skills/agency-security-guardian/SKILL.md) | 63 OWASP controls, Dual-Token decoupling (`evaluation_clearances` vs `evaluation_vault`), `FT-02` idempotency, Double-Blind locks |
| **Institutional UX Architect** | [`.agents/skills/agency-ux-architect/SKILL.md`](.agents/skills/agency-ux-architect/SKILL.md) | TOPCIT-inspired information architecture, plain student flow vs. staff left-sidebar hierarchy |
| **WASM Frontend Developer** | [`.agents/skills/agency-frontend-developer/SKILL.md`](.agents/skills/agency-frontend-developer/SKILL.md) | `evaluatech-web/` (`wasm32-unknown-unknown`) & `web-dist/styles.css` implementation |
| **Accessibility Auditor** | [`.agents/skills/agency-accessibility-auditor/SKILL.md`](.agents/skills/agency-accessibility-auditor/SKILL.md) | WCAG 2.2 AAA (`>= 7.0:1` contrast), `>= 44×44px` tap targets, `A+ Large Text`, keyboard `Tab` & `1`–`5` shortcuts |
| **UI Finish-Gate Reviewer** | [`.agents/skills/agency-ui-finish-gate-reviewer/SKILL.md`](.agents/skills/agency-ui-finish-gate-reviewer/SKILL.md) | Visual anti-slop audit across `1366×768`, `375×812`, and `320×568` viewports |
| **QA Reality Checker** | [`.agents/skills/agency-qa-reality-checker/SKILL.md`](.agents/skills/agency-qa-reality-checker/SKILL.md) | End-to-end `pytest`, `cargo test`, `cargo clippy`, benchmark parity, and headless browser DOM assertions |
| **Technical Writer** | [`.agents/skills/agency-technical-writer/SKILL.md`](.agents/skills/agency-technical-writer/SKILL.md) | Synchronization of `README.md`, `docs/*.md`, and `docs/analysis/*.md` with empirical facts |

---

## 3. Binding Engineering Rules

- [`.agents/rules/ml-scientific-integrity.md`](.agents/rules/ml-scientific-integrity.md) — Dataset expansion, `tl_calamancy_md-0.2.0` + `scikit-learn` training, anti-leakage SHA-256 gates, and unaltered reporting.
- [`.agents/rules/backend-standards.md`](.agents/rules/backend-standards.md) — Safe Rust API contracts, SQLite WAL concurrency, and Python warm worker IPC.
- [`.agents/rules/frontend-standards.md`](.agents/rules/frontend-standards.md) — Student plain layout, staff sidebar shell, inline Part 1 scale legend, and zero developer jargon in UI copy.
- [`.agents/rules/security-anonymity-integrity.md`](.agents/rules/security-anonymity-integrity.md) — RA 10173 Dual-Token decoupling, PII scrubbing, and Double-Blind grade release.
- [`.agents/rules/verification-standards.md`](.agents/rules/verification-standards.md) — Required build, lint, test, and browser verification gates before any commit.

---

## 4. Architecture & Operations Documentation Index

- [`docs/INSTITUTIONAL_SYSTEM_ARCHITECTURE.md`](docs/INSTITUTIONAL_SYSTEM_ARCHITECTURE.md) — Complete system architecture, 5 role workflows, and Python NLP/ML pipeline overview.
- [`docs/ARCHITECTURE_DECISIONS.md`](docs/ARCHITECTURE_DECISIONS.md) — Authoritative Architecture Decision Records (`ADR-001`–`ADR-008`).
- [`docs/GLOSSARY.md`](docs/GLOSSARY.md) — Canonical glossary mapping institutional domain concepts to Rust and Python modules.
- [`docs/PRODUCTION_DEPLOYMENT.md`](docs/PRODUCTION_DEPLOYMENT.md) — Production setup for `evaluatech-server`, `.venv` Python `calamanCy` worker, and one-time roster credentials.
- [`docs/MANAGED_KIOSK_DEPLOYMENT.md`](docs/MANAGED_KIOSK_DEPLOYMENT.md) — Campus computer laboratory kiosk configuration (Windows Assigned Access & Ubuntu LightDM).
- [`docs/analysis/ANNOTATION_GUIDELINES.md`](docs/analysis/ANNOTATION_GUIDELINES.md) — Multilingual sentiment & 6-cluster annotation taxonomy and zero-leakage partition rules.
- [`docs/analysis/EXTERNAL_DATASETS_REPORT.md`](docs/analysis/EXTERNAL_DATASETS_REPORT.md) — Research Department curation report for the 4 imported Hugging Face corpora (`EduRABSA_SA+ACD`, `RateMyProfessor`, `TagaSenti`, `CourseReviews`).
- [`docs/analysis/MODEL_COMPARISON_REPORT.md`](docs/analysis/MODEL_COMPARISON_REPORT.md) — Multi-iteration Dev-CV and blind holdout benchmark report.

---

## 5. Standard Build, Training & Verification Commands

```powershell
# 1. Regenerate zero-leakage corpora, import Hugging Face datasets & run multi-corpus ML training
.\.venv\Scripts\python.exe -u nlp-python\build_expanded_corpus.py
.\.venv\Scripts\python.exe -u nlp-python\import_external_datasets.py
.\.venv\Scripts\python.exe -u nlp-python\train_model.py

# 2. Run Python NLP, calamanCy vector, anti-leakage & holdout reproducibility tests
$env:PYTHONPATH = "$PWD\nlp-python"
.\.venv\Scripts\python.exe -m pytest nlp-python\tests -v

# 3. Rebuild WebAssembly frontend bundle
cargo build -p evaluatech-web --target wasm32-unknown-unknown --release
wasm-bindgen "target/wasm32-unknown-unknown/release/evaluatech_web.wasm" --out-dir "web-dist/pkg" --target web --no-typescript

# 4. Run zero-unsafe Rust formatting, Clippy, and full-stack integration tests
cargo fmt --all --check
cargo clippy --workspace --all-targets --all-features -- -D warnings
cargo test --workspace --all-features

# 5. Build & start the production-grade server (auto-spawns warm Python NLP worker)
cargo build -p evaluatech-server --release
.\START-EVALUATECH.bat
```
