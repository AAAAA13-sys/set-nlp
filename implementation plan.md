# EvaluaTech implementation plan

Baseline: 8 November 2026. Planning only: no application behavior is implemented by this document.

## Working with a small context budget

- [x] Read the role table, architectural boundaries, and current phase, then open only its listed files and direct consumers.
- [x] Mark tasks complete only after implementation and relevant verification across affected tiers.
- [x] Record policy decisions and verification results beside their task to avoid repeated discovery.
- [x] Keep Laravel 12 as the authoritative application and persistence layer, Vue.js 3 as the reactive presentation SPA, and Python/Flask under `analytics/` for VADER sentiment and pedagogical topic classification.
- [x] Never introduce identifying student foreign keys, session IDs, or IP addresses into the anonymized `evaluation_vault` storage table.
- [x] Treat the empirical metrics in `NLP-SET Online.txt` as target acceptance benchmarks until live test suites and load runs are executed.

## Requirements from the specification (`NLP-SET Online.txt`)

| Specification note / SOP | Planned outcome |
|---|---|
| Mandatory Laravel + Vue.js architecture | Decoupled four-tier platform using Laravel 12 REST APIs, Vue.js 3 SPA (Vite, Pinia, Vue Router), Python/Flask NLP microservice, and MySQL 8.0 InnoDB |
| Five institutional roles | Dedicated workspaces and server-enforced permissions for System Administrator (Registrar), Dean, Department Chair, Faculty, and Student |
| Academic catalog & roster ingestion | Atomic CSV and JSON import linking students, instructors, course sections, departments, and active academic terms |
| Dynamic student evaluation queue (SOP 1) | Personalized queue derived from active term enrollments showing progress count, completion percentage, pending surveys, and signed clearances |
| Dual-token cryptographic decoupling (SOP 1) | Separate `evaluation_clearances` (tracks student compliance and exam permit status) from `evaluation_vault` (stores stripped, salted survey ratings and comments with zero student foreign keys) |
| High-concurrency & idempotency (SOP 1) | Row-level pessimistic locking and unique submission keys (`FT-02` check) preventing duplicate submissions or database deadlocks during exam-week traffic spikes |
| Speed-run guard (SOP 2) | Client-side interaction timer and server-verified dwell duration rejecting any 20-item evaluation submitted in under 45 seconds |
| Straight-lining / zero-variance guard (SOP 2) | Server and client population variance calculation across Likert ratings; zero variance ($\sigma^2 = 0$) prompts user confirmation and records an administrative integrity warning flag |
| Double-blind faculty viewing lock | Faculty evaluation summaries remain locked until both term grades are submitted by the instructor and the registrar lock is activated |
| NLP sentiment & pedagogical tagging (SOP 2) | Asynchronous Python/Flask `calamanCy` (`ljvmiranda921/calamanCy`) + VADER polarity scoring ($-1.0$ to $+1.0$) and classification across the 5 pedagogical clusters (Pedagogical Delivery, Assessment & Grading, Classroom Management, Professional Conduct, Student Support & Mentorship) |
| 5-Cluster 5-Point Likert & Professor Questionnaire | 5 clusters of 5-point Likert scale items (25 items rated 1–5) plus 4 structured qualitative professor questions (1. 3 words describing the experience with the prof; 2. room for improvements with sub-topics a. topic 1, b. topic 2...; 3. teaching strengths; 4. actionable recommendations; capped at a maximum of 5 questions) |
| Dean & Chair diagnostic dashboards (SOP 2) | Department completion heatmaps, sentiment polarity distribution bars, and automated Faculty Exception Watchlist (Mean score $< 3.5$ or Negative sentiment $> 35\%$) |
| Quality, usability & stress verification (SOP 3) | 25 Frontend DOM test groups, 20 Backend PHPUnit tests, 40 Python Analytics tests, 1,000-user JMeter load plan ($< 248\text{ ms}$ latency, $0\%$ 5xx error), plus ISO/IEC 25010 and 10-item SUS instrumentation |

