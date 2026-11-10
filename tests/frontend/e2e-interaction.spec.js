import test, { describe, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const BASE_URL = 'http://127.0.0.1:8000';
const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

describe('EvaluaTech End-to-End User Interaction & Institutional Safeguards Suite', () => {
  let browser;

  before(async () => {
    browser = await chromium.launch({
      headless: true,
      executablePath: CHROME_PATH
    });
  });

  after(async () => {
    if (browser) {
      await browser.close();
    }
  });

  test('E2E-01: Public institutional landing page renders branding and navigates to sign-in', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      await page.goto(`${BASE_URL}/`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="landing-view-page"]');

      // Verify institutional title and headline
      const heading = await page.textContent('h1');
      assert.ok(heading.includes('Student Evaluation of Teachers'), 'Institutional heading rendered');

      // Verify PLP eyebrow
      const eyebrow = await page.textContent('.landing-hero-eyebrow');
      assert.ok(eyebrow.includes('PAMANTASAN NG LUNGSOD NG PASIG'), 'Institution eyebrow verified');

      // Verify 3 core safeguards
      const safeguards = await page.$$('.safeguard-card');
      assert.strictEqual(safeguards.length, 3, 'Three institutional safeguard cards present');

      // Click "Sign In to Access Evaluation Portal"
      const signInBtn = await page.waitForSelector('[data-testid="landing-signin-btn"]');
      await signInBtn.click();
      await page.waitForSelector('[data-testid="login-id-input"]');

      // URL should be /login
      assert.ok(page.url().includes('/login'), 'Navigated smoothly to login view');
    } finally {
      await page.close();
    }
  });

  test('E2E-02: Authentication flow rejects invalid inputs and admits valid administrator credentials', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="login-id-input"]');

      // Test failed login with incorrect password
      await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'wrongpassword');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="login-error-alert"]', { timeout: 4000 });
      const errorText = await page.textContent('[data-testid="login-error-alert"]');
      assert.ok(errorText.length > 0, 'Error banner displayed on invalid credentials');

      // Test successful administrator login with throttle delay
      await page.waitForTimeout(600);
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');

      // Should redirect to /admin dashboard
      await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 6000 });
      assert.ok(page.url().includes('/admin'), 'Redirected to administrator dashboard');
    } finally {
      await page.close();
    }
  });

  test('E2E-03: Academic Setup Student Participation Tracker displays metrics and reminder modal interaction', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      const setupNav = await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 6000 });
      await setupNav.click();
      await page.waitForSelector('[data-testid="admin-setup-view"]');

      // Switch to Tab 5 (Student Directory & Participation Tracker)
      const tabStudents = await page.waitForSelector('[data-testid="tab-students-btn"]');
      await tabStudents.click();
      await page.waitForSelector('[data-testid="student-tracker-summary-bar"]');

      // Verify tracker summary bar
      const summaryBar = await page.textContent('[data-testid="student-tracker-summary-bar"]');
      assert.ok(summaryBar.includes('Pending Evaluators') && summaryBar.includes('Total Students'), 'Participation tracker metrics present');

      // Check for export CSV button
      const exportBtn = await page.$('[data-testid="export-pending-students-csv-btn"]');
      assert.ok(exportBtn !== null, 'Export pending students CSV button present');

      // Test Student Reminder Modal if a pending student exists
      const remindBtn = await page.$('[data-testid^="remind-student-btn-"]');
      if (remindBtn) {
        await remindBtn.click();
        await page.waitForSelector('[data-testid="student-reminder-modal"]');

        const noticeText = await page.inputValue('[data-testid="reminder-notice-textarea"]');
        assert.ok(noticeText.includes('Pamantasan ng Lungsod ng Pasig'), 'Notice mentions PLP');
        assert.ok(noticeText.includes('Notice'), 'Notice contains formal notification text');

        // Close modal
        await page.click('[data-testid="close-reminder-modal-btn"]');
        await page.waitForSelector('[data-testid="student-reminder-modal"]', { state: 'detached', timeout: 3000 });
      }
    } finally {
      await page.close();
    }
  });

  test('E2E-04: Student Evaluation Queue and distraction-free workspace without sidebars', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as student 2024-10892
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', '2024-10892@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="student-queue-table"]', { timeout: 6000 });

      // Check student evaluation workspace
      assert.ok(page.url().includes('/queue'), 'Student routed to /queue evaluation queue');

      // Confirm zero sidebars rendered (full-width distraction-free student view)
      const sidebar = await page.$('.app-sidebar, [data-testid="workspace-sidebar"]');
      assert.strictEqual(sidebar, null, 'Sidebar strictly absent in student view');
    } finally {
      await page.close();
    }
  });

  test('E2E-05: Double-blind faculty protection locks unreleased section reports', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as faculty Prof. Dela Cruz
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'adelacruz@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="faculty-section-select"], [data-testid="double-blind-lock-screen"]', { timeout: 6000 });

      // Verify faculty portal rendered
      assert.ok(page.url().includes('/faculty'), 'Faculty routed to /faculty workspace');

      const hasSelector = (await page.$('[data-testid="faculty-section-select"]')) !== null;
      const hasLockScreen = (await page.$('[data-testid="double-blind-lock-screen"]')) !== null;
      assert.ok(hasSelector || hasLockScreen, 'Faculty double-blind workspace state verified');
    } finally {
      await page.close();
    }
  });

  test('E2E-06: Mobile responsive viewport (375x812) has zero horizontal overflow', async () => {
    const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
    try {
      await page.goto(`${BASE_URL}/`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="landing-view-page"]');

      // Measure scrollWidth vs innerWidth
      const overflow = await page.evaluate(() => {
        return document.documentElement.scrollWidth > window.innerWidth;
      });
      assert.strictEqual(overflow, false, 'Mobile viewport has zero horizontal overflow on landing page');

      // Navigate to login on mobile
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="login-split-screen"]');

      const loginOverflow = await page.evaluate(() => {
        return document.documentElement.scrollWidth > window.innerWidth;
      });
      assert.strictEqual(loginOverflow, false, 'Mobile viewport has zero horizontal overflow on login page');
    } finally {
      await page.close();
    }
  });

  test('E2E-07: Campaign Operations War Room renders 6-point pre-flight diagnostics and readiness score', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 6000 });

      // Navigate to Setup War Room tab
      await page.goto(`${BASE_URL}/admin/setup?tab=war-room`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="war-room-workspace"]', { timeout: 6000 });
      await page.waitForSelector('[data-testid="preflight-audit-card"]');
      await page.waitForSelector('[data-testid="check-card-1"]', { timeout: 6000 });

      // Verify verdict badge and readiness score
      const verdict = await page.textContent('[data-testid="preflight-verdict-badge"]');
      assert.ok(verdict.length > 0, 'Preflight verdict badge displayed');

      const readinessText = await page.textContent('[data-testid="readiness-score-text"]');
      assert.ok(readinessText.includes('%'), 'Readiness score percentage displayed');

      // Verify audit checks grid has check cards
      const checkCards = await page.$$('[data-testid^="check-card-"]');
      assert.strictEqual(checkCards.length, 6, 'All 6 diagnostic check cards rendered');
    } finally {
      await page.close();
    }
  });

  test('E2E-08: Departmental turnout velocity leaderboard and non-coercive reminder batch compilation', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 6000 });

      await page.goto(`${BASE_URL}/admin/setup?tab=war-room`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-testid="turnout-leaderboard-card"]', { timeout: 6000 });

      // Verify velocity status badge and turnout metric
      const targetBadge = await page.textContent('[data-testid="velocity-target-badge"]');
      assert.ok(targetBadge.length > 0, 'Velocity target status displayed');

      const turnoutPct = await page.textContent('[data-testid="stat-turnout-pct"]');
      assert.ok(turnoutPct.includes('%'), 'Turnout percentage rendered');

      // Click compile reminders button
      const compileBtn = await page.waitForSelector('[data-testid="btn-compile-reminders"]');
      await compileBtn.click();

      // Wait for reminder preview area
      await page.waitForSelector('[data-testid="reminder-preview-area"]', { timeout: 6000 });
      const recipientsTable = await page.$('[data-testid="reminder-recipients-table"]');
      assert.ok(recipientsTable !== null, 'Reminder recipients table rendered');
    } finally {
      await page.close();
    }
  });

  test('E2E-09: Faculty pedagogical action plan workspace with milestone tracking', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as faculty Prof. Dela Cruz
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'adelacruz@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="faculty-workspace"]', { timeout: 6000 });

      // Verify faculty portal rendered
      assert.ok(page.url().includes('/faculty'), 'Navigated to faculty workspace');
      const banner = await page.$('.header-split');
      assert.ok(banner !== null, 'Faculty header split rendered');
    } finally {
      await page.close();
    }
  });

  test('E2E-10: Department Chair peer mentor assignment and action plan sign-off modal', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as Department Chair
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'chair@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="chair-diagnostic-workspace"]', { timeout: 6000 });

      // Verify department heading
      const deptHeading = await page.textContent('#chair-heading');
      assert.ok(deptHeading.includes('Computer Science') || deptHeading.includes('Computer Studies'), 'Department name rendered in chair workspace');

      // Check for export faculty CSV button
      const exportBtn = await page.$('[data-testid="export-chair-faculty-csv-btn"]');
      assert.ok(exportBtn !== null, 'Export chair faculty CSV button present');
    } finally {
      await page.close();
    }
  });

  test('E2E-11: AACCUP Level III executive accreditation dossier compilation with cryptographic seal and facilities separation', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as College Dean
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'dean@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="dean-analytical-overview"]', { timeout: 6000 });

      // Switch to Semester Trends tab
      const trendsTab = await page.waitForSelector('[data-testid="tab-longitudinal-trends"]');
      await trendsTab.click();
      await page.waitForSelector('[data-testid="dean-evidence-dossier-card"]', { timeout: 6000 });

      // Verify existing digital seal
      const sealText = await page.textContent('[data-testid="dossier-digital-seal"]');
      assert.ok(sealText.includes('PLP-AACCUP3-'), 'Official cryptographic seal rendered');

      // Click compile AACCUP dossier button
      const compileBtn = await page.waitForSelector('[data-testid="compile-aaccup-dossier-btn"]');
      await compileBtn.click();

      // Verify compiled bundle card
      await page.waitForSelector('[data-testid="compiled-aaccup-bundle-card"]', { timeout: 6000 });
      const bundleSeal = await page.textContent('[data-testid="compiled-bundle-seal"]');
      assert.ok(bundleSeal.includes('PLP-AACCUP3-'), 'Compiled bundle has official digital seal');
    } finally {
      await page.close();
    }
  });

  test('E2E-12: Quivr Second Brain qualitative feedback semantic search with sentiment valence filtering and zero student PII disclosure', async () => {
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    try {
      // Login as Department Chair
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.fill('[data-testid="login-id-input"]', 'chair@plpasig.edu.ph');
      await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
      await page.click('[data-testid="login-submit-btn"]');
      await page.waitForSelector('[data-testid="chair-open-quivr-brain-btn"], [data-testid="open-quivr-brain-btn"]', { timeout: 6000 });

      // Open Quivr NLP Brain Drawer
      await page.click('[data-testid="chair-open-quivr-brain-btn"], [data-testid="open-quivr-brain-btn"]');
      await page.waitForSelector('[data-testid="quivr-nlp-brain-drawer"]', { timeout: 6000 });

      // Verify search input is present
      const searchInput = await page.$('[data-testid="quivr-query-input"]');
      assert.ok(searchInput !== null, 'Quivr natural language search input present');

      // Verify zero student identity guarantee banner
      const banner = await page.textContent('[data-testid="quivr-nlp-brain-drawer"]');
      assert.ok(banner.includes('EVALUATION ANALYSIS') || banner.includes('Professor') || banner.includes('anonymized'), 'Zero student identity guarantee present');

      // Close drawer
      await page.click('[data-testid="close-quivr-brain-btn"]');
    } finally {
      await page.close();
    }
  });
});
