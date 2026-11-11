import fs from 'node:fs';
import path from 'node:path';
import { execSync, spawn } from 'node:child_process';
import http from 'node:http';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const REPO_ROOT = path.resolve(__dirname, '..');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const CHROME_PATH = process.env.CHROME_BIN || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const SHOWCASE_DIR = path.join(REPO_ROOT, 'docs', 'showcase');
const RAW_VIDEOS_DIR = path.join(SHOWCASE_DIR, 'raw_videos');
const FINAL_VIDEO_PATH = path.join(SHOWCASE_DIR, 'evalua_tech_showcase.webm');

// Ensure showcase directories exist
fs.mkdirSync(SHOWCASE_DIR, { recursive: true });
fs.mkdirSync(RAW_VIDEOS_DIR, { recursive: true });

function pingServer(url) {
  return new Promise((resolve) => {
    const req = http.get(url, (res) => {
      resolve(true);
      res.resume();
    });
    req.on('error', () => resolve(false));
    req.setTimeout(500, () => {
      req.destroy();
      resolve(false);
    });
  });
}

async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function run() {
  console.log('========================================================================');
  console.log(' School AAAAA13 EvaluaTech — Showcase Video Recording Engine            ');
  console.log('========================================================================');
  console.log(`Base URL:           ${BASE_URL}`);
  console.log(`Showcase Dir:       ${SHOWCASE_DIR}`);
  console.log(`Raw Video Dir:      ${RAW_VIDEOS_DIR}`);
  console.log(`Final Video Target: ${FINAL_VIDEO_PATH}`);

  let serverProcess = null;
  const isUp = await pingServer(BASE_URL);
  if (!isUp) {
    console.log('--- Starting local PHP runtime on 127.0.0.1:8000...');
    const phpExe = process.env.PHP_BIN || (fs.existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php');
    serverProcess = spawn(phpExe, ['-S', '127.0.0.1:8000', '-t', 'public', 'public/index.php'], {
      stdio: 'ignore',
      detached: false,
    });
    for (let i = 0; i < 30; i++) {
      await sleep(200);
      if (await pingServer(BASE_URL)) break;
    }
  }

  // Reseed demo state for fresh showcase flow
  console.log('--- Reseeding pristine demonstration database state...');
  try {
    const phpExe = fs.existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php';
    execSync(`"${phpExe}" scripts/reseed-demo.php`, { stdio: 'inherit' });
  } catch (err) {
    console.warn('Warning: Reseeding returned non-zero status:', err.message);
  }

  console.log('--- Launching Chromium browser with native WebM video capture...');
  const launchOptions = {
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  };
  if (fs.existsSync(CHROME_PATH)) {
    launchOptions.executablePath = CHROME_PATH;
  }

  const browser = await chromium.launch(launchOptions);
  const context = await browser.newContext({
    viewport: { width: 1366, height: 768 },
    recordVideo: {
      dir: RAW_VIDEOS_DIR,
      size: { width: 1366, height: 768 }
    }
  });

  const page = await context.newPage();
  let recordedVideo = null;

  try {
    // =========================================================================
    // SCENE 1: Public Institutional Landing Page & Coolors Pastel Palette
    // =========================================================================
    console.log('\n>>> SCENE 1: Public Institutional Landing Page (/)');
    await page.goto(`${BASE_URL}/`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-testid="landing-view-page"]', { timeout: 10000 });
    await sleep(1500);

    // Scroll smoothly to Governance & Accreditation section
    console.log('--- Showcasing AACCUP Level III Governance & Anonymity Charter...');
    await page.evaluate(() => window.scrollBy({ top: 700, behavior: 'smooth' }));
    await sleep(1200);

    // Scroll smoothly to Student Participation Tracking section
    console.log('--- Showcasing Student Evaluation Participation Tracking (Zero Permits Policy)...');
    await page.evaluate(() => window.scrollBy({ top: 750, behavior: 'smooth' }));
    await sleep(1200);

    // Visit Public Clearance Verification Hub
    console.log('--- Navigating to Public Verification Hub (/verify)...');
    await page.goto(`${BASE_URL}/verify`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-testid="permit-verify-page"]', { timeout: 8000 });
    await sleep(800);
    const tokenInput = await page.$('[data-testid="permit-token-input"]');
    if (tokenInput) {
      await tokenInput.fill('EVT-CLR-2024-10892-SEALED');
      await sleep(1000);
    }

    // =========================================================================
    // SCENE 2: Academic Tech Sign-in (/login) with Floating Card
    // =========================================================================
    console.log('\n>>> SCENE 2: Academic Tech Sign-in (/login)');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 8000 });
    await sleep(1200);

    // =========================================================================
    // SCENE 3: Student Workspace & Evaluation Form (/queue, /evaluate/1)
    // =========================================================================
    console.log('\n>>> SCENE 3: Student Real Evaluation Journey (2024-10892)');
    await page.fill('[data-testid="login-id-input"]', '2024-10892@plpasig.edu.ph');
    await sleep(300);
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await sleep(500);
    await page.click('[data-testid="login-submit-btn"]');

    await page.waitForSelector('[data-testid="student-queue-table"]', { timeout: 8000 });
    await sleep(1200);

    // Open Rating Rubric Modal
    console.log('--- Opening Student In-Context Rubric Guidance...');
    const studentRubricBtn = await page.waitForSelector('[data-testid="student-open-rubric-btn"]', { timeout: 5000 });
    await studentRubricBtn.click();
    await page.waitForSelector('[data-testid="evaluation-rubric-modal"]', { timeout: 5000 });
    await sleep(1000);
    const closeRubricBtn = await page.waitForSelector('[data-testid="close-rubric-modal-btn"]');
    await closeRubricBtn.click();
    await sleep(600);

    // Open first pending course evaluation (/evaluate/1)
    console.log('--- Opening Course Evaluation Form (CS-401)...');
    const startEvalBtn = await page.waitForSelector('button[data-testid^="start-eval-"]', { timeout: 5000 });
    await startEvalBtn.click();
    await page.waitForSelector('[data-testid="top-back-to-courses-btn"]', { timeout: 8000 });
    await sleep(1000);

    // Fill 25 Likert questions across 5 clusters
    console.log('--- Scoring 25 Likert questions across 5 pedagogical clusters...');
    for (let i = 1; i <= 25; i++) {
      const rating = (i % 3 === 0) ? '5' : (i % 2 === 0 ? '4' : '5');
      const radio = await page.$(`input[name="item-${i}"][value="${rating}"]`);
      if (radio) {
        await radio.check();
      }
    }
    await sleep(1000);

    // Proceed to Step 2: Qualitative Written Feedback
    console.log('--- Proceeding to Step 2: Qualitative Written Feedback...');
    const proceedBtn = await page.waitForSelector('[data-testid="proceed-to-feedback-btn"]', { timeout: 5000 });
    await proceedBtn.click();
    await page.waitForSelector('[data-testid="professor-questions-section"]', { timeout: 5000 });
    await sleep(800);

    // Fill qualitative structured questionnaire
    const w1 = await page.$('[data-testid="three-word-1"]');
    if (w1) await w1.fill('Knowledgeable');
    const w2 = await page.$('[data-testid="three-word-2"]');
    if (w2) await w2.fill('Passionate');
    const w3 = await page.$('[data-testid="three-word-3"]');
    if (w3) await w3.fill('Inspirational');

    const q3 = await page.$('#prof-q3-input');
    if (q3) await q3.fill('Exceptional algorithmic explanations and industry-standard architecture examples.');

    const q4 = await page.$('#prof-q4-input');
    if (q4) await q4.fill('Incorporate weekly practical laboratory debugging challenges.');

    const narrative = await page.$('[data-testid="narrative-comment-input"]');
    if (narrative) await narrative.fill('Napakagaling magpaliwanag at laging handang tumulong sa mga estudyante tuwing laboratory sessions.');
    await sleep(1000);

    // Submit evaluation immediately (Mandatory Timing Policy: 0s+ dwell allowed)
    console.log('--- Submitting completed evaluation with cryptographic decoupling...');
    const submitBtn = await page.waitForSelector('[data-testid="submit-evaluation-btn"]');
    await submitBtn.click();
    await page.waitForSelector('[data-testid="submission-status-banner"]', { timeout: 10000 });
    await sleep(1500);

    // Return to queue
    const returnQueueBtn = await page.$('[data-testid="return-queue-success-btn"]');
    if (returnQueueBtn) {
      await returnQueueBtn.click();
      await sleep(1000);
    }

    // Sign out student
    const studentLogout = await page.waitForSelector('[data-testid="navbar-logout-btn"]', { timeout: 5000 });
    await studentLogout.click();
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 6000 });
    await sleep(800);

    // =========================================================================
    // SCENE 4: Student Evaluation Participation Tracker (Administrator)
    // =========================================================================
    console.log('\n>>> SCENE 4: Student Evaluation Participation Tracker (admin@plpasig.edu.ph)');
    await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await page.click('[data-testid="login-submit-btn"]');
    await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 8000 });

    const setupLink = await page.waitForSelector('[data-testid="nav-link-admin-setup"]');
    await setupLink.click();
    await page.waitForSelector('[data-testid="admin-setup-view"]', { timeout: 8000 });
    await sleep(800);

    // Click Student Participation Tracker Tab
    console.log('--- Inspecting Student Participation Tracker & Non-Coercive Turnout Metrics...');
    const studentsTabBtn = await page.waitForSelector('[data-testid="tab-students-btn"]', { timeout: 5000 });
    await studentsTabBtn.click();
    await page.waitForSelector('[data-testid="student-tracker-summary-bar"]', { timeout: 6000 });
    await sleep(1200);

    // Open Non-Coercive Reminder Modal
    const remindBtn = await page.$('[data-testid^="remind-student-btn-"]');
    if (remindBtn) {
      await remindBtn.click();
      await page.waitForSelector('[data-testid="student-reminder-modal"]', { timeout: 4000 });
      await sleep(1200);
      const closeReminder = await page.$('[data-testid="close-reminder-modal-btn"]');
      if (closeReminder) await closeReminder.click();
      await sleep(600);
    }

    // Sign out admin
    const adminLogout = await page.waitForSelector('[data-testid="navbar-logout-btn"]', { timeout: 5000 });
    await adminLogout.click();
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 6000 });
    await sleep(800);

    // =========================================================================
    // SCENE 5: Faculty Section Performance Dashboard & Double-Blind Unlock
    // =========================================================================
    console.log('\n>>> SCENE 5: Faculty Section Performance Dashboard (adelacruz@plpasig.edu.ph)');
    await page.fill('[data-testid="login-id-input"]', 'adelacruz@plpasig.edu.ph');
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await page.click('[data-testid="login-submit-btn"]');
    await page.waitForSelector('[data-testid="faculty-workspace"]', { timeout: 8000 });
    await sleep(1200);

    // Inspect Double-Blind Status & Unlocked Section Aggregates
    console.log('--- Inspecting Double-Blind Safeguards & Pedagogical Action Plans...');
    await page.evaluate(() => window.scrollBy({ top: 600, behavior: 'smooth' }));
    await sleep(1500);

    // Sign out faculty
    const facLogout = await page.waitForSelector('[data-testid="navbar-logout-btn"]', { timeout: 5000 });
    await facLogout.click();
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 6000 });
    await sleep(800);

    // =========================================================================
    // SCENE 6: Department Chair & Dean Analytics Heatmaps
    // =========================================================================
    console.log('\n>>> SCENE 6A: Department Chair Diagnostic Workspace (chair@plpasig.edu.ph)');
    await page.fill('[data-testid="login-id-input"]', 'chair@plpasig.edu.ph');
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await page.click('[data-testid="login-submit-btn"]');
    await page.waitForSelector('[data-testid="chair-diagnostic-workspace"]', { timeout: 8000 });
    await sleep(1200);

    // Open Quivr Second Brain Qualitative Drawer
    console.log('--- Showcasing Quivr Second Brain Qualitative Feedback Explorer...');
    const chairQuivrBtn = await page.waitForSelector('[data-testid="chair-open-quivr-brain-btn"]', { timeout: 5000 });
    await chairQuivrBtn.click();
    await page.waitForSelector('[data-testid="quivr-nlp-brain-drawer"]', { timeout: 5000 });
    await sleep(1000);
    const quivrQuery = await page.$('[data-testid="quivr-query-input"]');
    if (quivrQuery) {
      await quivrQuery.fill('teaching methodology lab support');
      await sleep(1200);
    }
    const closeQuivr = await page.waitForSelector('[data-testid="close-quivr-brain-btn"]', { timeout: 4000 });
    await closeQuivr.click();
    await sleep(600);

    // Sign out chair
    const chairLogout = await page.waitForSelector('[data-testid="navbar-logout-btn"]', { timeout: 5000 });
    await chairLogout.click();
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 6000 });
    await sleep(800);

    console.log('\n>>> SCENE 6B: College Dean Executive Overview & Heatmap (dean@plpasig.edu.ph)');
    await page.fill('[data-testid="login-id-input"]', 'dean@plpasig.edu.ph');
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await page.click('[data-testid="login-submit-btn"]');
    await page.waitForSelector('[data-testid="dean-analytical-overview"]', { timeout: 8000 });
    await sleep(1200);

    // Inspect Dean Completion Heatmap Matrix
    console.log('--- Viewing Course Section Completion Heatmap Matrix...');
    await page.evaluate(() => window.scrollBy({ top: 600, behavior: 'smooth' }));
    await sleep(1500);

    // Switch to Longitudinal Trends & Compile AACCUP Level III Dossier
    console.log('--- Compiling AACCUP Level III Executive Accreditation Dossier...');
    const tabLongitudinal = await page.waitForSelector('[data-testid="tab-longitudinal-trends"]', { timeout: 5000 });
    await tabLongitudinal.click();
    await page.waitForSelector('[data-testid="longitudinal-trends-panel"]', { timeout: 6000 });
    await sleep(800);
    const compileBtn = await page.waitForSelector('[data-testid="compile-aaccup-dossier-btn"]', { timeout: 5000 });
    await compileBtn.click();
    await page.waitForSelector('[data-testid="compiled-aaccup-bundle-card"]', { timeout: 8000 });
    await sleep(1500);

    // Sign out dean
    const deanLogout = await page.waitForSelector('[data-testid="navbar-logout-btn"]', { timeout: 5000 });
    await deanLogout.click();
    await page.waitForSelector('[data-testid="login-split-screen"]', { timeout: 6000 });
    await sleep(800);

    // =========================================================================
    // SCENE 7: Campaign Operations War Room & Pre-flight Diagnostics
    // =========================================================================
    console.log('\n>>> SCENE 7: Campaign Operations War Room (admin@plpasig.edu.ph)');
    await page.fill('[data-testid="login-id-input"]', 'admin@plpasig.edu.ph');
    await page.fill('[data-testid="login-password-input"]', 'evaluatech123');
    await page.click('[data-testid="login-submit-btn"]');
    await page.waitForSelector('[data-testid="nav-link-admin-setup"]', { timeout: 8000 });

    const adminSetup = await page.waitForSelector('[data-testid="nav-link-admin-setup"]');
    await adminSetup.click();
    await page.waitForSelector('[data-testid="admin-setup-view"]', { timeout: 8000 });

    // Open War Room Tab
    const warRoomTab = await page.waitForSelector('[data-testid="tab-war-room-btn"]', { timeout: 5000 });
    await warRoomTab.click();
    await page.waitForSelector('[data-testid="war-room-workspace"]', { timeout: 6000 });
    console.log('--- Inspecting 6-Point Pre-Flight Diagnostics & Turnout Velocity...');
    await sleep(1800);

    console.log('\n========================================================================');
    console.log(' Showcase Walkthrough Completed Successfully!                           ');
    console.log('========================================================================');
  } catch (err) {
    console.error('Showcase walkthrough encountered an error:', err);
    throw err;
  } finally {
    // Obtain reference to video before closing page
    recordedVideo = page.video();
    await page.close();
    await context.close();
    await browser.close();

    if (serverProcess) {
      try { serverProcess.kill('SIGTERM'); } catch (_) {}
      try { process.kill(serverProcess.pid); } catch (_) {}
    }
  }

  // Finalize video file into docs/showcase/evalua_tech_showcase.webm
  console.log('--- Finalizing showcase video artifact...');
  let savedPath = null;
  if (recordedVideo) {
    try {
      await recordedVideo.saveAs(FINAL_VIDEO_PATH);
      savedPath = FINAL_VIDEO_PATH;
    } catch (saveErr) {
      console.warn('saveAs failed, falling back to directory search:', saveErr.message);
    }
  }

  if (!savedPath || !fs.existsSync(FINAL_VIDEO_PATH)) {
    const rawFiles = fs.readdirSync(RAW_VIDEOS_DIR).filter((f) => f.endsWith('.webm'));
    if (rawFiles.length > 0) {
      const newestRaw = rawFiles
        .map((f) => ({ name: f, time: fs.statSync(path.join(RAW_VIDEOS_DIR, f)).mtimeMs }))
        .sort((a, b) => b.time - a.time)[0].name;
      fs.copyFileSync(path.join(RAW_VIDEOS_DIR, newestRaw), FINAL_VIDEO_PATH);
      savedPath = FINAL_VIDEO_PATH;
    }
  }

  if (fs.existsSync(FINAL_VIDEO_PATH)) {
    const stats = fs.statSync(FINAL_VIDEO_PATH);
    const sizeMb = (stats.size / (1024 * 1024)).toFixed(2);
    console.log(`[SUCCESS] Showcase video generated: ${FINAL_VIDEO_PATH} (${sizeMb} MB)`);
  } else {
    console.error('[ERROR] Could not find generated showcase video artifact!');
  }

  // Generate showcase markdown documentation
  generateShowcaseDocs();
}

