# EvaluaTech — Pamantasan ng Lungsod ng Pasig (PLP) Cross-Tier Architectural & Contract Audit

**Date**: 2026-11-08
**Reviewer**: Code Reviewer (`agency-code-reviewer`)
**Institution**: Pamantasan ng Lungsod ng Pasig (`https://plpasig.edu.ph/`)
**Audit Verdict**: **PASS (Zero Blockers — Full Cross-Tier Contract & Security Invariant Alignment)**

---

## 1. Executive Summary

A read-only cross-tier architectural and caller-trace review was executed across the Laravel 12 PHP domain services and standalone API dispatcher (`app/Services/DecoupledEvaluationService.php`, `app/Services/IntegrityValidatorService.php`, `app/Services/AnalyticsClientService.php`, `public/index.php`), the Vue.js 3 SPA and live ES Module portal runtime (`resources/js/utils/evaluationMath.js`, `resources/js/views/EvaluationFormView.vue`, `resources/js/plp-live-portal.js`, `resources/css/app.css`), and the Python `calamanCy` + VADER microservice (`analytics/engine.py`, `analytics/app.py`). All cross-tier JSON contracts, statistical formulas, brand color tokens, and zero-knowledge security invariants are synchronized.

---

## 2. Pamantasan ng Lungsod ng Pasig (PLP) Visual Identity & Color Token Alignment

| Layer / File | Institutional Identity & Token Verification | Status |
| :--- | :--- | :--- |
| `resources/css/app.css` | Defines `--plp-green-900: #0b4f26`, `--plp-green-800: #14532d`, `--plp-gold-500: #eab308`, `--plp-gold-400: #facc15`, `--bg-header: #0b4f26`, `--brand-primary: #0b4f26`, `--brand-primary-hover: #14532d`, and `--brand-gold: #eab308`. Includes responsive rules for mobile ($375\times812\text{ px}$ via `@media (max-width: 640px)`) and desktop ($1366\times768\text{ px}$ via `max-width: 1320px`). | **ALIGNED** |
| `resources/js/plp-live-portal.js` | Renders the PLP institutional header ribbon (`PAMANTASAN NG LUNGSOD NG PASIG`, `plpasig.edu.ph`), custom SVG seal (`#0B4F26`, `#14532D`, `#EAB308`, `#FACC15`), `@plpasig.edu.ph` five-role profiles (`student`, `faculty`, `chair`, `dean`, `admin`), and PLP Digital Examination Permit cards. | **ALIGNED** |
| `resources/js/views/EvaluationFormView.vue` | Displays the `PAMANTASAN NG LUNGSOD NG PASIG • 5 Clusters × 5-Point Likert Scale` header badge, `calamanCy` model attribution, and live authenticity metrics strip. | **ALIGNED** |
| `public/index.php` | Exposes `/api/health` and `/api/security/vault-audit` returning `institution: "PAMANTASAN NG LUNGSOD NG PASIG"` and `reference: "https://plpasig.edu.ph/"`. | **ALIGNED** |

---

## 3. 5-Cluster × 5-Point Likert Scale (25 Items) & 4-to-5 Professor Qualitative Questions Contract Trace

### 3.1 Five Pedagogical Clusters (25 Likert Items, Scale 1..5)

| Cluster # | Canonical Cluster Name | Vue / JS (`evaluationMath.js`) | Laravel PHP (`IntegrityValidatorService` & `AnalyticsClientService`) | Python NLP (`analytics/engine.py`) |
| :--- | :--- | :--- | :--- | :--- |
| **Cluster 1** | `Pedagogical Delivery` | `PD-1`..`PD-5` (Items 1–5, 5-pt Likert) | `CLUSTER_NAMES[0]` / `TAG_PEDAGOGICAL_DELIVERY` | `FIVE_LIKERT_CLUSTERS[0]` / `PEDAGOGICAL_TAGS[0]` |
| **Cluster 2** | `Assessment & Grading` | `AG-1`..`AG-5` (Items 6–10, 5-pt Likert) | `CLUSTER_NAMES[1]` / `TAG_ASSESSMENT_GRADING` | `FIVE_LIKERT_CLUSTERS[1]` / `PEDAGOGICAL_TAGS[1]` |
| **Cluster 3** | `Classroom Management` | `CM-1`..`CM-5` (Items 11–15, 5-pt Likert) | `CLUSTER_NAMES[2]` / `TAG_CLASSROOM_MANAGEMENT` | `FIVE_LIKERT_CLUSTERS[2]` / `PEDAGOGICAL_TAGS[2]` |
| **Cluster 4** | `Professional Conduct` | `PC-1`..`PC-5` (Items 16–20, 5-pt Likert) | `CLUSTER_NAMES[3]` / `TAG_PROFESSIONAL_CONDUCT` | `FIVE_LIKERT_CLUSTERS[3]` / `PEDAGOGICAL_TAGS[3]` |
| **Cluster 5** | `Student Support & Mentorship` | `SSM-1`..`SSM-5` (Items 21–25, 5-pt Likert) | `CLUSTER_NAMES[4]` / `TAG_STUDENT_SUPPORT_MENTORSHIP` | `FIVE_LIKERT_CLUSTERS[4]` / `PEDAGOGICAL_TAGS[4]` |

