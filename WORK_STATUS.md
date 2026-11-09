# EvaluaTech Coordinated Agency Work Status (`WORK_STATUS.md`)

**Repository:** `https://github.com/AAAAA13-sys/set-nlp`
**Institution:** Pamantasan ng Lungsod ng Pasig (PLP)
**Coordination Owner:** Lead Engineering & Coordination Agent (`Lead Systems Architect`)

---

## 1. General Objective & Four Specific Objectives

### General Objective
To design, develop, and evaluate **EvaluaTech**, a secure and user-friendly web-based Student Evaluation of Teachers platform for Pamantasan ng Lungsod ng Pasig that facilitates anonymous student feedback **without individual minimum or maximum answering durations**, within administrator-defined evaluation periods, automates evaluation completion tracking and examination clearance, and applies Natural Language Processing to support instructional improvement.

### Specific Objectives
1. **Specific Objective 1 (Accessible Evaluation & Administrator-Controlled Window):**
   To develop an accessible online evaluation system that enables students to assess their enrolled teachers through numerical ratings and written feedback and submit completed evaluations whenever they are ready, **without a minimum waiting period or maximum answering duration**, while allowing authorized administrators to establish and manage evaluation opening schedules and submission deadlines.
2. **Specific Objective 2 (Cryptographic Anonymity, Clearance Automation, & Double-Blind Faculty Lock):**
   To implement secure, role-based evaluation and clearance management mechanisms that separate student-identifying information from evaluation responses, prevent duplicate submissions, automatically update examination clearance upon completion of required evaluations, and restrict faculty access to results until grades have been submitted and locked by the registrar.
3. **Specific Objective 3 (Multilingual NLP Analytics & Role-Appropriate Dashboards):**
   To integrate Natural Language Processing for the classification of English, Filipino, and Taglish feedback according to sentiment and teaching-related themes, and to present consolidated ratings, feedback summaries, and trends through role-appropriate dashboards that support instructional assessment and improvement planning.
4. **Specific Objective 4 (Software Quality, Concurrency, Privacy, & Usability Evaluation):**
   To evaluate the platform’s functional suitability, usability, reliability, performance efficiency, and protection of student information through systematic software testing, concurrent submission assessments, privacy and access-control verification, and user evaluation using appropriate software quality criteria and the System Usability Scale.

---

## 2. Baseline Repository Assessment (Mapped to Objectives)

| Objective / Area | Verified Working Implementation | Required Changes / Obsolete Behavior to Remove |
| :--- | :--- | :--- |
| **Objective 1: Student Evaluation & Timing Policy** | 25-item 5-cluster Likert scale (`1`–`5`), 4-to-5 structured written feedback questions, optional comments, `sessionStorage` draft autosave, uniform-rating ($\sigma^2 = 0$) confirmation dialog, and evaluation window state table (`evaluation_window_configs`). | **Remove 45-second minimum dwell-time requirement** (`SPEED_RUN_DETECTED`, `min_dwell_seconds >= 45`, `DwellTimerBadge` countdown). Students must be able to submit completed evaluations immediately (`0s+`) with no minimum wait and no maximum duration while the window is open. Enhance `evaluation_window_configs` and Admin controls with explicit opening (`starts_at`) and closing (`ends_at`) date/time enforcement in `Asia/Manila`, plus distinct **Extend Deadline** (`EXTEND`) vs. **Reopen Closed Period** (`REOPEN`) actions. |
| **Objective 2: Anonymity, Clearance, & Double-Blind Lock** | Dual-token cryptographic decoupling (`evaluation_clearances` vs. `evaluation_vault` with zero student identifiers), `FT-02` idempotency (`submission_key` + `SELECT ... FOR UPDATE`), automatic digital examination permit unlock at 100% completion, and `EnsureDoubleBlindUnlocked` (`term_grades_submitted && registrar_lock`). | Preserve all decoupling, `FT-02`, and double-blind invariants. Ensure Faculty locked-report screen uses clear, accurate terminology (**Confirm Grade Submission Status**) and consistent labels (**Subject ID and Name**, **Section**, **Academic Term**, **Report Status: Locked / Available**). |
| **Objective 3: Multilingual NLP & Role Dashboards** | Python/Flask + deterministic fallback `calamanCy` (`tl_calamancy_md-0.1.0`) + VADER sentiment polarity ($P \in [-1.0, +1.0]$), 5 teaching clusters, PII scrubbing, and role dashboards across `student`, `faculty`, `chair`, `dean`, and `admin`. | Organize user experience into four clear groups (**1. Students**, **2. Faculty / Professors**, **3. Academic Supervisors: Department Chairs and College Deans**, **4. University Registrar / Administrators**) while retaining five separate permission roles. Frame sentiment explicitly as **supporting information, not a definitive judgment of teaching quality**. Add Academic Supervisor filters (Term, Department where permitted, Professor, Subject, Section) and per-row **View Report** inspection. |
| **Objective 4: Testing, Quality, & Documentation Synchronization** | 33 PHPUnit backend tests, 40 Node/Vue frontend test groups, 46 Python NLP unit tests (`119` total units), 63-control security posture, ISO/IEC 25010 & SUS evaluation modules, and Playwright desktop/mobile E2E verification. | Update backend and frontend test suites to verify immediate submission without waiting (`0s`/`1s` accepted), window opening/closing deadline rejections (`EVALUATION_WINDOW_NOT_YET_OPEN`, `EVALUATION_WINDOW_CLOSED`, `EVALUATION_WINDOW_SUSPENDED`, `EVALUATION_WINDOW_EXPIRED`), and `EXTEND` vs. `REOPEN`. Synchronize `AGENTS.md`, `.agents/rules/*.md`, `.agents/skills/*/SKILL.md`, `NLP-SET Online.txt`, `implementation plan.md`, `USER_WALKTHROUGH.md`, and `README.md` to eliminate obsolete 45-second dwell-time claims. |

