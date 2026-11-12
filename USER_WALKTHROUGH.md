# EvaluaTech — Complete User Walkthrough & Interactive Controls Guide

This document walks through **every page, button, input, tab, filter, and modal** across all five roles of **EvaluaTech** (*Student*, *Faculty*, *Department Chair*, *College Dean*, and *University Registrar*), paired with live `playwright-cli` screenshots.

---

## Quick Test Accounts (Seeded via `database/seeders/EvaluaTechDemoSeeder.php`)

These accounts exist only in the local demo database (`APP_ENV=local`; seeded automatically when the database is empty) and use the password **`evaluatech123`**. A production database has none of them: it is provisioned with `scripts/production-init.php`, and every account created there receives its own one-time password (see [`docs/PRODUCTION_DEPLOYMENT.md`](docs/PRODUCTION_DEPLOYMENT.md)).

| Role | ID / Email | Default Landing Route | Description |
| :--- | :--- | :--- | :--- |
| **Student** | `2024-10892` (`2024-10892@aaaaa13.edu`) | `/queue` | Juan Dela Cruz — 6 enrolled courses (3 completed, 3 pending) |
| **Faculty (Unlocked)** | `FAC-10001` (`adelacruz@aaaaa13.edu`) | `/faculty` | Prof. A. Dela Cruz — `CS-401` grades submitted & Registrar-locked |
| **Faculty (Locked)** | `FAC-10003` (`erivera@aaaaa13.edu`) | `/faculty` | Prof. E. Rivera — `ENG-102` awaiting Registrar grade lock |
| **Department Chair** | `CHR-00001` (`chair@aaaaa13.edu`) | `/chair` | Dr. L. Bautista — Scoped to *Department of Computer Science* |
| **College Dean** | `DEAN-0001` (`dean@aaaaa13.edu`) | `/dean` | Dr. V. Mendoza — University-wide & department-filtered analytics |
| **University Registrar** | `ADM-00001` (`admin@aaaaa13.edu`) | `/admin` | Registrar Admin — Evaluation window, grade locks, roster import & security |

---

## 1. Public Sign-In Portal (`/login` — `resources/js/views/LoginView.vue`)

- **Desktop Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/01_login_desktop.png`
- **Mobile Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/02_login_mobile.png`

### Interactive Controls & Buttons on `/login`
- **Hover / Keyboard Tooltip Hints (`?` `.ui-hint`)**: Hovering or focusing the `?` badge next to `"Sign in to your account"` shows a concise tooltip (`"Use your School AAAAA13 student number (e.g., 2024-10892), employee ID, or university email."`) so the card stays uncluttered.
1. **`Student / Employee ID or Email` Input (`[data-testid="login-identifier-input"]`)**:
   - Enter your School AAAAA13 student number (e.g., `2024-10892`), employee ID (`FAC-10001`, `CHR-00001`, `DEAN-0001`, `ADM-00001`), or `@aaaaa13.edu` email.
   - Automatically trims whitespace and clears any prior validation banner as you type.
2. **`Password` Input (`[data-testid="login-password-input"]`)**:
   - Enter your password (`evaluatech123` for seeded accounts).
3. **`Show / Hide Password` Eye Button (`[data-testid="toggle-password-visibility-btn"]`)**:
   - Clicking the eye icon inside the right edge of the password field toggles between masked (`••••••••`) and plain text.
4. **`Keep me signed in on this device` Checkbox (`[data-testid="login-remember-checkbox"]`)**:
   - **Unchecked (Default)**: Stores your session token in `sessionStorage` so closing the browser tab automatically signs you out (safe for shared campus computer labs).
   - **Checked**: Persists your session in `localStorage`.
5. **`Sign in` Button (`[data-testid="login-submit-btn"]`)**:
   - Submits credentials to `POST /api/auth/login`. Shows `"Signing in..."` while authenticating and redirects you to your role's home page (`/queue`, `/faculty`, `/chair`, `/dean`, or `/admin`).

---

## 2. Universal Top Navigation Shell (`NavbarHeader.vue`)

EvaluaTech features a single-page universal top navigation bar across all roles (`student`, `faculty`, `chair`, `dean`, `admin`) and unauthenticated views. There are **zero sidebars, zero collapsible side rails, and zero mobile side drawers**, ensuring a clean, distraction-free institutional layout. On mobile viewports ($375\times812$), an accessible dropdown menu expands directly beneath the top bar.

