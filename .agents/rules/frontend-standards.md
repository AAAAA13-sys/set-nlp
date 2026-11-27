# Rule: Role-Tailored Frontend & Anti-Slop UI Standards

1. **Two Distinct Layout Paradigms (`evaluatech-web/src/lib.rs`, `web-dist/styles.css`)**:
   - **Public Visitors (`/`, `/login`) & Students (`/queue`, `/evaluate/:id`, `/account/password`)**:
     - Render a calm, **plain single-column webpage** (`header.top-navbar` + `main.workspace-container`).
     - Never render a sidebar for students; their sole workflow is viewing completion progress (`Evaluations Completed: X / Y`) and completing 2-part subject evaluations.
   - **Professors up to Administrative Officers (`/faculty`, `/chair`, `/dean`, `/admin`)**:
     - Render the **Institutional Sidebar Shell** (`div.staff-layout` -> `aside.staff-sidebar` + `div.staff-main`).
     - Include officer identity card, module navigation with live action badges, Display Preferences (`A+ Large Text`, `Dark View`), `Sign Out`, and cross-portal administrative switchers for Deans (`/dean`, `/chair`) and Registrars (`/admin`, `/dean`, `/chair`, `/faculty`).
     - Never duplicate navigation with horizontal `.portal-tabs` inside staff views.

2. **Eliminated Features & Banned Jargon**:
   - **No Global Scale Page or Button**: Never display `Scale` in `.top-navbar` or `aside.staff-sidebar`, and never render a `1–5 Rating Scale` modal. Show the `1–5` legend quietly inline inside Part 1 of `/evaluate/:id` only.
   - **No Receipt Verifier or Exam Permits**: Never render receipt verification forms or mention examination permits anywhere in UI copy.
   - **Human Institutional Copy**: Never expose developer/internal identifiers (`WebAssembly`, `WASM`, `SQLite`, `UUIDv4`, `TF-IDF`, `VADER`, `calamanCy`, `sessionStorage`, `term_grades_submitted`, `registrar_lock`) in user-facing HTML.

3. **Accessibility & Responsiveness**:
   - Maintain WCAG 2.2 AAA contrast (`>= 7.0:1`) in both Light and Dark themes.
   - Every interactive control (buttons, icon actions, settings toggles, eye toggles, copy buttons, sidebar links) must measure `>= 44×44px`.
   - Zero horizontal overflow (`scrollWidth <= innerWidth`) across `1366×768`, `375×812`, and `320×568` (iPhone SE).
