# PAMANTASAN NG LUNGSOD NG PASIG (PLP) — EvaluaTech

**Automated Student Evaluation of Teachers (SET) Platform with Dual-Token Cryptographic Decoupling and `calamanCy` + VADER NLP Sentiment Analytics**

- **Institution**: **Pamantasan ng Lungsod ng Pasig (PLP)** — *Alkalde Joseco St., Brgy. Kapasigan, Pasig City*
- **Official Institutional Reference**: [https://plpasig.edu.ph/](https://plpasig.edu.ph/)
- **NLP Pipeline Reference**: [`calamanCy`](https://github.com/ljvmiranda921/calamanCy) (`tl_calamancy_md` / `tl_calamancy_lg`) + VADER Sentiment Polarity Engine ($P \in [-1.0, +1.0]$)

---

## 1. Architectural Overview

**EvaluaTech** is a full-stack academic evaluation and clearance synchronization system tailored for **Pamantasan ng Lungsod ng Pasig (PLP)**:

1. **Laravel 12 Backend Tier (`app/`, `routes/`, `database/`)**:
   - **Five-Role Institutional RBAC**: `student`, `faculty`, `chair`, `dean`, and `admin` (PLP Office of the University Registrar).
   - **Dual-Token Cryptographic Decoupling**: Physically separates student clearance records (`evaluation_clearances`) from anonymized quantitative ratings and narrative feedback (`evaluation_vault`). `evaluation_vault` stores **zero** student identifiers (`student_id`, `user_id`, `clearance_id`, IP address, session ID, or sub-second correlation timestamps).
   - **FT-02 Concurrency & Idempotency**: Enforces pessimistic row locking (`SELECT ... FOR UPDATE`) and `submission_key` deduplication so network retries never create duplicate evaluations.
   - **Double-Blind Faculty Grade-Lock Gate**: Faculty evaluation reports remain locked (`HTTP 403 DOUBLE_BLIND_LOCKED`) unless both `term_grades_submitted = true` AND `registrar_lock = true`.

2. **Vue.js 3 SPA + Zero-Build PLP Live Runtime (`resources/js/`, `resources/css/app.css`)**:
   - **PLP Emerald Green & Academic Gold Visual Identity**: Styled after [plpasig.edu.ph](https://plpasig.edu.ph/) (`#0b4f26`, `#14532d`, `#eab308`, `#facc15`).
   - **5 Clusters of 5-Point Likert Scale (25 Quantitative Items)**:
     1. *Cluster 1: Pedagogical Delivery* (5 items, rated `1–5`)
     2. *Cluster 2: Assessment & Grading* (5 items, rated `1–5`)
     3. *Cluster 3: Classroom Management* (5 items, rated `1–5`)
     4. *Cluster 4: Professional Conduct* (5 items, rated `1–5`)
     5. *Cluster 5: Student Support & Mentorship* (5 items, rated `1–5`)
   - **4 Structured Qualitative Questions Regarding the Professor (Capped at Max 5 Questions)**:
     1. `1. 3 words that describe the experience with the prof.` (`three_words`)
     2. `2. Room for improvements of the prof (a. topic 1, b. topic 2, ...)` (`room_for_improvements`)
     3. `3. Most effective teaching strategies or strengths of the prof.` (`teaching_strengths`)
     4. `4. Actionable recommendations for the prof in future semesters.` (`recommendations`)
     5. Optional `5th Question` (`additional_observation`, enforcing `max: 5` questions).
   - **Response Authenticity Guards**:
     - **Mandatory Timing Policy**: Enables immediate evaluation submission whenever students are ready (`dwell_time_seconds >= 0`) within administrator-defined evaluation windows, rejecting negative durations (`< 0`) with `HTTP 422`.
     - **Zero-Variance ($\sigma^2 = 0$) Straight-Lining Modal**: Prompts confirmation when all Likert items receive identical ratings and records `is_straight_lined = true` upon confirmation.

3. **Python / Flask `calamanCy` + VADER NLP Microservice (`analytics/`)**:
   - Integrates [`calamanCy`](https://github.com/ljvmiranda921/calamanCy) Tagalog/Taglish/English morphological preprocessing and lemmatization with VADER sentiment polarity calculation ($P \in [-1.0, +1.0]$).
   - Classifies comments into `Positive` ($P \ge +0.05$), `Neutral` ($-0.05 < P < +0.05$), or `Negative` ($P \le -0.05$) and maps feedback across the 5 pedagogical clusters.

---

## 2. Quick Start & Verification

### Run the Full 120-Unit Multi-Tier Verification Suite
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-all.ps1
```
- **Backend PHPUnit Suite**: `34 / 34 PASS (866 assertions)`
- **Frontend Vue 3 DOM & Domain Suite**: `40 / 40 Test Groups PASS`
- **Python `calamanCy` + VADER NLP Suite**: `46 / 46 PASS`

### Launch the Live PLP Portal (`http://127.0.0.1:8000`)
```bat
START-EVALUATECH.bat
```
Or start the services manually:
```powershell
python -m analytics.app
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public\index.php
```
Then open:
- **Student Queue (`2024-10892`)**: `http://127.0.0.1:8000/queue`
- **5-Cluster SET Survey + 4-to-5 Professor Questions**: `http://127.0.0.1:8000/evaluate/104`
- **Faculty Double-Blind Portal**: `http://127.0.0.1:8000/faculty`
- **Dean Analytical Overview & Watchlist**: `http://127.0.0.1:8000/dean`
- **PLP Registrar Admin & Locks Console**: `http://127.0.0.1:8000/admin`
