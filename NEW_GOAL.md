# Goal: Academic Setup Workspace

Date: 9 November 2026
Status: Proposed goal; implementation has not started.

## Objective

Build an Academic Setup workspace in the actual EvaluaTech website so administrators can configure and review an evaluation cycle through the interface. Extend the existing Vue 3 application and Laravel/standalone backend using established services, stores, and PLP styling.

## Reference

Inspired by [SaiyamTuteja/Faculty_Evaluation_System](https://github.com/SaiyamTuteja/Faculty_Evaluation_System), especially its academic-term management, faculty/class/subject assignment screen, and questionnaire overview:

- [Academic terms](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/academic_list.php)
- [Assignment management](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/manage_restriction.php)
- [Questionnaire overview](https://github.com/SaiyamTuteja/Faculty_Evaluation_System/blob/main/admin/questionnaire.php)

Adapt the workflows to EvaluaTech's architecture. If substantial upstream code is copied, retain its MIT copyright and permission notice and review bundled assets separately. Do not import its authentication or student-linked evaluation storage.

## Website scope

- [ ] Add an administrator-only Academic Setup destination to the existing workspace sidebar.
- [ ] Provide academic-year and semester management using the existing academic-term model.
- [ ] Provide subject/course and class/section management using existing catalog relationships.
- [ ] Let administrators assign faculty to course sections and review assignments before evaluations open.
- [ ] Preserve CSV/JSON roster imports and show their persisted results in the same workspace.
- [ ] Preview the existing 25-question evaluation instrument grouped into its five pedagogical clusters.
- [ ] Show the selected term's assigned sections, aggregate submission counts, and evaluation-window status.
- [ ] Identify incomplete setup, including sections without faculty assignments, with actionable validation messages.
- [ ] Connect window controls to the existing administrator schedule workflow, including Asia/Manila dates and EXTEND versus REOPEN semantics.
- [ ] Provide loading, empty, validation-error, save-success, and request-failure states.

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
- Preserve the existing PLP typography, forest-green/gold palette, sidebar, and accessibility conventions.

## Implementation approach

Before implementation, trace the existing routes, middleware, validators, controllers, services, models, router guards, Pinia stores, views, and tests. Reuse working catalog and schedule capabilities before adding endpoints. Keep Laravel and standalone HTTP behavior consistent, and update all consumers of any changed contract.

Prefer a focused extension of the current administrator workspace. Exact component boundaries and endpoint additions should follow the existing code after inspection; this goal does not prescribe an unverified schema or API.

## Acceptance criteria

- [ ] An administrator can create or select a term, maintain its course sections, assign faculty, and review readiness through the website.
- [ ] Valid changes persist after reload; invalid or duplicate input produces clear errors without partial writes.
- [ ] Manual setup and roster imports agree on catalog identifiers and enrollment relationships.
- [ ] Questionnaire preview shows the canonical 25 questions across all five clusters.
- [ ] Counts and window states reflect persisted data for the selected term, including a correct zero-response state.
- [ ] Non-admin setup mutations are rejected by both HTTP runtimes; existing tenant and double-blind restrictions remain enforced.
- [ ] Existing evaluation history and anonymity boundaries survive catalog updates.
- [ ] Desktop (1366 x 768) and mobile (375 x 812) layouts are usable with keyboard navigation, no horizontal overflow, and no console errors.
- [ ] Relevant tests cover persistence, validation, authorization, selected-term isolation, and existing roster-import compatibility.

## Verification required before completion

Confirm isolated test database configuration before running backend tests. Record actual pass counts rather than assuming historical suite counts.

- `powershell -ExecutionPolicy Bypass -File scripts/verify-all.ps1`
- `npm run build`, with no generated `.map` files in `public/build`
- `impeccable.cmd detect --json resources/js resources/css/app.css`, expecting zero warnings
- Live browser checks through `playwright-cli` at both required viewport sizes
- `git diff --check`

If the unified runner omits a required tier, run its dedicated backend, frontend, or Python command from AGENTS.md. At handoff, report the changed behavior, exact commands and results, browser evidence, and any remaining limitations.

## Completion outcome

Administrators can prepare and inspect a complete evaluation cycle from the EvaluaTech website, with persisted setup, clear readiness feedback, and the existing security and anonymity guarantees intact.
