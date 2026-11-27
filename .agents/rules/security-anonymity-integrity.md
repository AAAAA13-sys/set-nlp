# Rule: Security, Anonymity & Governance Invariants

1. **Physical Identity-Response Decoupling (RA 10173)**:
   - `evaluation_vault` MUST NEVER contain `student_id`, `user_id`, `clearance_id`, `ip_address`, `session_id`, `user_agent`, or sub-second correlation timestamps (`recorded_date` is strictly `YYYY-MM-DD` in `Asia/Manila`).
   - Automated schema inspection (`GET /api/security/vault-audit`) verifies zero forbidden columns on every test run.

2. **Double-Blind Faculty Release Gate**:
   - Instructors querying `GET /api/faculty/sections/{id}/report` MUST receive `403 DOUBLE_BLIND_LOCKED` unless **both** `term_grades_submitted = true` AND `registrar_lock = true`.

3. **Scoped Academic Containment**:
   - Department Chairs (`chair`) are restricted to their assigned `department_id` (`403 CROSS_DEPARTMENT_FORBIDDEN` otherwise).
   - College Deans (`dean`) are restricted to departments belonging to their assigned `college_name`.
   - Scoped participation follow-up lists (`GET /api/{chair,dean,admin}/participation`) read strictly from `enrollments` and `evaluation_clearances` without touching `evaluation_vault`.

4. **Authentication & 63 OWASP Controls**:
   - Stateless HMAC-SHA256 `EVT.*` Bearer tokens, constant-time bcrypt verification (with dummy hash anti-enumeration), sliding-window login rate limiting (`429`), CSPRNG one-time temporary passwords (`TMP-*` with `must_change_password = 1`), CSV formula neutralization, and strict CSP/HSTS/XFO headers.