- **Ratings Array Contract**: `EvaluationFormView.vue` and `plp-live-portal.js` initialize and submit 25 integer ratings (`1..5`). `IntegrityValidatorService::validateRatingsArray()` accepts both `FIVE_CLUSTER_QUESTION_COUNT = 25` and backward-compatible `DEFAULT_QUESTION_COUNT = 20`, ensuring zero regressions across legacy 20-item tests and 25-item 5-cluster submissions.
- **Per-Cluster Mean Calculation**: `calculateClusterMeans()` in `evaluationMath.js` and `calculateClusterScores()` in `IntegrityValidatorService.php` both slice ratings in 5-item blocks (`idx * 5` to `(idx + 1) * 5`) and map them to the exact 5 canonical cluster names.

### 3.2 Structured Professor Qualitative Questions (4 Default, Max 5 Cap)

| Question # | Field Key (Request Payload) | Normalized Output Key | Cross-Tier Validation & NLP Processing |
| :--- | :--- | :--- | :--- |
| **Q1** | `three_words` (or `q1_three_words`) | `q1_three_words` | Validated for 3 descriptor words; tokenized, lemmatized, and polarity-scored in PHP (`IntegrityValidatorService`, `AnalyticsClientService`), JS (`validateProfessorQuestions`), and Python (`analyze_professor_questions`). |
| **Q2** | `room_for_improvements` (or `q2_room_for_improvements`) | `q2_room_for_improvements` | Dynamically indexed into lettered sub-items (`a.`, `b.`, `c.`...) with individual polarity, valence, and pedagogical cluster tags in PHP, JS, and Python. |
| **Q3** | `teaching_strengths` (or `q3_teaching_strengths`) | `q3_teaching_strengths` | Narrative string analyzed for sentiment polarity and pedagogical cluster tag. |
| **Q4** | `recommendations` (or `q4_recommendations`) | `q4_recommendations` | Narrative string analyzed for actionable course recommendations. |
| **Q5 (Optional)** | `additional_observation` (or `q5_additional_observation`) | `q5_additional_observation` | Optional 5th question toggled in UI (`showFifthQuestion`). Both PHP (`IntegrityValidatorService::MAX_PROFESSOR_QUESTIONS = 5`) and Python (`MAX_PROFESSOR_QUESTIONS = 5`) reject payloads exceeding 5 questions with `MAX_PROFESSOR_QUESTIONS_EXCEEDED` (`HTTP 422`). |

---

## 4. `calamanCy` + VADER Sentiment Polarity Alignment

- **Engine & Model Metadata**: All three tiers reference `calamanCy` (`https://github.com/ljvmiranda921/calamanCy`), model `tl_calamancy_md-0.1.0`, and engine identifier `calamanCy+VADER`.
- **Polarity & Valence Thresholds**: Continuous polarity $P \in [-1.0, +1.0]$ maps to identical institutional valence bands across Python (`classify_valence`), PHP (`AnalyticsClientService::classifyValence`), and JS (`classifyCommentPreview`):
  - `Positive`: $P \ge +0.05$
  - `Neutral`: $-0.05 < P < +0.05$
  - `Negative`: $P \le -0.05$
- **Chapter III Canonical Benchmark Calibrations**:
  - *"Explains complex algorithms very clearly and provides great practical code examples."* $\to$ $P = +0.78$, `Positive`, `Pedagogical Delivery` (verified identical in Python, PHP fallback, and JS preview).
  - *"Takes over a month to return quiz scores and provides no helpful feedback."* $\to$ $P = -0.62$, `Negative`, `Assessment & Grading` (verified identical in Python, PHP fallback, and JS preview).
  - *"Arrives to morning lectures 15 minutes late consistently."* $\to$ $P = -0.45$, `Negative`, `Classroom Management` (verified identical in Python, PHP fallback, and JS preview).
- **Tagalog / Taglish Morphosyntactic Support**: Both `analytics/engine.py` and `AnalyticsClientService.php` include Tagalog/Taglish sentiment lexicons (`malinaw`, `mahusay`, `magaling`, `matulungin`, `magulo`, `nakakalito`, `mabagal`, `nahuhuli`, `masungit`), intensifiers (`sobrang`, `napaka`), negations (`hindi`, `walang`), and 5-cluster Tagalog domain keywords (`konsultasyon`, `gabay`, `pagsusulit`, `grado`).
- **Circuit-Breaker Resilience**: `AnalyticsClientService::callFlaskEndpoint()` enforces a bounded `0.15s` socket connect check and `1.5s` HTTP stream timeout, opening static `$circuitOpen = true` on failure and falling back to `analyzeLocally()` so student submissions and clearance tokens never block when the Python daemon is offline.

