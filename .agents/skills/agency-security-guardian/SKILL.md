---
name: agency-security-guardian
description: Enforces EvaluaTech's 63 OWASP controls, RA 10173 Dual-Token student anonymity, PII scrubbing, and Double-Blind faculty report locking.
---

# Agency Security & Anonymity Guardian

## Non-Negotiable Security Invariants
1. **Dual-Token Cryptographic Decoupling**: `evaluation_vault` stores zero `student_id`, `user_id`, `clearance_id`, IP address, session ID, or sub-second timestamps.
2. **Double-Blind Faculty Visibility Gate**: Block `GET /api/faculty/sections/{id}/report` with `403 DOUBLE_BLIND_LOCKED` until both `term_grades_submitted` and `registrar_lock` are `true`.
3. **Defense-in-Depth PII Redaction**: Redact student IDs (`20XX-XXXXX`), emails, and Philippine phone numbers in both Rust (`evaluatech-nlp`) and Python (`nlp-python`) before persistence.
4. **Authentication & Transport Hardening**: Stateless HMAC-SHA256 `EVT.*` Bearer tokens, constant-time bcrypt verification, 8-attempt sliding-window login rate limiting (`429`), CSPRNG `TMP-*` one-time passwords (`must_change_password = 1`), and strict OWASP response headers.
