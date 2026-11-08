# EvaluaTech repository agent guide

This file is the primary repository entry point and multi-tier engineering guide for **EvaluaTech** (Automated Student Evaluation of Teachers with Dual-Token Cryptographic Decoupling and NLP Sentiment Analytics). For changes spanning the Laravel 12 backend, Vue.js 3 SPA frontend (`resources/js/`), and Python/Flask NLP service (`analytics/`), apply all rules in this guide and its linked `.agents/` skills.

## Before editing

1. Check `git status` and read [`implementation plan.md`](implementation%20plan.md) and [`NLP-SET Online.txt`](NLP-SET%20Online.txt) to preserve existing work and align with the current phase.
2. Trace the affected API route, middleware, Form Request validator, controller, domain service, Eloquent model, Vue Router guard, Pinia store, Vue view/component, Python endpoint, and automated tests. Search every caller of a shared contract before changing its signature or payload shape.
3. Make the smallest complete, surgical change using established service and store patterns. Update all affected API consumers, Vue components, and multi-tier tests in the same change.

## Architectural boundaries

- **Laravel 12 (`app/`, `routes/`, `database/`)** owns authentication, five-role authorization (`admin`, `dean`, `chair`, `faculty`, `student`), academic roster ingestion, dynamic queue generation, server-side dwell-time and straight-line variance validation, atomic dual-token database transactions, and double-blind faculty visibility enforcement. Controllers map HTTP input/output only; domain logic belongs in `app/Services/`.
- **Vue.js 3 SPA (`resources/js/`, `resources/views/app.blade.php`)** owns the reactive user interface across mobile ($375\times812\text{ px}$) and desktop ($1366\times768\text{ px}$) viewports using Vue 3 Composition API, Pinia stores, and Vue Router. Client-side dwell timers and variance prompts improve UX friction and prevent accidental speed-running, but never replace server-side enforcement.
- **Python / Flask NLP Microservice (`analytics/`)** owns `calamanCy` (`https://github.com/ljvmiranda921/calamanCy` — Tagalog/Taglish/English spaCy pipeline) + VADER sentiment polarity calculation ($P \in [-1.0, +1.0]$), valence classification (`Positive`, `Neutral`, `Negative`), five-cluster pedagogical tagging (`Pedagogical Delivery`, `Assessment & Grading`, `Classroom Management`, `Professional Conduct`, `Student Support & Mentorship`), and structured analysis of the 4-to-5 professor qualitative questions (`three_words`, `room_for_improvements` with sub-topics `a.`, `b.`..., `teaching_strengths`, `recommendations`, capped at `max: 5` questions). Trace `AnalyticsClientService`, `ProcessEvaluationSentimentJob`, and `analytics/engine.py` together when modifying sentiment or topic rules.
- **MySQL 8.0 InnoDB (`database/migrations/`)** owns relational persistence, foreign-key integrity on catalog and clearance tables, row-level pessimistic locking (`SELECT ... FOR UPDATE`), and strict physical separation between `evaluation_clearances` and `evaluation_vault`.

## Non-negotiable invariants that must survive every change

- **Cryptographic Anonymity Decoupling**: `evaluation_clearances` records that a specific `student_id` completed an evaluation for a `course_section_id` and holds the signed clearance token. `evaluation_vault` stores the submitted Likert ratings, narrative comment, salted `comment_hash`, and NLP tags. **Never add `student_id`, `user_id`, `clearance_id`, IP address, session ID, or sub-second correlation timestamps to `evaluation_vault`.**
- **Submission Idempotency & Concurrency (`FT-02`)**: Every evaluation submission must carry a `submission_key` verified inside an atomic `DB::transaction()` after acquiring a pessimistic row lock (`SELECT ... FOR UPDATE`) on the enrollment/clearance state. Repeated clicks or network retries must return the original clearance token without inserting duplicate vault records.
- **Response Authenticity Guards**:
  - **Speed-Run Guard**: Reject any evaluation of $\ge 20$ items submitted in under $45\text{ seconds}$ (`dwell_time_seconds < 45` or server-signed start token delta $< 45\text{ s}$) with HTTP `422`.
  - **Straight-Lining Guard ($\sigma^2 = 0$)**: Compute population variance $\sigma^2$ across all 20 Likert ratings. If $\sigma^2 = 0$ and `confirm_uniform` is false, reject with a confirmation challenge; if confirmed (`confirm_uniform = true`), accept and set `is_straight_lined = true` in `evaluation_vault`.
