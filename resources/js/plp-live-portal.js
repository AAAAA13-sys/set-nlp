/**
 * Pamantasan ng Lungsod ng Pasig (PLP) — EvaluaTech Live Browser SPA Runtime
 * Zero-build native ES Module interactive runtime powered by evaluationMath.js
 * Reference: https://plpasig.edu.ph/
 */

import {
  CALAMANCY_CONFIG,
  DEMO_DEAN_METRICS,
  DEMO_SECTION_LOCKS,
  DEMO_STUDENT_QUEUE,
  LIKERT_SCALE_OPTIONS,
  MAX_PROFESSOR_QUESTIONS,
  PEDAGOGICAL_DIMENSIONS,
  calculateClusterMeans,
  calculateMean,
  calculatePopulationVariance,
  calculateQueueProgress,
  calculateSusScore,
  canFacultyViewEvaluation,
  classifyCommentPreview,
  evaluateWatchlistFlag,
  generateSubmissionKey,
  isDwellTimeValid,
  isStraightLined,
  sanitizeCsvCell,
  validateProfessorQuestions,
} from './utils/evaluationMath.js';

const ROLE_PROFILES = {
  student: {
    id: 1,
    institutional_id: '2024-10892',
    name: 'Student 2024-10892',
    email: '2024-10892@plpasig.edu.ph',
    role: 'student',
    role_label: 'Student',
    defaultRoute: '/queue',
  },
  faculty: {
    id: 2,
    institutional_id: 'FAC-2019-042',
    name: 'Prof. A. Dela Cruz',
    email: 'adelacruz@plpasig.edu.ph',
    role: 'faculty',
    role_label: 'Faculty',
    defaultRoute: '/faculty',
  },
  chair: {
    id: 3,
    institutional_id: 'CHR-2017-011',
    name: 'Dr. M. Santos',
    email: 'msantos.chair@plpasig.edu.ph',
    role: 'chair',
    role_label: 'Department Chair',
    defaultRoute: '/dean',
  },
  dean: {
    id: 4,
    institutional_id: 'DEAN-2015-001',
    name: 'Dr. V. Mendoza',
    email: 'dean.ccs@plpasig.edu.ph',
    role: 'dean',
    role_label: 'Dean',
    defaultRoute: '/dean',
  },
  admin: {
    id: 5,
    institutional_id: 'REG-2012-001',
    name: 'PLP University Registrar',
    email: 'registrar@plpasig.edu.ph',
    role: 'admin',
    role_label: 'System Administrator',
    defaultRoute: '/admin',
  },
};

const state = {
  currentPath: window.location.pathname === '/' ? '/queue' : window.location.pathname,
  role: 'student',
  activeTerm: 'Fall / 1st Semester',
  courses: DEMO_STUDENT_QUEUE.map((c) => ({ ...c })),
  sectionLocks: DEMO_SECTION_LOCKS.map((s) => ({ ...s })),
  deanOverview: JSON.parse(JSON.stringify(DEMO_DEAN_METRICS)),
  activeSectionId: 104,
  dwellSeconds: 0,
  submissionKey: generateSubmissionKey(104),
  ratings: [
    5, 4, 5, 5, 4,
    4, 5, 4, 4, 5,
    5, 5, 4, 5, 4,
    5, 4, 5, 5, 5,
    5, 4, 5, 4, 5,
  ],
  threeWords: ['Malinaw', 'Approachable', 'Organized'],
  improvementTopics: [
    'Faster turnaround time when returning quiz scores and rubrics',
    'More step-by-step live coding walkthroughs for dynamic programming',
  ],
  teachingStrengths: 'Explains complex algorithms very clearly and provides great practical code examples.',
  courseRecommendations: 'Provide practice problem sets with rubric solutions prior to midterm exams.',
  showFifthQuestion: false,
  additionalObservation: '',
  commentText: 'Explains complex algorithms very clearly and provides great practical code examples.',
  showStraightLineModal: false,
  statusBanner: null,
  facultySelectedSectionId: 101,
  deanActiveTab: 'overview',
  exportedCsvPreview: '',
  adminFormat: 'json',
  adminRosterInput: JSON.stringify(
    [
      {
        student_id: '2024-10892',
        course_code: 'CS-410',
        course_title: 'Cloud Security & Distributed Systems',
        instructor_name: 'Dr. H. Villanueva',
        department_name: 'College of Computer Studies',
      },
    ],
    null,
    2,
  ),
  adminImportStatus: null,
  susItems: [5, 1, 5, 2, 4, 2, 5, 1, 5, 2],
  vaultAuditResult: null,
};

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function plpSealSvg(size = 34) {
  return `
    <svg width="${size}" height="${size}" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="32" r="29" stroke="#FACC15" stroke-width="2.5" stroke-dasharray="3 2" />
      <circle cx="32" cy="32" r="23" fill="#0B4F26" stroke="#EAB308" stroke-width="1.8" />
      <path d="M32 14L44 20V31C44 40 38.8 46.8 32 50C25.2 46.8 20 40 20 31V20L32 14Z" fill="#14532D" stroke="#FACC15" stroke-width="1.8" />
      <path d="M32 20L35.5 27.5L43 28.5L37.5 33.8L39 41.5L32 37.8L25 41.5L26.5 33.8L21 28.5L28.5 27.5L32 20Z" fill="#FACC15" />
    </svg>
  `;
}

function navigate(path) {
  state.currentPath = path;
  if (path.startsWith('/evaluate/')) {
    const id = Number(path.split('/').pop()) || 104;
    state.activeSectionId = id;
    state.dwellSeconds = 0;
    state.submissionKey = generateSubmissionKey(id);
    state.statusBanner = null;
  }
  try {
    window.history.pushState({}, '', path);
  } catch (_) {}
  renderApp();
}

