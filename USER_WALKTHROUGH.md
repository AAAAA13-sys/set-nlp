# PLP EvaluTech — Complete User Walkthrough & Interactive Controls Guide

This document walks through **every page, button, input, tab, filter, and modal** across all five roles of **PLP EvaluTech** (*Student*, *Faculty*, *Department Chair*, *College Dean*, and *University Registrar*), paired with live `playwright-cli` screenshots.

---

## Quick Test Accounts (Seeded via `database/seeders/EvaluaTechDemoSeeder.php`)

All seeded accounts use the password **`evaluatech123`**:

| Role | ID / Email | Default Landing Route | Description |
| :--- | :--- | :--- | :--- |
| **Student** | `2024-10892` (`2024-10892@plpasig.edu.ph`) | `/queue` | Juan Dela Cruz — 6 enrolled courses (3 completed, 3 pending) |
| **Faculty (Unlocked)** | `FAC-10001` (`adelacruz@plpasig.edu.ph`) | `/faculty` | Prof. A. Dela Cruz — `CS-401` grades submitted & Registrar-locked |
| **Faculty (Locked)** | `FAC-10003` (`erivera@plpasig.edu.ph`) | `/faculty` | Prof. E. Rivera — `ENG-102` awaiting Registrar grade lock |
| **Department Chair** | `CHR-00001` (`chair@plpasig.edu.ph`) | `/chair` | Dr. L. Bautista — Scoped to *Department of Computer Science* |
| **College Dean** | `DEAN-0001` (`dean@plpasig.edu.ph`) | `/dean` | Dr. V. Mendoza — University-wide & department-filtered analytics |
| **University Registrar** | `ADM-00001` (`admin@plpasig.edu.ph`) | `/admin` | Registrar Admin — Evaluation window, grade locks, roster import & security |

---

## 1. Public Sign-In Portal (`/login` — `resources/js/views/LoginView.vue`)

- **Desktop Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/01_login_desktop.png`
- **Mobile Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/02_login_mobile.png`

### Interactive Controls & Buttons on `/login`
- **Hover / Keyboard Tooltip Hints (`?` `.ui-hint`)**: Hovering or focusing the `?` badge next to `"Sign in to your account"` shows a concise tooltip (`"Use your PLP student number (e.g., 2024-10892), employee ID, or university email."`) so the card stays uncluttered.
1. **`Student / Employee ID or Email` Input (`[data-testid="login-identifier-input"]`)**:
   - Enter your PLP student number (e.g., `2024-10892`), employee ID (`FAC-10001`, `CHR-00001`, `DEAN-0001`, `ADM-00001`), or `@plpasig.edu.ph` email.
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

## 2. Global Navigation Shell (`WorkspaceSidebar.vue` & `NavbarHeader.vue`)

Every authenticated screen shares the deep-emerald left sidebar (`#103c2e`) and white top bar:

| Control / Button | Location | What Happens When Clicked |
| :--- | :--- | :--- |
| **Collapse Sidebar Button** (`[data-testid="sidebar-collapse-toggle"]`) | Top-right of emerald sidebar (`[‖]`) or `Ctrl + B` | Toggles the left sidebar between full width (`248px`) and compact icon rail (`68px`). |
| **Workspace Links** (`My Courses`, `Evaluate Professor`, `Exam Permit & Share`, etc.) | Left sidebar under `WORKSPACE` | Navigates directly to the selected page; active route is highlighted with a warm gold pill (`#f4cd42`). |
| **`Command Palette` (`Ctrl K`)** (`[data-testid="sidebar-cmdk-btn"]` / `[data-testid="navbar-cmdk-btn"]`) | Left sidebar under `QUICK TOOLS` & top navbar search bar | Opens the keyboard-driven **Command Palette Modal** (`Ctrl + K`) to jump to any page or toggle table density. |
| **`Evaluation Analysis` (`AI`)** (`[data-testid="sidebar-quivr-btn"]`) | Left sidebar under `QUICK TOOLS` (Chair, Dean, Registrar) | Opens the **Professor's Evaluation Analysis** slide-over drawer to search anonymized student comments. |
| **`Shortcuts & Rules` (`?`)** (`[data-testid="sidebar-shortcuts-btn"]`) | Left sidebar under `QUICK TOOLS` | Opens the **Keyboard Shortcuts & Evaluation Rules** reference modal. |
| **`Sign Out` Button** (`[data-testid="sidebar-logout-btn"]` / `[data-testid="navbar-logout-btn"]`) | Bottom of left sidebar & top-right of navbar | Revokes your Bearer token (`POST /api/auth/logout`), clears session state, and returns to `/login`. |

