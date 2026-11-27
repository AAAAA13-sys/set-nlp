# Rule: Safe Rust Backend & Python IPC Standards

1. **Memory & Type Safety**:
   - Workspace-wide `unsafe_code = "forbid"` in `Cargo.toml` and `cargo clippy --workspace --all-targets --all-features -- -D warnings` with zero warnings.

2. **SQLite WAL & Atomic Dual-Token Persistence (`evaluatech-server/src/db.rs`, `routes.rs`)**:
   - Use `PRAGMA journal_mode = WAL;` and `PRAGMA foreign_keys = ON;`.
   - Evaluation submissions (`POST /api/evaluation/submit`) must execute inside `BEGIN IMMEDIATE ... COMMIT` transactions, atomically writing participation state to `evaluation_clearances` and stripped anonymous feedback to `evaluation_vault`.
   - Enforce `FT-02` idempotency on `submission_key`: duplicate submissions return `200 OK` (`IDEMPOTENT_REPLAY`) with the original `clearance_token` without inserting duplicate vault records.

3. **Warm Python Worker Bridge (`evaluatech-server/src/python_nlp.rs`)**:
   - `evaluatech-server` maintains a persistent, warm `python -u -m evaluatech_nlp_py.worker` subprocess communicating via UTF-8 JSON-lines over `stdin`/`stdout` (and `nlp-python/evaluatech_nlp_py/server.py` exposes an optional FastAPI HTTP interface).
   - Scrub PII (`[REDACTED-EMAIL]`, `[REDACTED-PHONE]`, `[REDACTED-ID]`) in both Rust defense-in-depth and Python before storing or analyzing comment text.
   - If the Python worker crashes or restarts, automatically respawn once and fail gracefully without dropping student rating submissions.