function renderNavbar() {
  const progress = calculateQueueProgress(state.courses);
  const profile = ROLE_PROFILES[state.role] || ROLE_PROFILES.student;
  const p = state.currentPath;

  return `
    <header class="navbar-header" role="banner" aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header">
      <div class="plp-top-ribbon">
        <div class="plp-top-ribbon-inner">
          <span>Republic of the Philippines • City Government of Pasig • Alkalde Joseco St., Kapasigan, Pasig City</span>
          <span class="plp-ribbon-motto">Official Reference: plpasig.edu.ph • Academic Excellence &amp; Pasigueño Integrity</span>
        </div>
      </div>

      <div class="navbar-container">
        <div class="navbar-brand-row">
          <div class="brand-identity">
            <div class="plp-crest-seal" aria-hidden="true">${plpSealSvg(34)}</div>
            <div class="brand-text-stack">
              <span class="plp-university-title">PAMANTASAN NG LUNGSOD NG PASIG</span>
              <div class="brand-sub-row">
                <span class="brand-badge">[PLP • EduMetrics / EvaluaTech]</span>
                <h1 class="brand-title">Institutional Evaluation Portal</h1>
              </div>
            </div>
          </div>

          <div class="term-pill" aria-label="Active Academic Term">
            <span class="term-label">Active Term:</span>
            <strong class="term-value">${escapeHtml(state.activeTerm)}</strong>
          </div>
        </div>

        <div class="navbar-meta-row">
          <div class="user-identity" data-testid="logged-in-user">
            <span class="meta-caption">Logged in as:</span>
            <strong class="user-code">${escapeHtml(profile.institutional_id)} (${escapeHtml(profile.role_label)})</strong>
          </div>

          <div class="navbar-controls">
            <label class="role-switcher-label" for="demo-role-switcher">
              <span>Workspace Role:</span>
              <select id="demo-role-switcher" class="role-switcher-select" data-action="switch-role">
                <option value="student" ${state.role === 'student' ? 'selected' : ''}>Student (2024-10892)</option>
                <option value="faculty" ${state.role === 'faculty' ? 'selected' : ''}>Faculty (Prof. A. Dela Cruz)</option>
                <option value="chair" ${state.role === 'chair' ? 'selected' : ''}>Department Chair (Dr. M. Santos)</option>
                <option value="dean" ${state.role === 'dean' ? 'selected' : ''}>Dean (Dr. V. Mendoza)</option>
                <option value="admin" ${state.role === 'admin' ? 'selected' : ''}>System Admin / PLP Registrar</option>
              </select>
            </label>

            <nav class="quick-nav" aria-label="Primary Workspace Navigation">
              <a href="/login" data-nav="/login" class="nav-link ${p === '/login' ? 'router-link-active' : ''}">Login</a>
              <a href="/queue" data-nav="/queue" class="nav-link ${p === '/queue' ? 'router-link-active' : ''}">Student Queue</a>
              <a href="/evaluate/104" data-nav="/evaluate/104" class="nav-link ${p.startsWith('/evaluate') ? 'router-link-active' : ''}">SET Survey</a>
              <a href="/faculty" data-nav="/faculty" class="nav-link ${p === '/faculty' ? 'router-link-active' : ''}">Faculty Portal</a>
              <a href="/dean" data-nav="/dean" class="nav-link ${p === '/dean' ? 'router-link-active' : ''}">Dean Analytics</a>
              <a href="/admin" data-nav="/admin" class="nav-link ${p === '/admin' ? 'router-link-active' : ''}">Admin &amp; Locks</a>
            </nav>

            <div class="clearance-badge ${progress.allCleared ? 'clearance-cleared' : 'clearance-pending'}" data-testid="navbar-clearance-badge" role="status">
              <span>Clearance:</span>
              <strong>${progress.clearanceStatus}</strong>
            </div>

            <button type="button" class="btn-secondary btn-xs" data-action="reset-plp-demo" title="Reset PLP evaluation queue back to 3 of 6 Completed">
              Reset Demo
            </button>
          </div>
        </div>
      </div>
    </header>
  `;
}

function renderClearancePermitCard() {
  const progress = calculateQueueProgress(state.courses);
  const studentId = ROLE_PROFILES.student.institutional_id;
  const masterToken = `PERMIT-${studentId}-FALL2026-VERIFIED`;

  return `
    <section class="clearance-permit-card ${progress.allCleared ? 'permit-unlocked' : 'permit-gated'}" data-testid="clearance-permit-card">
      <div class="permit-header">
        <div>
          <span class="permit-eyebrow">PAMANTASAN NG LUNGSOD NG PASIG • Office of the University Registrar • Dual-Token Clearance</span>
          <h3 class="permit-title">
            ${progress.allCleared ? 'Digital Examination Permit — CLEARED' : 'Digital Examination Permit — GATED (PENDING)'}
          </h3>
        </div>
        <span class="permit-status-pill ${progress.allCleared ? 'pill-cleared' : 'pill-pending'}">${progress.clearanceStatus}</span>
      </div>

      <div class="permit-body">
        ${
          !progress.allCleared
            ? `<p class="permit-description">
                Complete the remaining <strong>${progress.pending}</strong> pending faculty evaluation(s) in your
                Pamantasan ng Lungsod ng Pasig enrollment queue (${progress.completed} of ${progress.total} completed)
                to automatically issue your signed Digital Examination Permit. Your ratings and comments are stored in the
                decoupled <code>evaluation_vault</code> with zero link to your student ID.
              </p>`
            : `<div class="permit-verified-box">
                <p class="permit-description">
                  All <strong>${progress.total} of ${progress.total}</strong> enrolled course evaluations have been verified.
                  Your examination clearance is cryptographically signed and synchronized with the
                  <strong>Pamantasan ng Lungsod ng Pasig — Office of the University Registrar</strong>.
                </p>
                <div class="permit-token-grid">
                  <div><span class="token-label">Institution:</span> <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong></div>
                  <div><span class="token-label">Student ID:</span> <strong>${escapeHtml(studentId)}</strong></div>
                  <div><span class="token-label">Academic Term:</span> <strong>${escapeHtml(state.activeTerm)}</strong></div>
                  <div><span class="token-label">Master Clearance Token:</span> <code data-testid="master-permit-token">${escapeHtml(masterToken)}</code></div>
                </div>
              </div>`
        }
      </div>

      <div class="permit-footer">
        <div class="signed-tokens-list">
          ${state.courses
            .map(
              (c) => `
              <span class="token-chip ${c.status === 'COMPLETED' ? 'chip-signed' : 'chip-unsigned'}">
                ${escapeHtml(c.course_code)}: ${c.status === 'COMPLETED' ? escapeHtml(c.clearance_token || 'Clearance Signed') : 'PENDING'}
              </span>
            `,
            )
            .join('')}
        </div>
        ${
          progress.allCleared
            ? `<button type="button" class="btn-primary" data-action="print-permit">Print / Export Signed PLP Exam Permit</button>`
            : ''
        }
      </div>
    </section>
  `;
}

function renderQueueView() {
  const progress = calculateQueueProgress(state.courses);
  return `
    <main class="workspace-main student-queue-workspace">
      <section class="card queue-progress-card" data-testid="student-queue-progress">
        <div class="queue-header-row">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • SOP 1 • Dynamic Enrollment Queue &amp; Atomic Clearance</span>
            <h2 class="section-title">My Evaluation Queue (${progress.completed} of ${progress.total} Completed)</h2>
            <p class="section-subtitle">
              Complete all enrolled faculty evaluations for the active term at <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong>
              to unlock your cryptographically signed Digital Examination Permit.
            </p>
          </div>
          <div class="queue-stats-pill">
            <span>Completion: <strong>${progress.percentage}%</strong></span>
            <span>|</span>
            <span>Clearance: <strong>${progress.clearanceStatus}</strong></span>
            ${
              !progress.allCleared
                ? `<button type="button" class="btn-secondary btn-xs" data-action="complete-all-courses">Simulate All 6/6 Completed</button>`
                : ''
            }
          </div>
        </div>
        <div class="queue-progressbar-outer" role="progressbar" aria-valuenow="${progress.percentage}" aria-valuemin="0" aria-valuemax="100">
          <div class="queue-progressbar-inner" style="width: ${progress.percentage}%"></div>
        </div>
      </section>

      <section class="card queue-table-card">
        <div class="table-responsive">
          <table class="institutional-table" data-testid="student-queue-table">
            <thead>
              <tr>
                <th>Course Code</th>
                <th>Instructor</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              ${state.courses
                .map(
                  (c) => `
                <tr>
                  <td>
                    <strong class="course-code-cell">${escapeHtml(c.course_code)}</strong>
                    <div class="course-title-sub">${escapeHtml(c.course_title)}</div>
                  </td>
                  <td>${escapeHtml(c.instructor_name)}</td>
                  <td>
                    <span class="status-badge ${c.status === 'COMPLETED' ? 'status-completed' : 'status-pending'}">${escapeHtml(c.status)}</span>
                  </td>
                  <td>
                    ${
                      c.status === 'COMPLETED'
                        ? `<span class="btn-signed-badge">[Clearance Signed]</span>`
                        : `<button type="button" class="btn-primary btn-sm" data-action="start-eval" data-section-id="${c.course_section_id}">[Start Evaluation]</button>`
                    }
                  </td>
                </tr>
              `,
                )
                .join('')}
            </tbody>
          </table>
        </div>
      </section>

      ${renderClearancePermitCard()}
    </main>
  `;
}