- **Double-Blind Faculty Visibility Lock**: Faculty members cannot view quantitative ratings, score distributions, or sentiment summaries for any section unless both `term_grades_submitted = true` AND `registrar_lock = true`. Hiding a Vue button is not authorization; enforce `EnsureDoubleBlindUnlocked` on the server.
- **Dean Exception Watchlist Thresholds**: Flag any instructor on the administrative watchlist when their mean Likert score is $< 3.50$ OR their negative sentiment comment share exceeds $35\%$.
- **Resilient NLP Fallback**: Student evaluation submissions and exam clearance token issuance must never fail or block if the Python Flask daemon is offline; `AnalyticsClientService` must enforce a bounded timeout and fall back to deterministic local classification while queuing asynchronous retry processing.
- **Additive Migrations Only**: Never rewrite an already-verified migration to alter schema state; add a new forward migration and preserve test database isolation.

## Verification standards

- **Dedicated Test Database**: Confirm `phpunit.xml` and test environment overrides target an isolated test database (`evaluatech_test` or in-memory/isolated SQLite/MySQL test schema), never live institutional rosters.
- **Backend PHPUnit Suite (20 tests)**: Run `php artisan test` (or `php artisan test --filter=<TestName>` while iterating) to verify five-role RBAC, roster imports, dual-token decoupling, speed-run rejection, straight-line flagging, idempotency, double-blind locks, and watchlist math.
- **Frontend Vue / Vitest Suite (25 test groups)**: Run `npm test` and `npm run build` to verify DOM rendering, queue progress bars, 45s dwell timer countdowns, zero-variance confirmation modals, double-blind lock screens, and Dean analytics cards.
- **Python NLP Analytics Suite (40 tests)**: Run `python -m pytest analytics/tests -q` to verify VADER polarity bounds $[-1.0, +1.0]$, valence thresholds, all four pedagogical categories, Chapter III sample comment benchmarks, and batch endpoints.
- **Repository Hygiene**: Run `git diff --check` before handoff and keep all source files UTF-8 encoded without BOM.

## Specialized agency workers (`.agents/skills/`)

This repository includes **7 specialized workers** adapted from `agency-agents` in `.agents/skills/` alongside workspace rules in `.agents/rules/`:

1. **Backend Architect** ([`agency-backend-architect`](.agents/skills/agency-backend-architect/SKILL.md)): Owns Laravel 12 architecture, dual-token cryptographic decoupling (`evaluation_clearances` vs `evaluation_vault`), pessimistic row locking (`SELECT ... FOR UPDATE`), `FT-02` submission idempotency, and double-blind grade-lock state machines.
2. **Frontend Developer** ([`agency-frontend-developer`](.agents/skills/agency-frontend-developer/SKILL.md)): Owns the Vue.js 3 SPA (`resources/js/`), Pinia state stores, Vue Router role guards, responsive mobile ($375\times812$) and desktop ($1366\times768$) layouts, live 45-second dwell timers, straight-line ($\sigma^2 = 0$) confirmation modals, and digital exam clearance cards.
3. **AI & NLP Engineer** ([`agency-ai-engineer`](.agents/skills/agency-ai-engineer/SKILL.md)): Owns the Python/Flask NLP microservice (`analytics/`), VADER sentiment polarity engine ($[-1.0, +1.0]$), 4-domain pedagogical topic classifier (*Pedagogical Delivery*, *Assessment & Grading*, *Classroom Management*, *Professional Conduct*), and Laravel circuit-breaker integration.
4. **Security Guardian** ([`agency-security-guardian`](.agents/skills/agency-security-guardian/SKILL.md)): Owns zero-knowledge student anonymity verification, metadata/IP stripping before vault persistence, HMAC-SHA256 salted hashes, server-signed dwell token verification, five-role RBAC isolation, and CSV formula injection neutralization.
5. **QA Reality Checker** ([`agency-qa-reality-checker`](.agents/skills/agency-qa-reality-checker/SKILL.md)): Owns evidence-based certification across the 25 Frontend Vitest DOM groups, 20 Backend PHPUnit tests, 40 Python Pytest tests, 1,000-user Apache JMeter concurrency benchmark ($<248\text{ ms}$ latency, $0\%$ 5xx errors), and ISO/IEC 25010 / SUS (`83.92` Grade A) validation.
6. **Code Reviewer** ([`agency-code-reviewer`](.agents/skills/agency-code-reviewer/SKILL.md)): Owns cross-tier caller tracing (Laravel API resources $\leftrightarrow$ Vue Pinia stores $\leftrightarrow$ Python JSON payloads), surgical minimal-diff discipline, and regression prevention.
7. **UI Anti-Slop Specialist** ([`ui-anti-slop`](.agents/skills/ui-anti-slop/SKILL.md)): Owns anti-slop gatekeeping, eliminating generic AI clichés (unprompted purple gradients, lazy card grids, fake badges, missing empty states), and enforcing authentic PLP institutional branding (`#0b4f26` Green, `#eab308` Gold), ergonomic density, and WCAG 2.2 AA accessibility.

## Done means

All affected Laravel routes, Form Requests, services, Eloquent models, Vue 3 components/stores, Python NLP endpoints, and multi-tier automated tests agree. Report what changed, the exact verification commands executed with their pass counts, and any remaining risks. Never leak student identities into vault logs or administrative payloads.

