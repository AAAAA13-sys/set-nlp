# EvaluaTech Institutional and Regulatory Policy Matrix

**Institution:** School AAAAA13
**System:** EvaluaTech / EvaluaTech (Automated Student Evaluation of Teachers)
**Document Version:** 1.0 (AY 2026–2027)
**Standard Classification Scheme:**
- **[A] Applicable Institutional or Regulatory Requirement:** Enacted Philippine laws, ratified municipal charters, or formally adopted institutional regulations directly binding on School AAAAA13.
- **[B] Practice Observed at Another University:** Documented practices from benchmark peer universities (e.g., UP Diliman, Miriam College, DLSU) informing architectural comparative analysis.
- **[C] Explicit Project-Owner Requirement:** Explicit functional, cryptographic, or operational requirements specified by the EvaluaTech research project owner.
- **[D] Proposed Engineering or Research Decision:** Architectural, technical, or algorithmic decisions formulated by the engineering and research team to fulfill requirements.

---

## 1. Regulatory Context & Institutional Governance

> **School AAAAA13** is a local university (LUC) established under City Ordinance No. 05, s. 1999 and operates under the authority of the Pamahalaang Lungsod ng Pasig and the School AAAAA13 Board of Regents.
> **CHED CMO No. 19, series of 2025** explicitly covers State Universities and Colleges (SUCs). While CMO No. 19 s. 2025 provides valuable regulatory benchmarks on faculty evaluation instruments, weighted supervisory reviews, and legitimate exemptions, its direct statutory applicability to LUCs depends on formal institutional adoption by the School AAAAA13 Board of Regents. Pending documented institutional adoption, CMO 19 s. 2025 is treated as an authoritative reference benchmark rather than an assumed automatic mandate.

---

## 2. Policy-to-Implementation Traceability Matrix

| Requirement / Standard | Source Document & Section | Class | Implemented Behavior in EvaluaTech | Verification Evidence | Institutional Status / Notes |
| :--- | :--- | :---: | :--- | :--- | :--- |
| **Strict Student Anonymity** | RA 10173 (Data Privacy Act of 2012); School AAAAA13 Data Privacy Policy | **[A]** | Physical separation of `evaluation_clearances` (student ID, signed token) from `evaluation_vault` (ratings, comments, zero student ID/IP/timestamp). | PHPUnit `test_04`, `test_05`; Zero foreign keys in `evaluation_vault`. | Full institutional and legal compliance under RA 10173. |
| **Mandatory Answering Timing Policy** | Project Owner Mandate; CHED CMO 19 s. 2025 (accessibility) | **[C]** | Students submit completed evaluations whenever ready ($\ge 0\text{ s}$). Zero artificial minimum delay (no 45s countdown) and zero maximum duration. | PHPUnit `test_08`; Frontend `Group 04`; `IntegrityValidatorService`. | Formally approved project policy. Replaces deprecated speed-run block. |
| **Administrator-Controlled Evaluation Periods** | UP Diliman OAT SET Guidelines; Miriam College SELS Portal | **[B]** + **[C]** | Evaluation window schedules (`starts_at`, `ends_at`, `Asia/Manila`) managed by Registrar/Admin with `OPEN`, `SUSPEND`, `RESUME`, `CLOSE`, `EXTEND`, and `REOPEN` actions. | PHPUnit `test_24`; Frontend `Group 29`; `DecoupledEvaluationService`. | Standard academic practice observed at peer institutions and enforced in School AAAAA13 SET. |
| **Authoritative Session Identity & Enrollment Validation** | OWASP Top 10 API Security (BOLA/IDOR); Secure Architecture Standard | **[D]** | Student ID derived strictly from authenticated session; student must be enrolled in course section; client-supplied IDs, faculty IDs, and clock overrides rejected. | PHPUnit `test_01`, `test_25`; `StudentEvaluationController`. | Core security boundary hardening. |
| **Submission Idempotency & Concurrency (`FT-02`)** | Systems Engineering & Concurrency Control Guidelines | **[C]** + **[D]** | Unique `submission_key` scoped to `(student_id, course_section_id)`; atomic transaction with row-level pessimistic locking (`SELECT ... FOR UPDATE`). | PHPUnit `test_06`; 1,000-user JMeter concurrency test ($0\%$ errors). | Prevents duplicate submissions and cross-user key hijacking. |
| **Straight-Lining / Zero-Variance Safeguard** | Spooren et al. (2017) Educational Evaluation Measurement | **[C]** + **[D]** | Population variance $\sigma^2 = 0$ triggers confirmation modal (`confirm_uniform = true`); flagged in vault as `is_straight_lined = true` for audit telemetry. | PHPUnit `test_09`; Frontend `Group 06`, `Group 07`. | Balances student autonomy with administrative integrity metrics. |
| **Double-Blind Faculty Report Lock** | Miriam College SELS Policy; UP Diliman OAT SET Policy | **[B]** + **[C]** | Faculty cannot access ratings, sentiment distributions, or feedback until both `term_grades_submitted = 1` AND `registrar_lock = 1`. | PHPUnit `test_12`, `test_13`, `test_14`; `EnsureDoubleBlindUnlocked`. | Prevents grade retaliatory bias; aligns with national best practices. |
| **Five-Role RBAC & Departmental Containment** | School AAAAA13 Administrative Organizational Hierarchy | **[A]** + **[C]** | Dedicated workspaces for `student`, `faculty`, `chair`, `dean`, `admin`. Department Chairs strictly scoped to assigned `department_id` across all reports/exports. | PHPUnit `test_01`, `test_26`, `test_33`; `EnsureRole`. | Prevents horizontal privilege escalation (IDOR) across colleges/departments. |
| **Multilingual Tagalog/Taglish/English NLP Analytics** | Project Owner Mandate; AACCUP Level III Area III Research | **[C]** + **[D]** | `calamanCy` (`tl_calamancy_md-0.1.0`) + VADER polarity $P \in [-1.0, +1.0]$, 5 pedagogical clusters, and qualitative synthesis. Bounded timeout fallback. | Pytest `46 / 46 PASS`; PHPUnit `test_17`, `test_18`. | Formally framed as supporting diagnostic information, not definitive personnel judgment. |
| **Digital Examination Clearance Permit Unlock** | School AAAAA13 Registrar Examination Clearance Regulation | **[A]** + **[C]** | Completion of all enrolled course evaluations unlocks cryptographically signed exam permit (`School AAAAA13-REGISTRAR-AUTHENTICATED`). | PHPUnit `test_11`, `test_22`, `test_28`; Public permit verification. | Active institutional policy linking SET completion to semester final exam permits. |
| **Research Provenance of Quality & Usability Metrics** | ISO/IEC 25010 & System Usability Scale (SUS) Empirical Standards | **[D]** | Live evaluations stored in `quality_evaluations`; sample-weighted means calculated dynamically. Unverified values labeled as reference benchmarks. | `QualityMetricsService`; `quality_evaluations` database schema. | Prevents synthetic figures from being presented as verified empirical findings. |