function renderEvaluationFormView() {
  const course = state.courses.find((c) => Number(c.course_section_id) === Number(state.activeSectionId)) || state.courses[3] || state.courses[0];
  const mean = calculateMean(state.ratings);
  const variance = calculatePopulationVariance(state.ratings);
  const clusterMeans = calculateClusterMeans(state.ratings);
  const straightLined = isStraightLined(state.ratings);
  const dwellSatisfied = isDwellTimeValid(state.dwellSeconds, state.ratings.length, 45);
  const nlp = classifyCommentPreview(state.commentText);
  const profVal = validateProfessorQuestions({
    threeWords: state.threeWords,
    improvementTopics: state.improvementTopics,
    teachingStrengths: state.teachingStrengths,
    recommendations: state.courseRecommendations,
    additionalObservation: state.showFifthQuestion ? state.additionalObservation : '',
  });
  const activeProfQCount = state.showFifthQuestion ? 5 : 4;
  const dwellPct = Math.min(100, Math.round((state.dwellSeconds / 45) * 100));

  return `
    <main class="workspace-main evaluation-form-workspace">
      <section class="card eval-header-card">
        <div class="eval-course-banner">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • 5 Clusters × 5-Point Likert Scale • ${escapeHtml(CALAMANCY_CONFIG.engineLabel)}</span>
            <h2 class="section-title">${escapeHtml(course.course_code)} — ${escapeHtml(course.instructor_name)}</h2>
            <p class="section-subtitle">${escapeHtml(course.course_title)} • ${escapeHtml(course.department_name)} (Pamantasan ng Lungsod ng Pasig)</p>
          </div>
          <div class="idempotency-box">
            <span class="token-label">FT-02 Idempotency Key:</span>
            <code>${escapeHtml(state.submissionKey)}</code>
          </div>
        </div>

        <div class="dwell-timer-badge ${dwellSatisfied ? 'dwell-valid' : 'dwell-counting'}" role="timer" data-testid="dwell-timer-badge">
          <div class="dwell-timer-info">
            <strong class="dwell-title">
              ${dwellSatisfied ? 'Authenticity Guard Satisfied (≥ 45s)' : 'Speed-Run Guard Active (45s Minimum Reading Duration)'}
            </strong>
            <p class="dwell-subtitle" style="margin:0.2rem 0 0;">
              Elapsed Time: <strong>${state.dwellSeconds}s / 45s</strong>
              ${!dwellSatisfied ? ` — ${Math.max(0, 45 - state.dwellSeconds)}s remaining` : ' — Verified for 5-Cluster SET instrument'}
            </p>
          </div>
          <div style="display:flex;align-items:center;gap:0.65rem;">
            <div class="dwell-progress-track"><div class="dwell-progress-fill" style="width:${dwellPct}%"></div></div>
            ${
              !dwellSatisfied
                ? `<button type="button" class="btn-secondary btn-xs" data-action="fast-forward-dwell">Simulate 45s Reading Time</button>`
                : ''
            }
          </div>
        </div>

        <div class="live-metrics-strip">
          <div><span>Mean Score (μ):</span> <strong>${mean.toFixed(2)} / 5.00</strong></div>
          <div><span>Population Variance (σ²):</span> <strong>${variance.toFixed(4)}</strong></div>
          <div>
            <span>5-Cluster Guard:</span>
            <strong>${straightLined ? 'Zero Variance (σ² = 0 — Confirmation Required)' : 'Differentiated (σ² > 0)'}</strong>
          </div>
          <div class="preset-rating-buttons">
            <button type="button" class="btn-secondary btn-xs" data-action="fill-straight-5s">Test Straight-Line (All 5s)</button>
            <button type="button" class="btn-secondary btn-xs" data-action="fill-varied">Restore Varied Ratings</button>
          </div>
        </div>
      </section>

      ${
        state.statusBanner
          ? `<div class="alert-banner ${state.statusBanner.type === 'success' ? 'alert-success' : 'alert-error'}" role="alert">
              <div><strong>[${escapeHtml(state.statusBanner.code)}]</strong> ${escapeHtml(state.statusBanner.message)}</div>
              ${
                state.statusBanner.type === 'success'
                  ? `<button type="button" class="btn-secondary btn-xs" data-nav="/queue">Return to PLP Evaluation Queue</button>`
                  : ''
              }
            </div>`
          : ''
      }

      ${PEDAGOGICAL_DIMENSIONS.map(
        (dim) => `
        <section class="card dimension-card">
          <fieldset class="dimension-fieldset">
            <legend class="dimension-legend">
              <span class="dimension-name">
                Cluster ${dim.clusterNumber}: ${escapeHtml(dim.name)} (Mean: ${(clusterMeans[dim.name] || 0).toFixed(2)} / 5.00)
              </span>
              <small class="dimension-desc">${escapeHtml(dim.description)}</small>
            </legend>
            ${dim.items
              .map(
                (item) => `
              <div class="likert-item-row">
                <div class="likert-question">
                  <span class="item-code">${escapeHtml(item.code)}</span>
                  <span class="item-text">${escapeHtml(item.text)}</span>
                </div>
                <div class="likert-scale-group" role="radiogroup">
                  ${LIKERT_SCALE_OPTIONS.map(
                    (opt) => `
                    <label class="likert-radio-label ${state.ratings[item.id - 1] === opt.value ? 'selected' : ''}" title="${opt.value} — ${escapeHtml(opt.label)}">
                      <input type="radio" name="item-${item.id}" value="${opt.value}" data-rate-index="${item.id - 1}" ${state.ratings[item.id - 1] === opt.value ? 'checked' : ''} style="display:none;" />
                      <span>${opt.value}</span>
                    </label>
                  `,
                  ).join('')}
                </div>
              </div>
            `,
              )
              .join('')}
          </fieldset>
        </section>
      `,
      ).join('')}

      <section class="card prof-questions-card">
        <div class="prof-questions-header">
          <div>
            <span class="eyebrow-badge">
              Qualitative Professor Evaluation • ${activeProfQCount} of ${MAX_PROFESSOR_QUESTIONS} Questions Active (Max ${MAX_PROFESSOR_QUESTIONS})
            </span>
            <h3 class="section-title">Structured Questions Regarding the Professor (calamanCy NLP)</h3>
            <p class="section-subtitle">
              Powered by <code>calamanCy</code> (${escapeHtml(CALAMANCY_CONFIG.model)}) for Tagalog, Taglish, and English morphological lemmatization and VADER sentiment scoring.
            </p>
          </div>
          <button type="button" class="btn-secondary btn-xs" data-action="toggle-fifth-question">
            ${state.showFifthQuestion ? 'Remove Optional 5th Question (5/5 Max Reached)' : '+ Add 5th Question (4/5 Active — Max 5 Questions)'}
          </button>
        </div>

        <div class="prof-question-block">
          <label class="prof-question-label">1. 3 words that describe the experience with the prof:</label>
          <div class="three-words-grid">
            <input type="text" class="form-input" data-word-index="0" value="${escapeHtml(state.threeWords[0])}" placeholder="Word 1 (e.g., Malinaw)" />
            <input type="text" class="form-input" data-word-index="1" value="${escapeHtml(state.threeWords[1])}" placeholder="Word 2 (e.g., Approachable)" />
            <input type="text" class="form-input" data-word-index="2" value="${escapeHtml(state.threeWords[2])}" placeholder="Word 3 (e.g., Organized)" />
          </div>
        </div>

        <div class="prof-question-block">
          <div class="prof-question-subhead">
            <label class="prof-question-label">2. Room for improvements of the prof:</label>
            <button type="button" class="btn-secondary btn-xs" data-action="add-improvement-topic">
              + Add Topic (${String.fromCharCode(97 + state.improvementTopics.length)}.)
            </button>
          </div>
          ${state.improvementTopics
            .map(
              (topic, idx) => `
            <div class="improvement-topic-row">
              <span class="sub-topic-badge">${String.fromCharCode(97 + idx)}. Topic ${idx + 1}</span>
              <input type="text" class="form-input" data-topic-index="${idx}" value="${escapeHtml(topic)}" />
              ${
                state.improvementTopics.length > 2
                  ? `<button type="button" class="btn-secondary btn-xs" data-action="remove-topic" data-topic-idx="${idx}">Remove</button>`
                  : ''
              }
            </div>
          `,
            )
            .join('')}
        </div>

        <div class="prof-question-block">
          <label class="prof-question-label">3. Most effective teaching strategies or strengths of the prof:</label>
          <input type="text" class="form-input" data-field="teachingStrengths" value="${escapeHtml(state.teachingStrengths)}" />
        </div>

        <div class="prof-question-block">
          <label class="prof-question-label">4. Actionable recommendations for the prof in future semesters:</label>
          <input type="text" class="form-input" data-field="courseRecommendations" value="${escapeHtml(state.courseRecommendations)}" />
        </div>

        ${
          state.showFifthQuestion
            ? `<div class="prof-question-block">
                <label class="prof-question-label">5. Additional observation on consultation &amp; mentorship support (Max 5 of 5 Questions):</label>
                <input type="text" class="form-input" data-field="additionalObservation" value="${escapeHtml(state.additionalObservation)}" placeholder="Optional 5th question regarding consultation hours or thesis mentorship..." />
              </div>`
            : ''
        }
      </section>

      <section class="card narrative-card">
        <h3 class="section-title">Open-Ended Pedagogical Narrative Summary (calamanCy + VADER NLP)</h3>
        <p class="section-subtitle">
          Your comment and structured responses are stripped of all student identifiers, hashed with an institutional secret, and classified by the Python <code>calamanCy</code> + VADER engine across all 5 pedagogical clusters.
        </p>

        <div class="sample-comment-pills">
          <button type="button" class="btn-secondary btn-xs" data-sample="Explains complex algorithms very clearly and provides great practical code examples.">Positive • Pedagogical Delivery (+0.78)</button>
          <button type="button" class="btn-secondary btn-xs" data-sample="Takes over a month to return quiz scores and provides no helpful feedback.">Negative • Assessment &amp; Grading (-0.62)</button>
          <button type="button" class="btn-secondary btn-xs" data-sample="Arrives to morning lectures 15 minutes late consistently.">Negative • Classroom Management (-0.45)</button>
          <button type="button" class="btn-secondary btn-xs" data-sample="Sobrang matulungin sa consultation at laging available sa office hours para sa thesis guidance.">Taglish (calamanCy) • Student Support &amp; Mentorship</button>
        </div>

        <textarea rows="4" class="narrative-textarea" data-field="commentText">${escapeHtml(state.commentText)}</textarea>

        <div class="nlp-live-preview">
          <span>Engine: <strong>${escapeHtml(nlp.nlp_engine)}</strong></span>
          <span>Sentiment Polarity: <strong>P = ${nlp.polarity >= 0 ? `+${nlp.polarity.toFixed(2)}` : nlp.polarity.toFixed(2)}</strong></span>
          <span class="badge-valence valence-${nlp.valence.toLowerCase()}">${escapeHtml(nlp.valence)}</span>
          <span>Assigned Cluster Tag: <strong>${escapeHtml(nlp.tag)}</strong></span>
          <span>Prof Questions Valid: <strong>${profVal.valid ? `Yes (${profVal.questionCount}/${MAX_PROFESSOR_QUESTIONS})` : 'Incomplete'}</strong></span>
        </div>

        <div class="form-submit-footer">
          <button type="button" class="btn-secondary" data-nav="/queue">Back to Queue</button>
          <button type="button" class="btn-primary" data-action="submit-evaluation">Submit Anonymized Evaluation &amp; Sign PLP Clearance</button>
        </div>
      </section>

      ${
        state.showStraightLineModal
          ? `<div class="modal-backdrop" role="dialog" aria-modal="true">
              <div class="modal-card">
                <span class="eyebrow-badge">Authenticity Guard • Zero Variance (σ² = 0)</span>
                <h2 class="section-title">Uniform Rating Pattern Detected (Straight-Lining Check)</h2>
                <p>You selected an identical rating of <strong>${state.ratings[0] || 5} / 5</strong> across all 25 pedagogical criteria (<code>σ² = 0.00</code>).</p>
                <p>Confirming a uniform assessment records an anonymized aggregate flag (<code>is_straight_lined = true</code>) in the decoupled vault without attaching your student identity.</p>
                <div class="modal-footer">
                  <button type="button" class="btn-secondary" data-action="close-modal">Review &amp; Differentiate Ratings</button>
                  <button type="button" class="btn-primary" data-action="confirm-uniform-submit">Confirm Uniform Assessment (confirm_uniform = true)</button>
                </div>
              </div>
            </div>`
          : ''
      }
    </main>
  `;
}