| Control / Button | Location | What Happens When Clicked |
| :--- | :--- | :--- |
| **Institutional Brand & Seal** (`[data-testid="navbar-plp-seal"]`) | Top-left navbar | Displays the official School AAAAA13 seal and title; links to institutional home (`/`). |
| **Role Navigation Links** (`[data-testid^="nav-link-"]`) | Top navbar center strip | Navigates directly to the role portal (`Faculty Portal`, `Chair Portal`, `Dean Analytics`, `Registrar Console`, `Campus Advocacy & Standards`). Active route is marked with a distinctive forest-green underline and badge. |
| **Active Term Indicator** | Top navbar right strip | Displays the active institutional academic term (`Term: 1st Semester AY 2026-2027`). |
| **`Command Palette` (`Ctrl K`)** (`[data-testid="navbar-cmdk-btn"]`) | Top navbar actions | Opens the keyboard-driven **Command Palette Modal** (`Ctrl + K`) to jump to any page or toggle table density. |
| **`Evaluation Analysis` (`AI`)** (`[data-testid="open-quivr-brain-btn"]`) | Top navbar actions (Chair, Dean, Admin) | Opens the **Professor's Evaluation Analysis** slide-over drawer to search anonymized student comments. |
| **`Shortcuts & Rules` (`?`)** (`[data-testid="navbar-shortcuts-btn"]`) | Top navbar actions | Opens the **Keyboard Shortcuts & Evaluation Rules** reference modal. |
| **`Sign Out` Button** (`[data-testid="navbar-logout-btn"]`) | Top-right of navbar (All Roles) | Revokes your Bearer token (`POST /api/auth/logout`), clears session state, and returns to `/login`. |

---

## 3. Student Walkthrough (`/queue`, `/evaluate/:id`)

### Step 3A: My Courses Evaluation Queue (`/queue` — `StudentQueueView.vue`)