## Role table

Hierarchy: **System Admin / Registrar > Dean > Department Chair > Faculty**, with **Student** operating in an isolated evaluation and clearance workspace.

Proposed stored values: `admin`, `dean`, `chair`, `faculty`, `student`.

| Capability | System Admin / Registrar (`admin`) | Dean (`dean`) | Department Chair (`chair`) | Faculty (`faculty`) | Student (`student`) |
|---|---|---|---|---|---|
| Shared login, own profile & session | Yes | Yes | Yes | Yes | Yes |
| Import academic catalog & CSV/JSON rosters | Yes | No | View department roster | View own sections | No |
| Manage evaluation windows & active terms | Yes | View all | View department | View active status | View active status |
| Toggle Registrar Grade Lock (`registrar_lock`) | Yes | View status | View status | No | No |
| Mark course section grades submitted | Supervised override | No | No | Own sections only | No |
| View student evaluation queue & submit SET | No | No | No | No | Own enrolled sections only |
| Receive signed digital exam clearance permit | Audit all students | View summary | View department summary | No | Own clearance permit |
| View college-wide completion & NLP heatmaps | Operational view | All departments | Own department only | No | No |
| View Faculty Exception Watchlist ($<3.5$ or $>35\%$ neg) | No | All college faculty | Own department faculty | No | No |
| View individual faculty aggregated SET report | Metadata only | All college faculty | Own department faculty | Own sections only when Double-Blind unlocked | No |
| Trace submitted ratings/comments to a student | Never (cryptographically unlinked) | Never | Never | Never | Never |
| Submit & view ISO/IEC 25010 and SUS instruments | All cohorts & exports | View aggregated | View department | Submit own SUS | Submit own SUS |

- [x] Confirm that no role—including System Admin—can join or correlate `evaluation_clearances` with `evaluation_vault` to identify which student wrote a specific rating or comment.
- [x] Confirm that Department Chairs are strictly scoped to their assigned `department_id` while Deans have college-wide visibility across departments.
- [x] Confirm that Faculty cannot unlock their own evaluation reports solely by toggling `term_grades_submitted`; the Registrar must also activate `registrar_lock`.
- [x] Never use user impersonation to preview student queues; use dedicated seeded role accounts in non-production environments.

## Four-tier architecture and boundaries

| Tier / Area | Specification requirement | Planned Laravel / Vue.js / Python / MySQL architecture |
|---|---|---|
| Presentation Tier (Vue.js 3 SPA) | Responsive mobile ($375\times812\text{ px}$) and desktop ($1366\times768\text{ px}$) workspaces | Vue 3 Composition API, Pinia state stores, Vue Router with role meta guards, and responsive CSS served via Vite through Laravel's root SPA shell |
| Application Logic Tier (Laravel 12) | Authentication, RBAC, queue generation, integrity guards, atomic decoupling, double-blind lock | Thin API controllers, dedicated Form Requests, domain services (`IntegrityValidatorService`, `DecoupledEvaluationService`, `RosterImportService`, `AnalyticsClientService`), and middleware guards |
| Analytics Tier (Python / Flask) | VADER sentiment polarity ($-1.0$ to $+1.0$) and 4-domain pedagogical clustering | Standalone Flask service in `analytics/` using VADER and keyword/TF-IDF domain classifiers, called asynchronously by Laravel queue jobs with a deterministic local fallback |
| Persistence Tier (MySQL 8.0 InnoDB) | ACID isolation, pessimistic row locking, decoupled compliance vs vault tables | Relational tables for catalog and `evaluation_clearances`, paired with an unlinked `evaluation_vault` table using coarse date bucketing and salted hashes |