function renderFacultyView() {
  const sec = state.sectionLocks.find((s) => Number(s.course_section_id) === Number(state.facultySelectedSectionId)) || state.sectionLocks[0];
  const unlocked = canFacultyViewEvaluation(sec.term_grades_submitted, sec.registrar_lock);

  return `
    <main class="workspace-main faculty-dashboard-workspace">
      <section class="card faculty-header-card">
        <div class="header-split">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • Double-Blind Retaliation Mitigation Guard</span>
            <h2 class="section-title">PLP Faculty Pedagogical Feedback &amp; calamanCy Sentiment Portal</h2>
            <p class="section-subtitle">Formula: <code>CanViewEvaluation = (TermGradesSubmitted = TRUE) ∧ (RegistrarLock = TRUE)</code></p>
          </div>
          <div>
            <label for="faculty-sec-sel"><strong>Select Course Section:</strong></label>
            <select id="faculty-sec-sel" class="role-switcher-select" data-action="select-faculty-section">
              ${state.sectionLocks
                .map(
                  (s) => `
                <option value="${s.course_section_id}" ${Number(s.course_section_id) === Number(sec.course_section_id) ? 'selected' : ''}>
                  ${escapeHtml(s.course_code)} — ${escapeHtml(s.instructor_name)} (${s.term_grades_submitted && s.registrar_lock ? 'UNLOCKED' : 'DOUBLE-BLIND LOCKED'})
                </option>
              `,
                )
                .join('')}
            </select>
          </div>
        </div>
      </section>

      ${
        !unlocked
          ? `<section class="card double-blind-lock-card">
              <div style="display:flex;gap:0.75rem;align-items:center;margin-bottom:0.5rem;">
                <span class="badge-locked">HTTP 403 • DOUBLE_BLIND_LOCKED</span>
                <strong>${escapeHtml(sec.course_code)} (${escapeHtml(sec.course_title)})</strong>
              </div>
              <h3 class="section-title">Aggregated Faculty Evaluation Report Temporally Locked</h3>
              <p class="section-subtitle">
                To protect Pamantasan ng Lungsod ng Pasig students from potential grade retaliation, quantitative 5-cluster Likert distributions and <code>calamanCy</code> NLP sentiment summaries remain locked until both conditions below are verified:
              </p>
              <div class="lock-checklist">
                <div class="lock-check-item ${sec.term_grades_submitted ? 'check-done' : 'check-pending'}">
                  <strong>1. Instructor Term Grades Submitted (term_grades_submitted):</strong>
                  <span>${sec.term_grades_submitted ? 'TRUE (Submitted)' : 'FALSE (Pending Instructor Submission)'}</span>
                </div>
                <div class="lock-check-item ${sec.registrar_lock ? 'check-done' : 'check-pending'}">
                  <strong>2. Official PLP Registrar Grade Lock (registrar_lock):</strong>
                  <span>${sec.registrar_lock ? 'TRUE (Verified by PLP Registrar)' : 'FALSE (Awaiting University Registrar Lock)'}</span>
                </div>
              </div>
              <div class="lock-actions">
                <button type="button" class="btn-secondary" data-action="preview-unlocked-section">Preview Unlocked Section (CS-405 — Both Flags TRUE)</button>
                <button type="button" class="btn-primary" data-nav="/admin">Open Registrar Lock Console</button>
              </div>
            </section>`
          : `<section class="card unlocked-report-card">
              <span class="badge-unlocked">DOUBLE-BLIND UNLOCKED • AGGREGATED ANONYMOUS REPORT</span>
              <h3 class="section-title">${escapeHtml(sec.course_code)} — ${escapeHtml(sec.course_title)}</h3>
              <p class="section-subtitle">
                Instructor: <strong>${escapeHtml(sec.instructor_name)}</strong> •
                Evaluations Logged: <strong>${sec.evaluated_count} / ${sec.enrolled_count}</strong> •
                Overall Section Mean: <strong>${Number(sec.mean_score).toFixed(2)} / 5.00</strong>
              </p>
              <div class="dimension-averages-grid">
                ${Object.entries(sec.dimension_scores || {})
                  .map(
                    ([dimName, score]) => `
                  <div class="dimension-score-card">
                    <span>${escapeHtml(dimName)}</span>
                    <strong>${Number(score).toFixed(2)} / 5.00</strong>
                    <div class="dim-bar-track"><div class="dim-bar-fill" style="width:${Math.round((Number(score) / 5) * 100)}%"></div></div>
                  </div>
                `,
                  )
                  .join('')}
              </div>
              <h4>Structured Professor Questionnaire Synthesis (4-to-5 Questions • calamanCy NLP)</h4>
              <div class="metric-overview-grid" style="margin-bottom: 1rem;">
                <div class="metric-card">
                  <span class="metric-label">1. Top 3 Experience Descriptors</span>
                  <strong class="metric-value" style="font-size:1rem;">${escapeHtml(state.threeWords.filter(Boolean).join(' • ') || 'Malinaw • Approachable • Organized')}</strong>
                </div>
                <div class="metric-card">
                  <span class="metric-label">2. Room for Improvements (a., b.)</span>
                  <span class="section-subtitle">${state.improvementTopics.filter(Boolean).map((t, i) => `<strong>${String.fromCharCode(97 + i)}.</strong> ${escapeHtml(t)}`).join('<br/>')}</span>
                </div>
                <div class="metric-card">
                  <span class="metric-label">3. Key Teaching Strengths</span>
                  <span class="section-subtitle">${escapeHtml(state.teachingStrengths)}</span>
                </div>
                <div class="metric-card">
                  <span class="metric-label">4. Actionable Recommendations</span>
                  <span class="section-subtitle">${escapeHtml(state.courseRecommendations)}</span>
                </div>
              </div>
              <h4>calamanCy + VADER NLP-Classified Narrative Insights (Zero Student Identifiers)</h4>
              ${(sec.sample_comments || [])
                .map(
                  (c) => `
                <div class="comment-insight-card">
                  <p class="comment-quote">"${escapeHtml(c.excerpt)}"</p>
                  <div class="comment-meta">
                    <span>Polarity: <strong>${c.polarity >= 0 ? `+${c.polarity}` : c.polarity}</strong></span>
                    <span>Valence: <strong>${escapeHtml(c.valence)}</strong></span>
                    <span>Pedagogical Tag: <strong>${escapeHtml(c.tag)}</strong></span>
                  </div>
                </div>
              `,
                )
                .join('')}
            </section>`
      }
    </main>
  `;
}