- **Queue Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/03_student_queue.png`

#### Interactive Controls & Elements on `/queue`
- **Course Counter Pill (`3 / 6` `.ui-badge-count`) & Tooltip Hint (`?` `.ui-hint`)**: The `My Courses` header displays a compact `3 / 6` completion counter pill and a `?` tooltip (`"Complete evaluations for all enrolled courses."`).
- **Deadline Indicator**: The header displays the administrator-controlled submission deadline (e.g., `Asia/Manila` time) without artificial countdown pressure.
1. **`Search course or professor...` Input (`[data-testid="queue-search-input"]`)**:
   - Filters your enrolled course rows in real time (debounced `250ms`) by course code (`CS-401`), title (`Discrete Mathematics`), or professor name (`Dr. R. Garcia`).
2. **Status Filter Pills (`All (6)`, `Pending (3)`, `Completed (3)`)**:
   - `[data-testid="queue-filter-all"]`: Shows all enrolled courses.
   - `[data-testid="queue-filter-pending"]`: Filters to courses you still need to evaluate.
   - `[data-testid="queue-filter-completed"]`: Filters to courses you have already evaluated.
3. **`Start Evaluation` Button (`[data-testid="evaluate-btn-4"]`, etc.)**:
   - Appears on `PENDING` courses (`MATH-201`, `CS-408`, `PHYS-101`). Clicking it opens the focused evaluation stepper for that professor (`/evaluate/:courseSectionId`).
4. **`✓ Evaluated` Locked Badge (`[data-testid="evaluated-locked-badge"]`)**:
   - Appears on `COMPLETED` courses (`CS-401`, `CS-405`, `ENG-102`). Completed evaluations are cryptographically sealed and permanently locked against re-entry.

---

### Step 3B: Focused Evaluation Stepper (`/evaluate/:id` — `EvaluationFormView.vue`)

- **Part I Likert Rating Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/eval_step1_rating_questions.png`
- **Incomplete Alert & Auto-Scroll Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/eval_step1_validation_alert.png`
- **Part II Left-Aligned Feedback Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/eval_step2_written_feedback_left_aligned.png`
- **Submission Receipt & Lock Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/eval_submission_success.png`

#### Interactive Stepper Controls & Answering Flow
1. **`[⛶ Focused Fullscreen]` Toggle (`[data-testid="toggle-fullscreen-btn"]`)**:
   - Requests native browser fullscreen (`document.documentElement.requestFullscreen()`) to minimize desktop distractions. Degrades gracefully if unsupported or dismissed; does not trap the user.
2. **2-Step Progress Indicator**:
   - **Step 1: Part I — Rating Questions** (25 Likert items across 5 pedagogical clusters: *Pedagogical Delivery*, *Assessment & Grading*, *Classroom Management*, *Professional Conduct*, *Student Support & Mentorship*).
   - **Step 2: Part II — Written Feedback** (left-aligned qualitative responses and instant submit).
3. **Incomplete Rating Detection & Auto-Scroll**:
   - Clicking `[Continue to Written Feedback →]` before rating all 25 items blocks transition, displays a clear warning (`"Please complete all 25 ratings before proceeding (X remaining)."`), and smoothly scrolls directly to the first unrated question with an animated pulsing border (`.pulse-unanswered`).
4. **Mandatory Timing Policy**:
   - Students may advance and submit whenever ready with zero artificial dwell timers or countdown locks (`dwell_time_seconds >= 0`).
5. **Autosave Draft (`[data-testid="survey-autosave-indicator"]`)**:
   - Periodically preserves your in-progress selections locally with zero PII (`Draft saved`).
6. **Part II: Left-Aligned Qualitative Feedback**:
   - All questions, helper badges, and input textareas are strictly **left-aligned** for optimal reading flow:
     - **Q1 (`[data-testid="prof-q1-three-words"]`)**: Three words describing the professor.
     - **Q2 (`[data-testid="prof-q2-topic-0"]`, `[data-testid="prof-q2-topic-1"]`, `[data-testid="prof-q2-topic-2"]`)**: Exactly 3 pedagogical room-for-improvement prompts (`a. Pedagogical Delivery`, `b. Assessment & Grading`, `c. Classroom Management`).
     - **Q3 (`[data-testid="prof-q3-strengths"]`)**: Teaching strengths.
     - **Q4 (`[data-testid="prof-q4-recommendations"]`)**: Constructive recommendations for future offerings.
     - **Additional Narrative Remarks (`[data-testid="narrative-comment-input"]`)**: Optional open-ended comment (up to 2,000 characters, Tagalog/English/Taglish supported).
7. **`[Submit Evaluation]` Action (`[data-testid="submit-evaluation-btn"]`)**:
   - Displays a live loading indicator (`Submitting to Vault…`).
   - **Uniform Rating Safeguard**: If population variance is zero ($\sigma^2 = 0$, e.g., all 5s), a confirmation challenge modal prompts the student to confirm before setting `is_straight_lined = true`.
   - **Atomic Dual-Token Transaction**: Decouples student clearance from the anonymous evaluation vault, exits fullscreen, displays an official clearance receipt (`CLR-...`), and permanently locks the course evaluation.
8. **`[← Back to Rating Questions]` Button (`[data-testid="back-to-step1-btn"]`)**:
   - Allows students to freely review and revise their Likert ratings prior to final submission.

---

## 4. Faculty Walkthrough (`/faculty` — `FacultyDashboardView.vue`)

- **Unlocked Section Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/07_faculty_unlocked.png`
- **Locked Section Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/08_faculty_locked.png`

### Interactive Controls & Buttons on `/faculty`
- **Tooltip Hints (`?` `.ui-hint`) & Comment Counter (`.ui-badge-count`)**: `Course Evaluation Summary`, `Results Locked Until Final Grades Are Submitted`, `Student Written Feedback Summary`, `Pedagogical Action Plan`, and `Anonymous Student Comments` include `?` tooltip hints and a live comment count pill.
1. **`Section:` Dropdown (`[data-testid="faculty-section-select"]`)**:
   - Switch between your assigned course sections (e.g., `CS-401 (SEC-A) — Unlocked` vs `CS-401 (SEC-B) — Locked`).
2. **When Locked (`Grades Pending` — `[data-testid="double-blind-lock-screen"]`)**:
   - Shows the 2-step checklist required before student feedback can be viewed (`1. Final Semester Grades Submitted`, `2. Registrar Grade Verification`).
   - **`Confirm Final Grades Submitted` Button (`[data-testid="submit-term-grades-btn"]`)**: Marks your instructor term grades as submitted (`POST /api/faculty/sections/:id/submit-grades`).
3. **When Unlocked (`RESULTS AVAILABLE` — `[data-testid="faculty-unlocked-report"]`)**:
   - Displays the 5 teaching cluster averages (`Pedagogical Delivery`, `Assessment & Grading`, `Classroom Management`, `Professional Conduct`, `Student Support & Mentorship`), the **Student Written Feedback Summary** (`Q1`–`Q4`), and **Anonymous Student Comments** (with zero student names or IDs).
   - **`Acknowledge & Implement Plan` / `Submit Milestone Resolution Evidence` Buttons**: Appear if a section has an active support action plan.

---

## 5. Department Chair Walkthrough (`/chair` — `ChairDashboardView.vue`)

- **Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/09_chair_dashboard.png`