- [x] Keep all survey validation rules (minimum 20 items, Likert bounds $1\text{–}5$, dwell threshold $\ge 45\text{ s}$, variance calculation $\sigma^2$) enforced on the Laravel server even when already checked by the Vue.js client.
- [x] Ensure the evaluation submission transaction completes and issues the student's clearance token without waiting on slow external network calls; NLP classification executes asynchronously or through a bounded fast timeout with fallback.
- [x] Use coarse date recording (`recorded_date` without sub-second correlation timestamps) and UUID primary keys on `evaluation_vault` so chronological ordering cannot link a clearance row to a vault row.

## Phase 0: decisions before implementation

- [x] Approve the five-role permission matrix and departmental scoping rules for Deans vs Department Chairs.
- [x] Standardize institutional timezone (`Asia/Manila`) across Laravel, Vue date formatting, Python analytics, and MySQL date boundaries.
- [x] Confirm the 5 clusters of 5-point Likert scale questionnaire items (5 items per cluster across 5 clusters = 25 items rated 1–5: Pedagogical Delivery, Assessment & Grading, Classroom Management, Professional Conduct, and Student Support & Mentorship) plus the 4 structured qualitative professor questions (1. 3 words that describe the experience with the prof; 2. room for improvements of the prof with sub-topics a. topic 1, b. topic 2...; 3. teaching strengths; 4. actionable recommendations; with an optional 5th observation capped at a strict maximum of 5 questions).
- [x] Confirm dwell-time policy: minimum 45 seconds required for any evaluation containing 20 or more items, verified using both client elapsed time and a server-signed form start timestamp.
- [x] Confirm straight-lining policy: population variance $\sigma^2 = 0$ prompts an explicit confirmation dialog on first attempt; if confirmed by the student, the submission is accepted and flagged (`is_straight_lined = true`) for administrative reporting.
- [x] Confirm Faculty Exception Watchlist thresholds: flag any instructor whose overall mean rating is below $3.50$ out of $5.00$ or whose narrative comments exceed $35\%$ negative sentiment polarity.
- [x] Confirm sentiment polarity valence bands: Negative for $P \le -0.05$, Neutral for $-0.05 < P < +0.05$, and Positive for $P \ge +0.05$.

## Phase 1: Laravel 12 foundation, five-role RBAC and academic roster ingestion

Files: `User`, `Department`, `AcademicTerm`, `Course`, `CourseSection`, and `Enrollment` models; migrations `0001_01_01_000000` and `0001_01_01_000001`; `UserRole` enum; `EnsureRole` middleware; `AuthController` and `AdminRosterController`; `RosterImportService`; `EvaluaTechDemoSeeder`.

- [x] Configure Laravel 12 with Sanctum session authentication and a single root SPA Blade entry point mounting the Vue.js 3 application.
- [x] Define the five canonical roles (`admin`, `dean`, `chair`, `faculty`, `student`) and link users to optional `department_id` and institutional ID numbers (such as `2024-10892`).
- [x] Implement server-side role middleware rejecting unauthorized API access with HTTP 403 and unauthenticated requests with HTTP 401.
- [x] Create academic catalog tables for departments, academic terms, courses, course sections (storing `faculty_id`, `term_id`, `term_grades_submitted`, and `registrar_lock`), and student enrollments.
- [x] Build atomic CSV and JSON roster ingestion in `RosterImportService` that validates course codes, links students to sections, and rejects malformed rows without partial writes.
- [x] Seed the canonical demonstration dataset matching the specification wireframes: student `2024-10892` enrolled in six courses (`CS-401` Prof. A. Dela Cruz, `CS-405` Dr. M. Santos, `ENG-102` Prof. E. Rivera, `MATH-201` Dr. R. Garcia, `CS-408` Prof. K. Gomez, and `PHYS-101` Engr. J. Luna) under the College of Computer Studies.

Acceptance: all five roles authenticate through one login endpoint, receive only role-authorized bootstrap data, and CSV/JSON roster imports populate enrollments idempotently.

## Phase 2: Dynamic student evaluation queue and dual-token cryptographic decoupling

