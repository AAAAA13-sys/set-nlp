# School AAAAA13 — EvaluaTech

> **Automated Student Evaluation of Teachers (SET) Platform with Dual-Token Cryptographic Decoupling, 10,000-Sample Empirical Multilingual ML Pipeline, Coolors Periwinkle Design System, and AACCUP Level III Governance.**

[![Tests: 187/187 Passing](https://img.shields.io/badge/Tests-187%2F187%20PASS-brightgreen.svg?style=flat-square)](scripts/verify-all.ps1)
[![Sentiment accuracy on real comments: 87.2%](https://img.shields.io/badge/Sentiment%20(real%20comments)-87.2%25-blue.svg?style=flat-square)](docs/analysis/MODEL_COMPARISON_REPORT.md)
[![ML Latency: 1.5ms](https://img.shields.io/badge/ML%20Latency-1.5ms-success.svg?style=flat-square)](analytics/models/model_metadata.json)
[![Security: 63/63 Enforced](https://img.shields.io/badge/OWASP%20Controls-63%2F63%20ENFORCED-blueviolet.svg?style=flat-square)](app/Services/SecurityAuditService.php)
[![Accessibility: WCAG 2.2 AAA](https://img.shields.io/badge/Accessibility-WCAG%202.2%20AAA-informational.svg?style=flat-square)](resources/css/app.css)
[![Zero Permits Policy](https://img.shields.io/badge/Governance-Zero%20Permits%20Policy-teal.svg?style=flat-square)](docs/INSTITUTIONAL_SYSTEM_ARCHITECTURE.md)
[![Showcase Video](https://img.shields.io/badge/Showcase-Watch%20Video%20Demo-red.svg?style=flat-square)](docs/showcase/evalua_tech_showcase.webm)

- **Institution**: **School AAAAA13**
- **Repository Reference**: [https://github.com/AAAAA13-sys/set-nlp](https://github.com/AAAAA13-sys/set-nlp)
- **Showcase Video Artifact**: [`docs/showcase/evalua_tech_showcase.webm`](docs/showcase/evalua_tech_showcase.webm) (Interactive Chromium Playwright Walkthrough)
- **NLP Pipeline Reference**: Multilingual ML Pipeline (`scikit-learn` word 1-2 + character 2-5 n-gram TF-IDF + Logistic Regression) + `calamanCy` & VADER Polarity Engine ($P \in [-1.0, +1.0]$)
- **Specialized Brag Skill**: [`.agents/skills/agency-github-brag/SKILL.md`](.agents/skills/agency-github-brag/SKILL.md)

---

## 1. Architectural Highlights

**EvaluaTech** is a full-stack, enterprise-grade academic evaluation, cryptographic clearance, and natural language analytics platform custom-engineered for **School AAAAA13**:

### 1.1 Laravel 12 Backend & Standalone HTTP Runtime (`app/`, `routes/`, `database/`)
- **Five-Role Institutional RBAC**: Strict separation of concerns across `student`, `faculty`, `chair`, `dean`, and `admin` (School AAAAA13 Office of the University Registrar).
- **Dual-Token Cryptographic Decoupling**: Physically isolates student enrollment clearance records (`evaluation_clearances`) from anonymized numerical ratings and narrative feedback (`evaluation_vault`). The evaluation vault stores **zero** student identifiers (`student_id`, `user_id`, `clearance_id`, IP address, session ID, browser fingerprint, or sub-second timestamps).
- **FT-02 Concurrency & Idempotency**: Enforces pessimistic row locks (`SELECT ... FOR UPDATE`) and `submission_key` deduplication inside atomic transactions, guaranteeing network retries or double clicks never inject duplicate responses.
- **Double-Blind Faculty Grade-Lock Gate**: Faculty evaluation reports remain cryptographically sealed (`HTTP 403 DOUBLE_BLIND_LOCKED`) until two independent conditions are fulfilled: `term_grades_submitted = true` AND `registrar_lock = true`.
- **63 OWASP / AI Security Maxxxxxing Controls**: Constant-time password hashing, sliding-window rate limiting (`auth_rate_limits`), SSRF loopback allowlisting, path-traversal & `.env`/`.map` rejection, HMAC-SHA256 signed webhooks with nonce deduplication, and CSV formula injection neutralization.

### 1.2 Vue.js 3 SPA & Coolors Periwinkle-Blue Design System (`resources/js/`, `resources/css/app.css`)
- **Coolors Periwinkle Palette**: A calm, focused, and accessible institutional interface built on the Coolors periwinkle scale:
  - `#edf2fb` — Alice Blue Page Canvas (Distraction-free background)
  - `#e2eafc` — Soft Periwinkle Surface (Clean card containers)
  - `#d7e3fc` — Delicate Periwinkle Border (Subtle structural framing)
  - `#ccdbfd` — Periwinkle Tint (Hover states & interactive highlights)
  - `#c1d3fe` — Sky Soft Accent (Active status tags & filters)
  - `#b6ccfe` — Vibrant Periwinkle (Progress indicators & charts)
  - `#abc4ff` — Royal Lavender CTA (Primary action buttons & badges)
- **WCAG 2.2 AAA Accessibility**: Rigorous contrast ratios exceeding $\ge 7:1$, full keyboard navigation, ARIA semantics, and screen reader labels.
- **Universal Top-Bar Navigation (`NavbarHeader.vue`)**: Single-page navigation shell without sidebars, collapsible side rails, or mobile drawers.
- **Academic Tech Split-Screen Login (`LoginView.vue`)**: Full-viewport split-screen with geometric mesh narrative panel, floating card, floating-label inputs, and RA 10173 data privacy callout.
- **2-Step Evaluation Stepper (`EvaluationFormView.vue`)**:
  - *Part I: 25 Likert Scale Questions across 5 Pedagogical Clusters* (*Pedagogical Delivery*, *Assessment & Grading*, *Classroom Management*, *Professional Conduct*, *Student Support & Mentorship*).
  - *Part II: 4-to-5 Qualitative Professor Questions* (3 descriptive words, room for improvement, teaching strengths, actionable recommendations).
- **Mandatory Timing Policy**: Enables immediate evaluation submission whenever students are ready (`dwell_time_seconds >= 0`) within administrator-defined evaluation windows, rejecting negative durations (`< 0`) with `HTTP 422`.
- **Zero-Variance ($\sigma^2 = 0$) Straight-Lining Modal**: Detects uniform responses across all Likert items and challenges students for intentional confirmation.

### 1.3 Institutional Zero Permits Policy & Participation Tracking
- **Zero Permits Policy**: School AAAAA13 has completely eradicated examination permit gating. Student evaluations are never held as ransom for taking examinations.
- **Student Evaluation Participation Tracking**: Replaced permit barriers with transparent, non-coercive participation tracking across courses, sections, departments, and colleges.
- **Digital Evaluation Completion Certificate**: Students receive a cryptographically signed completion certificate (`EVT.CLR.*`) immediately upon evaluating all enrolled courses.
- **Non-Coercive Follow-Ups**: Administrators can export pending student rosters and generate polite reminder notices with zero student-to-response linkage.

### 1.4 Multilingual ML Pipeline (`analytics/`)
- **Training data**: 10,000 comments in English, Tagalog and Taglish. **800 are real student comments**; the other 9,200 were generated from templates by `scripts/generate_10k_dataset.py`. Split 70/30 (`seed = 42`) into 7,000 development and 3,000 held-out test rows (227 of the test rows are real).
- **Model**: word 1-2 grams + character 2-5 grams (robust to Taglish spelling variants) with Logistic Regression; real comments are weighted x5 in training. Selected by 5-fold cross-validation scored on real comments only (`scripts/train_ml_model.py`).
- **Headline results, measured on the 227 real held-out student comments** (`analytics/models/model_metadata.json`):
  - **Sentiment accuracy**: **87.22%** (macro F1 0.854); **negative recall 88.76%**.
  - **By language**: Tagalog 94.4%, Taglish 86.3%, English 83.9%.
  - **Topic (5 clusters + Unclassified) accuracy**: 89.87% (macro F1 0.721).
  - **Latency**: about 1.5 ms per comment.
- **Why not quote the 99% figure**: on the template-generated test rows the model scores 100%, because it learns the generator's patterns. That number says nothing about real students and is reported only as `synthetic_comment_test` in the metadata.
- **Known weaknesses**: Taglish intensifiers used as praise (e.g. "grabe") and facilities complaints can be misread as negative feedback about the teacher. More real, labelled comments are the most valuable next step.

---

## 2. Multi-Tier Verification Matrix (181 Automated Units — 100% PASS)

EvaluaTech enforces a comprehensive 5-layer automated testing matrix requiring a 100% pass rate:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-all.ps1
```

| Verification Layer | Target Scope | Units Executed | Pass Rate |
| :--- | :--- | :---: | :---: |
| **Layer 1: Backend PHPUnit Suite** | Five-Role RBAC, Dual-Token Decoupling, FT-02, 63 OWASP Controls | 41 Tests (1,135 assertions) | **100% PASS** |
| **Layer 2: Frontend Vue 3 DOM Suite** | Reactive UI, 2-Step Stepper, Timers, Straight-Line Modal, Store State | 78 Groups (82 tests) | **100% PASS** |
| **Layer 3: Python NLP Analytics Suite** | Polarity Bounds, Valence Boundaries, 5 Clusters, PII Scrubbing | 50 Tests | **100% PASS** |
| **Layer 4: Playwright E2E Suite** | Real Browser Journeys, War Room Pre-Flight, Mobile ($375\times812$) | 14 Tests | **100% PASS** |
| **Layer 5: Repository Hygiene & Syntax** | 62 PHP files clean (`php -l`), 275+ source files UTF-8 No-BOM | 337+ Files Checked | **100% PASS** |
| **Total Automated Coverage** | **End-to-End Multi-Tier Engineering Matrix** | **187 Units** | **100% PASS** |

---

### Production acceptance walkthrough

Beyond the automated matrix, a 20-step manual acceptance walkthrough runs the real UI against a
server started with `APP_ENV=production` on a database provisioned by `scripts/production-init.php`
(roster import, one-time passwords, forced first-sign-in password change, a full student evaluation,
public receipt verification, registrar locks, faculty and dean read-outs, mobile layout). The method,
results, defects found and fixed, and screenshots are in
[`docs/testing/TESTING_PHASE_REPORT.md`](docs/testing/TESTING_PHASE_REPORT.md); deployment steps are in
[`docs/PRODUCTION_DEPLOYMENT.md`](docs/PRODUCTION_DEPLOYMENT.md).

---

## 3. Specialized Agency Skills (`.agents/skills/`)

EvaluaTech incorporates **16 specialized agency worker skills**:

1. **GitHub Brag Specialist** ([`agency-github-brag`](.agents/skills/agency-github-brag/SKILL.md)): Owns viral release showcases, GitHub brag artifacts, measured ML benchmark callouts (87.2% sentiment accuracy on real student comments), Coolors periwinkle-blue design highlights, and visual walkthrough video links.
2. **Backend Architect** ([`agency-backend-architect`](.agents/skills/agency-backend-architect/SKILL.md)): Owns Laravel 12 & standalone PDO architecture, dual-token cryptographic decoupling, and AACCUP dossier sealing.
3. **Frontend Developer** ([`agency-frontend-developer`](.agents/skills/agency-frontend-developer/SKILL.md)): Owns the Vue.js 3 SPA, Coolors periwinkle design system, top-bar navigation shell, Pinia stores, and 2-step stepper.
4. **AI & NLP Engineer** ([`agency-ai-engineer`](.agents/skills/agency-ai-engineer/SKILL.md)): Owns the Python/Flask NLP microservice, 10k ML training pipeline, 5-cluster pedagogical classifier, and PII scrubbing.
5. **Security Guardian** ([`agency-security-guardian`](.agents/skills/agency-security-guardian/SKILL.md)): Owns zero-knowledge anonymity, the 63-Control Security Maxxxxxing posture, and sliding-window rate limiting.
6. **QA Reality Checker** ([`agency-qa-reality-checker`](.agents/skills/agency-qa-reality-checker/SKILL.md)): Owns multi-tier evidence-based certification across PHPUnit, Vue DOM, Python NLP, and Playwright E2E suites.
7. **Code Reviewer** ([`agency-code-reviewer`](.agents/skills/agency-code-reviewer/SKILL.md)): Owns cross-tier caller tracing, minimal-diff discipline, and regression prevention.
8. **UI Anti-Slop Specialist** ([`ui-anti-slop`](.agents/skills/ui-anti-slop/SKILL.md)): Owns anti-slop gatekeeping (`impeccable detect`), typography, and WCAG 2.2 AAA accessibility.
9. **UI Finish Gate Reviewer** ([`agency-ui-finish-gate-reviewer`](.agents/skills/agency-ui-finish-gate-reviewer/SKILL.md)): Enforces written design contracts and implementation finish gates.
10. **Technical Writer** ([`agency-technical-writer`](.agents/skills/agency-technical-writer/SKILL.md)): Transforms complex cryptographic decoupling and academic workflows into institutional manuals.
11. **Evidence Collector** ([`agency-evidence-collector`](.agents/skills/agency-evidence-collector/SKILL.md)): Captures visual proof, screenshot registries, and honest test reporting.
12. **Accessibility Auditor** ([`agency-accessibility-auditor`](.agents/skills/agency-accessibility-auditor/SKILL.md)): Enforces WCAG 2.2 AAA standards and screen-reader semantics.
13. **Compliance Auditor** ([`agency-compliance-auditor`](.agents/skills/agency-compliance-auditor/SKILL.md)): Verifies AACCUP Level III, CHED CMO 46, and RA 10173 Data Privacy compliance.
14. **Minimal Change Engineer** ([`agency-minimal-change-engineer`](.agents/skills/agency-minimal-change-engineer/SKILL.md)): Enforces surgical precision and eliminates speculative rewrites.
15. **UX Architect** ([`agency-ux-architect`](.agents/skills/agency-ux-architect/SKILL.md)): Designs ergonomic form structures and distraction-free student workspaces.
16. **Knowledge Graph & Architecture Navigator** ([`graphify`](.agents/skills/graphify/SKILL.md)): Owns AST and semantic GraphRAG knowledge extraction and interactive visualization in `graphify-out/`.

---

## 4. Video Showcase Walkthrough

Experience the complete School AAAAA13 EvaluaTech user journey in our automated video walkthrough:

- **Showcase Video**: [`docs/showcase/evalua_tech_showcase.webm`](docs/showcase/evalua_tech_showcase.webm) (13.6 MB native WebM capture)
- **Showcase Documentation**: [`docs/showcase/README.md`](docs/showcase/README.md)
- **Record Fresh Video**:
  ```powershell
  node scripts/record_showcase_video.js
  ```

---

## 5. Quick Start

### Start the Full Institutional Stack
```bat
START-EVALUATECH.bat
```

Or start the individual services manually:

```powershell
# 1. Start Python NLP Microservice (127.0.0.1:5001)
python -m analytics.app

# 2. Start Standalone PHP / Laravel HTTP Runtime (127.0.0.1:8000)
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public\index.php
```

### Access Portals (`http://127.0.0.1:8000`)
- **Public Institutional Landing Page**: `http://127.0.0.1:8000/`
- **Academic Tech Sign-in**: `http://127.0.0.1:8000/login`
- **Student Evaluation Queue**: `http://127.0.0.1:8000/queue`
- **Faculty Double-Blind Portal**: `http://127.0.0.1:8000/faculty`
- **Department Chair Diagnostic Workspace**: `http://127.0.0.1:8000/chair`
- **Dean Analytical Overview & Watchlist**: `http://127.0.0.1:8000/dean`
- **Registrar Administration Console**: `http://127.0.0.1:8000/admin`
- **Campaign Operations War Room & Setup**: `http://127.0.0.1:8000/admin/setup`