---

## 3. Agent Team Responsibilities & File Ownership Boundaries

| Agent Role | Subagent ID | Assigned Scope & File Ownership |
| :--- | :--- | :--- |
| **Lead Engineering / Coordination Agent** | `3de8eb98-14b7-49bf-9de7-3dc38345001b` | Architecture alignment, shared contract integration, `WORK_STATUS.md`, root `AGENTS.md`, `.agents/rules/*.md`, `.agents/skills/*/SKILL.md`, and final verification sign-off. |
| **Backend & Database Agent** | `8af00cac-3603-429c-83ef-b80580967f86` | `app/Services/IntegrityValidatorService.php`, `app/Services/DecoupledEvaluationService.php`, `app/Services/SecurityAuditService.php`, `app/Http/Requests/SubmitEvaluationRequest.php`, `app/Http/Controllers/Api/AdminRosterController.php`, `app/Http/Controllers/Api/StudentEvaluationController.php`, `database/migrations/2026_11_09_000002_add_schedule_metadata_to_evaluation_window_configs.php`. |
| **Frontend & UI/UX Agent** | `e13049c7-8d8d-4c94-8329-213e49085a0f` & `defc7938-582e-44b9-81ce-3c5cbf9a5d2f` | `resources/js/utils/evaluationMath.js`, `resources/js/stores/evaluation.js`, `resources/js/views/StudentQueueView.vue`, `resources/js/views/EvaluationFormView.vue`, `resources/js/views/FacultyDashboardView.vue`, `resources/js/views/ChairDashboardView.vue`, `resources/js/views/DeanDashboardView.vue`, `resources/js/views/AdminRosterView.vue`. |
| **Research & Thesis Writing Agent** | `a8335429-ebe9-45c5-a7a7-9820b96ecdb1` | `NLP-SET Online.txt`, `implementation plan.md`, `USER_WALKTHROUGH.md`, `README.md`. |
| **Security, Privacy, & QA Reality Checker** | `24b2ad26-89f8-4b82-b308-b153dc0ec513` | `tests/Feature/EvaluaTechBackendSuiteTest.php`, `tests/frontend/evaluatech.spec.js`, `analytics/tests/test_engine.py`, `impeccable detect`, and live Playwright desktop ($1366\times768$) & mobile ($375\times812$) audits. |

---

## 4. Shared Contracts