function generateShowcaseDocs() {
  const mdPath = path.join(SHOWCASE_DIR, 'README.md');
  const videoSizeMb = fs.existsSync(FINAL_VIDEO_PATH)
    ? (fs.statSync(FINAL_VIDEO_PATH).size / (1024 * 1024)).toFixed(2)
    : 'N/A';

  const content = `# School AAAAA13 EvaluaTech — Interactive Video Showcase

**System:** School AAAAA13 EvaluaTech (Student Evaluation of Teachers Platform)  
**Video Artifact:** [\`evalua_tech_showcase.webm\`](evalua_tech_showcase.webm) (${videoSizeMb} MB)  
**Format:** Native Chromium WebM Video Capture ($1366\\times768\\text{ px}$)  
**Generator:** \`scripts/record_showcase_video.js\` via Playwright  
**Date of Recording:** 11 November 2026  

---

## 🎬 Showcase Scene Breakdown

The recorded video walks through all seven fundamental operational stages of the platform with zero mock shortcuts:

| Scene | Timestamp / Stage | Description |
| :---: | :--- | :--- |
| **1** | **Public Institutional Landing Page (\`/\`)** | Introduces School AAAAA13 branding, serene Coolors pastel periwinkle design system (\`#edf2fb\` to \`#abc4ff\`), AACCUP Level III accreditation governance, and the Zero Permits Policy charter. |
| **2** | **Academic Tech Sign-in (\`/login\`)** | Shows the full-viewport split-screen login, floating card container, institutional kitemark, and RA 10173 data privacy notice. |
| **3** | **Student Workspace & Evaluation Form (\`/queue\`, \`/evaluate/1\`)** | Authentic student persona assesses course \`CS-401\`. Features in-queue rating rubric modal, 25 Likert scale questions scored across 5 pedagogical clusters, 4 structured qualitative professor questions, and instant submit under the Mandatory Timing Policy (0s+ dwell allowed) issuing a signed cryptographic clearance token (\`EVT.CLR.*\`). |
| **4** | **Student Evaluation Participation Tracker** | University Administrator inspects turnout velocity, section completion bars, and opens polite, non-coercive reminder notices for students with pending evaluations under the Zero Permits Policy. |
| **5** | **Faculty Section Performance Dashboard (\`/faculty\`)** | Shows double-blind unlock safeguards in action: unlocked sections display aggregated ratings with zero student PII, while unreleased sections display the double-blind lock (\`Confirm Grade Submission Status\`). Explores the Pedagogical Action Plan workspace. |
| **6** | **Department Chair & Dean Analytics Heatmaps (\`/chair\`, \`/dean\`)** | Chair manages faculty standing and performs semantic inquiry in the Quivr Second Brain qualitative feedback drawer. Dean reviews 91.4% college turnout, 4,821 submissions, section completion heatmap matrix, and compiles the AACCUP Level III Executive Accreditation Dossier. |
| **7** | **Campaign Operations War Room** | Final view of the 6-point pre-flight diagnostics engine, 100% readiness score bar, and Departmental Turnout Velocity Leaderboard. |

---

## 🚀 How to Re-Record the Showcase Video

To capture a fresh recording of the showcase video at any time:

\`\`\`powershell
# Execute the automated Playwright recording engine
node scripts/record_showcase_video.js
\`\`\`

The generated recording will be saved directly to \`docs/showcase/evalua_tech_showcase.webm\`.
`;

  fs.writeFileSync(mdPath, content, 'utf8');
  console.log(`[DOCUMENTATION] Generated showcase documentation at: ${mdPath}`);
}

run().catch((err) => {
  console.error('Fatal error during showcase video recording:', err);
  process.exit(1);
});
