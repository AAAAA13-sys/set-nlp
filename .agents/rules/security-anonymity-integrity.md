# EvaluaTech Security & Anonymity Integrity Standards

These rules enforce cryptographic survey anonymity, retaliation protection, and five-role access control:

1. **Dual-Token Cryptographic Decoupling**:
   - `evaluation_clearances` records student compliance (`student_id`, `course_section_id`, `clearance_token`, `status = 'CLEARED'`).
   - `evaluation_vault` records survey responses (`ratings_json`, `comment_text`, `comment_hash`, NLP polarity and pedagogical tag).
   - Never add `student_id`, `user_id`, `clearance_id`, IP address, session headers, or sub-second correlation timestamps to `evaluation_vault`.

2. **Double-Blind Faculty Visibility Guard**:
   - Faculty members cannot access evaluation scores, distributions, or comment summaries unless both `term_grades_submitted = true` AND `registrar_lock = true`.
   - Enforce this check on the server via `EnsureDoubleBlindUnlocked` middleware; hiding UI elements in Vue is never sufficient.

3. **Server-Side Five-Role Authorization**:
   - Verify `admin`, `dean`, `chair`, `faculty`, and `student` permissions on every API endpoint.
   - Scope Department Chairs strictly to their assigned `department_id` and Faculty to their assigned `course_sections`.

4. **Sanitization**:
   - Neutralize potential CSV formula injection (`=`, `+`, `-`, `@`) on all administrative roster and accreditation exports.

