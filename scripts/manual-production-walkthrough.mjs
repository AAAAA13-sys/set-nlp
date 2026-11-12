/**
 * EvaluaTech production-mode manual acceptance walkthrough.
 *
 * Drives the real UI (headless Chrome) against a server running with APP_ENV=production on a database
 * freshly provisioned by scripts/production-init.php, entering real details by hand-equivalent
 * keystrokes: roster import, account creation, first sign-in password change, a full student
 * evaluation, public receipt verification, registrar locks, and faculty/dean read-outs.
 *
 *   BASE_URL=http://127.0.0.1:8140 ADMIN_PASSWORD=... node scripts/manual-production-walkthrough.mjs
 *
 * Evidence (screenshots + step log JSON) is written to docs/testing/evidence/.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs', 'testing', 'evidence');
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8140';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || '';
const CHROME_PATH = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
if (!ADMIN_PASSWORD) {
  console.error('ADMIN_PASSWORD is required (the value given to scripts/production-init.php).');
  process.exit(2);
}
fs.mkdirSync(OUT, { recursive: true });

const steps = [];
const consoleErrors = [];
const httpErrorLogs = [];
let shotNo = 0;

async function step(id, title, fn) {
  const started = Date.now();
  try {
    const detail = await fn();
    steps.push({ id, title, result: 'PASS', ms: Date.now() - started, detail: detail ?? null });
    console.log(`PASS ${id} ${title}${detail ? ` — ${typeof detail === 'string' ? detail : JSON.stringify(detail)}` : ''}`);
  } catch (err) {
    steps.push({ id, title, result: 'FAIL', ms: Date.now() - started, detail: String(err?.message || err) });
    console.log(`FAIL ${id} ${title} — ${err?.message || err}`);
    throw err;
  }
}

async function shot(page, name) {
  shotNo += 1;
  const file = `${String(shotNo).padStart(2, '0')}-${name}.png`;
  await page.screenshot({ path: path.join(OUT, file), fullPage: false });
  return file;
}

function expect(cond, msg) {
  if (!cond) throw new Error(msg);
}

async function newPage(browser, viewport = { width: 1366, height: 768 }) {
  const ctx = await browser.newContext({ viewport });
  const page = await ctx.newPage();
  // Browsers log every 4xx/5xx response as a console error; negative-test steps produce those on purpose,
  // so HTTP status lines are recorded separately from genuine JavaScript errors.
  page.on('console', (m) => {
    if (m.type() !== 'error') return;
    (m.text().startsWith('Failed to load resource') ? httpErrorLogs : consoleErrors).push(`${page.url()} :: ${m.text()}`);
  });
  page.on('pageerror', (e) => consoleErrors.push(`${page.url()} :: ${e.message}`));
  return page;
}

async function login(page, id, password) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('[data-testid="login-id-input"]');
  await page.fill('[data-testid="login-id-input"]', id);
  await page.fill('[data-testid="login-password-input"]', password);
  await page.click('[data-testid="login-submit-btn"]');
}

async function apiStatus(page, url) {
  return page.evaluate(async (u) => {
    const token = sessionStorage.getItem('evaluatech_auth_token') || localStorage.getItem('evaluatech_auth_token');
    const r = await fetch(u, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
    return { status: r.status, body: await r.json().catch(() => ({})) };
  }, url);
}

async function changeOneTimePassword(page, oneTime, next) {
  await page.waitForURL('**/account/password', { timeout: 8000 });
  await page.waitForSelector('[data-testid="account-password-card"]');
  await page.fill('[data-testid="current-password-input"]', oneTime);
  await page.fill('[data-testid="new-password-input"]', next);
  await page.fill('[data-testid="confirm-password-input"]', next);
  await page.click('[data-testid="save-password-btn"]');
  await page.waitForSelector('[data-testid="password-change-success"]', { timeout: 6000 });
}