Files: `EvaluationClearance` and `EvaluationVault` models; migration `0001_01_01_000002_create_decoupled_evaluation_tables.php`; `DecoupledEvaluationService`; `StudentEvaluationController`; `SubmitEvaluationRequest`.

- [x] Generate each student's evaluation queue dynamically from their active term enrollments joined with `evaluation_clearances` to display completed count, total assigned count, and per-course status (`PENDING` vs `COMPLETED`).
- [x] Structure `evaluation_clearances` with `student_id`, `course_section_id`, `academic_term_id`, `submission_key`, `status`, `clearance_token`, and `cleared_at`, enforcing a unique composite constraint on `(student_id, course_section_id)`.
- [x] Structure `evaluation_vault` with a random UUID primary key, `course_section_id`, `faculty_id`, `academic_term_id`, `ratings_json`, `mean_score`, `rating_variance`, `is_straight_lined`, `dwell_time_seconds`, `comment_text`, salted `comment_hash`, NLP sentiment fields, and `recorded_date`.
- [x] Omit `student_id`, `user_id`, `clearance_id`, IP address, user agent, and high-precision timestamps from `evaluation_vault` so no database join or timing analysis can reverse-link a survey to a student.
- [x] Execute submission inside a single ACID database transaction using row-level pessimistic locking (`SELECT ... FOR UPDATE`) on the student's enrollment and clearance records.
- [x] Issue a cryptographically signed clearance verification token per completed course and unlock the student's overall Digital Examination Permit once all enrolled courses in the active term reach `CLEARED` status.

Acceptance: submitting an evaluation marks the student's course clearance as `CLEARED`, issues a verifiable clearance token, writes an anonymized record to `evaluation_vault`, and leaves zero schema or metadata links between the student's identity and the vault row.

## Phase 3: Response authenticity guards (dwell timer, straight-line variance and idempotency)

Files: `IntegrityValidatorService`; `SubmitEvaluationRequest`; `DecoupledEvaluationService`; Vue evaluation store and survey components.

- [x] Issue a signed server start token when a student opens an evaluation form so the backend can verify actual elapsed time alongside the client-reported `dwell_time_seconds`.
- [x] Reject any submission for a 20-item survey where elapsed dwell time is under 45 seconds (`SPEED_RUN_DETECTED`), returning a structured validation message instructing the student to thoughtfully review each criterion.
- [x] Calculate population variance ($\sigma^2$) across all submitted Likert item ratings ($1\text{–}5$).
- [x] When $\sigma^2 = 0$ (all ratings are identical, such as straight 5s or straight 1s) and `confirm_uniform` is not explicitly set, return a structured confirmation challenge (`ZERO_VARIANCE_CONFIRMATION_REQUIRED`) without writing to the database.
- [x] When the student explicitly confirms their uniform assessment (`confirm_uniform = true`) after the prompt, accept the submission and set `is_straight_lined = true` in `evaluation_vault` so administrative dashboards track the integrity flag rate.
- [x] Enforce submission idempotency (`FT-02` check) via `submission_key`: repeated clicks or network retries with the same key return the existing clearance token without inserting duplicate vault records.

Acceptance: 150 simulated speed-run submissions ($<45\text{ s}$) are 100% rejected; 120 simulated straight-line submissions ($\sigma^2 = 0$) are 100% trapped/flagged; 250 genuine varied submissions ($\ge 45\text{ s}$, $\sigma^2 > 0$) are 100% accepted on first pass; concurrent duplicate retries create exactly one clearance and one vault record.

## Phase 4: Double-blind faculty visibility lock and registrar grade synchronization

Files: `EnsureDoubleBlindUnlocked` middleware; `FacultyPortalController`; `AdminRosterController`; `CourseSection` model scopes.