---

## 3. Evaluation Instrument Specification (`School AAAAA13-SET-v2026.1`)

The active evaluation questionnaire is formally designated as **Instrument Identifier `School AAAAA13-SET-v2026.1`**:

1. **Rating Scale:** 5-Point Likert Agreement Scale
   - `5` – Strongly Agree (*Lubos na Sumasang-ayon*)
   - `4` – Agree (*Sumasang-ayon*)
   - `3` – Neutral / Moderately Agree (*Katamtamang Sumasang-ayon*)
   - `2` – Disagree (*Hindi Sumasang-ayon*)
   - `1` – Strongly Disagree (*Lubos na Hindi Sumasang-ayon*)

2. **Pedagogical Clusters (5 Clusters, 25 Items):**
   - **Cluster I: Pedagogical Delivery (Items 1–5):** Clarity of syllabus, explanation of difficult concepts, subject mastery, learning pacing, and instructional materials.
   - **Cluster II: Assessment & Grading (Items 6–10):** Alignment of exams with lessons, fair grading criteria, timely feedback, transparency, and return of academic papers.
   - **Cluster III: Classroom Management (Items 11–15):** Punctuality, class attendance, active student engagement, conducive environment, and respectful atmosphere.
   - **Cluster IV: Professional Conduct (Items 16–20):** Professional ethics, non-discriminatory behavior, approachability, adherence to university consultation hours.
   - **Cluster V: Student Support & Mentorship (Items 21–25):** Academic encouragement, guidance during difficulties, mentorship, empathetic listening, and responsiveness to student queries.

3. **Structured Qualitative Feedback (4 Core Questions + 1 Optional Observation):**
   - Question 1: *Three words that describe your learning experience with this professor.*
   - Question 2: *Room for improvements (specific pedagogical sub-topics).*
   - Question 3: *Greatest teaching strengths observed during lectures.*
   - Question 4: *Actionable recommendations for future course offerings.*
   - Question 5 (Optional, max 5): *Additional constructive observations.*

4. **Instrument Versioning & Prospective Adaptation:**
   - Any modifications to the 25 Likert items or qualitative prompts will increment the instrument version (e.g., `School AAAAA13-SET-v2027.1`).
   - Historical evaluations remain permanently indexed under their original instrument version to preserve longitudinal validity.