const ROSTER = [
  { student_id: '2026-10001', student_name: 'Maria Clara Santos', course_code: 'CS-201', course_title: 'Data Structures and Algorithms', section_code: 'A', instructor_name: 'Prof. Ramon Garcia', faculty_email: 'rgarcia@aaaaa13.edu', department_code: 'CCS', department_name: 'Department of Computer Science' },
  { student_id: '2026-10001', student_name: 'Maria Clara Santos', course_code: 'MATH-102', course_title: 'Discrete Mathematics', section_code: 'A', instructor_name: 'Prof. Elena Torres', faculty_email: 'etorres@aaaaa13.edu', department_code: 'CCS', department_name: 'Department of Computer Science' },
  { student_id: '2026-10002', student_name: 'Jose Antonio Cruz', course_code: 'CS-201', course_title: 'Data Structures and Algorithms', section_code: 'A', instructor_name: 'Prof. Ramon Garcia', faculty_email: 'rgarcia@aaaaa13.edu', department_code: 'CCS', department_name: 'Department of Computer Science' },
  { student_id: '2026-10003', student_name: 'Liza Mae Mendoza', course_code: 'MATH-102', course_title: 'Discrete Mathematics', section_code: 'A', instructor_name: 'Prof. Elena Torres', faculty_email: 'etorres@aaaaa13.edu', department_code: 'CCS', department_name: 'Department of Computer Science' },
];

const browser = await chromium.launch({ headless: true, executablePath: CHROME_PATH });
const creds = {};
let clearanceToken = '';
let exitCode = 0;

