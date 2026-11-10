import { chromium } from 'playwright';

const ARTIFACT_DIR = 'C:/Users/Ivan/.gemini/antigravity/brain/06629e52-e44e-4545-b82d-425165c408a6';

async function run() {
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
  });

  const page = await browser.newPage({
    viewport: { width: 1366, height: 768 }
  });

  // 1. Capture Public Landing Page
  console.log('Navigating to Public Landing Page at http://127.0.0.1:8000/');
  await page.goto('http://127.0.0.1:8000/');
  await page.waitForSelector('[data-testid="landing-view-page"]');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: `${ARTIFACT_DIR}/live_public_landing_page.png`, fullPage: false });
  console.log('Captured live_public_landing_page.png');

  // 2. Click Sign In from Landing Page
  console.log('Clicking Sign In on landing page...');
  await page.click('[data-testid="landing-signin-btn"]');
  await page.waitForSelector('[data-testid="login-id-input"]');
  await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
  await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
  await page.click('[data-testid="login-submit-btn"]');
  await page.waitForTimeout(2000);

  // 3. Client-side navigation to Academic Setup
  console.log('Navigating to Academic Setup via navbar link...');
  const setupLink = await page.waitForSelector('[data-testid="nav-link-admin-setup"]');
  await setupLink.click();
  await page.waitForTimeout(1500);

  // 4. Switch to Tab 5 (Student Directory & Participation Tracker)
  console.log('Switching to Tab 5 (Student Directory)...');
  const studentsTabBtn = await page.waitForSelector('[data-testid="tab-students-btn"]');
  await studentsTabBtn.click();
  await page.waitForSelector('[data-testid="student-tracker-summary-bar"]');
  await page.waitForTimeout(1000);
  await page.screenshot({ path: `${ARTIFACT_DIR}/live_student_participation_tracker.png`, fullPage: false });
  console.log('Captured live_student_participation_tracker.png');

  // 5. Open Student Reminder Notice Modal for a pending student
  const remindBtn = await page.$('[data-testid^="remind-student-btn-"]');
  if (remindBtn) {
    console.log('Opening Student Reminder Notice Modal...');
    await remindBtn.click();
    await page.waitForSelector('[data-testid="student-reminder-modal"]');
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${ARTIFACT_DIR}/live_student_reminder_modal.png`, fullPage: false });
    console.log('Captured live_student_reminder_modal.png');
  } else {
    console.log('No pending student remind button found in current term view.');
  }

  await browser.close();
  console.log('Browser capture complete!');
}

run().catch((err) => {
  console.error('Capture failed:', err);
  process.exit(1);
});
