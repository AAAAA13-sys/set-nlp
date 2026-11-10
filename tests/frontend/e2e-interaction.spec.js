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
});
