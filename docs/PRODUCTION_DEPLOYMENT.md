# EvaluaTech production deployment

How to bring up EvaluaTech for a real evaluation cycle. Every step here was exercised in the
testing phase recorded in [`testing/TESTING_PHASE_REPORT.md`](testing/TESTING_PHASE_REPORT.md).

## What runs in production

| Part | What it is | Notes |
| :--- | :--- | :--- |
| Web runtime | `public/index.php` (standalone PHP dispatcher) | Serves the JSON API and the built SPA. |
| Database | SQLite file at `storage/framework/evaluatech_aaaaa13.sqlite` | Created by the initializer. Back it up like any other institutional record. |
| Frontend | Prebuilt assets in `public/build/` | Rebuild with `npm run build` after any UI change. No source maps are emitted. |
| NLP service | `analytics/` Flask service on `127.0.0.1:5001` | Optional at runtime: submissions never block on it; the backend falls back to the local classifier. |

## 1. Configure the environment

Copy `.env.example` to `.env` and set at least:

| Variable | Required | Purpose |
| :--- | :---: | :--- |
| `APP_ENV=production` | yes | Disables demo seeding and development fallbacks. |
| `APP_DEBUG=false` | yes | No stack traces in responses. |
| `EVALUATECH_SIGNING_SECRET` | yes | Signs session tokens and clearance receipts. Use a long random value. The application refuses to start in production without it (or `APP_KEY`). |
| `EVALUATECH_WEBHOOK_SECRET` | if webhooks are used | Verifies signed Registrar webhooks. |
| `EVALUATECH_ADMIN_PASSWORD` | only for step 2 | First administrator password. At least 10 characters with letters and numbers. |

Generate a secret with, for example, `php -r "echo bin2hex(random_bytes(32));"`.

Changing `EVALUATECH_SIGNING_SECRET` later signs everyone out and invalidates previously issued
receipts, so set it once and keep it in your secret store.

## 2. Provision the database (once)

```powershell
$env:APP_ENV = "production"
$env:EVALUATECH_ADMIN_PASSWORD = "<your strong password>"
php scripts/production-init.php
Remove-Item Env:EVALUATECH_ADMIN_PASSWORD
```

The initializer creates the schema, the seven colleges and departments, the active term and its
evaluation window, and one administrator (`admin@aaaaa13.edu`, ID `ADM-00001`). It creates no students,
no evaluations and no demo accounts.

It deliberately refuses to run when:

- `EVALUATECH_ADMIN_PASSWORD` is missing or weak. Nothing is written in that case.
- the database already contains user accounts. It only initialises an empty database, so it can
  never reset a live administrator or re-key departments that existing records point to.

Remove `EVALUATECH_ADMIN_PASSWORD` from the environment and from `.env` afterwards.

Review the term dates and evaluation window (`starts_at`, `ends_at`, Asia/Manila) in the admin console
under **Academic setup** before opening the cycle.

## 3. Start the services

Serve `public/` through your web server with all requests routed to `public/index.php`. For a
single-machine pilot the built-in server is enough:

```powershell
$env:APP_ENV = "production"
php -S 127.0.0.1:8000 -t public public/index.php
```

Put it behind HTTPS (reverse proxy or web server) before real users sign in; passwords and bearer
tokens must never travel over plain HTTP.

Start the NLP service if you want model-based sentiment rather than the fallback classifier:

```powershell
python -m analytics.app
```

## 4. Load people and classes

Sign in as the administrator and use **Registrar console → Class Roster Import** (JSON or CSV).
Required per row: `student_id`, `course_code` (for example `CS-201`), `instructor_name`. Optional:
`student_name`, `course_title`, `section_code`, `faculty_email`, `department_code`, `department_name`.
The import is atomic: one bad row rejects the whole file and the screen shows which row and why.

Deans and chairs are created under **Academic setup → Users**. Assign each dean and chair to a
department: a chair sees that department, a dean sees every department in that department's college.
A dean with no department has no scope and is refused.

## 5. Hand out one-time passwords

Every account created by import or by the admin console gets its own random one-time password.

- The passwords are shown **once**, right after the import or creation, with a **Download as CSV**
  button. The server stores only bcrypt hashes, so they cannot be shown again.
- Distribute each password to its owner through your usual channel (printed slips, class adviser).
- At first sign-in the person must set their own password (at least 10 characters, letters and
  numbers, not containing their ID or email name). Until they do, the system lets them do nothing else.
- If someone loses their one-time password, edit the user under **Academic setup → Users** and set a
  new password; they will again be asked to replace it at next sign-in.
- Re-importing a roster never changes the password of an account that already exists.

Treat the downloaded CSV as confidential and delete it once the passwords are distributed.

## 6. Release results

Faculty see nothing for a section until both conditions hold: term grades are submitted and the
Registrar has locked them (**Registrar console → Grades Submitted / Registrar Lock**). Reports contain
aggregates and anonymous comments only.

## Pre-launch checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, signing secret set and stored safely.
- [ ] `php scripts/production-init.php` succeeded and reported zero evaluations, clearances and enrollments.
- [ ] `EVALUATECH_ADMIN_PASSWORD` removed from environment and `.env`.
- [ ] Site reachable over HTTPS only.
- [ ] `powershell -ExecutionPolicy Bypass -File scripts/verify-all.ps1` passes on the release commit.
- [ ] Signing in with `evaluatech123` fails for every account.
- [ ] Term dates and evaluation window reviewed.
- [ ] Roster imported; one-time passwords downloaded and distributed; CSV deleted afterwards.
- [ ] Each dean and chair assigned to the correct department.
- [ ] Database file included in backups.

## Upgrading an existing installation

- The `users.must_change_password` column is added automatically on first start (and by migration
  `2026_11_12_000001`). Existing accounts keep their passwords and are not forced to change them.
- Deans must now be assigned to a department. Earlier builds gave the dean with user ID 2 the College
  of Computer Studies automatically; that shortcut is removed. Assign the department under
  **Academic setup → Users**, otherwise the dean receives `UNASSIGNED_COLLEGE_SCOPE`.
- Accounts created by older builds may still hold the former shared default password. Reset those
  passwords (which forces a change at next sign-in) before opening a cycle.
