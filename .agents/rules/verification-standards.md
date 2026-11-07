# EvaluaTech Verification & Quality Assurance Standards

These rules enforce multi-tier verification rigor before any task is marked complete:

1. **Test Database Safety**:
   - Automated tests must target an isolated test database exclusively, never live institutional rosters.

2. **Mandatory Multi-Tier Test Execution**:
   - For Laravel backend changes: run `php artisan test` (20 backend tests covering RBAC, dual-token decoupling, speed-run rejection, straight-line flagging, idempotency, double-blind lock, and watchlist math).
   - For Python NLP analytics changes: run `python -m pytest analytics/tests -q` (40 tests covering VADER polarity bounds $[-1.0, +1.0]$, valence cutoffs, and all 4 pedagogical categories).
   - For Vue.js 3 frontend changes: run `npm test` (25 Vitest DOM test groups) and `npm run build`.

3. **Repository Cleanliness**:
   - Ensure all source files are UTF-8 encoded without BOM and run `git diff --check` to verify zero whitespace errors.