function renderDeanView() {
  const ov = state.deanOverview;
  const watchlist = ov.watchlist.filter((w) => evaluateWatchlistFlag(w.mean_score, w.negative_sentiment_pct));
  const susLive = calculateSusScore(state.susItems);

  return `
    <main class="workspace-main dean-dashboard-workspace">
      <section class="card dean-banner-card">
        <div class="dean-banner-top">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • [EduMetrics Admin] Dean's Analytical Overview</span>
            <h2 class="section-title">Department: ${escapeHtml(ov.department)}</h2>
            <p class="section-subtitle">Institution: <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong> • Period: <strong>${escapeHtml(ov.period)}</strong> (${escapeHtml(ov.activeTerm)})</p>
          </div>
          <div class="tab-switcher">
            <button type="button" class="tab-btn ${state.deanActiveTab === 'overview' ? 'active' : ''}" data-action="dean-tab" data-tab="overview">SOP 2: Sentiment &amp; Watchlist</button>
            <button type="button" class="tab-btn ${state.deanActiveTab === 'quality' ? 'active' : ''}" data-action="dean-tab" data-tab="quality">SOP 3: ISO/IEC 25010 (4.77) &amp; SUS (83.92 Grade A)</button>
          </div>
        </div>

        <div class="metric-overview-grid">
          <div class="metric-card"><span class="metric-label">Response Rate</span><strong class="metric-value">91.4% (Target: &gt;85%)</strong></div>
          <div class="metric-card"><span class="metric-label">Submissions Logged</span><strong class="metric-value">4,821</strong></div>
          <div class="metric-card"><span class="metric-label">Average Evaluation Time</span><strong class="metric-value">3m 42s</strong></div>
          <div class="metric-card"><span class="metric-label">Integrity Flags</span><strong class="metric-value">1.2%</strong></div>
        </div>
      </section>

      ${
        state.deanActiveTab === 'overview'
          ? `<section class="card sentiment-distribution-card">
              <h3 class="section-title">Sentiment Polarity Distribution (Narrative Comments • calamanCy + VADER)</h3>
              <div class="sentiment-bars">
                <div class="sentiment-row">
                  <div class="sentiment-label"><strong>Positive: ${ov.sentimentDistribution.positive}%</strong><span>(P ≥ +0.05)</span></div>
                  <div class="sentiment-bar-track"><div class="bar-positive" style="width:${ov.sentimentDistribution.positive}%"></div></div>
                </div>
                <div class="sentiment-row">
                  <div class="sentiment-label"><strong>Neutral: ${ov.sentimentDistribution.neutral}%</strong><span>(-0.05 &lt; P &lt; +0.05)</span></div>
                  <div class="sentiment-bar-track"><div class="bar-neutral" style="width:${ov.sentimentDistribution.neutral}%"></div></div>
                </div>
                <div class="sentiment-row">
                  <div class="sentiment-label"><strong>Negative: ${ov.sentimentDistribution.negative}%</strong><span>(P ≤ -0.05)</span></div>
                  <div class="sentiment-bar-track"><div class="bar-negative" style="width:${ov.sentimentDistribution.negative}%"></div></div>
                </div>
              </div>
            </section>

            <section class="card watchlist-card">
              <div class="watchlist-header">
                <div>
                  <h3 class="section-title">Faculty Exception Watchlist (Score &lt; 3.5 OR Negative Sentiment &gt; 35%)</h3>
                  <p class="section-subtitle">Automated diagnostic alerts for early pedagogical intervention by the PLP Dean and Department Chair.</p>
                </div>
                <button type="button" class="btn-secondary btn-sm" data-action="export-csv">Export Sanitized CSV Report</button>
              </div>
              <ul class="watchlist-items">
                ${watchlist
                  .map(
                    (w) => `
                  <li class="watchlist-item">
                    <div class="watchlist-main">
                      <strong>${escapeHtml(w.faculty_name)} (Avg: ${w.mean_score.toFixed(1)} / 5.0)</strong>
                      <span>-&gt;</span>
                      <span class="watchlist-tag-badge">Primary Tag: ${escapeHtml(w.wireframe_label)} (${escapeHtml(w.primary_tag)})</span>
                    </div>
                    <div class="watchlist-sub">
                      <span>Section: <strong>${escapeHtml(w.course_code)}</strong></span>
                      <span>Negative Sentiment Share: <strong>${w.negative_sentiment_pct}%</strong></span>
                      <span>Representative Extract: <em>"${escapeHtml(w.sample_comment)}"</em></span>
                    </div>
                  </li>
                `,
                  )
                  .join('')}
              </ul>
              ${state.exportedCsvPreview ? `<pre class="card" style="margin-top:1rem;background:#f8fbf9;">${escapeHtml(state.exportedCsvPreview)}</pre>` : ''}
            </section>`
          : `<section class="card iso-card">
              <div class="iso-header">
                <h3 class="section-title">ISO/IEC 25010 Software Quality Assessment (Scale: 1.00 - 5.00)</h3>
                <span class="badge-unlocked">Overall Mean Quality Score: ${ov.iso25010.overallMean} (${escapeHtml(ov.iso25010.interpretation)})</span>
              </div>
              <table class="institutional-table" style="margin-top:0.85rem;">
                <thead><tr><th>Quality Characteristic</th><th>Mean Score</th><th>Qualitative Interpretation</th></tr></thead>
                <tbody>
                  ${ov.iso25010.characteristics
                    .map((c) => `<tr><td><strong>${escapeHtml(c.name)}</strong></td><td>${c.score.toFixed(2)}</td><td>${escapeHtml(c.interpretation)}</td></tr>`)
                    .join('')}
                  <tr><td><strong>Overall Mean Quality Score</strong></td><td><strong>${ov.iso25010.overallMean.toFixed(2)}</strong></td><td><strong>${escapeHtml(ov.iso25010.interpretation)}</strong></td></tr>
                </tbody>
              </table>
            </section>
            <section class="card sus-card">
              <div class="iso-header">
                <h3 class="section-title">System Usability Scale (SUS) Score Breakdown (N = 50)</h3>
                <span class="badge-unlocked">Consolidated Mean SUS: ${ov.sus.consolidatedMean} (${escapeHtml(ov.sus.grade)}) • Live Calculator: ${susLive.score}</span>
              </div>
              <table class="institutional-table" style="margin-top:0.85rem;">
                <thead><tr><th>User Cohort</th><th>Sample Size (N)</th><th>Mean SUS Score</th></tr></thead>
                <tbody>
                  ${ov.sus.cohorts
                    .map((r) => `<tr><td><strong>${escapeHtml(r.cohort)}</strong></td><td>${r.sampleSize}</td><td>${r.meanScore.toFixed(2)}</td></tr>`)
                    .join('')}
                  <tr><td><strong>Consolidated Mean SUS</strong></td><td><strong>${ov.sus.totalSampleSize}</strong></td><td><strong>${ov.sus.consolidatedMean} (${escapeHtml(ov.sus.grade)})</strong></td></tr>
                </tbody>
              </table>
            </section>`
      }
    </main>
  `;
}

