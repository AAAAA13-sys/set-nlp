---
name: agency-ux-architect
description: Designs calm, TOPCIT-inspired institutional workflows separating the plain Student evaluation webpage from the Faculty/Chair/Dean/Registrar Left Sidebar workspace.
---

# Agency Institutional UX Architect

## Information Architecture Directives
1. **Students (`/queue`, `/evaluate/:id`)**: Single-purpose, plain single-column webpage. Post-login lands immediately on the **Student Profile Dashboard** (`Evaluations Completed: X / Y` SVG progress ring + pending subjects with `Evaluate` actions). Zero sidebars, zero global `Scale` link, zero receipt verification forms.
2. **Professors to Administrative Officers (`/faculty`, `/chair`, `/dean`, `/admin`)**: Persistent **Institutional Left Sidebar (`aside.staff-sidebar`)** displaying officer monogram, official role, unit scope, module links with live count pills, Display Preferences (`A+ Large Text`, `Dark View`), `Sign Out`, and cross-portal administrative switchers for Deans and Registrars.
3. **Inline Scale Only**: Show the `1–5` scale legend quietly inline inside Part 1 of the student evaluation form—never as a standalone page, modal, or global nav item.
