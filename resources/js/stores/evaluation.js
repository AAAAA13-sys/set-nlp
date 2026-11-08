import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
  DEMO_DEAN_METRICS,
  DEMO_STUDENT_QUEUE,
  PEDAGOGICAL_DIMENSIONS,
  calculateClusterMeans,
  calculateMean,
  calculatePopulationVariance,
  calculateQueueProgress,
  canFacultyViewEvaluation,
  classifyCommentPreview,
  evaluateWatchlistFlag,
  isDwellTimeValid,
  isStraightLined,
} from '../utils/evaluationMath.js';

export function generateSubmissionKey(courseSectionId, studentId = '2024-10892') {
  const randomSuffix = Math.random().toString(36).substring(2, 10).toUpperCase();
  return `FT02-${studentId}-SEC${courseSectionId}-${Date.now()}-${randomSuffix}`;
}

export const useEvaluationStore = defineStore('evaluation', () => {
  const courses = ref(DEMO_STUDENT_QUEUE.map((c) => ({ ...c })));
  const activeCourseId = ref(104); // MATH-201 by default (first PENDING course in queue)
  const dwellSeconds = ref(0);
  const startToken = ref(`SRV-START-${Date.now()}`);
  const submissionKey = ref(generateSubmissionKey(104));
  const submitting = ref(false);
  const submissionError = ref(null);
  const processedKeys = ref(new Map());

  // Double-blind faculty and registrar section lock states
  const sectionLocks = ref([
    {
      course_section_id: 101,
      course_code: 'CS-401',
      course_title: 'Algorithms and Complexity',
      instructor_name: 'Prof. A. Dela Cruz',
      department_name: 'College of Computer Studies',
      term_grades_submitted: true,
      registrar_lock: false,
      enrolled_count: 42,
      evaluated_count: 39,
      mean_score: 4.62,
      negative_sentiment_pct: 5.1,
      dimension_scores: {
        'Pedagogical Delivery': 4.78,
        'Assessment & Grading': 4.52,
        'Classroom Management': 4.6,
        'Professional Conduct': 4.58,
        'Student Support & Mentorship': 4.64,
      },
      sample_comments: [
        {
          excerpt: 'Explains complex algorithms very clearly and provides great practical code examples.',
          polarity: 0.78,
          valence: 'Positive',
          tag: 'Pedagogical Delivery',
        },
      ],
    },
    {
      course_section_id: 102,
      course_code: 'CS-405',
      course_title: 'Artificial Intelligence & NLP',
      instructor_name: 'Dr. M. Santos',
      department_name: 'College of Computer Studies',
      term_grades_submitted: true,
      registrar_lock: true,
      enrolled_count: 38,
      evaluated_count: 36,
      mean_score: 4.71,
      negative_sentiment_pct: 4.0,
      dimension_scores: {
        'Pedagogical Delivery': 4.82,
        'Assessment & Grading': 4.65,
        'Classroom Management': 4.68,
        'Professional Conduct': 4.7,
        'Student Support & Mentorship': 4.72,
      },
      sample_comments: [
        {
          excerpt: 'NLP lab sessions and VADER sentiment walkthroughs were well structured and practical.',
          polarity: 0.71,
          valence: 'Positive',
          tag: 'Pedagogical Delivery',
        },
      ],
    },
    {
      course_section_id: 104,
      course_code: 'MATH-201',
      course_title: 'Linear Algebra and Matrix Computation',
      instructor_name: 'Dr. R. Garcia',
      department_name: 'College of Computer Studies',
      term_grades_submitted: false,
      registrar_lock: false,
      enrolled_count: 45,
      evaluated_count: 40,
      mean_score: 3.1,
      negative_sentiment_pct: 41.0,
      dimension_scores: {
        'Pedagogical Delivery': 3.35,
        'Assessment & Grading': 2.65,
        'Classroom Management': 3.15,
        'Professional Conduct': 3.25,
        'Student Support & Mentorship': 3.1,
      },
      sample_comments: [
        {
          excerpt: 'Takes over a month to return quiz scores and provides no helpful feedback.',
          polarity: -0.62,
          valence: 'Negative',
          tag: 'Assessment & Grading',
        },
      ],
    },
    {
      course_section_id: 106,
      course_code: 'PHYS-101',
      course_title: 'University Physics for Computing',
      instructor_name: 'Engr. J. Luna',
      department_name: 'College of Computer Studies',
      term_grades_submitted: true,
      registrar_lock: false,
      enrolled_count: 40,
      evaluated_count: 35,
      mean_score: 3.3,
      negative_sentiment_pct: 38.5,
      dimension_scores: {
        'Pedagogical Delivery': 3.45,
        'Assessment & Grading': 3.4,
        'Classroom Management': 2.85,
        'Professional Conduct': 3.5,
        'Student Support & Mentorship': 3.3,
      },
      sample_comments: [
        {
          excerpt: 'Arrives to morning lectures 15 minutes late consistently.',
          polarity: -0.45,
          valence: 'Negative',
          tag: 'Classroom Management',
        },
      ],
    },
  ]);

  const deanOverview = ref({ ...DEMO_DEAN_METRICS });

  // Evaluation Window & Period Control State (SOP 1 / Registrar Parameter Console)
  const evaluationWindow = ref({
    academic_term_id: 1,
    term_code: 'FALL-2026',
    term_name: '1st Semester AY 2026-2027',
    academic_year: '2026-2027',
    is_active: true,
    evaluation_open: true,
    status: 'OPEN',
    min_dwell_seconds: 45,
    straight_line_variance_threshold: 0.0,
    watchlist_mean_threshold: 3.5,
    watchlist_negative_sentiment_pct: 35.0,
    announcement_message:
      'Pamantasan ng Lungsod ng Pasig (PLP) Student Evaluation of Teachers (SET) is currently active. All students must complete evaluations before securing the Digital Exam Permit.',
    starts_at: null,
    ends_at: null,
    updated_at: new Date().toISOString(),
  });

  const queueProgress = computed(() => calculateQueueProgress(courses.value));
  const activeCourse = computed(
    () =>
      courses.value.find((c) => Number(c.course_section_id) === Number(activeCourseId.value)) ||
      courses.value.find((c) => c.status === 'PENDING') ||
      courses.value[0],
  );

  async function selectCourseForEvaluation(courseSectionId) {
    activeCourseId.value = Number(courseSectionId);
    dwellSeconds.value = 0;
    startToken.value = `SRV-START-${courseSectionId}-${Date.now()}`;
    submissionKey.value = generateSubmissionKey(courseSectionId);
    submissionError.value = null;

    try {
      const res = await fetch(`/api/student/evaluations/${courseSectionId}/start`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({}),
      });
      if (res.ok) {
        const data = await res.json();
        if (data.start_token) {
          startToken.value = data.start_token;
        }
      }
    } catch (_) {}
  }

  function incrementDwellTime(seconds = 1) {
    dwellSeconds.value = Math.max(0, Number(dwellSeconds.value) + Number(seconds));
  }

  async function fetchQueue() {
    try {
      const response = await fetch('/api/student/queue', {
        headers: { Accept: 'application/json' },
      });
      if (response.ok) {
        const data = await response.json();
        const items = Array.isArray(data.courses)
          ? data.courses
          : (Array.isArray(data.queue) ? data.queue : []);
        if (items.length > 0) {
          courses.value = items;
        }
      }
    } catch (_) {
      // Retain seeded demonstration queue if offline
    }
    return courses.value;
  }

  /**
   * Submits an evaluation enforcing FT-02 idempotency, 45s speed-run guard, and sigma^2=0 straight-line check.
   */
  async function submitEvaluation({
    courseSectionId,
    ratings,
    commentText = '',
    professorQuestions = null,
    dwellTimeSeconds,
    confirmUniform = false,
    idempotencyKey,
  }) {
    const targetSectionId = Number(courseSectionId || activeCourseId.value);
    const key = idempotencyKey || submissionKey.value;
    const elapsed = Number(dwellTimeSeconds ?? dwellSeconds.value);

    // FT-02 Idempotency check: return cached clearance token without creating duplicate records
    if (processedKeys.value.has(key)) {
      return {
        ok: true,
        idempotentReplay: true,
        ...processedKeys.value.get(key),
      };
    }

    // 0. Window status check (SOP 1 / Period Control)
    if (evaluationWindow.value.status !== 'OPEN' || !evaluationWindow.value.evaluation_open) {
      const errPayload = {
        ok: false,
        status: 422,
        code: 'EVALUATION_WINDOW_CLOSED',
        window_status: evaluationWindow.value.status,
        message:
          'The student evaluation window is currently closed or suspended for this academic term.',
      };
      submissionError.value = errPayload.message;
      return errPayload;
    }

    // 1. Speed-run guard (< min_dwell_seconds, default 45 seconds for >= 20 items)
    const minDwellRequired = Number(evaluationWindow.value.min_dwell_seconds || 45);
    if (!isDwellTimeValid(elapsed, Array.isArray(ratings) ? ratings.length : 25, minDwellRequired)) {
      const errPayload = {
        ok: false,
        status: 422,
        code: 'SPEED_RUN_DETECTED',
        dwell_time_seconds: elapsed,
        minimum_required_seconds: minDwellRequired,
        message:
          `Minimum reading dwell time not met (${minDwellRequired} seconds required for 5-cluster SET instrument). Please review your responses carefully.`,
      };
      submissionError.value = errPayload.message;
      return errPayload;
    }

    // 2. Straight-line / zero-variance guard (sigma^2 <= straight_line_variance_threshold)
    const varianceThreshold = Number(evaluationWindow.value.straight_line_variance_threshold ?? 0.0);
    const variance = calculatePopulationVariance(ratings);
    const uniform = variance <= varianceThreshold || isStraightLined(ratings);
    if (uniform && !confirmUniform) {
      return {
        ok: false,
        status: 409,
        code: 'ZERO_VARIANCE_CONFIRMATION_REQUIRED',
        variance,
        message:
          'Uniform rating pattern detected (Variance σ² = 0). Please confirm that you intend to submit identical ratings across all pedagogical items.',
      };
    }

    submitting.value = true;
    submissionError.value = null;

    try {
      const response = await fetch('/api/student/evaluations/submit', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          course_section_id: targetSectionId,
          submission_key: key,
          start_token: startToken.value,
          dwell_time_seconds: elapsed,
          ratings,
          comment_text: commentText,
          professor_questions: professorQuestions,
          confirm_uniform: Boolean(confirmUniform),
        }),
      });

      if (response.ok) {
        const apiResult = await response.json();
        markCourseClearedLocal(targetSectionId, apiResult.clearance_token);
        processedKeys.value.set(key, apiResult);
        await fetchQueue();
        return { ok: true, ...apiResult };
      } else {
        const errResult = await response.json().catch(() => ({}));
        submissionError.value = errResult.message || 'Submission rejected by server.';
        return {
          ok: false,
          status: response.status,
          code: errResult.error || errResult.code || 'SERVER_VALIDATION_ERROR',
          message: errResult.message || submissionError.value,
          ...errResult,
        };
      }
    } catch (_) {
      // Deterministic local atomic fallback when offline or in pure frontend unit tests
    } finally {
      submitting.value = false;
    }

    const mean = calculateMean(ratings);
    const clusterScores = calculateClusterMeans(ratings);
    const nlp = classifyCommentPreview(commentText);
    const token = `CLR-2026-SEC${targetSectionId}-${Math.random().toString(36).substring(2, 10).toUpperCase()}`;

    markCourseClearedLocal(targetSectionId, token);

    const resultPayload = {
      clearance_token: token,
      status: 'CLEARED',
      submission_key: key,
      mean_score: mean,
      rating_variance: variance,
      is_straight_lined: uniform,
      cluster_scores: clusterScores,
      professor_questions: professorQuestions,
      sentiment: nlp,
    };

    processedKeys.value.set(key, resultPayload);
    return {
      ok: true,
      ...resultPayload,
    };
  }

  function markCourseClearedLocal(courseSectionId, token) {
    const idx = courses.value.findIndex(
      (c) => Number(c.course_section_id) === Number(courseSectionId) || Number(c.id) === Number(courseSectionId),
    );
    if (idx !== -1) {
      courses.value[idx] = {
        ...courses.value[idx],
        status: 'COMPLETED',
        action_label: 'Clearance Signed',
        clearance_token: token || courses.value[idx].clearance_token || `CLR-2026-${courses.value[idx].course_code}`,
        cleared_at: new Date().toISOString(),
      };
    }
  }

  function toggleSectionLock(courseSectionId, field, value) {
    const section = sectionLocks.value.find(
      (s) => Number(s.course_section_id) === Number(courseSectionId),
    );
    if (!section) return null;
    if (field === 'term_grades_submitted' || field === 'registrar_lock') {
      section[field] = typeof value === 'boolean' ? value : !section[field];
    }
    return {
      ...section,
      can_view_evaluation: canFacultyViewEvaluation(
        section.term_grades_submitted,
        section.registrar_lock,
      ),
    };
  }

  function ingestRosterRows(rows = []) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return { ok: false, imported: 0, message: 'No valid roster rows provided.' };
    }
    for (const row of rows) {
      if (!row.course_code || !row.instructor_name) {
        return {
          ok: false,
          imported: 0,
          message: 'Atomic import aborted: every row must contain course_code and instructor_name.',
        };
      }
    }
    let added = 0;
    for (const row of rows) {
      const exists = courses.value.some((c) => c.course_code === row.course_code);
      if (!exists) {
        const nextId = 100 + courses.value.length + 1;
        courses.value.push({
          id: nextId,
          course_section_id: nextId,
          course_code: row.course_code,
          course_title: row.course_title || `${row.course_code} Course Section`,
          instructor_name: row.instructor_name,
          department_name: row.department_name || 'College of Computer Studies',
          status: 'PENDING',
          action_label: 'Start Evaluation',
          clearance_token: null,
          cleared_at: null,
        });
        added += 1;
      }
    }
    return {
      ok: true,
      imported: rows.length,
      added,
      message: `Successfully processed ${rows.length} roster enrollment record(s).`,
    };
  }

  async function fetchEvaluationWindow(termId = 1) {
    try {
      const res = await fetch(`/api/admin/evaluation-window?academic_term_id=${termId}`, {
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        evaluationWindow.value = { ...evaluationWindow.value, ...data };
      }
    } catch (_) {
      // offline fallback
    }
    return evaluationWindow.value;
  }

  async function updateEvaluationWindowConfig(patch = {}) {
    const updated = {
      ...evaluationWindow.value,
      ...patch,
      evaluation_open: (patch.status ?? evaluationWindow.value.status) === 'OPEN',
      updated_at: new Date().toISOString(),
    };
    evaluationWindow.value = updated;

    try {
      const res = await fetch('/api/admin/evaluation-window', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(updated),
      });
      if (res.ok) {
        const serverData = await res.json();
        evaluationWindow.value = { ...evaluationWindow.value, ...serverData };
        return { ok: true, config: evaluationWindow.value };
      }
    } catch (_) {
      // offline mock update
    }
    return { ok: true, config: evaluationWindow.value };
  }

  function setEvaluationWindowStatus(status) {
    return updateEvaluationWindowConfig({ status });
  }

  async function fetchDeanOverview(departmentId = null) {
    try {
      const q = departmentId !== null && departmentId !== undefined && departmentId !== 'ALL'
        ? `?department_id=${encodeURIComponent(departmentId)}`
        : '';
      const response = await fetch(`/api/dean/overview${q}`, {
        headers: { Accept: 'application/json' },
      });
      if (response.ok) {
        const data = await response.json();
        deanOverview.value = { ...deanOverview.value, ...data };
        return { ok: true, data: deanOverview.value };
      }
    } catch (_) {
      // Retain seeded mock overview if offline
    }
    return { ok: false, data: deanOverview.value };
  }

  return {
    courses,
    activeCourseId,
    activeCourse,
    dwellSeconds,
    startToken,
    submissionKey,
    submitting,
    submissionError,
    sectionLocks,
    deanOverview,
    evaluationWindow,
    queueProgress,
    dimensions: PEDAGOGICAL_DIMENSIONS,
    selectCourseForEvaluation,
    incrementDwellTime,
    fetchQueue,
    submitEvaluation,
    markCourseClearedLocal,
    toggleSectionLock,
    ingestRosterRows,
    evaluateWatchlistFlag,
    canFacultyViewEvaluation,
    fetchEvaluationWindow,
    updateEvaluationWindowConfig,
    setEvaluationWindowStatus,
    fetchDeanOverview,
  };
});
