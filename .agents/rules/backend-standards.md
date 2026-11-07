# EvaluaTech Backend Standards

These rules enforce backend engineering practices across Laravel 12 controllers, domain services, database models, and Python NLP integrations:

1. **Service Layer Boundaries**:
   - Keep controllers thin. Controllers only validate input via Form Requests and return JSON responses.
   - Domain logic, population variance math ($\sigma^2$), dwell-time verification, dual-token decoupling, and roster imports belong in dedicated Services (`IntegrityValidatorService`, `DecoupledEvaluationService`, `RosterImportService`, `AnalyticsClientService`).

2. **Database Concurrency & Pessimistic Locking**:
   - Enforce deterministic row locking (`SELECT ... FOR UPDATE`) on the student's enrollment and clearance records inside `DB::transaction()`.
   - Re-verify submission status immediately after acquiring locks to prevent race conditions during exam-week traffic spikes.

3. **Submission Idempotency (`FT-02`)**:
   - Require a unique `submission_key` for every evaluation submission.
   - Retries with an already-processed `submission_key` must return the original signed clearance token without inserting duplicate records into `evaluation_vault`.

4. **Python NLP Analytics Coordination**:
   - Python processes under `analytics/` perform VADER sentiment polarity calculation and 4-domain pedagogical classification.
   - Enforce a bounded timeout in `AnalyticsClientService` with a deterministic local PHP fallback so student clearance issuance never blocks or fails if the Python microservice is restarting.

