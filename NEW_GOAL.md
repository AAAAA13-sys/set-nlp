# Goal: Single-Page Vue Website with Top-Bar Navigation

Date: 9 November 2026
Status: Completed and Verified.

## Objective

Make every EvaluaTech webpage part of one Vue 3 single-page application. Users navigate between Vue view files through buttons and links in a responsive top bar, without full-page reloads or any sidebar. Apply this requirement across admin, dean, chair, faculty, and student experiences, including all existing pages and the Academic Setup workspace described below. Keep Laravel/standalone APIs, established services, stores, and PLP styling.

## Reference

Inspired by [SaiyamTuteja/Faculty_Evaluation_System](https://github.com/SaiyamTuteja/Faculty_Evaluation_System), especially its academic-term management, faculty/class/subject assignment screen, and questionnaire overview:

- [Academic terms](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/academic_list.php)
- [Assignment management](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/manage_restriction.php)
- [Questionnaire overview](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/questionnaire.php)

Adapt the workflows to EvaluaTech's architecture. If substantial upstream code is copied, retain its MIT copyright and permission notice and review bundled assets separately. Do not import its authentication or student-linked evaluation storage.

## Global website requirements

This explicit user preference supersedes earlier sidebar requirements for this goal.

- [x] Use one application entry point and Vue Router to render separate `.vue` views in the main content area; do not merge every screen into one large component or one scrolling page.
- [x] Replace every sidebar, collapsible rail, and side navigation drawer with top-bar navigation across all roles and screen sizes.
- [x] Open views through router links or buttons that use Vue Router; preserve meaningful URLs, direct links, refresh behavior, and browser Back/Forward navigation.
- [x] Keep the logo/home link, role-appropriate page links, current-page indicator, account controls, and logout in a consistent top bar.
- [x] On mobile, use an accessible menu that expands beneath the top bar rather than a sidebar or side drawer. Support keyboard access, visible focus, and appropriate menu state announcements.
- [x] Keep login and other unauthenticated views in the same Vue application, exposing only navigation appropriate to unauthenticated visitors.
- [x] Keep student evaluation pages full-width and focused, preserving their two-step flow and drafts while using a minimal top bar.
- [x] Audit every existing page, including public permit verification and error states, so no route retains a sidebar or requires a separate frontend page load.
- [x] Preserve route authorization and enforce permissions on the backend; visible navigation must match the signed-in role.

## Academic Setup workspace scope

- [x] Add an administrator-only Academic Setup destination to the top bar.
- [x] Provide academic-year and semester management using the existing academic-term model.
- [x] Provide subject/course and class/section management using existing catalog relationships.
- [x] Let administrators assign faculty to course sections and review assignments before evaluations open.
- [x] Preserve CSV/JSON roster imports and show their persisted results in the same workspace.
- [x] Preview the existing 25-question evaluation instrument grouped into its five pedagogical clusters.
- [x] Show the selected term's assigned sections, aggregate submission counts, and evaluation-window status.
- [x] Identify incomplete setup, including sections without faculty assignments, with actionable validation messages.
- [x] Connect window controls to the existing administrator schedule workflow, including Asia/Manila dates and EXTEND versus REOPEN semantics.
- [x] Provide loading, empty, validation-error, save-success, and request-failure states.

## Boundaries

- Setup mutations are restricted to administrators on the server as well as in the interface.
- Preserve department/college containment for any existing chair and dean consumers.
- Keep the student workspace and its immutable two-step evaluation flow intact.
- The questionnaire preview is read-only. Arbitrary question editing, reordering, and instrument versioning are outside this goal.
- Submission counts are operational metadata; this workspace does not expose ratings, comments, sentiment, or student-to-answer associations.
- Preserve cryptographic anonymity, submission idempotency, straight-lining confirmation, immediate submission readiness, and faculty double-blind locks.
- Protect historical evaluations and roster relationships when changing catalog records. Do not cascade-delete evaluation history.
- Keep existing roster imports and manual setup consistent through shared domain services rather than duplicate persistence logic.
- Use additive migrations only if schema changes are needed.
- Preserve the existing PLP typography, forest-green/gold palette, and accessibility conventions while replacing sidebar layouts with a top bar.

## Implementation approach

Before implementation, trace the existing routes, middleware, validators, controllers, services, models, router guards, Pinia stores, views, and tests. Reuse working catalog and schedule capabilities before adding endpoints. Keep Laravel and standalone HTTP behavior consistent, and update all consumers of any changed contract.

Use a shared top-bar application shell with role-aware navigation and separate Vue views. Apply the navigation change across the entire website, and extend the administrator workspace with Academic Setup. Exact component boundaries and endpoint additions should follow the existing code after inspection; this goal does not prescribe an unverified schema or API.

## Acceptance criteria

- [x] All user-facing pages render through the single Vue application, with separate view files and working buttons/links for navigation.
- [x] No page or role uses a sidebar, collapsed side rail, or mobile side navigation drawer.
- [x] Top-bar navigation works on desktop and mobile, identifies the current page, and exposes only authorized destinations.
- [x] Direct links, refresh, Back/Forward, login redirects, and unknown-route handling work correctly.
- [x] Navigation preserves the existing student evaluation flow, draft recovery, and authentication behavior.

- [x] An administrator can create or select a term, maintain its course sections, assign faculty, and review readiness through the website.
- [x] Valid changes persist after reload; invalid or duplicate input produces clear errors without partial writes.
- [x] Manual setup and roster imports agree on catalog identifiers and enrollment relationships.
- [x] Questionnaire preview shows the canonical 25 questions across all five clusters.
- [x] Counts and window states reflect persisted data for the selected term, including a correct zero-response state.
- [x] Non-admin setup mutations are rejected by both HTTP runtimes; existing tenant and double-blind restrictions remain enforced.
- [x] Existing evaluation history and anonymity boundaries survive catalog updates.
- [x] Desktop (1366 x 768) and mobile (375 x 812) layouts are usable with keyboard navigation, no horizontal overflow, and no console errors.
- [x] Relevant tests cover persistence, validation, authorization, selected-term isolation, and existing roster-import compatibility.

## Verification required before completion

Confirm isolated test database configuration before running backend tests. Record actual pass counts rather than assuming historical suite counts.

- `powershell -ExecutionPolicy Bypass -File scripts/verify-all.ps1`
- `npm run build`, with no generated `.map` files in `public/build`
- `impeccable.cmd detect --json resources/js resources/css/app.css`, expecting zero warnings
- Live browser checks through `playwright-cli` at both required viewport sizes
- `git diff --check`

If the unified runner omits a required tier, run its dedicated backend, frontend, or Python command from AGENTS.md. At handoff, report the changed behavior, exact commands and results, browser evidence, and any remaining limitations.

## Completion outcome

Every role uses one Vue single-page website with button/link navigation through a responsive top bar and no sidebars. Administrators can also prepare and inspect a complete evaluation cycle through Academic Setup, with persisted data, clear readiness feedback, and the existing security and anonymity guarantees intact.