try {
  const admin = await newPage(browser);

  await step('MT-01', 'Shared demo password is rejected on a production database', async () => {
    await login(admin, 'admin@aaaaa13.edu', 'evaluatech123');
    await admin.waitForSelector('[data-testid="login-error-alert"]', { timeout: 5000 });
    return shot(admin, 'demo-password-rejected');
  });

  await step('MT-02', 'Registrar admin signs in with the provisioned password', async () => {
    await login(admin, 'admin@aaaaa13.edu', ADMIN_PASSWORD);
    await admin.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 8000 });
    expect(!admin.url().includes('/account/password'), 'provisioned admin should not be forced to change password');
    return shot(admin, 'admin-console');
  });

  await step('MT-03', 'Roster import form starts empty (no prefilled sample person)', async () => {
    await admin.waitForSelector('[data-testid="roster-import-textarea"]');
    const value = await admin.inputValue('[data-testid="roster-import-textarea"]');
    expect(value === '', `textarea should be empty, found ${value.length} chars`);
    expect(await admin.isDisabled('[data-testid="submit-roster-import-btn"]'), 'import button disabled while empty');
  });

  await step('MT-04', 'Malformed roster row is rejected with the server reason and nothing imported', async () => {
    await admin.fill('[data-testid="roster-import-textarea"]', JSON.stringify([{ student_id: '2026-19999', course_code: 'not-a-code', section_code: 'A', instructor_name: 'Prof. X' }]));
    await admin.click('[data-testid="submit-roster-import-btn"]');
    await admin.waitForSelector('[data-testid="roster-import-status"]');
    const text = await admin.textContent('[data-testid="roster-import-status"]');
    expect(/Invalid course_code/.test(text), `expected server reason, got: ${text}`);
    return text.trim();
  });

  await step('MT-05', 'Real roster (3 students, 2 instructors, 2 courses) imports and issues one-time passwords', async () => {
    await admin.fill('[data-testid="roster-import-textarea"]', JSON.stringify(ROSTER, null, 2));
    await admin.click('[data-testid="submit-roster-import-btn"]');
    await admin.waitForSelector('[data-testid="issued-credentials-panel"]', { timeout: 8000 });
    const rows = await admin.$$eval('[data-testid="issued-credential-row"]', (trs) =>
      trs.map((tr) => [...tr.querySelectorAll('td')].map((td) => td.textContent.trim())));
    for (const [id, , role, pwd] of rows) creds[id] = { role, pwd };
    expect(rows.length === 5, `expected 5 new accounts, got ${rows.length}`);
    expect(new Set(rows.map((r) => r[3])).size === 5, 'one-time passwords must be unique');
    const file = await shot(admin, 'roster-imported-credentials');
    const [download] = await Promise.all([
      admin.waitForEvent('download'),
      admin.click('[data-testid="download-credentials-btn"]'),
    ]);
    const csv = fs.readFileSync(await download.path(), 'utf8');
    expect(csv.split('\n')[0] === 'institutional_id,name,email,role,temporary_password', 'CSV header');
    expect(csv.includes('2026-10001'), 'CSV contains imported student');
    await admin.click('[data-testid="dismiss-credentials-btn"]');
    return { accounts: rows.map((r) => `${r[0]} (${r[2]})`), screenshot: file };
  });

  await step('MT-06', 'Admin creates a Dean account by hand; blank password issues a one-time password', async () => {
    await admin.click('[data-testid="nav-link-admin-setup"]');
    await admin.waitForSelector('[data-testid="admin-setup-view"]');
    await admin.click('[data-testid="tab-users-btn"]');
    await admin.click('[data-testid="add-user-btn"]');
    await admin.waitForSelector('[data-testid="new-user-modal"]');
    await admin.fill('[data-testid="user-name-input"]', 'Dr. Teresita Villanueva');
    await admin.fill('[data-testid="user-id-input"]', 'DEAN-2026');
    await admin.fill('[data-testid="user-email-input"]', 'tvillanueva@aaaaa13.edu');
    await admin.selectOption('[data-testid="user-role-select"]', 'dean');
    // Department 1 = Department of Computer Science (College of Computer Studies) from production-init.
    await admin.selectOption('[data-testid="user-dept-select"]', '1');
    await admin.click('[data-testid="submit-create-user-btn"]');
    await admin.waitForSelector('[data-testid="issued-credentials-panel"]', { timeout: 8000 });
    const row = await admin.$$eval('[data-testid="issued-credential-row"] td', (tds) => tds.map((td) => td.textContent.trim()));
    creds[row[0]] = { role: row[2], pwd: row[3] };
    expect(row[0] === 'DEAN-2026' && row[2] === 'dean', `unexpected row ${row}`);
    return shot(admin, 'dean-created-one-time-password');
  });

  await step('MT-07', 'Weak initial password chosen by admin is refused', async () => {
    await admin.click('[data-testid="dismiss-credentials-btn"]');
    await admin.click('[data-testid="add-user-btn"]');
    await admin.fill('[data-testid="user-name-input"]', 'Weak Password Test');
    await admin.fill('[data-testid="user-id-input"]', 'FAC-WEAK1');
    await admin.fill('[data-testid="user-email-input"]', 'weak.test@aaaaa13.edu');
    await admin.fill('[data-testid="user-password-input"]', 'evaluatech');
    await admin.click('[data-testid="submit-create-user-btn"]');
    await admin.waitForSelector('[data-testid="setup-error-alert"]', { timeout: 6000 });
    const text = await admin.textContent('[data-testid="setup-error-alert"]');
    expect(/Initial password rejected/.test(text), `expected policy error, got ${text}`);
    return text.trim();
  });

  const student = await newPage(browser);
  await step('MT-08', 'Student signs in with the one-time password and is forced to /account/password', async () => {
    await login(student, '2026-10001', creds['2026-10001'].pwd);
    await student.waitForURL('**/account/password', { timeout: 8000 });
    return shot(student, 'student-forced-password-change');
  });

  await step('MT-09', 'Server blocks other APIs until the password is changed', async () => {
    const res = await apiStatus(student, '/api/student/queue');
    expect(res.status === 403 && res.body.code === 'PASSWORD_CHANGE_REQUIRED', `got ${res.status} ${res.body.code}`);
    await student.goto(`${BASE_URL}/queue`);
    await student.waitForURL('**/account/password', { timeout: 6000 });
    return `${res.status} ${res.body.code}; /queue redirects back`;
  });

  await step('MT-10', 'Wrong one-time password and a password containing the student number are refused', async () => {
    await student.fill('[data-testid="current-password-input"]', 'not-the-password1');
    await student.fill('[data-testid="new-password-input"]', 'Sampaguita2026');
    await student.fill('[data-testid="confirm-password-input"]', 'Sampaguita2026');
    await student.click('[data-testid="save-password-btn"]');
    await student.waitForSelector('[data-testid="password-change-error"]');
    const wrong = (await student.textContent('[data-testid="password-change-error"]')).trim();
    await student.fill('[data-testid="current-password-input"]', creds['2026-10001'].pwd);
    await student.fill('[data-testid="new-password-input"]', 'pass2026-10001x');
    await student.fill('[data-testid="confirm-password-input"]', 'pass2026-10001x');
    await student.click('[data-testid="save-password-btn"]');
    await student.waitForFunction(() => /student number/i.test(document.querySelector('[data-testid="password-change-error"]')?.textContent || ''));
    const personal = (await student.textContent('[data-testid="password-change-error"]')).trim();
    await shot(student, 'password-policy-error');
    return { wrong, personal };
  });

  await step('MT-11', 'Student sets a valid password and reaches the evaluation queue (2 courses)', async () => {
    await student.fill('[data-testid="current-password-input"]', creds['2026-10001'].pwd);
    await student.fill('[data-testid="new-password-input"]', 'Sampaguita2026');
    await student.fill('[data-testid="confirm-password-input"]', 'Sampaguita2026');
    await student.click('[data-testid="save-password-btn"]');
    await student.waitForSelector('[data-testid="password-change-success"]');
    await shot(student, 'password-changed');
    await student.click('[data-testid="continue-workspace-btn"]');
    await student.waitForSelector('[data-testid="student-queue-table"]', { timeout: 8000 });
    await student.waitForSelector('[data-testid="queue-row-CS-201"]');
    await student.waitForSelector('[data-testid="queue-row-MATH-102"]');
    return shot(student, 'student-queue');
  });

  await step('MT-12', 'Student completes the CS-201 evaluation: 25 varied Likert answers + Taglish comment', async () => {
    await student.click('[data-testid="start-eval-CS-201"]');
    await student.waitForSelector('[data-testid="likert-row-1"]', { timeout: 8000 });
    const pattern = [5, 4, 5, 4, 3];
    for (let i = 1; i <= 25; i += 1) {
      const value = pattern[(i - 1) % pattern.length];
      await student.click(`[data-testid="likert-row-${i}"] label.likert-radio-label >> nth=${value - 1}`);
    }
    await shot(student, 'evaluation-likert-complete');
    await student.click('[data-testid="proceed-to-feedback-btn"]');
    await student.waitForSelector('[data-testid="narrative-comment-input"]');
    await student.fill('[data-testid="narrative-comment-input"]',
      'Malinaw magturo si Prof. Garcia at maayos ang mga halimbawa sa linked lists. Sana mas maaga ilabas ang grades ng quizzes.');
    for (let w = 1; w <= 3; w += 1) {
      await student.fill(`[data-testid="three-word-${w}"]`, ['clear', 'patient', 'organized'][w - 1]);
    }
    const openText = await student.$$('[data-testid^="improvement-topic-row-"] input, [data-testid="prof-question-3"] input, [data-testid="prof-question-4"] input');
    for (const input of openText) {
      await input.fill('Clear explanations with worked examples; more practice problems before exams would help.');
    }
    await student.click('[data-testid="submit-evaluation-btn"]');
    await student.waitForSelector('[data-testid="student-clearance-receipt-card"], [data-testid="issued-clearance-token"], [data-testid="receipt-clearance-token"]', { timeout: 10000 });
    const tokenEl = await student.$('[data-testid="receipt-clearance-token"]') || await student.$('[data-testid="issued-clearance-token"]');
    clearanceToken = tokenEl ? (await tokenEl.textContent()).trim() : '';
    expect(clearanceToken.length > 10, 'clearance receipt token issued');
    return shot(student, 'evaluation-submitted-receipt');
  });

  await step('MT-13', 'Queue shows CS-201 completed and MATH-102 still pending', async () => {
    await student.goto(`${BASE_URL}/queue`);
    await student.waitForSelector('[data-testid="queue-row-CS-201"]');
    const done = await student.textContent('[data-testid="queue-row-CS-201"]');
    const pending = await student.textContent('[data-testid="queue-row-MATH-102"]');
    expect(!/start|evaluate now/i.test(done) || /complet|done|receipt/i.test(done), `CS-201 row: ${done}`);
    expect(await student.$('[data-testid="start-eval-MATH-102"]'), 'MATH-102 still startable');
    await shot(student, 'queue-progress');
    return { done: done.replace(/\s+/g, ' ').trim().slice(0, 90), pending: pending.replace(/\s+/g, ' ').trim().slice(0, 90) };
  });

  await step('MT-14', 'Receipt verifies publicly at /verify without revealing answers', async () => {
    const pub = await newPage(browser);
    await pub.goto(`${BASE_URL}/verify`, { waitUntil: 'domcontentloaded' });
    const input = await pub.waitForSelector('input');
    await input.fill(clearanceToken);
    await pub.keyboard.press('Enter');
    await pub.waitForTimeout(1500);
    const body = await pub.textContent('main');
    expect(/verif|valid|cleared|complete/i.test(body), 'verification result shown');
    expect(!body.includes('Malinaw magturo'), 'answers must never appear in verification');
    const file = await shot(pub, 'public-receipt-verification');
    await pub.context().close();
    return file;
  });

  const faculty = await newPage(browser);
  // Faculty IDs are generated on import, so try each issued faculty credential for Prof. Garcia's email.
  await step('MT-15', 'Faculty (Prof. Ramon Garcia) reaches a locked report before grades are registrar-locked', async () => {
    const facultyIds = Object.keys(creds).filter((k) => creds[k].role === 'faculty');
    let signedIn = false;
    for (const id of facultyIds) {
      await login(faculty, 'rgarcia@aaaaa13.edu', creds[id].pwd);
      try {
        await faculty.waitForURL('**/account/password', { timeout: 4000 });
        await changeOneTimePassword(faculty, creds[id].pwd, 'Narra2026Faculty');
        signedIn = true;
        break;
      } catch (_) { /* wrong credential pair, try next */ }
    }
    expect(signedIn, 'faculty one-time password worked');
    await faculty.click('[data-testid="continue-workspace-btn"]');
    await faculty.waitForSelector('[data-testid="faculty-workspace"]', { timeout: 8000 });
    await faculty.waitForSelector('[data-testid="double-blind-lock-screen"], [data-testid="faculty-empty-sections"], [data-testid="faculty-section-select"]', { timeout: 8000 });
    expect(!(await faculty.$('[data-testid="faculty-unlocked-report"]')), 'report must stay locked');
    return shot(faculty, 'faculty-double-blind-locked');
  });

  await step('MT-16', 'Registrar marks CS-201 grades submitted and registrar-locked', async () => {
    await admin.goto(`${BASE_URL}/admin`);
    await admin.waitForSelector('[data-testid="registrar-locks-table"]', { timeout: 8000 });
    await admin.click('[data-testid="toggle-grades-CS-201"]');
    await admin.waitForTimeout(800);
    await admin.click('[data-testid="toggle-registrar-CS-201"]');
    await admin.waitForTimeout(800);
    return shot(admin, 'registrar-locks-applied');
  });

  await step('MT-17', 'Faculty now sees the anonymous CS-201 report', async () => {
    await faculty.reload();
    await faculty.waitForSelector('[data-testid="faculty-workspace"]');
    const select = await faculty.$('[data-testid="faculty-section-select"]');
    if (select) {
      const options = await faculty.$$eval('[data-testid="faculty-section-select"] option', (o) => o.map((x) => ({ v: x.value, t: x.textContent })));
      const cs = options.find((o) => /CS-201/.test(o.t));
      if (cs) await faculty.selectOption('[data-testid="faculty-section-select"]', cs.v);
    }
    await faculty.waitForSelector('[data-testid="faculty-unlocked-report"]', { timeout: 8000 });
    // The written comment must reach the lecturer (after the report loads), without any identity.
    await faculty.waitForFunction(
      () => (document.querySelector('[data-testid="faculty-unlocked-report"]')?.textContent || '').includes('Malinaw magturo'),
      null,
      { timeout: 10000 },
    );
    const report = await faculty.textContent('[data-testid="faculty-unlocked-report"]');
    expect(!report.includes('2026-10001') && !report.includes('Maria Clara Santos'), 'report must not identify the student');
    await faculty.waitForSelector('.skeleton-loader, [data-testid="skeleton-loader"]', { state: 'detached', timeout: 5000 }).catch(() => {});
    await faculty.locator('.anonymized-comments-section').scrollIntoViewIfNeeded();
    return shot(faculty, 'faculty-report-unlocked');
  });

  const dean = await newPage(browser);
  await step('MT-18', 'Dean signs in with one-time password, sets own password, sees college overview', async () => {
    await login(dean, 'DEAN-2026', creds['DEAN-2026'].pwd);
    await changeOneTimePassword(dean, creds['DEAN-2026'].pwd, 'Molave2026Dean');
    await dean.click('[data-testid="continue-workspace-btn"]');
    await dean.waitForSelector('[data-testid="dean-analytical-overview"]', { timeout: 10000 });
    const college = (await dean.textContent('[data-testid="dean-college-name"]')).trim();
    expect(college === 'College of Computer Studies', `dean header college: ${college}`);
    // Participation is real data: 3 students enrolled in the college, none finished all, 1 evaluation submitted.
    // Participation panel and KPI strip load from separate endpoints; read the KPI value element directly.
    const submittedKpi = () => [...document.querySelectorAll('.metric-card')]
      .find((c) => /Evaluations submitted/.test(c.textContent))?.querySelector('.metric-value')?.textContent.trim();
    // Inline logic: the app's CSP (correctly) forbids eval/new Function in page context.
    await dean.waitForFunction(() => {
      const t = document.querySelector('main')?.innerText || '';
      const kpi = [...document.querySelectorAll('.metric-card')]
        .find((c) => /Evaluations submitted/.test(c.textContent))?.querySelector('.metric-value')?.textContent.trim();
      return /Students enrolled\s*3/.test(t) && kpi === '1';
    }, null, { timeout: 15000 }).catch(() => {});
    const text = await dean.innerText('main');
    const submitted = await dean.evaluate(submittedKpi);
    expect(/Students enrolled\s*3/.test(text), 'dean sees 3 enrolled students in the college');
    expect(submitted === '1', `dean sees the 1 submitted evaluation (KPI shows "${submitted}")`);
    expect(!text.includes('Maria Clara Santos') || !/Malinaw magturo/.test(text), 'participation never pairs a student with an answer');
    await shot(dean, 'dean-overview');
    return { college, enrolled: 3, submitted: 1 };
  });

  await step('MT-19', 'Mobile 375x812: password screen and queue have no horizontal overflow', async () => {
    const mobile = await newPage(browser, { width: 375, height: 812 });
    await login(mobile, '2026-10002', creds['2026-10002'].pwd);
    await mobile.waitForURL('**/account/password', { timeout: 8000 });
    const overflowPwd = await mobile.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    await shot(mobile, 'mobile-password-change');
    await changeOneTimePassword(mobile, creds['2026-10002'].pwd, 'Ilang2026Ilang');
    await mobile.click('[data-testid="continue-workspace-btn"]');
    await mobile.waitForSelector('[data-testid="student-queue-table"]', { timeout: 8000 });
    const overflowQueue = await mobile.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    await shot(mobile, 'mobile-queue');
    expect(overflowPwd <= 0 && overflowQueue <= 0, `overflow px: password=${overflowPwd} queue=${overflowQueue}`);
    await mobile.context().close();
    return { overflowPwd, overflowQueue };
  });

  await step('MT-20', 'Old one-time password no longer signs in after the change', async () => {
    const p = await newPage(browser);
    await login(p, '2026-10001', creds['2026-10001'].pwd);
    await p.waitForSelector('[data-testid="login-error-alert"]', { timeout: 5000 });
    await p.context().close();
  });
} catch (_) {
  exitCode = 1;
} finally {
  await browser.close();
  const report = {
    base_url: BASE_URL,
    run_at: new Date().toISOString(),
    passed: steps.filter((s) => s.result === 'PASS').length,
    failed: steps.filter((s) => s.result === 'FAIL').length,
    steps,
    console_errors: consoleErrors,
    expected_http_error_logs: httpErrorLogs,
  };
  fs.writeFileSync(path.join(OUT, 'walkthrough-results.json'), `${JSON.stringify(report, null, 2)}\n`);
  console.log(`\n${report.passed} passed, ${report.failed} failed, ${consoleErrors.length} JS console errors, ${httpErrorLogs.length} expected HTTP error logs`);
  process.exit(exitCode || (report.failed > 0 ? 1 : 0));
}