---

## 3. Student Walkthrough (`/queue`, `/evaluate/:id`, `/advocacy`)

### Step 3A: My Courses & Examination Permit (`/queue` — `StudentQueueView.vue`)

- **Queue Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/03_student_queue.png`
- **Permit Card Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/20_student_queue_permit_card.png`

#### Interactive Controls & Buttons on `/queue`
- **Course Counter Pill (`3 / 6` `.ui-badge-count`) & Tooltip Hint (`?` `.ui-hint`)**: The `My Courses` header displays a compact `3 / 6` completion counter pill and a `?` tooltip (`"Complete all enrolled course evaluations to unlock your examination permit."`).
1. **`Search course or professor...` Input (`[data-testid="queue-search-input"]`)**:
   - Filters your enrolled course rows in real time (debounced `220ms`) by course code (`CS-401`), title (`Discrete Mathematics`), or professor name (`Dr. R. Garcia`).
2. **Status Filter Pills (`All (6)`, `Pending (3)`, `Completed (3)`)**:
   - `[data-testid="queue-filter-all"]`: Shows all enrolled courses.
   - `[data-testid="queue-filter-pending"]`: Filters to courses you still need to evaluate.
   - `[data-testid="queue-filter-completed"]`: Filters to courses you have already evaluated.
3. **`Share Class Link` Button (`[data-testid="queue-advocacy-link"]`)**:
   - Navigates to `/advocacy` (`Exam Permit & Share`) so you can copy the portal link for classmates or verify your permit code.
4. **`Start Evaluation` Button (`[data-testid="evaluate-btn-4"]`, etc.)**:
   - Appears on `PENDING` courses (`MATH-201`, `CS-408`, `PHYS-101`). Clicking it opens the 25-item evaluation form for that professor (`/evaluate/:courseSectionId`).
5. **`Clearance Signed` Badge**:
   - Appears on `COMPLETED` courses (`CS-401`, `CS-405`, `ENG-102`). These courses are locked so duplicate evaluations cannot be submitted.
6. **`Digital Examination Permit` Card (`[data-testid="clearance-permit-card"]`)**:
   - Displays your per-course clearance codes (`CLR-...`) and overall permit status (`PENDING` until all 6 courses are evaluated, then unlocks to `CLEARED` with a `Print / Save Official PDF Permit` button and a `Verify Permit` proctor checker).

---

### Step 3B: Evaluating a Professor (`/evaluate/:id` — `EvaluationFormView.vue`)

- **Top Likert Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/04_student_evaluate_top.png`
- **Written Feedback & Submit Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/21_student_evaluate_written_submit.png`

#### Interactive Controls & Buttons on `/evaluate/:id`
1. **45-Second Dwell Timer Banner (`[data-testid="dwell-timer-badge"]`)**:
   - Counts up from `0s` to `45s` (`43s remaining before you can submit`). Once 45 seconds have elapsed, the badge turns green (`Ready to submit`).
2. **Autosave Indicator (`[data-testid="survey-autosave-indicator"]`)**:
   - Automatically saves your in-progress ratings and comments locally (`Autosave ready` / `Draft saved`) with zero personal identifiers so you don't lose work if you refresh.
3. **25 Likert Rating Pills (`1` to `5`) across 5 Teaching Areas**:
   - Each teaching cluster header includes a compact `X / 5` `.ui-badge-count` progress pill, and the `Questions About Your Professor` and `Additional Comments` headers include `?` `.ui-hint` tooltips so the evaluation form stays focused on the questions.
   - Click `1` (*Strongly Disagree*), `2` (*Disagree*), `3` (*Neutral*), `4` (*Agree*), or `5` (*Strongly Agree*) for each of the 25 items (`PD-1`..`PD-5`, `AG-1`..`AG-5`, `CM-1`..`CM-5`, `PC-1`..`PC-5`, `SS-1`..`SS-5`).
   - The progress bar updates live (`Answered: X / 25`, `Average: X.XX / 5.00`).