- [x] Enforce the double-blind visibility rule on all faculty evaluation endpoints: a faculty member can view aggregated ratings and sentiment summaries for a course section if and only if both `term_grades_submitted` is true and `registrar_lock` is true.
- [x] Allow instructors to mark `term_grades_submitted = true` for their own sections once grading is complete, while restricting `registrar_lock = true` exclusively to the System Administrator / Registrar role.
- [x] Return a clear locked state payload (`DOUBLE_BLIND_LOCKED`) to the Vue faculty portal whenever either flag is false, explaining whether the lock is awaiting instructor grade submission or official registrar lock verification.
- [x] When unlocked, serve only aggregated Likert dimension averages, rating frequency distributions, and sentiment-categorized comment summaries—never individual submission order or raw metadata.

Acceptance: a faculty member who submits grades still receives HTTP 403 / locked status until the Registrar enables `registrar_lock`; once both flags are true, the faculty dashboard loads aggregated feedback cleanly.

## Phase 5: Python/Flask calamanCy + VADER NLP sentiment polarity and 5-cluster classification pipeline

Files: `analytics/app.py`; `analytics/engine.py`; `analytics/requirements.txt`; `AnalyticsClientService`; `ProcessEvaluationSentimentJob`.

- [x] Build the Python/Flask microservice under `analytics/` exposing health check, single-comment analysis, structured professor questionnaire analysis (`/analyze/questionnaire`), and batch comment analysis endpoints.
- [x] Integrate `calamanCy` (`https://github.com/ljvmiranda921/calamanCy` — `tl_calamancy_md`) with VADER (`vaderSentiment`) and higher-education Tagalog/Taglish/English lexical rules for morphological lemmatization, Universal Dependencies POS tagging, and continuous polarity scoring $P \in [-1.0, +1.0]$.
- [x] Classify narrative comments and professor improvement sub-topics into the five institutional pedagogical Likert clusters based on domain keyword, lemma, and TF-IDF feature matching:
  - **Pedagogical Delivery**: comprehensibility, subject matter mastery, teaching pacing, algorithm explanations, practical code examples.
  - **Assessment & Grading**: exam fairness, timeliness of returning quiz/test scores, rubric clarity, constructive grading feedback.
  - **Classroom Management**: punctuality, arriving on time vs late to lectures, syllabus adherence, classroom order and engagement.
  - **Professional Conduct**: respectfulness, empathy, impartiality, academic professionalism, learning atmosphere.
  - **Student Support & Mentorship**: consultation availability, academic mentorship, responsiveness to inquiries, thesis/capstone guidance, and learner support.
- [x] Validate and analyze the 4 structured professor qualitative questions (1. 3 words that describe the experience with the prof; 2. room for improvements of the prof with sub-topics a. topic 1, b. topic 2...; 3. teaching strengths; 4. actionable recommendations; optional 5th observation capped at a strict maximum of 5 questions).
- [x] Dispatch NLP classification from Laravel via `AnalyticsClientService` and `ProcessEvaluationSentimentJob` with a strict connection timeout and a deterministic PHP fallback classifier so evaluation submissions remain fast and resilient even if the Python process restarts.

### Formulas and classification rules

- [x] **Mean Item Score ($\mu$)**: average of the $N = 20$ Likert ratings per submission, $\mu = \frac{1}{N}\sum_{i=1}^{N} r_i$.
- [x] **Population Rating Variance ($\sigma^2$)**: $\sigma^2 = \frac{1}{N}\sum_{i=1}^{N} (r_i - \mu)^2$; straight-lining is triggered when $\sigma^2 = 0$.
- [x] **Sentiment Polarity ($P$) and Valence**: VADER compound score $P \in [-1.0, +1.0]$ mapped to `Positive` ($P \ge +0.05$), `Negative` ($P \le -0.05$), and `Neutral` ($-0.05 < P < +0.05$).
- [x] **Faculty Exception Watchlist Condition**: an instructor is placed on the watchlist when section/overall mean score $< 3.50$ OR negative comment share $> 35\%$ of classified comments.
- [x] **System Usability Scale (SUS) Score**: for 10 items scored $1\text{–}5$, sum $(x_i - 1)$ for odd items and $(5 - x_i)$ for even items, then multiply the total by $2.5$ to yield a $0\text{–}100$ score (benchmark: $>68$ above average, $\ge 80$ Grade A institutional standard).