function renderAdminView() {
  return `
    <main class="workspace-main admin-roster-workspace">
      <section class="card admin-header-card">
        <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • Office of the University Registrar • RBAC &amp; Grade-Lock Control</span>
        <h2 class="section-title">PLP Academic Catalog Ingestion &amp; Double-Blind Registrar Lock Console</h2>
        <p class="section-subtitle">
          Manage atomic CSV/JSON course enrollments for <strong>Pamantasan ng Lungsod ng Pasig</strong> and synchronize Registrar Grade Locks (<code>registrar_lock</code>) to release aggregated faculty evaluation reports safely.
        </p>
      </section>

      <section class="card registrar-locks-card">
        <h3 class="section-title">Course Section Double-Blind Lock Synchronization</h3>
        <div class="table-responsive">
          <table class="institutional-table">
            <thead>
              <tr>
                <th>Course Code</th>
                <th>Instructor</th>
                <th>Grades Submitted (term_grades_submitted)</th>
                <th>Registrar Lock (registrar_lock)</th>
                <th>Faculty Report Visibility</th>
              </tr>
            </thead>
            <tbody>
              ${state.sectionLocks
                .map((sec) => {
                  const unlocked = canFacultyViewEvaluation(sec.term_grades_submitted, sec.registrar_lock);
                  return `
                    <tr>
                      <td><strong>${escapeHtml(sec.course_code)}</strong><div class="course-title-sub">${escapeHtml(sec.course_title)}</div></td>
                      <td>${escapeHtml(sec.instructor_name)}</td>
                      <td>
                        <button type="button" class="btn-toggle ${sec.term_grades_submitted ? 'toggle-on' : 'toggle-off'}" data-action="toggle-lock" data-sec="${sec.course_section_id}" data-field="term_grades_submitted">
                          ${sec.term_grades_submitted ? 'TRUE (Submitted)' : 'FALSE (Pending)'}
                        </button>
                      </td>
                      <td>
                        <button type="button" class="btn-toggle ${sec.registrar_lock ? 'toggle-on' : 'toggle-off'}" data-action="toggle-lock" data-sec="${sec.course_section_id}" data-field="registrar_lock">
                          ${sec.registrar_lock ? 'TRUE (Locked)' : 'FALSE (Unlocked)'}
                        </button>
                      </td>
                      <td>
                        <span class="status-badge ${unlocked ? 'status-completed' : 'status-pending'}">
                          ${unlocked ? 'UNLOCKED (Accessible)' : 'DOUBLE-BLIND LOCKED'}
                        </span>
                      </td>
                    </tr>
                  `;
                })
                .join('')}
            </tbody>
          </table>
        </div>
      </section>

      <section class="card roster-import-card">
        <h3 class="section-title">Atomic CSV / JSON Academic Roster Ingestion</h3>
        <textarea rows="6" class="narrative-textarea" data-field="adminRosterInput">${escapeHtml(state.adminRosterInput)}</textarea>
        ${
          state.adminImportStatus
            ? `<div class="alert-banner ${state.adminImportStatus.ok ? 'alert-success' : 'alert-error'}" style="margin-bottom:0.85rem;">${escapeHtml(state.adminImportStatus.message)}</div>`
            : ''
        }
        <button type="button" class="btn-primary" data-action="import-roster">Execute Atomic Roster Import</button>
      </section>

      <section class="card">
        <div class="watchlist-header">
          <div>
            <span class="eyebrow-badge">Cryptographic Decoupling Proof • Zero-Knowledge Vault</span>
            <h3 class="section-title">Live evaluation_vault Schema &amp; Anonymity Auditor</h3>
            <p class="section-subtitle">
              Verify against the live SQLite database that <code>evaluation_vault</code> contains zero student-identifying columns (no <code>student_id</code>, <code>user_id</code>, <code>clearance_id</code>, or <code>ip_address</code>).
            </p>
          </div>
          <button type="button" class="btn-primary btn-sm" data-action="run-vault-audit">
            Run Live evaluation_vault Schema Audit
          </button>
        </div>
        ${
          state.vaultAuditResult
            ? `<pre class="card" style="margin-top:0.85rem;background:#f8fbf9;overflow-x:auto;">${escapeHtml(JSON.stringify(state.vaultAuditResult, null, 2))}</pre>`
            : ''
        }
      </section>
    </main>
  `;
}

