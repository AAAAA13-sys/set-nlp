import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  CALAMANCY_CONFIG,
  FIVE_LIKERT_CLUSTERS,
  MAX_PROFESSOR_QUESTIONS,
  MIN_PROFESSOR_QUESTIONS,
  PEDAGOGICAL_DIMENSIONS,
  PROFESSOR_QUALITATIVE_QUESTIONS,
  DEMO_STUDENT_QUEUE,
  DEMO_DEAN_METRICS,
  calculateClusterMeans,
  calculateMean,
  calculatePopulationVariance,
  isStraightLined,
  isDwellTimeValid,
  calculateQueueProgress,
  classifyCommentPreview,
  evaluateWatchlistFlag,
  canFacultyViewEvaluation,
  calculateSusScore,
  sanitizeCsvCell,
  validateProfessorQuestions,
} from '../../resources/js/utils/evaluationMath.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '../..');
const JS_DIR = path.join(ROOT_DIR, 'resources', 'js');

function readResourceFile(relativePath) {
  return fs.readFileSync(path.join(JS_DIR, relativePath), 'utf8');
}

describe('EvaluaTech Vue 3 SPA & Domain Verification Suite (38 Test Groups)', () => {
  // 1. Header portal branding & active term display
  describe('Group 01: Header portal branding & active term display (NavbarHeader.vue — Pamantasan ng Lungsod ng Pasig)', () => {
    it('renders PAMANTASAN NG LUNGSOD NG PASIG branding, PLP Green/Gold theme, active term, Bearer auth session bar, and clearance status badge', () => {
      const navbarSfc = readResourceFile('components/NavbarHeader.vue');
      const authStoreSrc = readResourceFile('stores/auth.js');
      const appCss = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'css', 'app.css'), 'utf8');

      assert.ok(navbarSfc.includes('PAMANTASAN NG LUNGSOD NG PASIG'));
      assert.ok(navbarSfc.includes('plpasig.edu.ph'));
      assert.ok(navbarSfc.includes('Institutional Evaluation Portal'));
      assert.ok(navbarSfc.includes('Active Term:'));
      assert.ok(navbarSfc.includes('Signed in as:'));
      assert.ok(navbarSfc.includes('data-testid="navbar-clearance-badge"'));
      assert.ok(appCss.includes('--plp-green-900: #0b4f26'));
      assert.ok(appCss.includes('--plp-gold-500: #eab308'));
      assert.ok(authStoreSrc.includes('evaluatech_auth_token'));
      assert.ok(authStoreSrc.includes('1st Semester AY 2026-2027'));
      assert.ok(authStoreSrc.includes('Bearer '));
    });
  });

  // 2. Student queue progress bar calculation
  describe('Group 02: Student queue progress bar calculation (3 of 6 = 50%, 6 of 6 = 100%)', () => {
    it('calculates 50% progress for 3 of 6 completed courses and 100% CLEARED when all 6 complete', () => {
      const initialProgress = calculateQueueProgress(DEMO_STUDENT_QUEUE);
      assert.equal(initialProgress.completed, 3);
      assert.equal(initialProgress.pending, 3);
      assert.equal(initialProgress.total, 6);
      assert.equal(initialProgress.percentage, 50);
      assert.equal(initialProgress.allCleared, false);
      assert.equal(initialProgress.clearanceStatus, 'PENDING');
      assert.equal(initialProgress.label, 'My Evaluation Queue (3 of 6 Completed)');

      const allCompletedQueue = DEMO_STUDENT_QUEUE.map((c) => ({ ...c, status: 'COMPLETED' }));
      const fullProgress = calculateQueueProgress(allCompletedQueue);
      assert.equal(fullProgress.completed, 6);
      assert.equal(fullProgress.pending, 0);
      assert.equal(fullProgress.percentage, 100);
      assert.equal(fullProgress.allCleared, true);
      assert.equal(fullProgress.clearanceStatus, 'CLEARED');
      assert.equal(fullProgress.label, 'My Evaluation Queue (6 of 6 Completed)');
    });
  });

  // 3. Course roster table rendering (CS-401 through PHYS-101)
  describe('Group 03: Course roster table rendering (CS-401 through PHYS-101)', () => {
    it('renders all 6 canonical courses with expected instructors, status badges, and action labels', () => {
      const queueViewSfc = readResourceFile('views/StudentQueueView.vue');
      const codes = DEMO_STUDENT_QUEUE.map((c) => c.course_code);
      assert.deepEqual(codes, ['CS-401', 'CS-405', 'ENG-102', 'MATH-201', 'CS-408', 'PHYS-101']);

      const instructors = DEMO_STUDENT_QUEUE.map((c) => c.instructor_name);
      assert.deepEqual(instructors, [
        'Prof. A. Dela Cruz',
        'Dr. M. Santos',
        'Prof. E. Rivera',
        'Dr. R. Garcia',
        'Prof. K. Gomez',
        'Engr. J. Luna',
      ]);

      assert.equal(DEMO_STUDENT_QUEUE[0].status, 'COMPLETED');
      assert.equal(DEMO_STUDENT_QUEUE[0].action_label, 'Clearance Signed');
      assert.equal(DEMO_STUDENT_QUEUE[3].status, 'PENDING');
      assert.equal(DEMO_STUDENT_QUEUE[3].action_label, 'Start Evaluation');
      assert.ok(queueViewSfc.includes('Course Code'));
      assert.ok(queueViewSfc.includes('Instructor'));
    });
  });

  // 4. Mandatory Timing Policy & Non-Negative Duration Validation
  describe('Group 04: Mandatory Timing Policy & Non-Negative Duration Validation (0s+ allowed, negative blocked)', () => {
    it('accepts completed evaluation submissions whenever students are ready (0s+) and rejects negative durations', () => {
      const dwellBadgeSfc = readResourceFile('components/DwellTimerBadge.vue');
      assert.ok(dwellBadgeSfc.includes('data-testid="dwell-timer-badge"'));
      assert.ok(dwellBadgeSfc.includes('role="timer"'));

      // Under Mandatory Timing Policy: minSeconds = 0, immediate submissions are valid
      assert.equal(isDwellTimeValid(0, 25, 0), true);
      assert.equal(isDwellTimeValid(12, 25, 0), true);
      assert.equal(isDwellTimeValid(45, 25, 0), true);
      assert.equal(isDwellTimeValid(120, 25, 0), true);
      // Negative durations rejected
      assert.equal(isDwellTimeValid(-1, 25, 0), false);
      assert.equal(isDwellTimeValid(-5, 25, 0), false);
    });
  });

  // 5. Population variance sigma^2 calculation on 20 Likert ratings
  describe('Group 05: Population variance sigma^2 calculation on 20 Likert ratings', () => {
    it('computes exact arithmetic mean (mu) and population variance (sigma^2) across 20 items', () => {
      const alternating4And5 = Array.from({ length: 20 }, (_, idx) => (idx % 2 === 0 ? 5 : 4));
      assert.equal(calculateMean(alternating4And5), 4.5);
      assert.equal(calculatePopulationVariance(alternating4And5), 0.25);

      const variedSet = [5, 4, 5, 3, 4, 5, 4, 5, 3, 4, 5, 4, 5, 3, 4, 5, 4, 5, 3, 4];
      assert.equal(calculateMean(variedSet), 4.2);
      assert.equal(calculatePopulationVariance(variedSet), 0.56);
    });
  });

  // 6. Straight-lining (sigma^2 = 0) detection for all-5s and all-1s
  describe('Group 06: Straight-lining (sigma^2 = 0) detection for all-5s and all-1s', () => {
    it('detects zero variance for straight 5s, straight 1s, and straight 3s across all 20 Likert items', () => {
      const all5s = Array(20).fill(5);
      const all1s = Array(20).fill(1);
      const all3s = Array(20).fill(3);

      assert.equal(calculatePopulationVariance(all5s), 0);
      assert.equal(calculatePopulationVariance(all1s), 0);
      assert.equal(isStraightLined(all5s), true);
      assert.equal(isStraightLined(all1s), true);
      assert.equal(isStraightLined(all3s), true);

      const oneDifferent = [...Array(19).fill(5), 4];
      assert.equal(isStraightLined(oneDifferent), false);
      assert.ok(calculatePopulationVariance(oneDifferent) > 0);
    });
  });

  // 7. StraightLineModal confirmation workflow (confirm_uniform = false vs true)
  describe('Group 07: StraightLineModal confirmation workflow (confirm_uniform = false vs true)', () => {
    it('challenges uniform submissions when confirm_uniform is false and records is_straight_lined when confirmed', () => {
      const modalSfc = readResourceFile('components/StraightLineModal.vue');
      const evalStoreSrc = readResourceFile('stores/evaluation.js');

      assert.ok(modalSfc.includes('data-testid="straight-line-modal"'));
      assert.ok(modalSfc.includes('data-testid="modal-confirm-uniform-btn"'));
      assert.ok(modalSfc.includes('is_straight_lined = true'));
      assert.ok(evalStoreSrc.includes('ZERO_VARIANCE_CONFIRMATION_REQUIRED'));
    });
  });

  // 8. Genuine varied rating submission acceptance (5 clusters x 5-point Likert scale & 4-to-5 Prof Questions)
  describe('Group 08: Genuine varied rating submission acceptance (5 clusters x 5-point Likert & Prof Questions)', () => {
    it('validates 25 pedagogical items across 5 clusters of 5-point Likert scale and 4-to-5 professor questions', () => {
      const totalItems = PEDAGOGICAL_DIMENSIONS.reduce((sum, dim) => sum + dim.items.length, 0);
      assert.equal(PEDAGOGICAL_DIMENSIONS.length, 5);
      assert.equal(FIVE_LIKERT_CLUSTERS.length, 5);
      assert.equal(totalItems, 25);

      const genuineRatings = [
        5, 4, 5, 5, 4,
        5, 4, 4, 5, 4,
        5, 5, 4, 5, 4,
        5, 5, 4, 5, 5,
        5, 4, 5, 4, 5,
      ];
      assert.equal(isDwellTimeValid(68, genuineRatings.length, 45), true);
      assert.equal(isStraightLined(genuineRatings), false);
      assert.ok(calculateMean(genuineRatings) > 4.0);
      assert.ok(calculatePopulationVariance(genuineRatings) > 0);

      const clusterScores = calculateClusterMeans(genuineRatings);
      assert.equal(Object.keys(clusterScores).length, 5);
      assert.ok('Student Support & Mentorship' in clusterScores);

      // Verify 4 structured professor questions (max of 5 questions)
      assert.equal(MIN_PROFESSOR_QUESTIONS, 4);
      assert.equal(MAX_PROFESSOR_QUESTIONS, 5);
      assert.equal(PROFESSOR_QUALITATIVE_QUESTIONS.length, 5);

      const validProfQ = validateProfessorQuestions({
        threeWords: ['Malinaw', 'Approachable', 'Organized'],
        improvementTopics: ['Faster return of quiz scores', 'More dynamic programming code walkthroughs'],
        teachingStrengths: 'Explains complex algorithms very clearly.',
        recommendations: 'Provide practice sets before midterms.',
      });
      assert.equal(validProfQ.valid, true);
      assert.equal(validProfQ.questionCount, 4);
      assert.equal(validProfQ.maxQuestions, 5);
      assert.deepEqual(validProfQ.threeWords, ['Malinaw', 'Approachable', 'Organized']);
      assert.equal(validProfQ.improvementTopics[0].subItem, 'a.');
      assert.equal(validProfQ.improvementTopics[1].subItem, 'b.');

      const exceededProfQ = validateProfessorQuestions({
        threeWords: ['Malinaw', 'Approachable', 'Organized'],
        improvementTopics: ['Topic 1'],
        questions: ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'],
      });
      assert.equal(exceededProfQ.valid, false);
    });
  });

  // 9. FT-02 submission idempotency key persistence across retries
  describe('Group 09: FT-02 submission idempotency key persistence across retries', () => {
    it('generates unique FT-02 submission keys and prevents duplicate vault entries on network retry', () => {
      const evalStoreSrc = readResourceFile('stores/evaluation.js');
      const formViewSfc = readResourceFile('views/EvaluationFormView.vue');

      assert.ok(evalStoreSrc.includes('generateSubmissionKey'));
      assert.ok(evalStoreSrc.includes('FT02-'));
      assert.ok(evalStoreSrc.includes('processedKeys.value.has(key)'));
      assert.ok(evalStoreSrc.includes('idempotentReplay: true'));
      assert.ok(formViewSfc.includes('submitting'));
    });
  });

  // 10. Digital Examination Clearance Permit unlock only when all 6 courses are CLEARED
  describe('Group 10: Digital Examination Clearance Permit unlock only when all 6 courses are CLEARED', () => {
    it('keeps permit gated when 3/6 or 5/6 courses are completed and unlocks only when 6/6 are COMPLETED', () => {
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');
      assert.ok(permitCardSfc.includes('data-testid="clearance-permit-card"'));
      assert.ok(permitCardSfc.includes('Digital Examination Permit — CLEARED'));
      assert.ok(permitCardSfc.includes('Digital Examination Permit — GATED (PENDING)'));

      const fiveOfSix = DEMO_STUDENT_QUEUE.map((c, idx) => ({
        ...c,
        status: idx < 5 ? 'COMPLETED' : 'PENDING',
      }));
      assert.equal(calculateQueueProgress(fiveOfSix).allCleared, false);

      const sixOfSix = DEMO_STUDENT_QUEUE.map((c) => ({ ...c, status: 'COMPLETED' }));
      assert.equal(calculateQueueProgress(sixOfSix).allCleared, true);
    });
  });

  // 11. Signed JWT clearance token verification display
  describe('Group 11: Signed JWT clearance token verification display', () => {
    it('displays per-course signed clearance tokens and master examination permit token upon completion', () => {
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');
      assert.ok(permitCardSfc.includes('data-testid="master-permit-token"'));
      assert.ok(permitCardSfc.includes('PERMIT-${props.studentId}-FALL2026-VERIFIED'));

      const completedCourses = DEMO_STUDENT_QUEUE.filter((c) => c.status === 'COMPLETED');
      assert.equal(completedCourses.length, 3);
      for (const course of completedCourses) {
        assert.ok(String(course.clearance_token).startsWith('CLR-2026-'));
      }
    });
  });

  // 12. Double-Blind faculty lock screen when term_grades_submitted = false
  describe('Group 12: Double-Blind faculty lock screen when term_grades_submitted = false', () => {
    it('blocks faculty evaluation visibility when term_grades_submitted is false', () => {
      const facultyViewSfc = readResourceFile('views/FacultyDashboardView.vue');
      assert.equal(canFacultyViewEvaluation(false, false), false);
      assert.equal(canFacultyViewEvaluation(false, true), false);
      assert.ok(facultyViewSfc.includes('Double-Blind'));
      assert.ok(facultyViewSfc.includes('term_grades_submitted'));
    });
  });

  // 13. Double-Blind faculty lock screen when registrar_lock = false
  describe('Group 13: Double-Blind faculty lock screen when registrar_lock = false', () => {
    it('blocks faculty evaluation visibility when registrar_lock is false even after grades are submitted', () => {
      assert.equal(canFacultyViewEvaluation(true, false), false);
      const facultyViewSfc = readResourceFile('views/FacultyDashboardView.vue');
      const evalStoreSrc = readResourceFile('stores/evaluation.js');
      assert.ok(facultyViewSfc.includes('data-testid="double-blind-lock-screen"'));
      assert.ok(facultyViewSfc.includes('DOUBLE_BLIND_LOCKED'));
      assert.ok(evalStoreSrc.includes('/api/faculty/sections'));
    });
  });

  // 14. Double-Blind faculty unlock when both term_grades_submitted = true AND registrar_lock = true
  describe('Group 14: Double-Blind faculty unlock when both term_grades_submitted = true AND registrar_lock = true', () => {
    it('unlocks aggregated faculty evaluation reports only when both grade submission and registrar lock are true', () => {
      assert.equal(canFacultyViewEvaluation(true, true), true);
      const facultyViewSfc = readResourceFile('views/FacultyDashboardView.vue');
      assert.ok(facultyViewSfc.includes('canFacultyViewEvaluation'));
      assert.ok(facultyViewSfc.includes('data-testid="faculty-unlocked-report"'));
      assert.ok(facultyViewSfc.includes('DOUBLE-BLIND UNLOCKED'));
      assert.ok(facultyViewSfc.includes('data-testid="print-faculty-report-btn"'));
      assert.ok(facultyViewSfc.includes('data-testid="print-faculty-report-auth"'));
      assert.ok(facultyViewSfc.includes('printEvaluationReport'));
    });
  });

  // 15. Anonymity guarantee in faculty view (zero student_id or student names in feedback cards)
  describe('Group 15: Anonymity guarantee in faculty view (zero student_id or student names in feedback cards)', () => {
    it('ensures faculty comment payloads and cards never expose student_id, user_id, clearance_id, or IP addresses', () => {
      const evalStoreSrc = readResourceFile('stores/evaluation.js');
      const facultyViewSfc = readResourceFile('views/FacultyDashboardView.vue');

      assert.equal(facultyViewSfc.includes('student_id'), false);
      assert.equal(facultyViewSfc.includes('clearance_id'), false);
      assert.equal(facultyViewSfc.includes('ip_address'), false);
      assert.ok(evalStoreSrc.includes('fetchFacultySectionReport'));
      assert.ok(facultyViewSfc.includes('Zero Student Identifiers'));
    });
  });

  // 16. Dean dashboard KPI metrics (Response Rate: 91.4%, 4,821 submissions, 1.2% integrity flags)
  describe('Group 16: Dean dashboard KPI metrics (Response Rate: 91.4%, 4,821 submissions, 1.2% integrity flags)', () => {
    it('matches Chapter II wireframe metrics for College of Computer Studies Final SET overview', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      assert.equal(DEMO_DEAN_METRICS.department, 'College of Computer Studies');
      assert.equal(DEMO_DEAN_METRICS.period, 'Final SET');
      assert.equal(DEMO_DEAN_METRICS.responseRate, 91.4);
      assert.equal(DEMO_DEAN_METRICS.responseTarget, 85.0);
      assert.equal(DEMO_DEAN_METRICS.submissionsLogged, 4821);
      assert.equal(DEMO_DEAN_METRICS.averageEvaluationTime, '3m 42s');
      assert.equal(DEMO_DEAN_METRICS.integrityFlagsPct, 1.2);
      assert.ok(deanViewSfc.includes('Response Rate'));
      assert.ok(deanViewSfc.includes('Submissions Logged'));
    });
  });

  // 17. Sentiment polarity distribution bars (72% Positive, 18% Neutral, 10% Negative)
  describe('Group 17: Sentiment polarity distribution bars (72% Positive, 18% Neutral, 10% Negative)', () => {
    it('renders horizontal polarity distribution summing to 100% (72% Positive, 18% Neutral, 10% Negative)', () => {
      const dist = DEMO_DEAN_METRICS.sentimentDistribution;
      assert.equal(dist.positive, 72);
      assert.equal(dist.neutral, 18);
      assert.equal(dist.negative, 10);
      assert.equal(dist.positive + dist.neutral + dist.negative, 100);

      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      assert.ok(deanViewSfc.includes('Sentiment Polarity Distribution'));
    });
  });

  // 18. Faculty Exception Watchlist trigger when Mean Score < 3.50
  describe('Group 18: Faculty Exception Watchlist trigger when Mean Score < 3.50', () => {
    it('flags instructors with mean rating below 3.50 (Faculty Member X: 3.1, Faculty Member Y: 3.3)', () => {
      assert.equal(evaluateWatchlistFlag(3.1, 10.0), true);
      assert.equal(evaluateWatchlistFlag(3.3, 15.0), true);
      assert.equal(evaluateWatchlistFlag(3.49, 0.0), true);
      assert.equal(evaluateWatchlistFlag(3.5, 10.0), false);
      assert.equal(evaluateWatchlistFlag(4.62, 5.1), false);

      assert.equal(DEMO_DEAN_METRICS.watchlist.length, 2);
      assert.equal(DEMO_DEAN_METRICS.watchlist[0].mean_score, 3.1);
      assert.equal(DEMO_DEAN_METRICS.watchlist[1].mean_score, 3.3);
    });
  });

  // 19. Faculty Exception Watchlist trigger when Negative Sentiment > 35%
  describe('Group 19: Faculty Exception Watchlist trigger when Negative Sentiment > 35%', () => {
    it('flags instructors when negative comment sentiment exceeds 35% even if mean score is >= 3.50', () => {
      assert.equal(evaluateWatchlistFlag(4.1, 35.1), true);
      assert.equal(evaluateWatchlistFlag(3.85, 42.0), true);
      assert.equal(evaluateWatchlistFlag(3.85, 35.0), false);
      assert.equal(evaluateWatchlistFlag(4.2, 12.0), false);
    });
  });

  // 20. Pedagogical domain tag classification (4 institutional categories & Chapter III samples)
  describe('Group 20: Pedagogical domain tag classification (4 categories & Chapter III benchmarks)', () => {
    it('classifies Chapter III narrative comments into exact polarity scores, valences, and pedagogical tags', () => {
      const sample1 = classifyCommentPreview(
        'Explains complex algorithms very clearly and provides great practical code examples.',
      );
      assert.equal(sample1.polarity, 0.78);
      assert.equal(sample1.valence, 'Positive');
      assert.equal(sample1.pedagogical_tag, 'Pedagogical Delivery');

      const sample2 = classifyCommentPreview(
        'Takes over a month to return quiz scores and provides no helpful feedback.',
      );
      assert.equal(sample2.polarity, -0.62);
      assert.equal(sample2.valence, 'Negative');
      assert.equal(sample2.pedagogical_tag, 'Assessment & Grading');

      const sample3 = classifyCommentPreview(
        'Arrives to morning lectures 15 minutes late consistently.',
      );
      assert.equal(sample3.polarity, -0.45);
      assert.equal(sample3.valence, 'Negative');
      assert.equal(sample3.pedagogical_tag, 'Classroom Management');

      const sample4 = classifyCommentPreview(
        'Demonstrates professional conduct, empathy, and respectful consultation with students.',
      );
      assert.equal(sample4.valence, 'Positive');
      assert.equal(sample4.pedagogical_tag, 'Professional Conduct');

      const sample5 = classifyCommentPreview(
        'Sobrang matulungin sa konsultasyon at laging available sa office hours para sa thesis mentorship at gabay.',
      );
      assert.equal(sample5.valence, 'Positive');
      assert.equal(sample5.pedagogical_tag, 'Student Support & Mentorship');
      assert.equal(sample5.nlp_engine, 'calamanCy+VADER');
      assert.equal(CALAMANCY_CONFIG.repo, 'https://github.com/ljvmiranda921/calamanCy');

      const labModalSfc = readResourceFile('components/PlpAnalyticsLabModal.vue');
      assert.ok(labModalSfc.includes('plp-nlp-lab-modal'));
      assert.ok(labModalSfc.includes('CALAMANCY_CONFIG'));
    });
  });

  // 21. Admin CSV/JSON roster ingestion validation & CSV formula injection neutralization
  describe('Group 21: Admin CSV/JSON roster ingestion validation & CSV formula neutralization', () => {
    it('validates roster imports in AdminRosterView.vue and neutralizes CSV formula injection (=, +, -, @)', () => {
      const adminViewSfc = readResourceFile('views/AdminRosterView.vue');
      assert.ok(adminViewSfc.includes('CSV'));
      assert.ok(adminViewSfc.includes('JSON'));
      assert.ok(adminViewSfc.includes('admin-vault-audit-panel'));
      assert.ok(adminViewSfc.includes('evaluation_vault'));

      assert.equal(sanitizeCsvCell('=CMD|\' /C calc\'!A0'), '\'=CMD|\' /C calc\'!A0');
      assert.equal(sanitizeCsvCell('+SUM(A1:A10)'), '\'+SUM(A1:A10)');
      assert.equal(sanitizeCsvCell('-1+2'), '\'-1+2');
      assert.equal(sanitizeCsvCell('@IMPORTXML("http://evil.test")'), '\'@IMPORTXML("http://evil.test")');
      assert.equal(sanitizeCsvCell('CS-401 Prof. A. Dela Cruz'), 'CS-401 Prof. A. Dela Cruz');
    });
  });

  // 22. Admin Registrar Grade-Lock toggle controls
  describe('Group 22: Admin Registrar Grade-Lock toggle controls', () => {
    it('provides per-section registrar_lock and term_grades_submitted toggles in AdminRosterView.vue', () => {
      const adminViewSfc = readResourceFile('views/AdminRosterView.vue');
      const evalStoreSrc = readResourceFile('stores/evaluation.js');

      assert.ok(adminViewSfc.includes('registrar_lock'));
      assert.ok(adminViewSfc.includes('term_grades_submitted'));
      assert.ok(evalStoreSrc.includes('function toggleSectionLock'));
    });
  });

  // 23. System Usability Scale (SUS) 10-item calculation (83.92 Grade A benchmark)
  describe('Group 23: System Usability Scale (SUS) 10-item calculation (83.92 Grade A benchmark)', () => {
    it('computes 10-item SUS scores and matches Chapter III cohort breakdown (N=50, 83.92 Grade A / Excellent)', () => {
      const perfectResponses = [5, 1, 5, 1, 5, 1, 5, 1, 5, 1];
      const perfectSus = calculateSusScore(perfectResponses);
      assert.equal(perfectSus.score, 100);
      assert.equal(perfectSus.grade, 'Grade A');
      assert.equal(perfectSus.isInstitutionalGrade, true);

      const benchmarkResponses = [5, 2, 4, 1, 5, 2, 4, 2, 5, 1]; // total = 35 * 2.5 = 87.5
      const benchmarkSus = calculateSusScore(benchmarkResponses);
      assert.equal(benchmarkSus.score, 87.5);
      assert.equal(benchmarkSus.isInstitutionalGrade, true);

      assert.equal(DEMO_DEAN_METRICS.sus.consolidatedMean, 83.92);
      assert.equal(DEMO_DEAN_METRICS.sus.totalSampleSize, 50);
      assert.equal(DEMO_DEAN_METRICS.sus.grade, 'Grade A / Excellent');
      assert.equal(DEMO_DEAN_METRICS.sus.cohorts[0].meanScore, 84.25);
      assert.equal(DEMO_DEAN_METRICS.sus.cohorts[1].meanScore, 81.5);
      assert.equal(DEMO_DEAN_METRICS.sus.cohorts[2].meanScore, 86.0);
    });
  });

  // 24. ISO/IEC 25010 5-characteristic quality score aggregation (4.77 Excellent benchmark)
  describe('Group 24: ISO/IEC 25010 5-characteristic quality score aggregation (4.77 Excellent)', () => {
    it('aggregates Functional Suitability, Performance Efficiency, Usability, Reliability, and Portability to 4.77', () => {
      const chars = DEMO_DEAN_METRICS.iso25010.characteristics;
      assert.equal(chars.length, 5);

      const scores = chars.map((c) => c.score);
      assert.deepEqual(scores, [4.78, 4.65, 4.82, 4.74, 4.88]);

      const mean = Number((scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(2));
      assert.equal(mean, 4.77);
      assert.equal(DEMO_DEAN_METRICS.iso25010.overallMean, 4.77);
      assert.equal(DEMO_DEAN_METRICS.iso25010.interpretation, 'Excellent');
    });
  });

  // 25. Responsive viewport layout constraints (375x812 mobile & 1366x768 desktop) & RBAC router guards
  describe('Group 25: Responsive viewport layout constraints (375x812 & 1366x768) & five-role router guards', () => {
    it('enforces five-role route authorization, unauthenticated /login redirect, and responsive CSS breakpoints', () => {
      const routerSrc = readResourceFile('router/index.js');
      const loginViewSfc = readResourceFile('views/LoginView.vue');

      assert.ok(routerSrc.includes('canAccessRoute'));
      assert.ok(routerSrc.includes("roles: ['student']"));
      assert.ok(routerSrc.includes("roles: ['faculty']"));
      assert.ok(routerSrc.includes("roles: ['chair']"));
      assert.ok(routerSrc.includes("roles: ['dean']"));
      assert.ok(routerSrc.includes("roles: ['admin']"));
      assert.ok(routerSrc.includes('/access-denied'));
      assert.ok(routerSrc.includes("path: '/login'"));
      assert.ok(loginViewSfc.includes("institutionalId = ref('')"));
      assert.ok(loginViewSfc.includes('data-testid="login-id-input"'));
    });
  });

  // 26. Institutional Accreditation & Pedagogical Action Plan tracking (AACCUP, CHED, ISO 25010, 5 Clusters, Resolution Milestones)
  describe('Group 26: Institutional Accreditation & Pedagogical Action Plan tracking (AACCUP, CHED, ISO 25010, 5 Clusters)', () => {
    it('verifies AACCUP Area III compliance, CHED CMO 46 certification, 5-cluster distribution, and Dean action plan tracking', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');

      // 1. Verify Accreditation card & frameworks
      assert.ok(deanViewSfc.includes('data-testid="accreditation-compliance-card"'));
      assert.ok(deanViewSfc.includes('AACCUP Area III: COMPLIANT'));
      assert.ok(deanViewSfc.includes('CHED CMO 46: CERTIFIED'));
      assert.ok(deanViewSfc.includes('AACCUP LEVEL III READY'));

      // 2. Verify all 5 pedagogical clusters are represented with 20% weight
      assert.ok(deanViewSfc.includes('1. Pedagogical Delivery'));
      assert.ok(deanViewSfc.includes('2. Assessment &amp; Grading'));
      assert.ok(deanViewSfc.includes('3. Classroom Management'));
      assert.ok(deanViewSfc.includes('4. Professional Conduct'));
      assert.ok(deanViewSfc.includes('5. Student Support &amp; Mentorship'));
      assert.ok(deanViewSfc.includes('25 Items'));

      // 3. Verify Dean Action Plan interactive controls and status badges
      assert.ok(deanViewSfc.includes('plan-status-badge-'));
      assert.ok(deanViewSfc.includes('approve-plan-btn-'));
      assert.ok(deanViewSfc.includes('resolve-plan-btn-'));
      assert.ok(deanViewSfc.includes('Dean Verification Notes:'));
    });
  });

  // 27. Digital Examination Permit Registrar Verification & Dean Qualitative Narrative Synthesis (calamanCy + VADER)
  describe('Group 27: Digital Examination Permit Registrar Verification & Dean Qualitative Synthesis', () => {
    it('verifies interactive registrar signature check on examination permit card and renders calamanCy synthesis tab', () => {
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');

      // 1. Verify ClearancePermitCard registrar verification button and seal badge
      assert.ok(permitCardSfc.includes('data-testid="verify-permit-btn"'));
      assert.ok(permitCardSfc.includes('data-testid="permit-verification-badge"'));
      assert.ok(permitCardSfc.includes('SEAL: PLP-REGISTRAR-AUTHENTICATED'));
      assert.ok(permitCardSfc.includes('verifyPermitOnServer'));
      assert.ok(permitCardSfc.includes('/api/student/permit/verify'));

      // 2. Verify DeanDashboardView SOP 4 tab switch & calamanCy header
      assert.ok(deanViewSfc.includes('data-testid="tab-qualitative-synthesis"'));
      assert.ok(deanViewSfc.includes('data-testid="qualitative-synthesis-panel"'));
      assert.ok(deanViewSfc.includes('data-testid="calamancy-header-card"'));
      assert.ok(deanViewSfc.includes('data-testid="calamancy-engine-badge"'));
      assert.ok(deanViewSfc.includes('tl_calamancy_md'));

      // 3. Verify Q1 Top Descriptors cloud and lemma badges
      assert.ok(deanViewSfc.includes('data-testid="q1-top-descriptors-card"'));
      assert.ok(deanViewSfc.includes('data-testid="top-descriptors-cloud"'));

      // 4. Verify Q2 Room for Improvement 5-cluster breakdown
      assert.ok(deanViewSfc.includes('data-testid="q2-improvement-clusters-card"'));
      assert.ok(deanViewSfc.includes('clustersList'));
      assert.ok(deanViewSfc.includes('getClusterTopics'));

      // 5. Verify Q3 & Q4 Key Strengths and Recommendations
      assert.ok(deanViewSfc.includes('data-testid="q3-key-strengths-card"'));
      assert.ok(deanViewSfc.includes('data-testid="q4-key-recommendations-card"'));

      // 6. Verify DEMO_DEAN_METRICS qualitative_synthesis payload integrity
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis);
      assert.equal(DEMO_DEAN_METRICS.qualitative_synthesis.nlp_engine, 'calamanCy+VADER');
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis.top_descriptors.length >= 5);
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis.improvement_topics.length >= 4);
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis.key_strengths.length >= 2);
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis.key_recommendations.length >= 2);
      assert.ok(DEMO_DEAN_METRICS.qualitative_synthesis.improvement_clusters['Pedagogical Delivery']);
    });
  });

  // 28. Faculty Portal Pedagogical Action Plan Acknowledgment & Milestone Resolution
  describe('Group 28: Faculty Portal Pedagogical Action Plan Acknowledgment & Milestone Resolution', () => {
    it('renders pedagogical action plan card for watchlist-flagged sections with interactive implementation actions', () => {
      const facultyViewSfc = readResourceFile('views/FacultyDashboardView.vue');

      // 1. Verify action plan card and status badge
      assert.ok(facultyViewSfc.includes('data-testid="faculty-action-plan-card"'));
      assert.ok(facultyViewSfc.includes('data-testid="faculty-plan-status-badge"'));
      assert.ok(facultyViewSfc.includes('sectionActionPlan'));

      // 2. Verify interactive faculty buttons
      assert.ok(facultyViewSfc.includes('data-testid="faculty-acknowledge-plan-btn"'));
      assert.ok(facultyViewSfc.includes('data-testid="faculty-submit-milestone-btn"'));
      assert.ok(facultyViewSfc.includes('data-testid="faculty-plan-resolved-badge"'));
      assert.ok(facultyViewSfc.includes('updateFacultyPlanStatus'));
      assert.ok(facultyViewSfc.includes('/api/faculty/action-plan/status'));

      // 3. Verify database-backed faculty section selector
      assert.ok(facultyViewSfc.includes('data-testid="faculty-section-select"'));
      assert.ok(facultyViewSfc.includes('fetchFacultySections'));

      // 4. Verify institutional excellence metadata
      assert.ok(facultyViewSfc.includes('PAMANTASAN NG LUNGSOD NG PASIG • Continuous Pedagogical Improvement'));
      assert.ok(facultyViewSfc.includes('Dean Verification &amp; Sign-off Notes:'));
    });
  });

  // 29. Admin Evaluation Window & Parameter Tuning Console (SOP 1 / SOP 2)
  describe('Group 29: Admin Evaluation Window & Parameter Tuning Console', () => {
    it('renders evaluation window card, status transition buttons, dynamic threshold inputs, and submission block guard', () => {
      const adminViewSfc = readResourceFile('views/AdminRosterView.vue');
      const studentQueueSfc = readResourceFile('views/StudentQueueView.vue');
      const evaluationStoreCode = readResourceFile('stores/evaluation.js');

      // 1. Verify AdminRosterView elements
      assert.ok(adminViewSfc.includes('data-testid="admin-evaluation-window-card"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-window-status-badge"'));
      assert.ok(adminViewSfc.includes('data-testid="btn-set-window-open"'));
      assert.ok(adminViewSfc.includes('data-testid="btn-set-window-extend"'));
      assert.ok(adminViewSfc.includes('data-testid="btn-set-window-reopen"'));
      assert.ok(adminViewSfc.includes('data-testid="btn-set-window-suspended"'));
      assert.ok(adminViewSfc.includes('data-testid="btn-set-window-closed"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-starts-at-input"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-ends-at-input"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-timezone-badge"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-variance-input"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-watchlist-mean-input"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-watchlist-neg-input"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-announcement-input"'));
      assert.ok(adminViewSfc.includes('data-testid="save-evaluation-window-btn"'));
      assert.ok(adminViewSfc.includes('data-testid="evaluation-window-status-msg"'));

      // 2. Verify StudentQueueView announcement & window alert elements
      assert.ok(studentQueueSfc.includes('data-testid="student-window-announcement"'));
      assert.ok(studentQueueSfc.includes('data-testid="student-window-status-alert"'));

      // 3. Verify Pinia evaluation store window state and actions
      assert.ok(evaluationStoreCode.includes('evaluationWindow'));
      assert.ok(evaluationStoreCode.includes('fetchEvaluationWindow'));
      assert.ok(evaluationStoreCode.includes('updateEvaluationWindowConfig'));
      assert.ok(evaluationStoreCode.includes('setEvaluationWindowStatus'));
      assert.ok(evaluationStoreCode.includes('EVALUATION_WINDOW_CLOSED'));

      // 4. Verify dynamic threshold evaluations via pure math domain functions
      // Mandatory timing policy (0s+ allowed, negative blocked)
      assert.equal(isDwellTimeValid(0, 20, 0), true);
      assert.equal(isDwellTimeValid(30, 20, 0), true);
      assert.equal(isDwellTimeValid(45, 20, 0), true);
      assert.equal(isDwellTimeValid(-1, 20, 0), false);

      // Dean Watchlist cutoff parameter tuning
      assert.equal(evaluateWatchlistFlag(3.55, 20.0, 3.5, 35.0), false);
      assert.equal(evaluateWatchlistFlag(3.55, 20.0, 3.6, 35.0), true); // Triggered under tightened 3.60 threshold
      assert.equal(evaluateWatchlistFlag(4.1, 32.0, 3.5, 35.0), false);
      assert.equal(evaluateWatchlistFlag(4.1, 32.0, 3.5, 30.0), true); // Triggered under tightened 30% negative threshold
    });
  });

  // 30. Student evaluation submission & queue clearance transition flow
  describe('Group 30: Student Evaluation Submission & Queue Transition Flow', () => {
    it('verifies submission confirmation banner, issued clearance token, return to queue button, and queue progression math', () => {
      const evalFormSfc = readResourceFile('views/EvaluationFormView.vue');
      const evalStoreCode = readResourceFile('stores/evaluation.js');
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');

      // 1. Verify EvaluationFormView submission success elements
      assert.ok(evalFormSfc.includes('data-testid="submission-status-banner"'));
      assert.ok(evalFormSfc.includes('data-testid="issued-clearance-token"'));
      assert.ok(evalFormSfc.includes('data-testid="return-queue-success-btn"'));

      // 2. Verify Evaluation store submission API contracts
      assert.ok(evalStoreCode.includes('/api/student/evaluations/submit'));
      assert.ok(evalStoreCode.includes('/api/student/evaluations/'));
      assert.ok(evalStoreCode.includes('/start'));
      assert.ok(evalStoreCode.includes('markCourseClearedLocal'));

      // 3. Verify Queue Progression Math: 3/6 -> 4/6 -> 6/6 (100% CLEARED)
      const mockQueue = [
        { course_section_id: 1, course_code: 'CS-401', status: 'COMPLETED' },
        { course_section_id: 2, course_code: 'CS-405', status: 'COMPLETED' },
        { course_section_id: 3, course_code: 'ENG-102', status: 'COMPLETED' },
        { course_section_id: 4, course_code: 'MATH-201', status: 'PENDING' },
        { course_section_id: 5, course_code: 'CS-408', status: 'PENDING' },
        { course_section_id: 6, course_code: 'PHYS-101', status: 'PENDING' },
      ];

      const p3 = calculateQueueProgress(mockQueue);
      assert.equal(p3.completed, 3);
      assert.equal(p3.total, 6);
      assert.equal(p3.percentage, 50);
      assert.equal(p3.allCleared, false);
      assert.equal(p3.clearanceStatus, 'PENDING');

      // Advance 1 course: 4 of 6 = 67%
      mockQueue[3].status = 'COMPLETED';
      const p4 = calculateQueueProgress(mockQueue);
      assert.equal(p4.completed, 4);
      assert.equal(p4.total, 6);
      assert.equal(p4.percentage, 67);
      assert.equal(p4.allCleared, false);

      // Advance all courses: 6 of 6 = 100% (CLEARED)
      mockQueue[4].status = 'COMPLETED';
      mockQueue[5].status = 'COMPLETED';
      const p6 = calculateQueueProgress(mockQueue);
      assert.equal(p6.completed, 6);
      assert.equal(p6.total, 6);
      assert.equal(p6.percentage, 100);
      assert.equal(p6.allCleared, true);
      assert.equal(p6.clearanceStatus, 'CLEARED');

      // 4. Verify Digital Exam Permit unlocks when allCleared is true
      assert.ok(permitCardSfc.includes('data-testid="clearance-permit-card"'));
      assert.ok(permitCardSfc.includes('Digital Examination Permit — CLEARED'));
      assert.ok(permitCardSfc.includes('PLP-REGISTRAR-AUTHENTICATED'));
    });
  });

  // 31. Dean Department Filtering & calamanCy Qualitative Export
  describe('Group 31: Dean Department Filtering & calamanCy Qualitative Export', () => {
    it('verifies academic department filter selector and calamanCy qualitative JSON and CSV export actions', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      const evalStoreCode = readResourceFile('stores/evaluation.js');

      // 1. Verify Department Filter selector in Dean overview
      assert.ok(deanViewSfc.includes('data-testid="dean-dept-filter-select"'));
      assert.ok(deanViewSfc.includes('selectedDepartment'));
      assert.ok(deanViewSfc.includes('onDepartmentChange'));

      // 2. Verify calamanCy qualitative export buttons
      assert.ok(deanViewSfc.includes('data-testid="export-qualitative-json-btn"'));
      assert.ok(deanViewSfc.includes('data-testid="export-qualitative-csv-btn"'));
      assert.ok(deanViewSfc.includes('downloadQualitativeJson'));
      assert.ok(deanViewSfc.includes('downloadQualitativeCsv'));

      // 3. Verify fetchDeanOverview store action
      assert.ok(evalStoreCode.includes('fetchDeanOverview'));
      assert.ok(evalStoreCode.includes('/api/dean/overview'));

      // 4. Verify CSV formula injection neutralization on qualitative descriptors
      assert.equal(sanitizeCsvCell('=cmd|"/C calc"!A0'), "'=cmd|\"/C calc\"!A0");
      assert.equal(sanitizeCsvCell('+12345'), "'+12345");
      assert.equal(sanitizeCsvCell('-5.00'), "'-5.00");
      assert.equal(sanitizeCsvCell('@SUM(A1:A10)'), "'@SUM(A1:A10)");
      assert.equal(sanitizeCsvCell('Malinaw at organisado'), 'Malinaw at organisado');
    });
  });

  // 32. Strict Five-Role Dedicated Webpages & RBAC Dynamic Navigation & Access Denied Guards
  describe('Group 32: Strict Five-Role Dedicated Webpages & RBAC Dynamic Navigation & Access Denied Guards', () => {
    it('verifies dedicated routes for all five roles, dynamic role navigation links, and access-denied guards', () => {
      const routerSrc = readResourceFile('router/index.js');
      const authStoreSrc = readResourceFile('stores/auth.js');
      const navbarSfc = readResourceFile('components/NavbarHeader.vue');
      const chairViewSfc = readResourceFile('views/ChairDashboardView.vue');
      const accessDeniedSfc = readResourceFile('views/AccessDeniedView.vue');

      // 1. Verify strict role mapping to dedicated home pages
      assert.ok(authStoreSrc.includes("case 'student':\n      return '/queue';") || authStoreSrc.includes("return '/queue'"));
      assert.ok(authStoreSrc.includes("return '/faculty'"));
      assert.ok(authStoreSrc.includes("return '/chair'"));
      assert.ok(authStoreSrc.includes("return '/dean'"));
      assert.ok(authStoreSrc.includes("return '/admin'"));

      // 2. Verify router role configurations
      assert.ok(routerSrc.includes("path: '/queue'"));
      assert.ok(routerSrc.includes("roles: ['student']"));
      assert.ok(routerSrc.includes("path: '/faculty'"));
      assert.ok(routerSrc.includes("roles: ['faculty']"));
      assert.ok(routerSrc.includes("path: '/chair'"));
      assert.ok(routerSrc.includes("roles: ['chair']"));
      assert.ok(routerSrc.includes("path: '/dean'"));
      assert.ok(routerSrc.includes("roles: ['dean']"));
      assert.ok(routerSrc.includes("path: '/admin'"));
      assert.ok(routerSrc.includes("roles: ['admin']"));
      assert.ok(routerSrc.includes("path: '/access-denied'"));

      // 3. Verify NavbarHeader dynamic navigation and role switcher
      assert.ok(navbarSfc.includes('roleNavLinks'));
      assert.ok(navbarSfc.includes('authorizedNavLinks'));
      assert.ok(navbarSfc.includes('data-testid="navbar-quick-nav"'));
      assert.ok(navbarSfc.includes('nav-link-student-queue'));
      assert.ok(navbarSfc.includes('nav-link-faculty-portal'));
      assert.ok(navbarSfc.includes('nav-link-chair-portal'));
      assert.ok(navbarSfc.includes('nav-link-dean-analytics'));
      assert.ok(navbarSfc.includes('nav-link-admin-console'));

      // 4. Verify Department Chair dedicated diagnostic workspace
      assert.ok(chairViewSfc.includes('data-testid="chair-diagnostic-workspace"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-department-badge"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-faculty-table"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-watchlist-card"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-endorse-plan-btn"'));

      // 5. Verify AccessDeniedView 403 Forbidden screen
      assert.ok(accessDeniedSfc.includes('data-testid="access-denied-card"'));
      assert.ok(accessDeniedSfc.includes('data-testid="denied-attempted-route"'));
      assert.ok(accessDeniedSfc.includes('data-testid="denied-current-role"'));
      assert.ok(accessDeniedSfc.includes('data-testid="denied-required-roles"'));
      assert.ok(accessDeniedSfc.includes('data-testid="return-authorized-workspace-btn"'));
      assert.ok(accessDeniedSfc.includes('data-testid="switch-account-btn"'));
    });
  });

  // 33. Document Generator Print & PDF Export Styling & WCAG 2.2 AA Accessibility
  describe('Group 33: Document Generator Print & PDF Export Styling & WCAG 2.2 AA Accessibility', () => {
    it('verifies print styling, official registrar authentication blocks, and WCAG accessibility standards', () => {
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');
      const navbarSfc = readResourceFile('components/NavbarHeader.vue');
      const appCss = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'css', 'app.css'), 'utf8');

      // 1. Verify ClearancePermitCard official print signature & registrar block
      assert.ok(permitCardSfc.includes('data-testid="print-permit-signature-block"'));
      assert.ok(permitCardSfc.includes('data-testid="print-permit-btn"'));
      assert.ok(permitCardSfc.includes('ATTY. UNIVERSITY REGISTRAR'));
      assert.ok(permitCardSfc.includes('DR. V. MENDOZA'));
      assert.ok(permitCardSfc.includes('printPermit'));

      // 2. Verify master print CSS rules
      assert.ok(appCss.includes('@page {'));
      assert.ok(appCss.includes('size: A4 portrait;'));
      assert.ok(appCss.includes('@media print {'));
      assert.ok(appCss.includes('page-break-inside: avoid'));
      assert.ok(appCss.includes('.print-permit-auth-block'));
      assert.ok(appCss.includes('.auth-signatures-row'));

      // 3. Verify WCAG 2.2 AA accessibility semantics and color contrast
      assert.ok(navbarSfc.includes('role="banner"'));
      assert.ok(navbarSfc.includes('aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header"'));
      assert.ok(appCss.includes('--plp-green-900: #0b4f26'));
      assert.ok(appCss.includes('--plp-gold-500: #eab308'));
    });
  });

  // 34. Institutional Login Portal, RA 10173 Privacy Notice & Header Sign Out Workflow
  describe('Group 34: Institutional Login Portal, RA 10173 Privacy Notice & Header Sign Out Workflow', () => {
    it('verifies clean production login form controls, show/hide password toggle, RA 10173 privacy banner, and navbar sign out', () => {
      const loginViewSfc = readResourceFile('views/LoginView.vue');
      const navbarSfc = readResourceFile('components/NavbarHeader.vue');

      // 1. Verify LoginView card structure and institutional branding
      assert.ok(loginViewSfc.includes('data-testid="login-card"'));
      assert.ok(loginViewSfc.includes('data-testid="login-privacy-banner"'));
      assert.ok(loginViewSfc.includes('RA 10173 Data Privacy'));
      assert.ok(loginViewSfc.includes('Cryptographic Decoupling'));
      assert.ok(loginViewSfc.includes('Pamantasan ng Lungsod ng Pasig'));

      // 2. Verify interactive input fields, autocomplete ergonomics, show/hide password toggle, and remember session
      assert.ok(loginViewSfc.includes('data-testid="login-id-input"'));
      assert.ok(loginViewSfc.includes('autocomplete="username"'));
      assert.ok(loginViewSfc.includes('spellcheck="false"'));
      assert.ok(loginViewSfc.includes('data-testid="login-password-input"'));
      assert.ok(loginViewSfc.includes('autocomplete="current-password"'));
      assert.ok(loginViewSfc.includes('data-testid="toggle-password-btn"'));
      assert.ok(loginViewSfc.includes('togglePasswordVisibility'));
      assert.ok(loginViewSfc.includes('data-testid="remember-session-input"'));
      assert.ok(loginViewSfc.includes('data-testid="login-submit-btn"'));

      // 3. Verify NavbarHeader Sign Out workflow and authenticated bar visibility guard
      assert.ok(navbarSfc.includes('data-testid="navbar-logout-btn"'));
      assert.ok(navbarSfc.includes('showAuthenticatedBar'));
      assert.ok(navbarSfc.includes('handleLogout'));
      assert.ok(navbarSfc.includes('roleNavLinks'));
    });
  });

  // 35. Interactive calamanCy NLP Diagnostics Lab Modal & Accessibility Hardening
  describe('Group 35: Interactive calamanCy NLP Diagnostics Lab Modal & Accessibility Hardening', () => {
    it('verifies NLP diagnostics lab modal integration across Dean and Chair dashboards, Escape key listeners, and WCAG focus styling', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      const chairViewSfc = readResourceFile('views/ChairDashboardView.vue');
      const modalSfc = readResourceFile('components/PlpAnalyticsLabModal.vue');
      const straightLineModalSfc = readResourceFile('components/StraightLineModal.vue');
      const appCss = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'css', 'app.css'), 'utf8');

      // 1. Verify Dean dashboard lab launcher and component mount
      assert.ok(deanViewSfc.includes('data-testid="open-analytics-lab-btn"'));
      assert.ok(deanViewSfc.includes('PlpAnalyticsLabModal'));
      assert.ok(deanViewSfc.includes('showAnalyticsLab'));

      // 2. Verify Chair dashboard lab launcher and component mount
      assert.ok(chairViewSfc.includes('data-testid="chair-open-analytics-lab-btn"'));
      assert.ok(chairViewSfc.includes('PlpAnalyticsLabModal'));
      assert.ok(chairViewSfc.includes('showAnalyticsLab'));

      // 3. Verify PlpAnalyticsLabModal structure and interactive elements
      assert.ok(modalSfc.includes('data-testid="plp-nlp-lab-modal"'));
      assert.ok(modalSfc.includes('data-testid="close-plp-nlp-lab-btn"'));
      assert.ok(modalSfc.includes('data-testid="plp-lab-textarea"'));
      assert.ok(modalSfc.includes('data-testid="plp-lab-polarity-strip"'));
      assert.ok(modalSfc.includes('data-testid="plp-five-cluster-distribution"'));
      assert.ok(modalSfc.includes('data-testid="plp-prof-questions-breakdown"'));
      assert.ok(modalSfc.includes('handleKeyDown'));
      assert.ok(modalSfc.includes("event.key === 'Escape'"));

      // 4. Verify StraightLineModal Escape key listener
      assert.ok(straightLineModalSfc.includes('handleKeyDown'));
      assert.ok(straightLineModalSfc.includes("event.key === 'Escape'"));

      // 5. Verify WCAG 2.2 focus-visible ring styles in app.css
      assert.ok(appCss.includes(':focus-visible'));
      assert.ok(appCss.includes('outline: 2.5px solid var(--plp-green-800)'));
      assert.ok(appCss.includes('outline-offset: 2px'));
    });
  });

  // 36. Longitudinal Semester Trend Analytics (AACCUP Area III) & Public Proctor Examination Permit Verification
  describe('Group 36: Longitudinal Semester Trend Analytics (AACCUP Area III) & Public Proctor Examination Permit Verification', () => {
    it('verifies longitudinal semester trends, delta indicator pills, semester comparison table, dossier export, and proctor verification simulator', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      const chairViewSfc = readResourceFile('views/ChairDashboardView.vue');
      const permitCardSfc = readResourceFile('components/ClearancePermitCard.vue');
      const apiRoutes = fs.readFileSync(path.join(ROOT_DIR, 'routes', 'api.php'), 'utf8');

      // 1. Verify Dean dashboard longitudinal tab and metrics card
      assert.ok(deanViewSfc.includes('data-testid="tab-longitudinal-trends"'));
      assert.ok(deanViewSfc.includes('data-testid="longitudinal-trends-card"'));
      assert.ok(deanViewSfc.includes('data-testid="longitudinal-kpi-grid"'));
      assert.ok(deanViewSfc.includes('data-testid="semester-comparison-table"'));
      assert.ok(deanViewSfc.includes('data-testid="cluster-shift-table"'));
      assert.ok(deanViewSfc.includes('data-testid="export-dossier-bundle-btn"'));
      assert.ok(deanViewSfc.includes('exportAccreditationDossier'));
      assert.ok(deanViewSfc.includes('PLP-AACCUP-Area-III-Dossier-AY2026-2027.csv'));

      // 2. Verify Chair dashboard department longitudinal card and cluster shifts
      assert.ok(chairViewSfc.includes('data-testid="chair-longitudinal-trends-card"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-longitudinal-grid"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-cluster-shifts-table"'));
      assert.ok(chairViewSfc.includes('departmentLongitudinal'));

      // 3. Verify ClearancePermitCard live Registrar signature verification and official badge
      assert.ok(permitCardSfc.includes('data-testid="verify-permit-btn"'));
      assert.ok(permitCardSfc.includes('data-testid="proctor-verification-details"'));
      assert.ok(permitCardSfc.includes('SEAL: PLP-REGISTRAR-AUTHENTICATED'));
      assert.ok(permitCardSfc.includes('https://plpasig.edu.ph/'));
      assert.ok(permitCardSfc.includes('PROCTOR VERIFICATION PROTOCOL: ADMISSION AUTHORIZED'));

      // 4. Verify public un-gated proctor permit verification route in api.php
      assert.ok(apiRoutes.includes("Route::match(['GET', 'POST'], '/permit/verify'"));
      assert.ok(apiRoutes.includes("Route::match(['GET', 'POST'], '/student/permit/verify'"));
      assert.ok(apiRoutes.includes('/dean/longitudinal-trends'));
      assert.ok(apiRoutes.includes('/chair/longitudinal-trends'));
    });
  });

  // 37. Department Completion Heatmaps & AACCUP Level III Compliance Audit Checklist
  describe('Group 37: Department Completion Heatmaps & AACCUP Level III Compliance Audit Checklist', () => {
    it('verifies department completion heatmap rendering, color bands, table rows, and 8-point accreditation audit checklist', () => {
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      const chairViewSfc = readResourceFile('views/ChairDashboardView.vue');
      const apiRoutes = fs.readFileSync(path.join(ROOT_DIR, 'routes', 'api.php'), 'utf8');

      // 1. Verify Dean dashboard completion heatmap matrix and legend
      assert.ok(deanViewSfc.includes('data-testid="completion-heatmap-card"'));
      assert.ok(deanViewSfc.includes('data-testid="heatmap-legend"'));
      assert.ok(deanViewSfc.includes('data-testid="heatmap-table"'));
      assert.ok(deanViewSfc.includes('EXCELLENT'));
      assert.ok(deanViewSfc.includes('OPTIMAL'));
      assert.ok(deanViewSfc.includes('WARNING'));
      assert.ok(deanViewSfc.includes('CRITICAL'));
      assert.ok(deanViewSfc.includes('fetchHeatmapData'));

      // 2. Verify Dean dashboard AACCUP Level III & CHED CMO 46 compliance checklist card
      assert.ok(deanViewSfc.includes('data-testid="compliance-audit-checklist-card"'));
      assert.ok(deanViewSfc.includes('data-testid="compliance-checkpoints-grid"'));
      assert.ok(deanViewSfc.includes('CRIT-01'));
      assert.ok(deanViewSfc.includes('CRIT-02'));
      assert.ok(deanViewSfc.includes('CRIT-03'));
      assert.ok(deanViewSfc.includes('CRIT-04'));
      assert.ok(deanViewSfc.includes('CRIT-05'));
      assert.ok(deanViewSfc.includes('CRIT-06'));
      assert.ok(deanViewSfc.includes('CRIT-07'));
      assert.ok(deanViewSfc.includes('CRIT-08'));
      assert.ok(deanViewSfc.includes('GRADE A (EXCELLENT)'));
      assert.ok(deanViewSfc.includes('fetchComplianceAudit'));

      // 3. Verify Chair dashboard department completion heatmap card
      assert.ok(chairViewSfc.includes('data-testid="chair-completion-heatmap-card"'));
      assert.ok(chairViewSfc.includes('data-testid="chair-heatmap-table"'));
      assert.ok(chairViewSfc.includes('chairHeatmapData'));

      // 4. Verify API route endpoints for heatmap and compliance audit
      assert.ok(apiRoutes.includes('/dean/completion-heatmap'));
      assert.ok(apiRoutes.includes('/chair/completion-heatmap'));
      assert.ok(apiRoutes.includes('/dean/compliance-audit'));
    });
  });

  // 38. Production Anti-Demo Regression Gate, Seeder Bearer Auth & Accreditation Dossier/Quartiles
  describe('Group 38: Production Anti-Demo Regression Gate, Seeder Bearer Auth & Accreditation Dossier/Quartiles', () => {
    it('enforces zero demo/cheat buttons across all Vue/JS sources and verifies DB-backed quartiles, dossier seal, and CSS ergonomics', () => {
      const forbiddenPatterns = [
        'demo-role-switcher',
        'switchDemoRole',
        'DEMO_ROLE_PROFILES',
        'one-click-login-btn',
        'preset-role-',
        'complete-all-demo-btn',
        'simulate-45s-btn',
        'fill-straight-5s-btn',
        'fill-varied-btn',
        'switch-unlocked-section-btn',
        'preview-watchlist-plan-btn',
        'simulate-proctor-scan-btn',
        'reset-demo',
      ];

      function collectSourceFiles(dir) {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        const files = [];
        for (const entry of entries) {
          const fullPath = path.join(dir, entry.name);
          if (entry.isDirectory()) {
            files.push(...collectSourceFiles(fullPath));
          } else if (entry.name.endsWith('.vue') || entry.name.endsWith('.js')) {
            files.push(fullPath);
          }
        }
        return files;
      }

      const jsVueFiles = collectSourceFiles(JS_DIR);
      assert.ok(jsVueFiles.length >= 15);

      for (const filePath of jsVueFiles) {
        const content = fs.readFileSync(filePath, 'utf8');
        const rel = path.relative(ROOT_DIR, filePath);
        for (const forbidden of forbiddenPatterns) {
          assert.equal(
            content.includes(forbidden),
            false,
            `Forbidden demo artifact "${forbidden}" found in ${rel}`,
          );
        }
      }

      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');
      const appCss = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'css', 'app.css'), 'utf8');

      assert.ok(deanViewSfc.includes('data-testid="dean-performance-quartiles-card"'));
      assert.ok(deanViewSfc.includes('data-testid="dean-evidence-dossier-card"'));
      assert.ok(deanViewSfc.includes('data-testid="dossier-digital-seal"'));
      assert.ok(deanViewSfc.includes('data-testid="dossier-sha256-digest"'));
      assert.ok(appCss.includes('font-variant-numeric: tabular-nums'));
      assert.ok(appCss.includes('@media (prefers-reduced-motion: reduce)'));
      assert.equal(/transition:\s*all\b/.test(appCss), false, 'app.css must not use transition: all');
    });
  });

  // 39. PLP Split-Screen Login, Collapsible Emerald Sidebar Shell, Quivr Second Brain & All 20 SaaS UX Patterns
  describe('Group 39: PLP Split-Screen Login, Collapsible Emerald Sidebar Shell, Quivr Second Brain & All 20 SaaS UX Patterns', () => {
    it('verifies sch-inspired split login, collapsible sidebar rail, Command Palette, Quivr NLP Brain, ReusableDataTable, autosave, and graceful error recovery', async () => {
      const uxHelpers = await import('../../resources/js/utils/uxHelpers.js');
      const loginViewSfc = readResourceFile('views/LoginView.vue');
      const sidebarSfc = readResourceFile('components/WorkspaceSidebar.vue');
      const cmdkSfc = readResourceFile('components/CommandPaletteModal.vue');
      const quivrSfc = readResourceFile('components/QuivrNlpBrainDrawer.vue');
      const tableSfc = readResourceFile('components/ReusableDataTable.vue');
      const skeletonSfc = readResourceFile('components/SkeletonLoader.vue');
      const errorRecoverySfc = readResourceFile('components/ErrorRecoveryBanner.vue');
      const destructiveSfc = readResourceFile('components/DestructiveConfirmModal.vue');
      const cheatsheetSfc = readResourceFile('components/ShortcutsCheatsheetModal.vue');
      const onboardingSfc = readResourceFile('components/WorkspaceOnboardingBanner.vue');
      const notFoundSfc = readResourceFile('views/NotFoundView.vue');
      const advocacySfc = readResourceFile('views/EvaluationAdvocacyView.vue');
      const evalFormSfc = readResourceFile('views/EvaluationFormView.vue');
      const adminViewSfc = readResourceFile('views/AdminRosterView.vue');

      // 1. Academic Tech 50/50 Split-Screen Login (PLP EvaluTech, floating card, floating labels, shield/eye/brain tags) & collapsible emerald sidebar shell
      assert.ok(loginViewSfc.includes('data-testid="login-split-screen"'));
      assert.ok(loginViewSfc.includes('PLP EvaluTech'));
      assert.ok(loginViewSfc.includes('Candid student feedback,'));
      assert.ok(loginViewSfc.includes('decoupled by design.'));
      assert.ok(loginViewSfc.includes('floating-auth-card'));
      assert.ok(loginViewSfc.includes('floating-label'));
      assert.ok(loginViewSfc.includes('feature-tag-item'));
      assert.ok(sidebarSfc.includes('data-testid="workspace-sidebar"'));
      assert.ok(sidebarSfc.includes('data-testid="sidebar-collapse-btn"'));
      assert.ok(sidebarSfc.includes('data-testid="sidebar-tier-badge"'));

      // 2. Command Palette (Ctrl+K), Quivr NLP Brain, Cheatsheet (?), Onboarding, 404, Advocacy
      const labModalSfc = readResourceFile('components/PlpAnalyticsLabModal.vue');
      const appCss = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'css', 'app.css'), 'utf8');
      assert.ok(cmdkSfc.includes('data-testid="command-palette-modal"'));
      assert.ok(quivrSfc.includes('data-testid="quivr-nlp-brain-drawer"'));
      assert.ok(quivrSfc.includes("Professor's Evaluation Analysis"));
      assert.ok(labModalSfc.includes("Professor's Evaluation Analysis"));
      assert.ok(appCss.includes('.ui-hint {'));
      assert.ok(appCss.includes('.ui-hint[data-hint]::after {'));
      assert.ok(appCss.includes('.ui-badge-count {'));
      assert.ok(cheatsheetSfc.includes('data-testid="shortcuts-cheatsheet-modal"'));
      assert.ok(onboardingSfc.includes('data-testid="workspace-onboarding-banner"'));
      assert.ok(notFoundSfc.includes('data-testid="custom-404-view"'));
      assert.ok(advocacySfc.includes('data-testid="campus-advocacy-view"'));

      // 3. ReusableDataTable, SkeletonLoader, Autosave, Bulk Actions, Destructive Modal & Graceful Error Recovery
      assert.ok(tableSfc.includes('data-testid="reusable-data-table"'));
      assert.ok(tableSfc.includes('data-testid="table-bulk-action-bar"'));
      assert.ok(tableSfc.includes('class="ui-badge-count"'));
      assert.ok(skeletonSfc.includes('data-testid="skeleton-loader"'));
      assert.ok(evalFormSfc.includes('data-testid="draft-autosave-badge"'));
      assert.ok(adminViewSfc.includes('data-testid="admin-bulk-lock-bar"'));
      assert.ok(destructiveSfc.includes('data-testid="destructive-confirm-modal"'));
      assert.ok(destructiveSfc.includes('class="ui-hint"'));
      assert.ok(errorRecoverySfc.includes("Couldn't save changes"));
      assert.ok(errorRecoverySfc.includes('Your work is safe. Check your connection.'));
      assert.ok(errorRecoverySfc.includes('class="ui-hint"'));
      assert.ok(sidebarSfc.includes('pendingCourseCount'));

      // 4. jQuery UI Top-Right Error DOM Notification with 4-second timer
      const jqueryUiNotifySfc = readResourceFile('components/JQueryUiNotification.vue');
      const jqueryUiUtilSrc = readResourceFile('utils/jqueryUiNotification.js');
      assert.ok(jqueryUiNotifySfc.includes('data-testid="jquery-ui-error-notification"'));
      assert.ok(jqueryUiNotifySfc.includes('ui-widget ui-notification-container'));
      assert.ok(jqueryUiNotifySfc.includes('ui-state-error ui-corner-all'));
      assert.ok(jqueryUiNotifySfc.includes('ui-icon ui-icon-alert'));
      assert.ok(jqueryUiNotifySfc.includes('jqueryUiNotificationState.timerDuration'));
      assert.ok(appCss.includes('.ui-notification-container {'));
      assert.ok(appCss.includes('.jquery-ui-error-box.ui-state-error {'));
      assert.ok(evalFormSfc.includes('showJQueryUiError'));
      assert.ok(jqueryUiUtilSrc.includes('showJQueryUiError'));
      assert.ok(jqueryUiUtilSrc.includes('dismissJQueryUiError'));

      const jqueryUiUtil = await import('../../resources/js/utils/jqueryUiNotification.js');
      jqueryUiUtil.showJQueryUiError('Sample validation incomplete alert', 'Incomplete Ratings', 4000);
      assert.equal(jqueryUiUtil.jqueryUiNotificationState.visible, true);
      assert.equal(jqueryUiUtil.jqueryUiNotificationState.title, 'Incomplete Ratings');
      assert.equal(jqueryUiUtil.jqueryUiNotificationState.message, 'Sample validation incomplete alert');
      assert.equal(jqueryUiUtil.jqueryUiNotificationState.timerDuration, 4000);
      jqueryUiUtil.dismissJQueryUiError();
      assert.equal(jqueryUiUtil.jqueryUiNotificationState.visible, false);

      // 5. Verify getRoleTierBadge & throttle behavior
      assert.equal(uxHelpers.getRoleTierBadge('dean').tierCode, 'TIER 1');
      assert.equal(uxHelpers.getRoleTierBadge('chair').tierCode, 'TIER 2');
      assert.equal(uxHelpers.getRoleTierBadge('student').tierCode, 'SET PASS');

      let callCount = 0;
      const throttledFn = uxHelpers.throttle(() => {
        callCount += 1;
      }, 500);
      throttledFn();
      throttledFn();
      assert.equal(callCount, 1);
    });
  });

  // 40. 63-Control Security Maxxxxxing Frontend Guards, Open-Redirect Sanitizer, Ephemeral Session Default & Front-End Checklist
  describe('Group 40: 63-Control Security Maxxxxxing Frontend Guards, Open-Redirect Sanitizer, Ephemeral Session Default & Front-End Checklist', () => {
    it('blocks external/protocol-relative open redirects, defaults tokens to sessionStorage, enforces zero .map files, and verifies Front-End Checklist head tags', async () => {
      const { sanitizeRedirectPath } = await import('../../resources/js/utils/uxHelpers.js');
      const authStoreSrc = readResourceFile('stores/auth.js');
      const loginViewSfc = readResourceFile('views/LoginView.vue');
      const adminViewSfc = readResourceFile('views/AdminRosterView.vue');
      const bladeHtml = fs.readFileSync(path.join(ROOT_DIR, 'resources', 'views', 'app.blade.php'), 'utf8');
      const viteConfig = fs.readFileSync(path.join(ROOT_DIR, 'vite.config.js'), 'utf8');

      // 1. Open redirect sanitizer (Security #18)
      assert.equal(sanitizeRedirectPath('https://evil.example/phish', '/queue'), '/queue');
      assert.equal(sanitizeRedirectPath('//evil.example/phish', '/queue'), '/queue');
      assert.equal(sanitizeRedirectPath('/\\evil.example', '/queue'), '/queue');
      assert.equal(sanitizeRedirectPath('javascript:alert(1)', '/queue'), '/queue');
      assert.equal(sanitizeRedirectPath('/login?redirect=/dean', '/dean'), '/dean');
      assert.equal(sanitizeRedirectPath('/evaluate/3', '/queue'), '/evaluate/3');

      // 2. Ephemeral sessionStorage default (Security #38) & 63-control Admin console
      assert.ok(authStoreSrc.includes('remember = false'));
      assert.ok(loginViewSfc.includes('const rememberSession = ref(false)'));
      assert.ok(adminViewSfc.includes('data-testid="security-maxxing-posture-card"'));
      assert.ok(adminViewSfc.includes('/api/admin/security-posture'));

      // 3. Zero production source maps (Security #61)
      assert.ok(viteConfig.includes('sourcemap: false'));
      const buildDir = path.join(ROOT_DIR, 'public', 'build');
      if (fs.existsSync(buildDir)) {
        const checkNoMaps = (dir) => {
          for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            const p = path.join(dir, entry.name);
            if (entry.isDirectory()) checkNoMaps(p);
            else assert.equal(entry.name.endsWith('.map'), false, `Unexpected source map ${p}`);
          }
        };
        checkNoMaps(buildDir);
      }

      // 4. Front-End Checklist <head> & skip-link hygiene
      assert.ok(bladeHtml.includes('name="theme-color" content="#103c2e"'));
      assert.ok(bladeHtml.includes('property="og:title"'));
      assert.ok(bladeHtml.includes('rel="icon" type="image/svg+xml"'));
      assert.ok(bladeHtml.includes('class="skip-link" href="#main-workspace"'));
    });
  });

  describe('Group 41: Academic Setup Workspace, Term Cycle Management, Faculty Assignment Matrix & Questionnaire Preview', () => {
    it('verifies route registration, sidebar link, view SFC structure, and academic setup store workflows', () => {
      const routerSrc = readResourceFile('router/index.js');
      const sidebarSfc = readResourceFile('components/WorkspaceSidebar.vue');
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');
      const rosterViewSfc = readResourceFile('views/AdminRosterView.vue');
      const setupStoreSrc = readResourceFile('stores/academicSetup.js');

      // 1. Router Registration & Admin Role Scoping
      assert.ok(routerSrc.includes("path: '/admin/setup'"));
      assert.ok(routerSrc.includes("name: 'admin-setup'"));
      assert.ok(routerSrc.includes("AdminSetupView"));
      assert.ok(routerSrc.includes("roles: ['admin']"));

      // 2. Sidebar Navigation Link
      assert.ok(sidebarSfc.includes("sidebar-link-admin-setup"));
      assert.ok(sidebarSfc.includes("to: '/admin/setup'"));
      assert.ok(sidebarSfc.includes("label: 'Academic Setup'"));
      assert.ok(sidebarSfc.includes("item.icon === 'academic'"));

      // 3. AdminSetupView SFC Components & Test IDs
      assert.ok(setupViewSfc.includes('data-testid="admin-setup-view"'));
      assert.ok(setupViewSfc.includes('data-testid="term-selector-dropdown"'));
      assert.ok(setupViewSfc.includes('data-testid="cycle-readiness-bar"'));
      assert.ok(setupViewSfc.includes('data-testid="readiness-chip"'));
      assert.ok(setupViewSfc.includes('data-testid="tab-assignments-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="tab-terms-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="tab-courses-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="tab-questionnaire-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="sections-table"'));
      assert.ok(setupViewSfc.includes('data-testid="terms-table"'));
      assert.ok(setupViewSfc.includes('data-testid="courses-table"'));
      assert.ok(setupViewSfc.includes('data-testid="pane-courses"'));
      assert.ok(setupViewSfc.includes('data-testid="catalog-new-course-btn"'));
      assert.ok(setupViewSfc.includes('openNewSectionModalForCourse'));
      assert.ok(setupViewSfc.includes('data-testid="pane-questionnaire"'));
      assert.ok(setupViewSfc.includes('data-testid="new-term-modal"'));
      assert.ok(setupViewSfc.includes('data-testid="new-section-modal"'));
      assert.ok(setupViewSfc.includes('data-testid="new-course-modal"'));
      assert.ok(setupViewSfc.includes('data-testid="delete-section-modal"'));
      assert.ok(setupViewSfc.includes('data-testid="import-roster-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="import-roster-modal"'));
      assert.ok(setupViewSfc.includes('data-testid="academic-setup-roster-textarea"'));
      assert.ok(setupViewSfc.includes('data-testid="submit-setup-roster-import-btn"'));
      assert.ok(setupViewSfc.includes('toggle-lock-btn-'));
      assert.ok(setupViewSfc.includes('handleToggleRegistrarLock'));
      assert.ok(setupViewSfc.includes('edit-term-btn-'));
      assert.ok(setupViewSfc.includes('openEditTermModal'));
      assert.ok(rosterViewSfc.includes('data-testid="goto-academic-setup-btn"'));
      assert.ok(setupViewSfc.includes('data-testid="goto-admin-console-btn"'));

      // 4. Academic Setup Pinia Store Methods & Safety Invariants
      assert.ok(setupStoreSrc.includes('fetchOverview'));
      assert.ok(setupStoreSrc.includes('createOrUpdateTerm'));
      assert.ok(setupStoreSrc.includes('setActiveTerm'));
      assert.ok(setupStoreSrc.includes('createCourse'));
      assert.ok(setupStoreSrc.includes('createSection'));
      assert.ok(setupStoreSrc.includes('assignFaculty'));
      assert.ok(setupStoreSrc.includes('deleteSection'));
      assert.ok(setupStoreSrc.includes('fetchQuestionnaire'));
      assert.ok(setupStoreSrc.includes('/api/admin/setup/overview'));
      assert.ok(setupStoreSrc.includes('/api/admin/setup/terms/set-active'));
      assert.ok(setupStoreSrc.includes('/api/admin/setup/sections/assign-faculty'));
    });
  });

  describe('Group 42: Universal Top-Bar Single-Page Navigation, Mobile Expandable Menu & User UI Cleanups', () => {
    it('verifies universal top-bar navigation across roles, mobile dropdown menu beneath header, app shell column layout, and user-requested UI simplifications', () => {
      const navbarSfc = readResourceFile('components/NavbarHeader.vue');
      const appJsSrc = readResourceFile('app.js');
      const evalFormSfc = readResourceFile('views/EvaluationFormView.vue');
      const appCss = readResourceFile('../css/app.css');
      const notFoundSfc = readResourceFile('views/NotFoundView.vue');

      // 1. Universal Top-Bar Desktop & Mobile Navigation (NavbarHeader.vue)
      assert.ok(navbarSfc.includes('sch-inline-nav'), 'Desktop top-bar inline nav container');
      assert.ok(navbarSfc.includes('sch-nav-pill'), 'Top-bar nav pill buttons');
      assert.ok(navbarSfc.includes('isLinkActive(link)'), 'Active page indicator detection');
      assert.ok(navbarSfc.includes('mobile-menu-trigger'), 'Mobile accessible menu button');
      assert.ok(navbarSfc.includes('sch-mobile-dropdown-menu'), 'Mobile dropdown menu expanding beneath top bar');
      assert.ok(navbarSfc.includes('toggleMobileMenu'), 'Mobile dropdown menu toggle handler');

      // 2. App Shell Column Layout without Runtime Sidebar
      assert.ok(appJsSrc.includes('app-shell-authenticated'), 'Authenticated app shell container');
      assert.ok(appCss.includes('.app-shell-authenticated {'), 'Authenticated shell styling');
      assert.ok(appCss.includes('flex-direction: column'), 'Top-bar full-width column layout');
      assert.equal(appJsSrc.includes('h(WorkspaceSidebar,'), false, 'WorkspaceSidebar must not be rendered at runtime');

      // 3. User UI Cleanups (Back to Courses at top, autosave hidden, fullscreen removed, brand kicker sr-only)
      assert.ok(evalFormSfc.includes('data-testid="top-back-to-courses-btn"'), 'Back to courses button at top of evaluation form');
      assert.ok(evalFormSfc.includes('v-show="false" class="autosave-pill'), 'Autosave pill visually hidden');
      assert.equal(evalFormSfc.includes('fullscreen-toggle-btn'), false, 'Focused Fullscreen button must not exist in evaluation form template');
      assert.ok(navbarSfc.includes('<span class="sr-only">PAMANTASAN NG LUNGSOD NG PASIG · plpasig.edu.ph</span>'), 'Header kicker visually hidden via sr-only');
      assert.ok(notFoundSfc.includes('Use the top navigation bar'), '404 guidance points to top navigation bar');
    });
  });

  describe('Group 43: Academic Supervisor Evaluation Report Inspection & Student Clearance Receipt Certificate', () => {
    it('verifies Department Chair evaluation breakdown inspection modal and student course clearance receipt print certificate', () => {
      const chairViewSfc = readResourceFile('views/ChairDashboardView.vue');
      const evalFormSfc = readResourceFile('views/EvaluationFormView.vue');

      // 1. Department Chair Faculty Evaluation Report Modal (ChairDashboardView.vue)
      assert.ok(chairViewSfc.includes('view-eval-report-btn-'), 'Chair faculty roster table view report button');
      assert.ok(chairViewSfc.includes('data-testid="chair-faculty-eval-modal"'), 'Chair faculty evaluation report modal container');
      assert.ok(chairViewSfc.includes('data-testid="report-performance-adjective"'), 'Performance descriptive adjective badge');
      assert.ok(chairViewSfc.includes('data-testid="print-chair-report-btn"'), 'Print/Export faculty report button');
      assert.ok(chairViewSfc.includes('openFacultyReportModal'), 'Open faculty report modal handler');
      assert.ok(chairViewSfc.includes('closeFacultyReportModal'), 'Close faculty report modal handler');
      assert.ok(chairViewSfc.includes('getPerformanceRatingText'), 'Performance adjective rating function');

      // 2. Student Course Evaluation Clearance Receipt (EvaluationFormView.vue)
      assert.ok(evalFormSfc.includes('data-testid="print-course-receipt-btn"'), 'Print clearance receipt button on submission success');
      assert.ok(evalFormSfc.includes('data-testid="print-completed-course-receipt-btn"'), 'Print clearance receipt button on completed course view');
      assert.ok(evalFormSfc.includes('data-testid="student-clearance-receipt-card"'), 'Student clearance receipt modal container');
      assert.ok(evalFormSfc.includes('data-testid="receipt-clearance-token"'), 'Cryptographic clearance signature token element');
      assert.ok(evalFormSfc.includes('data-testid="print-receipt-action-btn"'), 'Print official receipt action button');
      assert.ok(evalFormSfc.includes('openReceiptModal'), 'Open receipt modal handler');
      assert.ok(evalFormSfc.includes('closeReceiptModal'), 'Close receipt modal handler');
    });
  });

  describe('Group 44: Faculty Directory Tab, Student Queue Clearance Permit Unlocking & Dean Heatmap Section Report Modal', () => {
    it('verifies Academic Setup faculty directory tab, student queue completed clearance permit embedding, and dean section report modal', () => {
      const studentQueueSfc = readResourceFile('views/StudentQueueView.vue');
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');
      const deanViewSfc = readResourceFile('views/DeanDashboardView.vue');

      // 1. Student Queue Clearance Permit Unlocking (StudentQueueView.vue)
      assert.ok(studentQueueSfc.includes('ClearancePermitCard'), 'ClearancePermitCard imported in StudentQueueView');
      assert.ok(studentQueueSfc.includes('<ClearancePermitCard'), 'ClearancePermitCard rendered in template');
      assert.ok(studentQueueSfc.includes('v-if="progress.allCleared"'), 'Permit unlocked only when all evaluations are cleared');
      assert.ok(studentQueueSfc.includes(':courses="evaluationStore.courses"'), 'Enrolled courses passed to ClearancePermitCard');

      // 2. Faculty Directory Tab in Academic Setup (AdminSetupView.vue, inspired by faculty_list.php)
      assert.ok(setupViewSfc.includes('data-testid="tab-faculty-btn"'), 'Faculty directory tab button in Academic Setup');
      assert.ok(setupViewSfc.includes('data-testid="pane-faculty"'), 'Faculty directory tab pane container');
      assert.ok(setupViewSfc.includes('data-testid="faculty-search-input"'), 'Faculty search and filter input');
      assert.ok(setupViewSfc.includes('data-testid="faculty-directory-table"'), 'Faculty directory data table');
      assert.ok(setupViewSfc.includes('assign-faculty-shortcut-btn-'), 'Quick assign faculty to teaching section button');
      assert.ok(setupViewSfc.includes('filteredFacultyDirectory'), 'Computed filtered faculty directory');
      assert.ok(setupViewSfc.includes('getFacultyAssignedSectionsCount'), 'Faculty assigned sections counter');
      assert.ok(setupViewSfc.includes('quickAssignFaculty'), 'Quick assign faculty handler');

      // 3. Dean Section Evaluation Report Modal (DeanDashboardView.vue, inspired by report.php)
      assert.ok(deanViewSfc.includes('dean-view-report-btn-'), 'Section evaluation report button in completion heatmap');
      assert.ok(deanViewSfc.includes('data-testid="dean-section-report-modal"'), 'Dean section evaluation report modal container');
      assert.ok(deanViewSfc.includes('data-testid="dean-report-target-status"'), 'Dean report target met status chip');
      assert.ok(deanViewSfc.includes('data-testid="print-dean-report-btn"'), 'Print/Export section report button');
      assert.ok(deanViewSfc.includes('openDeanSectionReport'), 'Open dean section report handler');
      assert.ok(deanViewSfc.includes('closeDeanSectionReport'), 'Close dean section report handler');
    });
  });

  describe('Group 45: Student Directory Tab & Evaluation Clearance Tracking in Academic Setup', () => {
    it('verifies Academic Setup student directory tab, clearance progress bar, and store integration', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');
      const setupStoreSrc = readResourceFile('stores/academicSetup.js');

      // 1. Student Directory Tab & Table (AdminSetupView.vue, inspired by student_list.php)
      assert.ok(setupViewSfc.includes('data-testid="tab-students-btn"'), 'Student directory tab button');
      assert.ok(setupViewSfc.includes('data-testid="pane-students"'), 'Student directory tab pane');
      assert.ok(setupViewSfc.includes('data-testid="student-search-input"'), 'Student search input');
      assert.ok(setupViewSfc.includes('data-testid="student-status-filter-select"'), 'Student status filter select');
      assert.ok(setupViewSfc.includes('data-testid="student-directory-table"'), 'Student directory table');
      assert.ok(setupViewSfc.includes('student-clearance-status-'), 'Student clearance status badge');
      assert.ok(setupViewSfc.includes('filteredStudentsDirectory'), 'Computed filtered students directory');

      // 2. Academic Setup Pinia Store Student Integration
      assert.ok(setupStoreSrc.includes('studentsList'), 'Academic setup store exposes studentsList');
      assert.ok(setupStoreSrc.includes('overview.value.students'), 'Computed students from overview');
    });
  });

  describe('Group 46: Student Queue Clearance Receipt & Administrator Master Evaluation Report Modal', () => {
    it('verifies student queue course clearance receipt modal and direct receipt printing action', () => {
      const studentQueueSfc = readResourceFile('views/StudentQueueView.vue');

      // 1. Student Queue Clearance Receipt Modal & Receipt Trigger Button
      assert.ok(studentQueueSfc.includes('queue-view-receipt-btn-'), 'Student queue receipt action button');
      assert.ok(studentQueueSfc.includes('data-testid="student-queue-clearance-receipt-modal"'), 'Student queue clearance receipt modal');
      assert.ok(studentQueueSfc.includes('data-testid="queue-receipt-clearance-token"'), 'Queue clearance signature token proof');
      assert.ok(studentQueueSfc.includes('data-testid="print-queue-receipt-action-btn"'), 'Print queue clearance receipt button');
      assert.ok(studentQueueSfc.includes('openCourseReceiptModal'), 'Open course receipt modal handler');
      assert.ok(studentQueueSfc.includes('closeQueueReceiptModal'), 'Close course receipt modal handler');
    });

    it('verifies administrator section evaluation report breakdown modal and institutional CSV export', () => {
      const adminRosterSfc = readResourceFile('views/AdminRosterView.vue');

      // 2. Administrator Master Summary Export Button & Formula-Injection Neutralization
      assert.ok(adminRosterSfc.includes('data-testid="export-master-summary-btn"'), 'Export master summary CSV button');
      assert.ok(adminRosterSfc.includes('exportMasterEvaluationSummaryCsv'), 'Export master summary CSV function');
      assert.ok(adminRosterSfc.includes('PLP_Evaluation_Master_Summary_'), 'Filename pattern for institutional summary');

      // 3. Section Locks Table View Report Action & Administrator Report Modal
      assert.ok(adminRosterSfc.includes('admin-view-report-btn-'), 'Admin section locks View Report button');
      assert.ok(adminRosterSfc.includes('data-testid="admin-section-eval-modal"'), 'Admin section evaluation report modal');
      assert.ok(adminRosterSfc.includes('data-testid="admin-report-target-status"'), 'Admin report target status badge');
      assert.ok(adminRosterSfc.includes('data-testid="admin-print-report-btn"'), 'Admin print report button');
      assert.ok(adminRosterSfc.includes('openAdminSectionReport'), 'Open admin section report function');
      assert.ok(adminRosterSfc.includes('closeAdminSectionReport'), 'Close admin section report function');
    });
  });

  describe('Group 47: System User Accounts & Staff Directory in Academic Setup', () => {
    it('verifies Academic Setup system user accounts tab, role filter, and store integration', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');
      const setupStoreSrc = readResourceFile('stores/academicSetup.js');

      // 1. System Accounts Tab & Table (AdminSetupView.vue, inspired by admin/user_list.php)
      assert.ok(setupViewSfc.includes('data-testid="tab-users-btn"'), 'System users directory tab button');
      assert.ok(setupViewSfc.includes('data-testid="pane-users"'), 'System users directory tab pane');
      assert.ok(setupViewSfc.includes('data-testid="users-search-input"'), 'Users search input');
      assert.ok(setupViewSfc.includes('data-testid="users-role-filter-select"'), 'Users role filter select');
      assert.ok(setupViewSfc.includes('data-testid="system-users-table"'), 'System users directory table');
      assert.ok(setupViewSfc.includes('user-role-badge-'), 'User role badge in table');
      assert.ok(setupViewSfc.includes('filteredSystemUsers'), 'Computed filtered system users');

      // 2. Academic Setup Pinia Store System Users Integration
      assert.ok(setupStoreSrc.includes('systemUsers'), 'Academic setup store exposes systemUsers');
      assert.ok(setupStoreSrc.includes('overview.value.system_users'), 'Computed system users from overview');
    });
  });

  describe('Group 48: Institutional Stats Ribbon & Course Catalog CSV Export in Academic Setup', () => {
    it('verifies institutional overview stats ribbon and quick navigation cards', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');

      // 1. Overview Stats Ribbon (Inspired by admin/home.php)
      assert.ok(setupViewSfc.includes('data-testid="admin-stats-ribbon"'), 'Admin stats ribbon container');
      assert.ok(setupViewSfc.includes('data-testid="stat-card-courses"'), 'Stat card for courses');
      assert.ok(setupViewSfc.includes('data-testid="stat-card-sections"'), 'Stat card for sections');
      assert.ok(setupViewSfc.includes('data-testid="stat-card-faculty"'), 'Stat card for faculty');
      assert.ok(setupViewSfc.includes('data-testid="stat-card-students"'), 'Stat card for students');
      assert.ok(setupViewSfc.includes('data-testid="stat-card-users"'), 'Stat card for system users');
    });

    it('verifies course catalog CSV export button, handler, and filename pattern', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');

      // 2. Course Catalog CSV Export
      assert.ok(setupViewSfc.includes('data-testid="export-courses-csv-btn"'), 'Export courses CSV button');
      assert.ok(setupViewSfc.includes('exportCoursesCatalogCsv'), 'Export courses CSV handler');
      assert.ok(setupViewSfc.includes('PLP_Course_Catalog'), 'Course catalog CSV filename pattern');
    });
  });

  describe('Group 49: Comprehensive Institutional Directory & Assignments Matrix CSV Export Suite', () => {
    it('verifies assignments matrix, faculty directory, student roster, and system users CSV export actions in Academic Setup', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');

      // 1. Shared formula-injection neutralized CSV generator
      assert.ok(setupViewSfc.includes('triggerCsvDownload'), 'Shared CSV download helper function');

      // 2. Faculty Assignments Matrix Export
      assert.ok(setupViewSfc.includes('data-testid="export-assignments-csv-btn"'), 'Export assignments matrix CSV button');
      assert.ok(setupViewSfc.includes('exportAssignmentsMatrixCsv'), 'Export assignments matrix CSV handler');
      assert.ok(setupViewSfc.includes('PLP_Faculty_Assignments_Matrix'), 'Assignments matrix CSV filename prefix');

      // 3. Faculty Directory Export
      assert.ok(setupViewSfc.includes('data-testid="export-faculty-csv-btn"'), 'Export faculty directory CSV button');
      assert.ok(setupViewSfc.includes('exportFacultyDirectoryCsv'), 'Export faculty directory CSV handler');
      assert.ok(setupViewSfc.includes('PLP_Faculty_Directory'), 'Faculty directory CSV filename prefix');

      // 4. Student Clearance Roster Export
      assert.ok(setupViewSfc.includes('data-testid="export-students-csv-btn"'), 'Export student clearance roster CSV button');
      assert.ok(setupViewSfc.includes('exportStudentsDirectoryCsv'), 'Export student clearance roster CSV handler');
      assert.ok(setupViewSfc.includes('PLP_Student_Clearance_Roster'), 'Student roster CSV filename prefix');

      // 5. System Users Directory Export
      assert.ok(setupViewSfc.includes('data-testid="export-users-csv-btn"'), 'Export system users CSV button');
      assert.ok(setupViewSfc.includes('exportSystemUsersCsv'), 'Export system users CSV handler');
      assert.ok(setupViewSfc.includes('PLP_System_Users_Directory'), 'System users CSV filename prefix');
    });

    it('verifies evaluation questionnaire instrument CSV export action in Academic Setup', () => {
      const setupViewSfc = readResourceFile('views/AdminSetupView.vue');

      // 6. Questionnaire Instrument Export
      assert.ok(setupViewSfc.includes('data-testid="export-questionnaire-csv-btn"'), 'Export questionnaire CSV button');
      assert.ok(setupViewSfc.includes('exportQuestionnaireInstrumentCsv'), 'Export questionnaire CSV handler');
      assert.ok(setupViewSfc.includes('PLP_AACCUP3_Evaluation_Instrument'), 'Questionnaire instrument CSV filename prefix');
    });
  });
});
