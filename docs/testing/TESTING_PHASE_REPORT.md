# EvaluaTech testing phase report

**Date:** 12 November 2026 (Asia/Manila)
**Scope:** MVP production-readiness of EvaluaTech (School AAAAA13 Student Evaluation of Teachers)
**Outcome:** 187 of 187 automated checks pass, and 20 of 20 manual acceptance steps pass on a
production-mode installation. Fourteen defects were found during the phase and fixed; four items remain
open and are listed under [Known limits](#known-limits).

## 1. What was tested and how

| Layer | Method | Environment | Result |
| :--- | :--- | :--- | :--- |
| Backend domain logic | `php scripts/run-backend-tests.php` | In-memory SQLite, `APP_ENV=testing` | 41 tests, 1,135 assertions, all pass |
| Frontend structure and domain rules | `node --test tests/frontend/evaluatech.spec.js` | Node test runner | 82 tests in 78 groups, all pass |
| NLP service | `python -m unittest discover -s analytics/tests -p "test_*.py"` | Local Python | 50 tests, all pass |
| Browser end-to-end (demo data) | `node --test tests/frontend/e2e-interaction.spec.js` | Headless Chrome, `APP_ENV=local`, demo seed | 14 tests, all pass |
| Hygiene | `php -l` on 64 PHP files; UTF-8 no-BOM on 299 source files | Local | Clean |
| Production build | `npm run build` | Vite | Builds; zero `.map` files |
| Manual acceptance walkthrough | `scripts/manual-production-walkthrough.mjs` | Headless Chrome, `APP_ENV=production`, database from `scripts/production-init.php` | 20 steps, all pass, 0 JavaScript errors |
| Provisioning | `scripts/production-init.php` run four ways | `APP_ENV=production` | 4 of 4 behave as specified |

All of the above are run by one command, except the manual walkthrough and provisioning checks:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify-all.ps1
```

### Starting point

Before any change in this phase the matrix stood at 184 of 184 passing. That baseline did not exercise
a production-mode database at all, which is where most of the defects below were hiding.

## 2. Manual acceptance walkthrough

The walkthrough enters real details through the real user interface, the way a Registrar, a student,
an instructor and a dean would, on a server started with `APP_ENV=production` against a database that
contained only what `production-init.php` creates (seven departments, one term, one administrator).

Data entered by hand during the run:

- A class roster of 4 enrolment rows: students Maria Clara Santos (`2026-10001`), Jose Antonio Cruz
  (`2026-10002`) and Liza Mae Mendoza (`2026-10003`); courses `CS-201` Data Structures and Algorithms
  (Prof. Ramon Garcia) and `MATH-102` Discrete Mathematics (Prof. Elena Torres).
- A dean account, Dr. Teresita Villanueva (`DEAN-2026`), assigned to the Department of Computer Science.
- One complete evaluation of `CS-201`: 25 Likert answers in the pattern 5-4-5-4-3 (mean 4.20), three
  descriptive words, four open answers, and a Taglish comment ("Malinaw magturo si Prof. Garcia at
  maayos ang mga halimbawa sa linked lists. Sana mas maaga ilabas ang grades ng quizzes.").
- New passwords chosen by each person at first sign-in.

To repeat it:

```powershell
$env:APP_ENV = "production"; $env:EVALUATECH_ADMIN_PASSWORD = "<password>"
php scripts/production-init.php          # on an empty database
php -S 127.0.0.1:8140 -t public public/index.php
# second terminal
$env:BASE_URL = "http://127.0.0.1:8140"; $env:ADMIN_PASSWORD = "<password>"
node scripts/manual-production-walkthrough.mjs
```

### Results

| Step | What was done | Expected | Result | Evidence |
| :--- | :--- | :--- | :---: | :--- |
| MT-01 | Sign in as admin with the old shared demo password | Refused | PASS | [01](evidence/01-demo-password-rejected.png) |
| MT-02 | Sign in as admin with the provisioned password | Admin console; no forced change | PASS | [02](evidence/02-admin-console.png) |
| MT-03 | Open roster import | Text box empty; Import disabled | PASS | |
| MT-04 | Import a row with course code `not-a-code` | Whole import refused; reason shown | PASS | "Row 1: Invalid course_code format" |
| MT-05 | Import the 4-row roster; download the CSV | 5 accounts created (3 students, 2 instructors), 5 distinct one-time passwords, CSV correct | PASS | [03](evidence/03-roster-imported-credentials.png) |
| MT-06 | Create a dean with the password field blank | One-time password issued and shown once | PASS | [04](evidence/04-dean-created-one-time-password.png) |
| MT-07 | Create a user with initial password `evaluatech` | Refused by password policy | PASS | "Include at least one letter and one number." |
| MT-08 | Student signs in with one-time password | Sent to "Set your own password" | PASS | [05](evidence/05-student-forced-password-change.png) |
| MT-09 | Same student calls the queue API and opens `/queue` directly | API 403 `PASSWORD_CHANGE_REQUIRED`; page redirects back | PASS | |
| MT-10 | Wrong one-time password; new password containing the student number | Both refused with a clear reason | PASS | [06](evidence/06-password-policy-error.png) |
| MT-11 | Student sets a valid password | Success; queue lists CS-201 and MATH-102 | PASS | [07](evidence/07-password-changed.png), [08](evidence/08-student-queue.png) |
| MT-12 | Student completes the CS-201 evaluation | Accepted; receipt code issued | PASS | [09](evidence/09-evaluation-likert-complete.png), [10](evidence/10-evaluation-submitted-receipt.png) |
| MT-13 | Return to the queue | CS-201 Done with receipt; MATH-102 still To do | PASS | [11](evidence/11-queue-progress.png) |
| MT-14 | Verify the receipt at the public `/verify` page | Confirms completion; shows no answers | PASS | [12](evidence/12-public-receipt-verification.png) |
| MT-15 | Instructor signs in, sets own password, opens results | Report locked (grades not yet locked) | PASS | [13](evidence/13-faculty-double-blind-locked.png) |
| MT-16 | Registrar marks CS-201 grades submitted and registrar-locked | Locks applied | PASS | [14](evidence/14-registrar-locks-applied.png) |
| MT-17 | Instructor reloads results | Report unlocked; mean 4.20; the Taglish comment shown (Positive, +0.78); no student name or number anywhere | PASS | [15](evidence/15-faculty-report-unlocked.png) |
| MT-18 | Dean signs in, sets own password | Header names College of Computer Studies; 3 students enrolled; 1 evaluation submitted; 25% response rate | PASS | [16](evidence/16-dean-overview.png) |
| MT-19 | Second student on a 375 by 812 phone viewport | No horizontal overflow on the password screen or the queue | PASS | [17](evidence/17-mobile-password-change.png), [18](evidence/18-mobile-queue.png) |
| MT-20 | Sign in again with the first student's old one-time password | Refused | PASS | |

The machine-readable log is [`evidence/walkthrough-results.json`](evidence/walkthrough-results.json).
It records zero JavaScript console errors. The eight "failed to load resource" lines it lists are the
browser reporting the 401, 403 and 422 responses that steps MT-01, MT-04, MT-07, MT-09, MT-10 and
MT-20 provoke on purpose.

One-time passwords visible in screenshots 03 and 04 belonged to accounts in a throwaway database that
was deleted after the run.

### Provisioning checks

| Case | Expected | Result |
| :--- | :--- | :---: |
| `production-init.php` without `EVALUATECH_ADMIN_PASSWORD` | Refuses; database file not created | PASS |
| With a weak password (`evaluatech`) | Refuses with the policy reason | PASS |
| With a strong password on an empty database | Provisions; reports 0 clearances, 0 vault rows, 0 enrolments, 0 sections | PASS |
| Run again on the provisioned database | Refuses; existing accounts untouched | PASS |

## 3. Defects found and fixed

Severity: **Critical** = blocks production or exposes accounts or data; **High** = wrong or misleading
behaviour for real users; **Medium** = quality.

| # | Severity | Defect | How it was found | Fix |
| :--- | :--- | :--- | :--- | :--- |
| D-01 | Critical | Every student and instructor created by roster import received the same password, `evaluatech123`. Anyone knowing a student number could sign in as that student and submit their evaluations. | Code review of account creation | Random one-time password per new account, returned once to the Registrar, stored as bcrypt only, with a mandatory change at first sign-in enforced by the server (`403 PASSWORD_CHANGE_REQUIRED`) and the router. |
| D-02 | Critical | Admin-created users defaulted to `evaluatech123` when no password was typed; the form advertised it as the default. | Code review | Same one-time password mechanism; chosen initial passwords must meet the policy. |
| D-03 | Critical | Session tokens were signed with a secret hard-coded in the public repository whenever `EVALUATECH_SIGNING_SECRET` was unset, which would let anyone forge an administrator token. | Code review of `AuthController` | Uses the existing resolver that refuses to run in production without a configured secret. |
| D-04 | Critical | `scripts/production-init.php` could not run: it wrote to a column (`academic_terms.period_label`) that does not exist. No production database could be provisioned. | First provisioning attempt in this phase | Statement matched to the real schema. |
| D-05 | Critical | The initializer fell back to an administrator password published in the repository. | Code review | Password must be supplied and meet policy; checked before anything is written. |
| D-06 | High | The runtime seeded demo accounts into any empty database, including production. | Code review of `public/index.php` | Demo seeding only when `APP_ENV` is `local` or `testing`. |
| D-07 | High | Roster import through the UI always failed: the screen sends `student_id`, the server accepted only `student_institutional_id` or `student_number`. Failures showed a generic message. | Tracing the import before manual entry | Server accepts `student_id`; the screen shows the server's row-level reasons. |
| D-08 | High | Both import forms opened pre-filled with a fictional student. Submitting without editing would enrol a person who does not exist. | UI review | Forms start empty; the format is shown as placeholder text; Import is disabled while empty. |
| D-09 | High | A dean with no assigned college was silently given the College of Computer Studies if their user ID was 2. In production that is whoever was created second. | Manual walkthrough (dean overview) | Shortcut removed; scope comes only from the dean's department. Demo seed data updated to assign it. |
| D-10 | High | The dean dashboard started with invented figures (mean 4.42, 91.4% response rate, 94.2% plan resolution) that remained on screen if the request failed. | UI review during MT-18 | Starts at zero until the server answers. |
| D-11 | High | Printed rating sheets and clearance cards carried a fictional signatory ("DR. V. MENDOZA, Dean, College of Computer Studies") for every college. | Screenshot review of MT-17 | Signature lines show the role with the section's real department and college. |
| D-12 | Medium | While an instructor's report was loading, the page stated "No open-ended narrative comments have been submitted", and the section skeleton stayed visible after sections had loaded. | Screenshot review of MT-17 | Shows "Loading anonymous comments…" until the report arrives; skeleton ends when sections load. |
| D-13 | Medium | The dean header did not say which college was being shown. | Screenshot review of MT-18 | College name added to the header. |
| D-14 | Medium | A dead compatibility class (`ExaminationPermitService`) kept the retired permit terminology in the service layer. | Checkpoint 59 review | Removed; callers use `EvaluationClearanceService`. |

Critical: 5. High: 6. Medium: 3.

### New behaviour added in this phase

- **Change password** for every signed-in user (lock icon in the top bar; `POST /api/auth/password`),
  with its own attempt throttle and an audit entry that contains no password material.
- **One-time password panel** after roster import and user creation, with CSV download that
  neutralises spreadsheet formula injection.
- An administrator password reset now also requires the user to choose a new password at next sign-in.

## 4. Automated coverage added

| Test | Covers |
| :--- | :--- |
| Backend `test_41_one_time_credentials_and_mandatory_password_change` (36 assertions) | Unique one-time passwords on import; no reissue on re-import; shared default rejected; forced-change flag on login and token; six password-policy and injection cases; successful change; old password dead; admin creation and reset; audit log free of passwords. |
| Frontend Group 77 | Route, router guard order, store API call, labelled inputs with autocomplete hints, screen-reader rule list, 44 px actions, top-bar link. |
| Frontend Group 78 | No shared default password anywhere in the UI source; import forms start empty; no fictional people; credentials panel present on both import paths; CSV formula neutralisation. |
| Frontend Group 33 (updated) | Official records carry role titles and no hard-coded signatory names. |

## 5. Observations confirmed during testing

- **Scope containment works.** In an early run the dean was assigned to a department in another
  college and correctly saw zero students and zero evaluations for the Computer Studies data.
- **Anonymity held end to end.** The instructor's report showed the comment text, sentiment and topic
  but no student name or number; the public receipt check confirmed completion without any answers;
  the dean saw who is pending, never what anyone wrote.
- **Atomic import held.** One malformed row left the database unchanged.
- **The content security policy is active.** It blocked an `eval`-style construct in an early version
  of the test script itself.

## Known limits

1. **`impeccable detect` was not run.** The CLI is not installed on the test machine, so the design
   anti-slop gate named in `AGENTS.md` is unverified for this change set.
2. **The test harness has its own schema.** `tests/TestCase.php` builds tables separately from
   `DatabaseSchemaManager` and includes a column the real schema lacks, which is why the broken
   initializer (D-04) was never caught by the backend suite. The harness should build its schema from
   `DatabaseSchemaManager`.
3. **Transport security is a deployment responsibility.** The walkthrough ran over local HTTP. Real
   use requires HTTPS in front of the application (see `docs/PRODUCTION_DEPLOYMENT.md`).
4. **Not load-tested in this phase.** Concurrency was covered only by the existing idempotency and
   locking tests, not by a multi-user run against the production-mode server. The MySQL path of the
   schema manager was not exercised; all runs used SQLite.

The accuracy of the sentiment model was outside this phase; it is reported separately in
`docs/analysis/MODEL_COMPARISON_REPORT.md` and `analytics/models/model_metadata.json`.