---

## 5. Non-Negotiable Security & Integrity Invariants Verification

| Invariant | Implementation & Caller Trace | Verification Result |
| :--- | :--- | :--- |
| **Zero-Knowledge `evaluation_vault` Decoupling** | `DecoupledEvaluationService::initializeSchema()` creates `evaluation_vault` with columns `id`, `course_section_id`, `faculty_id`, `academic_term_id`, `ratings_json`, `mean_score`, `rating_variance`, `is_straight_lined`, `dwell_time_seconds`, `comment_text`, `comment_hash`, `sentiment_polarity`, `sentiment_valence`, `pedagogical_tag`, and coarse `recorded_date` (`Y-m-d`). `IntegrityValidatorService::stripIdentifyingMetadata()` strips `student_id`, `user_id`, `clearance_id`, `ip_address`, `session_id`, `user_agent`, `email`, `institutional_id`, `submission_key`, `start_token`, and `clearance_token`. `verifyVaultAnonymity()` audits live table columns via `/api/security/vault-audit`. | **PASS (Zero Leaks)** |
| **Submission Idempotency & Concurrency (`FT-02`)** | `DecoupledEvaluationService::submitEvaluation()` checks `findClearanceByKeyOrStudentSection()` before and inside `beginTransaction()` with `SELECT * FROM evaluation_clearances ... FOR UPDATE` (on MySQL) and a `catch (Throwable)` race handler, returning `IDEMPOTENT_REPLAY` (`HTTP 200`) and the original `clearance_token` without duplicating vault records. | **PASS** |
| **45-Second Speed-Run Guard** | Client-side `isDwellTimeValid(dwellSeconds, ratings.length, 45)` blocks premature submission in `EvaluationFormView.vue` and `plp-live-portal.js`. Server-side `IntegrityValidatorService::validateDwellTime()` and HMAC-SHA256 `verifyStartToken()` reject submissions under 45 seconds with `SPEED_RUN_DETECTED` (`HTTP 422`). | **PASS** |
| **Straight-Lining Guard ($\sigma^2 = 0$)** | Population variance $\sigma^2 = \frac{1}{N}\sum(r_i - \mu)^2$ is computed across all 25 Likert items. When $\sigma^2 = 0.0$ and `confirm_uniform = false`, server returns `ZERO_VARIANCE_CONFIRMATION_REQUIRED` (`HTTP 422`) and UI opens `StraightLineModal`. When `confirm_uniform = true`, submission succeeds and persists `is_straight_lined = 1`. | **PASS** |
| **Double-Blind Faculty Visibility Lock** | `DecoupledEvaluationService::getFacultySectionReport()` enforces `canFacultyViewSection($gradesSubmitted, $registrarLock)` (`term_grades_submitted && registrar_lock`), returning `HTTP 403 DOUBLE_BLIND_LOCKED` if either flag is false, and `HTTP 403 FORBIDDEN_FACULTY_SECTION` if `faculty_id` does not match the section instructor. | **PASS** |
| **Dean Exception Watchlist & CSV Sanitization** | `DecoupledEvaluationService::shouldFlagOnWatchlist()` and `evaluateWatchlistFlag()` flag instructors when `mean_score < 3.50` OR `negative_sentiment_pct > 35.0`. `IntegrityValidatorService::sanitizeCsvCell()` and `evaluationMath.js::sanitizeCsvCell()` prefix leading `=`, `+`, `-`, `@` characters with a single quote (`'`). | **PASS** |

---

## 6. Review Taxonomy Findings (Blockers, Suggestions, Nits)

- **Blockers (🔴 Must Fix Before Hand-off)**:
  - **None (0 Blockers)**. All cross-tier contracts, security guards, and PLP institutional requirements are satisfied.
- **Suggestions (🟡 Should Fix in Future Iterations)**:
  - **Port Configuration Alignment**: `AnalyticsClientService::__construct()` defaults to `http://127.0.0.1:5001` whereas `analytics/app.py` defaults to port `5005` when launched directly (`NLP_PORT=5005`). Aligning the default port constant or environment variable (`NLP_SERVICE_URL`) avoids relying on the local PHP circuit-breaker fallback when running the Python daemon on its default port.
- **Nits (💭 Optional Polish)**:
  - **Duplicate Action Branches in `resources/js/plp-live-portal.js`**: Lines 1233–1279 repeat the `else if` branches for `reset-plp-demo`, `set-sus-item`, and `run-vault-audit` already handled at lines 1187–1232. These duplicate branches are unreachable dead code with zero functional impact.
