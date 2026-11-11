# School AAAAA13 — EvaluaTech

> **Automated Student Evaluation of Teachers (SET) Platform with Dual-Token Cryptographic Decoupling, 10,000-Sample Empirical Multilingual ML Pipeline, Coolors Periwinkle Design System, and AACCUP Level III Governance.**

[![Tests: 181/181 Passing](https://img.shields.io/badge/Tests-181%2F181%20PASS-brightgreen.svg?style=flat-square)](scripts/verify-all.ps1)
[![ML Accuracy: 98.80%](https://img.shields.io/badge/ML%20Accuracy-98.80%25-blue.svg?style=flat-square)](docs/analysis/MODEL_COMPARISON_REPORT.md)
[![ML Latency: 0.209ms](https://img.shields.io/badge/ML%20Latency-0.209ms-success.svg?style=flat-square)](docs/analysis/MODEL_COMPARISON_REPORT.md)
[![Security: 63/63 Enforced](https://img.shields.io/badge/OWASP%20Controls-63%2F63%20ENFORCED-blueviolet.svg?style=flat-square)](app/Services/SecurityAuditService.php)
[![Accessibility: WCAG 2.2 AAA](https://img.shields.io/badge/Accessibility-WCAG%202.2%20AAA-informational.svg?style=flat-square)](resources/css/app.css)
[![Zero Permits Policy](https://img.shields.io/badge/Governance-Zero%20Permits%20Policy-teal.svg?style=flat-square)](docs/INSTITUTIONAL_SYSTEM_ARCHITECTURE.md)
[![Showcase Video](https://img.shields.io/badge/Showcase-Watch%20Video%20Demo-red.svg?style=flat-square)](docs/showcase/evalua_tech_showcase.webm)

- **Institution**: **School AAAAA13**
- **Repository Reference**: [https://github.com/AAAAA13-sys/set-nlp](https://github.com/AAAAA13-sys/set-nlp)
- **Showcase Video Artifact**: [`docs/showcase/evalua_tech_showcase.webm`](docs/showcase/evalua_tech_showcase.webm) (Interactive Chromium Playwright Walkthrough)
- **NLP Pipeline Reference**: 10,000-Sample Multilingual ML Pipeline (`scikit-learn` TF-IDF $(1,3)$ + Calibrated Logistic Regression) + `calamanCy` & VADER Polarity Engine ($P \in [-1.0, +1.0]$)
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

### 1.4 10,000-Sample Empirical Multilingual ML Pipeline (`analytics/`)
- **Corpus Scale & Stratification**: 10,000 unique student evaluation comments across English (3,390), Tagalog (3,258), and Taglish (3,352). Stratified 70/30 (`seed = 42`) into 7,000 Development and 3,000 Untouched Held-Out Test samples.
- **Empirical Blind Test Benchmark (3,000 Samples)**:
  - **Overall Accuracy**: **$98.80\%$** ($2,964 / 3,000$ correct), outperforming rule-based baselines ($89.23\%$) by **$+9.57\%$**.
  - **Inference Latency**: **$0.209\text{ ms}$** raw model inference ($2.39\text{ ms}$ end-to-end multi-tier pipeline).
  - **Negative Sentiment Recall**: **$99.07\%$** ($1,281 / 1,293$) — critical for Dean and Chair early-warning interventions.
  - **Macro F1-Score**: **$0.9875$** across Positive ($0.9872$), Neutral ($0.9856$), and Negative ($0.9896$).
  - **Language Generalization**: English: $98.33\%$, Tagalog: $99.28\%$, Taglish: $98.81\%$.
- **5-Cluster Pedagogical Domain Classifier**: Achieves $98.57\%$ accuracy and $0.9606$ Macro F1, with explicit isolation of non-teaching campus facilities feedback (*Unclassified / Needs Review*).

---

## 2. Multi-Tier Verification Matrix (181 Automated Units — 100% PASS)

EvaluaTech enforces a comprehensive 5-layer automated testing matrix requiring a 100% pass rate:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-all.ps1
```

| Verification Layer | Target Scope | Units Executed | Pass Rate |
| :--- | :--- | :---: | :---: |
| **Layer 1: Backend PHPUnit Suite** | Five-Role RBAC, Dual-Token Decoupling, FT-02, 63 OWASP Controls | 39 Tests (1,077 assertions) | **100% PASS** |
| **Layer 2: Frontend Vue 3 DOM Suite** | Reactive UI, 2-Step Stepper, Timers, Straight-Line Modal, Store State | 76 Groups (80 tests) | **100% PASS** |
| **Layer 3: Python NLP Analytics Suite** | Polarity Bounds, Valence Boundaries, 5 Clusters, PII Scrubbing | 50 Tests | **100% PASS** |
| **Layer 4: Playwright E2E Suite** | Real Browser Journeys, War Room Pre-Flight, Mobile ($375\times812$) | 12 Tests | **100% PASS** |
| **Layer 5: Repository Hygiene & Syntax** | 62 PHP files clean (`php -l`), 275+ source files UTF-8 No-BOM | 337+ Files Checked | **100% PASS** |
| **Total Automated Coverage** | **End-to-End Multi-Tier Engineering Matrix** | **181 Units** | **100% PASS** |

---

## 3. Specialized Agency Skills (`.agents/skills/`)

EvaluaTech incorporates **16 specialized agency worker skills**:

1. **GitHub Brag Specialist** ([`agency-github-brag`](.agents/skills/agency-github-brag/SKILL.md)): Owns viral release showcases, GitHub brag artifacts, empirical ML benchmark callouts (10k dataset, 98.80% accuracy, 0.209 ms latency), Coolors periwinkle-blue design highlights, and visual walkthrough video links.
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