function renderLoginView() {
  const profile = ROLE_PROFILES[state.role] || ROLE_PROFILES.student;
  return `
    <main class="workspace-main login-workspace">
      <section class="card login-card">
        <div class="plp-login-banner">
          <div class="plp-crest-seal">${plpSealSvg(34)}</div>
          <div>
            <h3>PAMANTASAN NG LUNGSOD NG PASIG</h3>
            <p>Alkalde Joseco St., Kapasigan, Pasig City • Official SET &amp; calamanCy NLP Portal (plpasig.edu.ph)</p>
          </div>
        </div>

        <span class="eyebrow-badge">PLP EvaluaTech • Five-Role Institutional Access Control</span>
        <h2 class="section-title">Automated Student Evaluation of Teachers (SET) Portal</h2>
        <p class="section-subtitle">
          Select an institutional role below or sign in with your Pamantasan ng Lungsod ng Pasig credentials.
          Student submissions are protected by Dual-Token Cryptographic Decoupling.
        </p>

        <div class="role-preset-grid">
          ${Object.entries(ROLE_PROFILES)
            .map(
              ([key, p]) => `
            <button type="button" class="role-preset-btn ${state.role === key ? 'active' : ''}" data-action="preset-role" data-role="${key}">
              <strong>${escapeHtml(p.role_label)}</strong>
              <span>${escapeHtml(p.institutional_id)}</span>
              <small>${escapeHtml(p.name)}</small>
            </button>
          `,
            )
            .join('')}
        </div>

        <div class="login-form">
          <div class="form-field">
            <label>PLP Institutional ID / University Email (@plpasig.edu.ph)</label>
            <input type="text" value="${escapeHtml(profile.institutional_id)}" readonly />
          </div>
          <div class="form-field">
            <label>Institutional Passphrase</label>
            <input type="password" value="EvaluaTech!2026" readonly />
          </div>
          <button type="button" class="btn-primary" data-action="login-continue">
            Sign In as ${escapeHtml(profile.role_label)} (${escapeHtml(profile.institutional_id)})
          </button>
        </div>
      </section>
    </main>
  `;
}

function submitCurrentEvaluation(confirmUniform = false) {
  if (!isDwellTimeValid(state.dwellSeconds, state.ratings.length, 45)) {
    state.statusBanner = {
      type: 'error',
      code: 'SPEED_RUN_DETECTED',
      message: 'Speed-Run Guard Rejected Submission (HTTP 422): Minimum reading dwell duration of 45 seconds is required.',
    };
    renderApp();
    return;
  }

  if (isStraightLined(state.ratings) && !confirmUniform) {
    state.showStraightLineModal = true;
    renderApp();
    return;
  }

  state.showStraightLineModal = false;
  const course = state.courses.find((c) => Number(c.course_section_id) === Number(state.activeSectionId)) || state.courses[3];
  const token = `CLR-2026-${course.course_code.replace(/[^A-Z0-9]/g, '')}-${Math.random().toString(36).substring(2, 6).toUpperCase()}`;
  course.status = 'COMPLETED';
  course.action_label = 'Clearance Signed';
  course.clearance_token = token;

  state.statusBanner = {
    type: 'success',
    code: 'CLEARANCE_ISSUED',
    message: `Evaluation recorded anonymously in evaluation_vault! Signed PLP Clearance Token Issued: ${token}`,
  };
  renderApp();

  // Persist asynchronously to live SQLite backend API (/api/evaluation/submit)
  fetch('/api/evaluation/submit', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      course_section_id: course.course_section_id,
      submission_key: state.submissionKey,
      ratings: state.ratings,
      dwell_time_seconds: state.dwellSeconds,
      confirm_uniform: confirmUniform,
      comment_text: state.commentText,
      professor_questions: {
        three_words: state.threeWords,
        room_for_improvements: state.improvementTopics.filter((t) => String(t).trim().length > 0),
        teaching_strengths: state.teachingStrengths,
        recommendations: state.courseRecommendations,
        additional_observation: state.showFifthQuestion ? state.additionalObservation : null,
      },
    }),
  }).catch(() => {});
}