1. **Timing & Evaluation Window Contract (`/api/evaluation-window`, `/api/admin/evaluation-window`, `/api/student/evaluations/submit`):**
   - `min_dwell_seconds` defaults to `0` and is never used to block submissions.
   - `evaluation_window_configs` and window responses include:
     - `academic_term_id`, `term_code`, `term_name`, `academic_year`
     - `status`: `'OPEN' | 'SUSPENDED' | 'CLOSED'`
     - `window_state`: `'OPEN' | 'NOT_YET_OPEN' | 'SUSPENDED' | 'CLOSED' | 'EXPIRED'`
     - `evaluation_open`: boolean (`true` iff `status === 'OPEN'` and current time is within `[starts_at, ends_at]`)
     - `starts_at`: ISO/datetime string or `null`
     - `ends_at`: ISO/datetime string or `null`
     - `timezone`: `'Asia/Manila'`
     - `last_window_action`: `'OPEN' | 'EXTEND' | 'REOPEN' | 'SUSPEND' | 'RESUME' | 'CLOSE' | 'SCHEDULE'`
     - `participation_summary`: `{ total_enrolled_evaluations, completed_evaluations, pending_evaluations, completion_rate_pct }`
   - Server-side submission checks in `DecoupledEvaluationService::submitEvaluation()`:
     - Rejects before `starts_at` with HTTP `422` (`code: 'EVALUATION_WINDOW_NOT_YET_OPEN'`).
     - Rejects when `status === 'SUSPENDED'` with HTTP `422` (`code: 'EVALUATION_WINDOW_SUSPENDED'`, `window_status: 'SUSPENDED'`).
     - Rejects after `ends_at` with HTTP `422` (`code: 'EVALUATION_WINDOW_EXPIRED'`, `window_status: 'EXPIRED'`).
     - Rejects when `status === 'CLOSED'` with HTTP `422` (`code: 'EVALUATION_WINDOW_CLOSED'`, `window_status: 'CLOSED'`).
     - Accepts valid completed evaluations immediately (`0s+`) when `evaluation_open === true`.
2. **Four User Groups / Five Permission Roles Contract:**
   - Group 1: **Students** (`student` role -> `/queue`, `/evaluate/:sectionId`, `/advocacy`)
   - Group 2: **Faculty / Professors** (`faculty` role -> `/faculty`)
   - Group 3: **Academic Supervisors** (`chair` role -> `/chair` scoped strictly to assigned `department_id`; `dean` role -> `/dean` scoped to college and its departments)
   - Group 4: **University Registrar / Administrators** (`admin` role -> `/admin`)

---

## 5. Synchronization Checkpoints

- [x] **Checkpoint 1:** Repository, instruction, and code inspection completed; `WORK_STATUS.md` established.
- [x] **Checkpoint 2:** Backend timing policy removal & administrator window boundary enforcement implemented (`33 / 33 PASS`, 814 assertions).
- [x] **Checkpoint 3:** Frontend 4-user-group UX refinements, immediate submission workflow, and visible/expandable guidance implemented (`40 / 40 PASS`, 0 errors).
- [x] **Checkpoint 4:** Agent instructions (`AGENTS.md`, `.agents/`), thesis manuscript (`NLP-SET Online.txt`), and walkthrough documentation synchronized.
- [x] **Checkpoint 5:** All 119 automated tests, repository hygiene & syntax audit, and Playwright desktop/mobile visual verification completed (`100% PASS`).

---

## 6. Multi-Tier Verification Evidence Log

| Verification Tier | Command Executed | Executed Units | Pass Count | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Backend PHPUnit Suite** | `php scripts/run-backend-tests.php` | 33 tests (814 assertions) | 33 / 33 | **100% PASS** |
| **Frontend DOM & UX Suite** | `npm.cmd test` | 40 test groups | 40 / 40 | **100% PASS** |
| **Python NLP Analytics Suite** | `python -m unittest discover -s analytics/tests -p "test_*.py" -v` | 46 unit tests | 46 / 46 | **100% PASS** |
| **Hygiene & Syntax Audit** | `php -l` & UTF-8 BOM scan | 48 PHP files, 191 sources | 100% clean | **100% PASS** |
| **Vite Production Bundle** | `npm.cmd run build` | 72 modules | Zero `.map` files | **100% PASS** |
| **Unified Verification Matrix** | `powershell -ExecutionPolicy Bypass -File scripts/verify-all.ps1` | 119 automated units | 119 / 119 | **100% PASS** |