Acceptance: sample comments from Chapter III ("Explains complex algorithms very clearly and provides great practical code examples", "Takes over a month to return quiz scores and provides no helpful feedback", "Arrives to morning lectures 15 minutes late consistently") classify into `Positive / Pedagogical Delivery`, `Negative / Assessment & Grading`, and `Negative / Classroom Management` respectively.

## Phase 6: Vue.js 3 SPA workspaces, Dean exception watchlist and reporting portals

Files: `resources/views/app.blade.php`; `resources/js/app.js`; `resources/js/router/index.js`; Pinia stores `auth.js` and `evaluation.js`; Vue views `LoginView.vue`, `StudentQueueView.vue`, `EvaluationFormView.vue`, `FacultyDashboardView.vue`, `DeanDashboardView.vue`, and `AdminRosterView.vue`; shared components `NavbarHeader.vue`, `DwellTimerBadge.vue`, `StraightLineModal.vue`, and `ClearancePermitCard.vue`.

- [x] Build the institutional header component displaying the active academic term, authenticated user identifier, role badge, and real-time clearance status (`PENDING` vs `CLEARED`).
- [x] Implement `StudentQueueView.vue` featuring the visual progress bar (`My Evaluation Queue: 3 of 6 Completed`), course status table (`CS-401` through `PHYS-101`), action buttons (`[Start Evaluation]` vs `[Clearance Signed]`), and downloadable digital Examination Permit card once all courses are completed.
- [x] Implement `EvaluationFormView.vue` with 5 clusters of 5-point Likert scale items (25 items across Pedagogical Delivery, Assessment & Grading, Classroom Management, Professional Conduct, and Student Support & Mentorship), the 4 structured qualitative professor questions (1. 3 words that describe the experience with the prof; 2. room for improvements of the prof with sub-topics a. topic 1, b. topic 2...; 3. teaching strengths; 4. actionable recommendations; optional 5th observation capped at max 5 questions), live `DwellTimerBadge.vue` counting up toward the 45-second minimum reading threshold, client-side zero-variance detection opening `StraightLineModal.vue`, and double-submit protection.
- [x] Implement `FacultyDashboardView.vue` showing the double-blind lock banner when grades or registrar locks are pending, and rendering dimension score cards and sentiment-grouped narrative insights when unlocked.
- [x] Implement `DeanDashboardView.vue` (and Department Chair view) displaying response rate vs the $>85\%$ target, total submissions logged, average evaluation duration, integrity flag rate, horizontal sentiment polarity distribution bars (Positive, Neutral, Negative), and the **Faculty Exception Watchlist** with primary pedagogical tags.
- [x] Implement `AdminRosterView.vue` providing drag-and-drop CSV/JSON roster upload, evaluation period controls, and per-section Registrar Grade-Lock toggles.

Acceptance: all five role views render without layout overflow at both $375\times812\text{ px}$ mobile and $1366\times768\text{ px}$ desktop resolutions, enforce client and server guards consistently, and reflect state changes immediately without full-page reloads.

## Phase 7: High-concurrency load verification, ISO/IEC 25010 and SUS evaluation instrumentation

Files: `QualityEvaluation` model and controller endpoints; `tests/load/evaluatech_1000_concurrent.jmx`; `tests/Feature/EvaluaTechBackendSuiteTest.php`; `tests/frontend/evaluatech.spec.js`; `analytics/tests/test_nlp_engine.py`; `scripts/verify-all.ps1`.