### Interactive Controls & Buttons on `/chair`
1. **`Evaluation Analysis` Button (`[data-testid="chair-open-analytics-lab-btn"]`)**:
   - Opens the **Professor's Evaluation Analysis** modal to inspect comment sentiment and teaching category breakdown.
2. **`Search Feedback` Button (`[data-testid="chair-open-quivr-brain-btn"]`)**:
   - Opens the **Professor's Evaluation Analysis** drawer scoped strictly to your department (`Department of Computer Science`).
3. **Department Chair Notes & `Endorse Action Plan to Dean` Button (`[data-testid="chair-endorse-plan-btn"]`)**:
   - In the lower **Faculty Watchlist & Support Plans** card, type chair recommendations into the textarea and click **`Endorse Action Plan to Dean`** (`POST /api/chair/action-plan/status`) to save your endorsement to the database.

---

## 6. College Dean Walkthrough (`/dean` — `DeanDashboardView.vue`)

- **Tab 1 (`Overview & Watchlist`)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/10_dean_tab1_overview.png`
- **Tab 2 (`Semester Trends & Dossier`)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/11_dean_tab2_trends.png`
- **Tab 3 (`System Quality & Compliance`)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/12_dean_tab3_quality.png`
- **Tab 4 (`Written Feedback` — Professor's Evaluation Analysis)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/13_dean_tab4_written.png`

### Interactive Controls & Buttons on `/dean`
1. **`Department:` Filter Dropdown (`[data-testid="dean-dept-filter-select"]`)**:
   - Filter all metrics, watchlists, heatmaps, and trends between `All Colleges & Departments`, `College of Computer Studies (CCS)`, and `College of Arts and Sciences (CAS)`.
2. **4 Analytical View Tabs**:
   - **`Overview & Watchlist` (`[data-testid="tab-dean-overview"]`)**: Shows Student Comment Sentiment bars, Faculty Performance Quartiles, Section Completion Heatmap, Faculty Exception Watchlist (with **`Export Sanitized CSV Report`**, **`View Pedagogical Intervention Plan`**, **`Approve Plan`**, and **`Mark Milestone Resolved`** buttons), and the interactive **College Section & Sentiment Explorer** data table (with search `/`, facet filters, column sorting, and **`Comfortable` / `Compact`** density buttons).
   - **`Semester Trends & Dossier` (`[data-testid="tab-longitudinal-trends"]`)**: Shows the **Accreditation Evidence Summary** (`School AAAAA13-AACCUP3-*` seal & SHA-256 digest), **`Export Accreditation Dossier Bundle (CSV)`** button, and semester-over-semester rating comparison tables.
   - **`System Quality & Compliance` (`[data-testid="tab-quality-sus"]`)**: Shows the 8-point **Accreditation & Quality Standards Checklist**, ISO/IEC 25010 Software Quality table, and System Usability Scale (SUS) breakdown.
   - **`Written Feedback` (`[data-testid="tab-qualitative-synthesis"]`)**: Shows **Professor's Evaluation Analysis** across `Q1`–`Q4` with `?` tooltip hints (`Three Words Describing the Professor`, `Areas for Improvement`, `Teaching Strengths`, `Student Recommendations`) plus **`Export JSON`** and **`Export CSV`** buttons.
3. **`Search Feedback` (`[data-testid="open-quivr-brain-btn"]`) & `Evaluation Analysis` (`[data-testid="open-analytics-lab-btn"]`) Buttons**:
   - Open the **Professor's Evaluation Analysis** feedback drawer or diagnostic modal.

---

## 7. University Registrar Walkthrough (`/admin` — `AdminRosterView.vue`)