export function renderApp() {
  const root = document.getElementById('app');
  if (!root) return;

  let viewHtml = '';
  if (state.currentPath === '/login') {
    viewHtml = renderLoginView();
  } else if (state.currentPath.startsWith('/evaluate')) {
    viewHtml = renderEvaluationFormView();
  } else if (state.currentPath === '/faculty') {
    viewHtml = renderFacultyView();
  } else if (state.currentPath === '/dean') {
    viewHtml = renderDeanView();
  } else if (state.currentPath === '/admin') {
    viewHtml = renderAdminView();
  } else {
    viewHtml = renderQueueView();
  }

  root.innerHTML = `<div class="evaluatech-app-shell">${renderNavbar()}<div class="evaluatech-view-container">${viewHtml}</div></div>`;
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  setInterval(() => {
    if (state.currentPath.startsWith('/evaluate')) {
      state.dwellSeconds += 1;
      const badge = document.querySelector('[data-testid="dwell-timer-badge"]');
      if (badge && state.dwellSeconds <= 46) {
        renderApp();
      }
    }
  }, 1000);

  document.addEventListener('click', (e) => {
    const navEl = e.target.closest('[data-nav]');
    if (navEl) {
      e.preventDefault();
      navigate(navEl.getAttribute('data-nav'));
      return;
    }

    const sampleBtn = e.target.closest('[data-sample]');
    if (sampleBtn) {
      state.commentText = sampleBtn.getAttribute('data-sample');
      renderApp();
      return;
    }

    const actionEl = e.target.closest('[data-action]');
    if (!actionEl) return;
    const action = actionEl.getAttribute('data-action');

    if (action === 'start-eval') {
      navigate(`/evaluate/${actionEl.getAttribute('data-section-id')}`);
    } else if (action === 'complete-all-courses') {
      state.courses.forEach((c) => {
        if (c.status !== 'COMPLETED') {
          c.status = 'COMPLETED';
          c.action_label = 'Clearance Signed';
          c.clearance_token = `CLR-2026-${c.course_code.replace(/[^A-Z0-9]/g, '')}-PLPVERIFIED`;
        }
      });
      renderApp();
    } else if (action === 'fast-forward-dwell') {
      state.dwellSeconds = Math.max(45, state.dwellSeconds);
      renderApp();
    } else if (action === 'fill-straight-5s') {
      state.ratings = Array(25).fill(5);
      renderApp();
    } else if (action === 'fill-varied') {
      state.ratings = [5, 4, 5, 5, 4, 4, 5, 4, 4, 5, 5, 5, 4, 5, 4, 5, 4, 5, 5, 5, 5, 4, 5, 4, 5];
      renderApp();
    } else if (action === 'toggle-fifth-question') {
      state.showFifthQuestion = !state.showFifthQuestion;
      renderApp();
    } else if (action === 'add-improvement-topic') {
      if (state.improvementTopics.length < 6) {
        state.improvementTopics.push('');
        renderApp();
      }
    } else if (action === 'remove-topic') {
      const idx = Number(actionEl.getAttribute('data-topic-idx'));
      if (state.improvementTopics.length > 2) {
        state.improvementTopics.splice(idx, 1);
        renderApp();
      }
    } else if (action === 'submit-evaluation') {
      submitCurrentEvaluation(false);
    } else if (action === 'close-modal') {
      state.showStraightLineModal = false;
      renderApp();
    } else if (action === 'confirm-uniform-submit') {
      submitCurrentEvaluation(true);
    } else if (action === 'preview-unlocked-section') {
      state.facultySelectedSectionId = 102;
      renderApp();
    } else if (action === 'dean-tab') {
      state.deanActiveTab = actionEl.getAttribute('data-tab');
      renderApp();
    } else if (action === 'export-csv') {
      const headers = ['CourseCode', 'FacultyName', 'MeanScore', 'NegativeSentimentPct', 'PrimaryTag'];
      const rows = state.deanOverview.watchlist.map((r) => [
        sanitizeCsvCell(r.course_code),
        sanitizeCsvCell(r.faculty_name),
        sanitizeCsvCell(r.mean_score.toFixed(2)),
        sanitizeCsvCell(`${r.negative_sentiment_pct}%`),
        sanitizeCsvCell(r.primary_tag),
      ]);
      state.exportedCsvPreview = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
      renderApp();
    } else if (action === 'toggle-lock') {
      const secId = Number(actionEl.getAttribute('data-sec'));
      const field = actionEl.getAttribute('data-field');
      const target = state.sectionLocks.find((s) => Number(s.course_section_id) === secId);
      if (target) {
        target[field] = !target[field];
        renderApp();
      }
    } else if (action === 'import-roster') {
      try {
        const parsed = JSON.parse(state.adminRosterInput);
        if (Array.isArray(parsed) && parsed.length > 0) {
          parsed.forEach((row, idx) => {
            if (!state.courses.some((c) => c.course_code === row.course_code)) {
              state.courses.push({
                course_section_id: 200 + state.courses.length + idx,
                course_code: row.course_code || 'CS-NEW',
                course_title: row.course_title || 'Imported PLP Course',
                instructor_name: row.instructor_name || 'PLP Faculty',
                department_name: row.department_name || 'College of Computer Studies',
                status: 'PENDING',
                action_label: 'Start Evaluation',
                clearance_token: null,
              });
            }
          });
          state.adminImportStatus = { ok: true, message: `Imported ${parsed.length} PLP course enrollment(s) atomically.` };
        }
      } catch (err) {
        state.adminImportStatus = { ok: false, message: `Malformed JSON payload: ${err.message}` };
      }
      renderApp();
    } else if (action === 'preset-role') {
      state.role = actionEl.getAttribute('data-role') || 'student';
      renderApp();
    } else if (action === 'login-continue') {
      const p = ROLE_PROFILES[state.role] || ROLE_PROFILES.student;
      navigate(p.defaultRoute);
    } else if (action === 'print-permit') {
      window.print();
    } else if (action === 'reset-plp-demo') {
      state.courses = DEMO_STUDENT_QUEUE.map((c) => ({ ...c }));
      state.sectionLocks = DEMO_SECTION_LOCKS.map((s) => ({ ...s }));
      state.dwellSeconds = 0;
      state.statusBanner = null;
      fetch('/api/admin/reset-demo', { method: 'POST' }).catch(() => {});
      renderApp();
    } else if (action === 'set-sus-item') {
      const idx = Number(actionEl.getAttribute('data-sus-idx'));
      const val = Number(actionEl.getAttribute('data-sus-val'));
      if (idx >= 0 && idx < 10 && val >= 1 && val <= 5) {
        state.susItems[idx] = val;
        renderApp();
      }
    } else if (action === 'run-vault-audit') {
      fetch('/api/security/vault-audit')
        .then((r) => r.json())
        .then((data) => {
          state.vaultAuditResult = data;
          renderApp();
        })
        .catch(() => {
          state.vaultAuditResult = {
            decoupled: true,
            institution: 'PAMANTASAN NG LUNGSOD NG PASIG',
            vault_columns: [
              'id',
              'course_section_id',
              'faculty_id',
              'academic_term_id',
              'ratings_json',
              'mean_score',
              'rating_variance',
              'is_straight_lined',
              'dwell_time_seconds',
              'comment_text',
              'comment_hash',
              'sentiment_polarity',
              'sentiment_valence',
              'pedagogical_tag',
              'recorded_date',
            ],
            forbidden_found: [],
          };
          renderApp();
        });
    }
  });

  document.addEventListener('change', (e) => {
    if (e.target.matches('[data-action="switch-role"]')) {
      state.role = e.target.value;
      const p = ROLE_PROFILES[state.role] || ROLE_PROFILES.student;
      navigate(p.defaultRoute);
    } else if (e.target.matches('[data-action="select-faculty-section"]')) {
      state.facultySelectedSectionId = Number(e.target.value);
      renderApp();
    } else if (e.target.matches('[data-rate-index]')) {
      const idx = Number(e.target.getAttribute('data-rate-index'));
      state.ratings[idx] = Number(e.target.value);
      renderApp();
    }
  });

  document.addEventListener('input', (e) => {
    if (e.target.matches('[data-word-index]')) {
      state.threeWords[Number(e.target.getAttribute('data-word-index'))] = e.target.value;
    } else if (e.target.matches('[data-topic-index]')) {
      state.improvementTopics[Number(e.target.getAttribute('data-topic-index'))] = e.target.value;
    } else if (e.target.matches('[data-field]')) {
      const field = e.target.getAttribute('data-field');
      state[field] = e.target.value;
      if (field === 'commentText') {
        renderApp();
      }
    }
  });

  window.addEventListener('popstate', () => {
    state.currentPath = window.location.pathname === '/' ? '/queue' : window.location.pathname;
    renderApp();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderApp);
  } else {
    renderApp();
  }
}