4. **Written Feedback Inputs (`Q1`–`Q4` & Optional `Q5`)**:
   - **Q1 (`[data-testid="prof-q1-three-words"]`)**: Enter three words describing the professor (e.g., `patient, clear, fair`).
   - **Q2 (`[data-testid="prof-q2-topic-0"]`, `[data-testid="prof-q2-topic-1"]`)**: List specific topics or areas for improvement (`a.`, `b.`).
   - **`+ Add Another Topic` Button (`[data-testid="add-improvement-topic-btn"]`)**: Adds up to 6 lettered topic fields (`c.`, `d.`, `e.`, `f.`); each extra row has a `Remove` button.
   - **Q3 (`[data-testid="prof-q3-strengths"]`)**: Describe the professor's teaching strengths.
   - **Q4 (`[data-testid="prof-q4-recommendations"]`)**: Share recommendations for future offerings of the course.
   - **`+ Include Optional 5th Observation` Button (`[data-testid="toggle-fifth-question-btn"]`)**: Toggles an optional 5th input field for laboratory/facilities remarks.
5. **Additional Comments Textarea (`[data-testid="narrative-comment-input"]`)**:
   - Optional open-ended comment box (up to `2000` characters, supports English, Tagalog, and Taglish). Shows a live sentiment preview tag below as you type.
6. **`Back to Courses` / `Reset Form` Button**:
   - Clears your current draft or returns to `/queue`.
7. **`Submit Evaluation` Button (`[data-testid="submit-evaluation-btn"]`)**:
   - Validates that all 25 Likert items and Q1–Q4 are answered and that at least 45 seconds have passed.
   - **Uniform Rating Check**: If you selected the exact same number for all 25 items (e.g., all `5`s, variance $\sigma^2 = 0$), a confirmation modal (**`Confirm Uniform Ratings`**) opens with **`Review My Ratings`** and **`Confirm & Submit`** buttons.
   - On submission, issues your signed `CLR-*` clearance token and decouples your identity from the rating vault.

---

### Step 3C: Exam Permit & Share (`/advocacy` — `EvaluationAdvocacyView.vue`)

- **Screenshot**: `C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6/06_student_advocacy_permit.png`

#### Interactive Controls & Buttons on `/advocacy`
- **Tooltip Hints (`?` `.ui-hint`)**: `Share Portal Link & Check Permit Status`, `Invite Classmates`, and `Your Clearance Code` use `?` hover/focus tooltip badges instead of long explanatory paragraphs.
1. **`Copy Link` Button (`[data-testid="copy-advocacy-link-btn"]`)**:
   - Copies the portal share URL (`http://.../login?utm_source=plp_campus_advocacy`) to your clipboard and switches button text to `Copied!`.
2. **`Copy Code` Button (`[data-testid="copy-clearance-token-btn"]`)**:
   - Copies your signed examination clearance code (`CLR-...`) so you can present it to your exam proctor.

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
   - **`Semester Trends & Dossier` (`[data-testid="tab-longitudinal-trends"]`)**: Shows the **Accreditation Evidence Summary** (`PLP-AACCUP3-*` seal & SHA-256 digest), **`Export Accreditation Dossier Bundle (CSV)`** button, and semester-over-semester rating comparison tables.
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
1. **Evaluation Window Status Buttons (`OPEN`, `SUSPENDED`, `CLOSED`)**:
   - **`OPEN (Evaluations Active)` (`[data-testid="btn-set-window-open"]`)**: Opens student evaluations immediately.
   - **`SUSPENDED (Temporary Hold)` (`[data-testid="btn-set-window-suspended"]`)** & **`CLOSED (Term Concluded)` (`[data-testid="btn-set-window-closed"]`)**: Open a **Destructive Action Confirmation Modal** where you type `SUSPENDED` or `CLOSED` to confirm pausing/closing the evaluation period.
2. **Threshold Inputs with `?` Tooltip Hints & `Save Settings` Button (`[data-testid="save-evaluation-window-btn"]`)**:
   - Adjust `Min Dwell Time (s)`, `Uniform Rating Cutoff`, `Watchlist Mean (<)`, `Watchlist Negative (> %)`, and `Student Banner Notice` (hover over any `?` badge for details), then click **`Save Settings`**.
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