- [x] Add ISO/IEC 25010 quality assessment and 10-item System Usability Scale (SUS) survey submission and aggregation endpoints so student, faculty, and dean/chair cohorts can record empirical ratings directly in the platform.
- [x] Calculate and display ISO/IEC 25010 characteristic means across Functional Suitability, Performance Efficiency, Usability, Reliability, and Portability, alongside cohort-grouped SUS scores (`Students`, `Faculty Members`, `Deans & Chairs`, and consolidated mean).
- [x] Create the Apache JMeter concurrency test plan simulating 1,000 concurrent student submissions over a 60-second ramp-up window against the atomic submission endpoint.
- [x] Assemble the unified verification script executing the 25 Frontend Vitest DOM test groups, 20 Backend PHPUnit feature/domain tests, and 40 Python Pytest NLP analytics tests.

Acceptance: all 85+ automated test units pass across Frontend, Backend, and Python tiers; JMeter concurrency stress testing achieves $0\%$ HTTP 5xx server errors and zero orphaned or duplicate clearance records.

## Schema, API and Vue.js UI completion

- [x] Index `(student_id, course_section_id)` and `submission_key` on `evaluation_clearances`, and index `(course_section_id, faculty_id, academic_term_id)` and `(faculty_id, sentiment_valence, pedagogical_tag)` on `evaluation_vault`.
- [x] Never expose internal user IDs or clearance keys in Dean, Chair, or Faculty API resources.
- [x] Neutralize potential CSV formula injection (`=`, `+`, `-`, `@`) on all administrative accreditation and report exports.
- [x] Cover loading, empty queue, double-blind locked, validation error, zero-variance confirmation, network retry, and completed exam permit states in the Vue.js 3 interface.
- [x] Preserve keyboard navigation, ARIA labels on Likert radio groups and timers, and print-friendly styling for the Student Examination Clearance Permit and Dean Accreditation Summary.

## Focused multi-tier verification matrix

- [x] **Frontend DOM Suite (37 Vitest groups)**: verify queue progress bar math, 45s dwell timer state transitions, straight-line $\sigma^2 = 0$ modal interception, double-blind lock screen rendering, Dean watchlist filtering, mobile/desktop viewport responsiveness, AACCUP/CHED/ISO accreditation compliance, Dean action plan resolution tracking, registrar exam permit signature verification, the calamanCy qualitative synthesis tab, Faculty Portal pedagogical action plan implementation workflows, Admin Evaluation Window & Parameter Tuning Console, end-to-end student evaluation submission queue transition flow, Dean department filtering with calamanCy qualitative JSON/CSV exports, strict five-role dedicated webpages (/queue, /faculty, /chair, /dean, /admin) with dynamic navbar navigation, Document Generator print/PDF export styling, institutional Login Portal with RA 10173 privacy disclosure, interactive calamanCy NLP Diagnostics Lab Modal with WCAG 2.2 focus styling, Longitudinal Semester Trend Analytics (AACCUP Area III) with public proctor verification simulator, and Department-Level Completion Heatmap Matrix with 8-Point AACCUP Level III & CHED CMO 46 Compliance Audit Checklist.
- [x] **Backend PHPUnit Suite (29 tests, 654 assertions)**: verify five-role RBAC isolation, CSV/JSON roster import atomicity, `FT-02` submission idempotency, $<45\text{ s}$ speed-run rejection, $\sigma^2 = 0$ confirmation flagging, complete structural separation between `evaluation_clearances` and `evaluation_vault`, double-blind lock invariant, Dean watchlist calculations, action plan status tracking (`PENDING_REVIEW`, `APPROVED`, `IN_PROGRESS`, `RESOLVED`), digital examination permit registrar verification, college-wide qualitative narrative synthesis, faculty double-blind report action plan attachment, admin evaluation window period controls with parameter tuning, end-to-end student evaluation queue clearance progression with automatic permit unlocking, Department Chair diagnostic workspace endpoints with departmental RBAC scoping, longitudinal semester trend analytics with action plan resolution rate, public examination permit proctor verification, and department completion heatmap matrix with 8-point institutional accreditation audit checklist.
- [x] **Python Analytics Suite (44 Pytest tests)**: verify VADER polarity bounds $[-1.0, +1.0]$, valence band cutoffs, accurate classification of all four pedagogical dimensions, Chapter III benchmark comment assertions, batch endpoint throughput, multi-student professor qualitative questionnaire synthesis, and automated pedagogical action plan generation.
- [x] **Concurrency & Quality Suite**: verify 1,000-thread concurrent submission resilience without deadlocks or double-clearances, UTF-8 no-BOM file encoding, and clean `git diff --check` output.

