# Master UI/UX Audit & Strategic Redesign Directives

**Target Aesthetic:** Inspired by `topcit.or.kr` (Clean, institutional, highly focused, dashboard-driven, minimal text).
**Core Goal:** Eliminate "AI Slop" (generic enterprise UI), reduce cognitive load, consolidate features, and streamline the user journey into a highly functional, task-oriented application.

---

## PART 1: MAJOR STRATEGIC PIVOTS (The "Nutshell" Changes)

### 1. Feature Removal: Kill the "Verification/Receipt" System
* **The Issue:** The "Check a Completion Receipt" input and the "Verify Receipt" menu item are unnecessary friction. The "Exam Permit" block is irrelevant.
* **The Fix:** **Delete** the receipt verification form entirely. **Delete** the "Verify Receipt" link from the top navigation. **Delete** all copy referencing "Examination Permits."
* **The Pivot:** The system tracks completion internally. The dashboard shows if a user has evaluated a professor.

### 2. Post-Login Landing = User Profile Dashboard
* **The Issue:** Previously, students landed on a list of subjects or a generic "Home" page and had to hunt for their overall status.
* **The Fix:** The **User Profile Dashboard** is the immediate post-login landing page.
* **What it shows:** User Name/ID; a massive progress indicator ("Evaluations Completed: 4/6") with an SVG progress ring; a list of *Pending* subjects with immediate primary navy "Evaluate" buttons; and **zero** marketing fluff.

### 3. Navigation Consolidation (Top Bar)
* **The Issue:** The top bar had too many items ("Home", "Verify Receipt", "Rating Scale", "A+ Large Text", "Dark View"), creating decision fatigue.
* **The Fix:** Combine alike functions. Remove "Verify Receipt". Move "A+ Large Text" and "Dark View" into a single **"Settings"** gear icon dropdown. Provide "Rating Scale" inside the evaluation form as a tooltip/accordion in addition to the consolidated top bar link.
* **Resulting Top Bar:** `[Logo] | Dashboard | Scale | [Gear Icon] | [User Profile / Sign Out]`

### 4. Reduce Text Density
* **The Issue:** The pre-login landing page read like a legal document.
* **The Fix:** Reduce copy by 70%. Use concise bullet points. Move the "Student Privacy Protections" text into a collapsible accordion. Center the primary focus on a large, clear "Sign In" button.

---

## PART 2: PER-FILE UI/UX AUDIT (Waves 1–5)

### Wave 1 & Wave 2 Foundation
* Soft elevated cards, Plus Jakarta Sans + JetBrains Mono typography, deep collegiate teal-navy palette, underline tabs, eye toggle inside password inputs, group-only red validation outlines, red destructive `Clear Answers` button, celebratory gold `Outstanding` cards, blue `Submitted` vs. green `Released` vs. amber `On Hold` badges, dark monospace CSV editor, and stacked mobile cards.

### Wave 3: Refined Core Workflows (V2)
* **`student_eval_written_comments_v2.png`:** Subtle slate-grey background (`#f1f5f9`) on form inputs so they look immediately interactive.
* **`student_subjects_list_v2.png`:** Primary navy `Evaluate` buttons on pending subjects; clean `Completed` status on finished subjects (receipt modal removed).
* **`faculty_results_outstanding_v2.png`:** Stronger gold border (`#eab308`) and crisp star icon on `Outstanding` badges.
* **`faculty_pending_grade_release_v2.png`:** Darker secondary/muted text (`#334155` / `#475569`) for high WCAG contrast.
* **`error_access_restricted_v2.png`:** Elevated drop shadow lifting the centered white card off the canvas.
* **`chair_dept_overview_v2.png`:** Wider `0.08em` letter-spacing on uppercase table headers.
* **`chair_needs_support_v2.png`:** Instant hover/focus tooltips (`Approve Support Plan`, `Mark Completed`) on compact icon action buttons.
* **`student_home_dark_v2.png`:** Toned-down steel-teal primary button (`#2b7a9b`) in dark mode instead of bright cyan.
* **`student_eval_errors_v2.png`:** Increased `10px` top margin between statement text and red validation message.
* **`student_eval_clear_modal_v2.png`:** Default programmatic focus on `Keep My Answers` to prevent accidental destructive clicks.

### Wave 4 & 5: Mobile Refinements & Admin (V3/V4)
* **`create_permanent_password_v3.png`:** Smaller, lighter password hint text (`0.78rem`).
* **`mobile_home_v3.png`:** `1.65` line-height for mobile readability.
* **`mobile_menu_open_v3.png`:** Uniform vertical spacing across mobile menu items.
* **`mobile_subjects_list_v4.png` & `mobile_eval_form_v3.png`:** Clear horizontal scroll affordance on filter chips and category jump pills; collapsible `Scale Guide` accordion + inline tooltip.
* **`mobile_registrar_schedule_v3.png`:** Clean stacked card layouts across all mobile data tables.
* **`registrar_grade_release_v3.png`:** Confirmation modal when clicking `Release All`.
* **`registrar_roster_import_v3.png`:** Click-to-copy temporary password pills with copy icon and clipboard feedback.
* **`registrar_privacy_stats_v3.png`:** Consistent `2rem / 800` typographic hierarchy across all stat cards.

---

## PART 3: TOPCIT DESIGN PRINCIPLES APPLIED
1. **Clear Dashboard:** Immediate post-login User Profile Dashboard with SVG progress ring, big numbers, and pending tasks.
2. **Minimalist Header:** Thin, solid dark navy header (`Logo` left, `Dashboard | Scale` center, `Settings Gear | Profile / Sign Out` right).
3. **Focus on the Task:** Evaluation form stripped down to essentials with inline scale tooltip/accordion and sticky progress bar.
4. **Data Density:** Tightly aligned typographic contrast (large bold numbers, muted micro-labels) without heavy borders.