- **Evaluation Window & Thresholds**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/14_admin_registrar_top.png`
- **Course Grade Locks Table**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/24_admin_roster_and_locks.png`
- **Class Roster Import & System Security Status**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/25_admin_security_posture.png`

### Interactive Controls & Buttons on `/admin`
1. **Evaluation Window Actions (`OPEN`, `EXTEND DEADLINE`, `REOPEN CLOSED PERIOD`, `SUSPEND`, `CLOSE`)**:
   - **`OPEN (Evaluations Active)` (`[data-testid="btn-set-window-open"]`)**: Opens student evaluations immediately.
   - **`EXTEND DEADLINE (+7 Days)` (`[data-testid="btn-set-window-extend"]`)**: Extends closing deadline during active periods.
   - **`REOPEN CLOSED PERIOD` (`[data-testid="btn-set-window-reopen"]`)**: Reopens an expired/closed window with 7-day extension.
   - **`SUSPEND (Temporary Hold)` (`[data-testid="btn-set-window-suspended"]`)** & **`CLOSE (Term Concluded)` (`[data-testid="btn-set-window-closed"]`)**: Pause or conclude the evaluation period with audit confirmation.
2. **Schedule Controls & Threshold Inputs with `?` Tooltip Hints & `Save Settings` Button (`[data-testid="save-evaluation-window-btn"]`)**:
   - Adjust `Opening Date & Time` (`starts_at`), `Closing Date & Time` (`ends_at`), `Asia/Manila` timezone, `Uniform Rating Cutoff`, `Watchlist Mean (<)`, `Watchlist Negative (> %)`, and `Student Banner Notice` (hover over any `?` badge for details), then click **`Save Settings`**.
3. **Course Grade Locks Table (`[data-testid="registrar-locks-table"]`)**:
   - **Row Checkboxes & Select-All Checkbox**: Selecting one or more course sections reveals the **Bulk Actions Bar** (`Lock Selected`, `Unlock Selected`, `Mark Grades Submitted`) to update multiple sections in one click.
   - **Individual `Submitted` / `Pending` & `Locked` / `Open` Toggle Buttons**: Click any section's `Grades Submitted` or `Registrar Lock` button to toggle its state with instant optimistic UI updates. When both are active, `Report Status` changes to `Available`.
4. **Class Roster Import**:
   - Switch between **`JSON Format`** and **`CSV Format`** radio buttons, paste enrollment rows into the editor, and click **`Import Roster`** (`[data-testid="submit-roster-import-btn"]`) to create/update courses, sections, and student enrollments.
5. **System Security Status**:
   - Filter all 63 security controls by keyword or category (`All (63)`, `Part 1`, `Part 2`, `Part 3`), click **`Refresh Posture`**, or click **`Run Zero-Knowledge Anonymity Audit`** at the bottom to verify that `evaluation_vault` has zero student identity columns.

---

## 8. Quick-Tool Modals, Drawers & Error Pages (`Ctrl+K`, `AI`, `?`, `403`, `404`)

- **Command Palette Modal (`Ctrl+K`)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/17_command_palette_modal.png`
- **Professor's Evaluation Analysis Drawer**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/18_quivr_ai_drawer.png`
- **Professor's Evaluation Analysis Modal**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/23_sentiment_lab_modal.png`
- **Shortcuts & Evaluation Rules Modal (`?`)**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/19_shortcuts_cheatsheet_modal.png`
- **403 Access Restricted**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/15_access_denied_403.png`
- **404 Page Not Found**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/16_not_found_404.png`
- **Destructive Action Safeguard Modal (`DestructiveConfirmModal.vue`)**: Features an inline `?` `.ui-hint` tooltip badge explaining governance audit logging and dual-token preservation.
- **Graceful Error Recovery Banner (`ErrorRecoveryBanner.vue`)**: Displays an inline `?` `.ui-hint` tooltip badge confirming that local evaluation drafts remain safely saved in the browser while retrying.
- **Reusable Institutional Data Table (`ReusableDataTable.vue`)**: Equipped with `/` keyboard search focus, facet filter pills, column sorting, `Comfortable` / `Compact` row density toggle, `?` `.ui-hint` shortcut tooltip, and `.ui-badge-count` pagination counters.

---

## 9. Knowledge Graph & GraphRAG Architecture (`graphify-out/`)

- **Interactive HTML Graph**: `graphify-out/graph.html`
- **Raw Graph JSON**: `graphify-out/graph.json` (`1,412 nodes`, `2,645 edges`, `86 communities`)
- **Audit Report**: `graphify-out/GRAPH_REPORT.md`

