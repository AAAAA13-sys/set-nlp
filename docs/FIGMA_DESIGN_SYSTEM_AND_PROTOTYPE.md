# EvaluaTech Figma Design System, Screen Inventory & Prototype Specification

**Document Version:** 2.0.0
**Institution:** School AAAAA13
**Authority:** Office of Academic Affairs & EvaluaTech Engineering Team
**Design Reference:** Single-Page Universal Top-Bar Architecture (Zero Sidebars)

---

## 1. Executive Summary & Design Principles

EvaluaTech is the official Student Evaluation of Teachers (SET) platform for School AAAAA13. In accordance with the Project Lead's Memorandum on stabilization and interface redesign, this document establishes the complete screen inventory, design tokens, responsive states, and clickable navigation flows prior to frontend visual implementation.

### Live Clickable Prototype Access
- **Interactive Canvas & Prototype (Live Web Server):** `http://127.0.0.1:8000/prototype/`
- **Standalone Source Files:** [`public/prototype/index.html`](file:///c:/Users/Ivan/Music/NLP%20SET/public/prototype/index.html) and [`docs/prototype/index.html`](file:///c:/Users/Ivan/Music/NLP%20SET/docs/prototype/index.html)
- **Features:** Single-page Figma-style canvas featuring all 11 screen frames side-by-side, interactive device click-through flows, viewport toggle (Desktop 1366×768 vs Mobile 375×812), system state inspection (Normal, Loading Shimmer, Empty Roster, Validation Error, Double-Blind Locked Report), and inspectable School AAAAA13 institutional tokens.

### Core Design Principles
1. **School-Based Institutional Restraint:** Eliminate generic SaaS clichés, decorative dashboards, floating particles, and oversized marketing headings. Design for purposeful academic administration.
2. **Universal Top-Bar Single-Page Navigation:** Every page and role is reached through a consistent, responsive top navigation bar. No sidebars, no collapsible side rails, and no mobile navigation drawers.
3. **No Quick Tools or Demo Shortcuts:** Remove all cheat/demo role switches, bypass buttons, and dwell skip buttons. Authentication strictly utilizes verified bcrypt institutional credentials.
4. **Evaluation Participation Tracking (Zero Permits):** School AAAAA13 does not gate examination permits on student evaluations. The system tracks evaluation progress, identifies students with pending evaluations by course, section, department, and college, and assists follow-up without linking student identity to evaluation responses.
5. **Ergonomic Typography & High Contrast:** Root typography at 16.5px–17.5px with 1.62 line height, authentic School AAAAA13 emerald and gold palette, meeting WCAG 2.2 AA accessibility standards.

---

## 2. Institutional Design Tokens

### Color Palette (School AAAAA13 Academic Tech)
```css
:root {
  /* Primary Institutional Palette */
  --color-forest-dark: #0b3322;      /* Deep Forest Green (Header, Footers) */
  --color-emerald-base: #103c2e;     /* School AAAAA13 Emerald (Brand, Primary Buttons, Active States) */
  --color-emerald-hover: #0a271e;    /* Hover State */
  --color-gold-accent: #f4cd42;      /* School AAAAA13 Gold Accent (Pills, Rating Stars, Warnings) */
  --color-gold-warm: #eab308;        /* Warm Gold (Badges) */
  --color-offwhite-canvas: #f9f9f8;  /* Page Background Canvas */
  --color-card-bg: #ffffff;          /* Card & Modal Surface */
  --color-border-subtle: #e5e7eb;    /* Subtle Divider Border */
  --color-border-institutional: #dce7dc; /* Light Emerald Border */

  /* Text & Foreground */
  --color-text-primary: #111827;     /* High-Contrast Body Text */
  --color-text-secondary: #4b5563;   /* Descriptive Captions */
  --color-text-muted: #6b7280;       /* Footnotes & Metadata */

  /* Semantic Feedback */
  --color-success-bg: #ecfdf5;       /* Cleared / Completed */
  --color-success-text: #065f46;
  --color-warning-bg: #fffbeb;       /* Pending / Action Required */
  --color-warning-text: #92400e;
  --color-error-bg: #fef2f2;         /* Error / Gated Lock */
  --color-error-text: #991b1b;
}
```

### Typography Hierarchy
- **Primary Interface Font:** `Public Sans`, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif
- **Editorial / Accreditations:** `Newsreader`, Georgia, serif (used sparingly for institutional decrees and certificates)
- **Monospace Tokens & Proofs:** `JetBrains Mono`, "SF Mono", Consolas, monospace
- **Scale:**
  - `H1 Page Title`: 28px–32px (1.25 line height, 800 weight)
  - `H2 Section Title`: 22px–24px (1.3 line height, 700 weight)
  - `H3 Subsection Title`: 18px–20px (1.35 line height, 600 weight)
  - `Body Text`: 16px–17.5px (1.62 line height, 400 weight)
  - `Small / Captions`: 13px–14.5px (1.5 line height, 500 weight)
  - `Tokens / Code`: 12px–13.5px (monospace, letter-spacing 0.04em)

---

## 3. Screen Inventory & Architecture

| ID | Screen Name | Route | Role Scoping | Key Purpose | Primary Actions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SCR-01** | **Public Landing Page** | `/` | Public (Unauthenticated) | Official introduction to EvaluaTech, School AAAAA13 SET purpose, AACCUP governance, data privacy. | `[Sign In →]`, `[Review Guidelines]` |
| **SCR-02** | **Split-Screen Login** | `/login` | Public (Guest Only) | Secure institutional sign-in with Institutional ID & password. | `[Sign In to Portal]`, `[Forgot Password]` |
| **SCR-03** | **Student Evaluation Queue** | `/queue` | `student` | Course evaluation queue with progress bar, status chips, completion receipt modal. | `[Start Evaluation]`, `[Receipt 📜]`, `[Export CSV]` |
| **SCR-04** | **Student Evaluation Form** | `/evaluate/:id` | `student` | 2-step stepper: Part I (25 Likert items with auto-scroll), Part II (written feedback). Immediate submission. | `[Proceed to Part II]`, `[Submit Evaluation]` |
| **SCR-05** | **Faculty Evaluation Dashboard** | `/faculty` | `faculty` | Section rating summary, 5-cluster bars, sentiment analytics, action plans (locked until grades submitted). | `[Confirm Grade Status]`, `[Export CSV]`, `[Rubric 📋]` |
| **SCR-06** | **Department Chair Portal** | `/chair` | `chair` | Scoped to assigned `department_id`. Heatmap, faculty performance, action plan compliance, Quivr Brain. | `[View Report 📊]`, `[Export Faculty CSV]`, `[Rubric 📋]` |
| **SCR-07** | **College Dean Portal** | `/dean` | `dean` | Scoped to assigned college. College-wide heatmap, sentiment explorer, exception watchlist (Mean < 3.50). | `[View Report 📊]`, `[Export Summary]`, `[Rubric 📋]` |
| **SCR-08** | **Academic Setup Workspace** | `/admin/setup` | `admin` | 7 modular tabs: Assignments, Terms, Courses, Faculty, Students (Participation Tracker), Accounts, Questionnaire. | `[+ New Term]`, `[+ Add Section]`, `[Track Pending]`, `[CSV]` |
| **SCR-09** | **Registrar Console & Grade Locks** | `/admin` | `admin` | Bulk Registrar Grade-Lock synchronization, search/department filter, evaluation window scheduling. | `[Toggle Lock]`, `[Bulk Lock]`, `[Schedule Window]` |
| **SCR-10** | **Campus SET Advocacy & Standards** | `/advocacy` | Public / All Roles | Student evaluation guidelines, 4 pillars of anonymity, rating rubric standards, downloadable charter. | `[Open Rubric Modal]`, `[Download Charter TXT]` |
| **SCR-11** | **Completion Status Verification** | `/verify` | Public / Registrar | Real-time verification of evaluation completion status using student ID or clearance token. | `[Verify Status 🔍]`, `[Print Certificate]` |

---

## 4. Evaluation Participation Tracking Architecture

### Privacy & Separation Invariant
In strict accordance with Specific Objective 2 and Lead Engineer Directive 5:
- **Identifiable Participation Record:** Stored in `evaluation_clearances` (`student_id`, `course_section_id`, `academic_term_id`, `clearance_token`, `cleared_at`). Used solely to determine who has evaluated vs who still needs to evaluate.
- **Anonymous Evaluation Response:** Stored in `evaluation_vault` (`course_section_id`, `academic_term_id`, ratings, comments, NLP tags, `comment_hash`).
- **Strict Invariant:** There is **NO FOREIGN KEY, NO USER ID, NO CLEARANCE ID, NO IP, AND NO SUB-SECOND TIMESTAMP** in `evaluation_vault`. Administrative participation reports query `evaluation_clearances` and `enrollments` only, guaranteeing that an administrator can identify *who has pending evaluations* without ever being able to link that student to their ratings or feedback.

### Participation Tracking Workflow
1. **Institutional Overview:** Total enrolled students, cleared students (100%), pending students (in-progress), institution turnout %.
2. **Course Section Breakdown:** Section enrollment count, submitted evaluations, pending evaluations count, turnout %.
3. **Pending Students Follow-Up:**
   - Filter directory by `PENDING`.
   - Export targeted `School AAAAA13_Incomplete_Student_Evaluations_FollowUp.csv`.
   - Remind modal generates ready-to-send institutional notice for student reminders.

---

## 5. Screen Layout Wireframes & Component Specs

### Frame 1: Public Institutional Landing Page (SCR-01: `/`)
```
+----------------------------------------------------------------------------------------------------+
| [School AAAAA13 Logo] School AAAAA13 | EvaluaTech                       [Sign In] [Help/Guide] |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|   OFFICE OF ACADEMIC AFFAIRS • SCHOOL AAAAA13                                      |
|   EvaluaTech: Automated Student Evaluation of Teachers                                             |
|                                                                                                    |
|   A secure, school-based faculty evaluation system designed to gather anonymous student feedback   |
|   for instructional improvement, governed by AACCUP Level III accreditation standards.             |
|                                                                                                    |
|   [ Sign In to Institutional Portal → ]   [ Review Evaluation Standards & Guidelines 📋 ]         |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|   HOW THE EVALUATION PROCESS WORKS                                                                 |
|   1. Sign In             2. Review Queue         3. Complete 2-Step Form    4. Automatic Clearance |
|   Access your account    View enrolled courses   25 Likert items + written  Your feedback is saved |
|   with School AAAAA13 credentials   and pending reviews     pedagogical feedback       with 100% anonymity    |
+----------------------------------------------------------------------------------------------------+
|   THREE CORE INSTITUTIONAL SAFEGUARDS                                                              |
|   [ Complete Anonymity ]         [ Double-Blind Grade Lock ]     [ Pedagogical Action Plans ]      |
|   No student IDs or IP addresses Faculty cannot view results     Objective NLP sentiment analysis  |
|   are ever stored in vault.      until grades are submitted.     guides instructional enhancement. |
+----------------------------------------------------------------------------------------------------+
```

### Frame 2: Student Directory & Participation Tracker (SCR-08: `/admin/setup?tab=students`)
```
+----------------------------------------------------------------------------------------------------+
| [Assignments] [Terms] [Courses] [Faculty] [Students 👥] [System Users] [Questionnaire]             |
+----------------------------------------------------------------------------------------------------+
| SUMMARY: Total Students: 1,500 | Cleared: 1,220 (81.3%) | ⚠️ Pending Evaluators: 280 (18.7%)        |
+----------------------------------------------------------------------------------------------------+
| [Search by name/ID...]  [All Status / Cleared / PENDING]  [All Departments]  [Reset]               |
| Actions: [📊 Export All Students CSV]  [⚠️ Export Pending Evaluators CSV]  [+ New Student]          |
+----------------------------------------------------------------------------------------------------+
| Student ID   | Name               | Department | Enrolled | Progress    | Status   | Actions         |
| 2024-10892   | Maria Santos       | CCS        | 5 Courses| [|||||] 5/5 | CLEARED  | [Courses] [Edit]|
| 2024-10893   | Juan Dela Cruz     | CCS        | 5 Courses| [|||..] 3/5 | PENDING  | [Courses][Remind|
| 2024-10894   | Ana Reyes          | CBA        | 4 Courses| [....] 0/4  | PENDING  | [Courses][Remind|
+----------------------------------------------------------------------------------------------------+
```

---

## 6. Verification & Implementation Roadmap

1. **Step 1:** Establish Public Landing Page (`LandingView.vue`) at `/` with clean single-page top navigation, and route unauthenticated users gracefully.
2. **Step 2:** Refactor `AdminSetupView.vue` into modular tab components (`AdminStudentsTab.vue`, `AdminFacultyTab.vue`, etc.) to improve maintainability and testability.
3. **Step 3:** Implement the dedicated **Pending Students Participation Tracker & Follow-Up Notice Modal** in both UI and backend endpoints (`/api/admin/setup/students/pending`).
4. **Step 4:** Execute unified verification matrix across all 152+ automated units and capture updated live Playwright evidence.