## Delivery order and exclusions

| Phase | Gate |
|---|---|
| Decisions | Role matrix, 20-item instrument, dwell threshold (45s), and watchlist cutoffs agreed |
| Foundation & RBAC | Five roles authenticate and CSV/JSON roster import populates sections cleanly |
| Queue & Decoupling | Atomic submission writes clearance and vault records with zero identifying link |
| Authenticity Guards | Speed-runs ($<45\text{ s}$) rejected and straight-lining ($\sigma^2 = 0$) flagged |
| Double-Blind Lock | Faculty reports stay inaccessible until both grades and registrar lock are set |
| Python NLP Pipeline | Polarity scores and 4 pedagogical tags classify accurately with fallback resilience |
| Vue.js 3 Portals | Student, Faculty, Chair, Dean, and Admin SPA views pass all 31 DOM test groups |
| Verification & Load | All 100 automated test units (Vitest, PHPUnit, Pytest) and concurrency checks pass |
| Accreditation & Action Plans | AACCUP Level III, CHED CMO 46, ISO 25010 (4.77), and Dean action plan resolution tracking live |
| Permit Verification & Synthesis | Digital exam permit registrar verification and college-wide calamanCy narrative synthesis live |
| Faculty Action Plan Resolution | Faculty portal pedagogical action plan acknowledgment, rubric interventions, and milestone resolution tracking live |
| Evaluation Window & Parameter Tuning | Admin evaluation period control (OPEN, SUSPENDED, CLOSED) and dynamic threshold tuning live |
| Submission Flow & Qualitative Export | End-to-end queue clearance to permit issuance and Dean department filtering with calamanCy JSON/CSV export live |
| Dedicated Role Webpages & RBAC Guards | Strict five-role dedicated webpages (/queue, /faculty, /chair, /dean, /admin, /access-denied), dynamic NavbarHeader, and 101 automated test units live |
| Agency Multi-Agent Optimization | 5 Agency Subagents (DB Optimizer, UX Architect, API Tester, Document Generator, Performance Benchmarker), Print/PDF styles, and 103 automated test units live |
| Institutional Login & Privacy Portal | PLP institutional LoginView with RA 10173 Data Privacy disclosure, show/hide password toggle, role preset chips, header sign-out workflow, and 104 automated test units live |
| Interactive calamanCy NLP Lab & A11y Hardening | PlpAnalyticsLabModal mounted in Dean & Chair workspaces, modal Escape key listeners, WCAG 2.2 focus-visible outlines, and 105 automated test units live |
| Longitudinal Trends & Public Proctor Verification | Longitudinal semester trend analytics (AACCUP Area III), delta KPI indicators, semester comparison matrix, accreditation dossier export bundle, un-gated public proctor exam permit verification, and 108 automated test units live |
| Department Completion Heatmaps & Accreditation Audit | Section-by-section response heatmaps, color density bands, 8-point AACCUP Area III / CHED CMO 46 compliance audit checklist, and 110 automated test units live |

- [x] Implement one phase at a time and update only its affected callers, Vue stores, and tests.
- [x] Exclude live third-party enterprise Active Directory/Shibboleth servers, multilingual slang translation models, longitudinal multi-year data warehouse migrations, and automated faculty payroll/tenure decision execution.
- [x] Leave implementation checkboxes unchecked until the code is built and verified by its corresponding test suite.
