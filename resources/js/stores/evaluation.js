import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { useAuthStore } from './auth.js';
import {
  INITIAL_DEAN_METRICS,
  DEMO_DEAN_METRICS,
  PEDAGOGICAL_DIMENSIONS,
  calculatePopulationVariance,
  calculateQueueProgress,
  canFacultyViewEvaluation,
  evaluateWatchlistFlag,
  isDwellTimeValid,
  isStraightLined,
} from '../utils/evaluationMath.js';

export function generateSubmissionKey(courseSectionId, studentId = 'STU') {
  const randomSuffix = Math.random().toString(36).substring(2, 10).toUpperCase();
  const cleanId = String(studentId || 'STU').replace(/[^A-Za-z0-9-]/g, '');
  return `FT02-${cleanId}-SEC${courseSectionId}-${Date.now()}-${randomSuffix}`;
}

export const useEvaluationStore = defineStore('evaluation', () => {
  const courses = ref([]);
  const examPermitToken = ref(null);
  const queueLoading = ref(false);
  const activeCourseId = ref(null);
  const dwellSeconds = ref(0);
  const startToken = ref('');
  const submissionKey = ref('');
  const submitting = ref(false);
  const submissionError = ref(null);
  const processedKeys = ref(new Map());

  // Faculty and Registrar database-backed section states
  const facultySections = ref([]);
  const facultyReports = ref({});
  const sectionLocks = ref([]);
  const adminSummary = ref({
    total_sections: 0,
    total_students: 0,
    total_faculty: 0,
    total_enrollments: 0,
    total_clearances: 0,
    total_vault_records: 0,
  });

  const deanOverview = ref({ ...INITIAL_DEAN_METRICS });

  // Evaluation Window & Period Control State (SOP 1 / Registrar Parameter Console)
  const evaluationWindow = ref({
    academic_term_id: 1,
    term_code: '1SEM-2026',
    term_name: '1st Semester AY 2026-2027',
    academic_year: '2026-2027',
    is_active: true,
    evaluation_open: true,
    status: 'OPEN',
    min_dwell_seconds: 0,
    straight_line_variance_threshold: 0.0,
    watchlist_mean_threshold: 3.5,
    watchlist_negative_sentiment_pct: 35.0,
    announcement_message: '',
    starts_at: null,
    ends_at: null,
    timezone: 'Asia/Manila',
    last_window_action: 'OPEN',
    participation_summary: null,
    updated_at: null,
  });

  function getApiHeaders(jsonBody = false) {
    try {
      const authStore = useAuthStore();
      return authStore.authHeaders(jsonBody ? { 'Content-Type': 'application/json' } : {});
    } catch (_) {
      return jsonBody
        ? { 'Content-Type': 'application/json', Accept: 'application/json' }
        : { Accept: 'application/json' };
    }
  }

  const queueProgress = computed(() => calculateQueueProgress(courses.value));
  const activeCourse = computed(
    () =>
      courses.value.find((c) => Number(c.course_section_id) === Number(activeCourseId.value)) ||
      courses.value.find((c) => c.status === 'PENDING') ||
      courses.value[0] ||
      null,
  );

  function resetState() {
    courses.value = [];
    examPermitToken.value = null;
    activeCourseId.value = null;
    dwellSeconds.value = 0;
    startToken.value = '';
    submissionKey.value = '';
    submitting.value = false;
    submissionError.value = null;
    processedKeys.value = new Map();
    facultySections.value = [];
    facultyReports.value = {};
    sectionLocks.value = [];
  }

  async function fetchQueue() {
    queueLoading.value = true;
    try {
      const response = await fetch('/api/student/queue', {
        headers: getApiHeaders(),
      });
      if (response.ok) {
        const data = await response.json();
        const items = Array.isArray(data.courses)
          ? data.courses
          : Array.isArray(data.queue)
            ? data.queue
            : [];
        courses.value = items;
        examPermitToken.value = data.exam_permit_token || null;

        if (!activeCourseId.value || !items.some((c) => Number(c.course_section_id) === Number(activeCourseId.value))) {
          const nextPending = items.find((c) => c.status === 'PENDING') || items[0];
          if (nextPending) {
            activeCourseId.value = Number(nextPending.course_section_id);
          }
        }
      }
    } catch (_) {
      // Network failure handled gracefully by empty state UI
    } finally {
      queueLoading.value = false;
    }
    return courses.value;
  }

  async function selectCourseForEvaluation(courseSectionId) {
    if (courses.value.length === 0) {
      await fetchQueue();
    }
    const resolvedId = Number(
      courseSectionId ||
        courses.value.find((c) => c.status === 'PENDING')?.course_section_id ||
        courses.value[0]?.course_section_id ||
        0,
    );
    if (!resolvedId) {
      return;
    }

    let studentId = 'STU';
    try {
      studentId = useAuthStore().user?.institutional_id || 'STU';
    } catch (_) {}

    activeCourseId.value = resolvedId;
    dwellSeconds.value = 0;
    startToken.value = '';
    submissionKey.value = generateSubmissionKey(resolvedId, studentId);
    submissionError.value = null;

    try {
      const res = await fetch(`/api/student/evaluations/${resolvedId}/start`, {
        method: 'POST',
        headers: getApiHeaders(true),
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

    // Validate all Likert items are answered (integers 1..5)
    if (
      !Array.isArray(ratings) ||
      ratings.length < 20 ||
      ratings.some((val) => !Number.isInteger(Number(val)) || Number(val) < 1 || Number(val) > 5)
    ) {
      const errPayload = {
        ok: false,
        status: 422,
        code: 'INCOMPLETE_RATINGS',
        message: 'Please select a rating from 1 to 5 for every item across all five pedagogical clusters before submitting.',
      };
      submissionError.value = errPayload.message;
      return errPayload;
    }

    const numericRatings = ratings.map((r) => Number(r));

    // 1. Mandatory Timing Policy: Non-negative elapsed answering duration validation
    if (!isDwellTimeValid(elapsed, numericRatings.length, 0)) {
      const errPayload = {
        ok: false,
        status: 422,
        code: 'INVALID_ELAPSED_DURATION',
        dwell_time_seconds: elapsed,
        message: 'Elapsed answering duration cannot be negative.',
      };
      submissionError.value = errPayload.message;
      return errPayload;
    }

    // 2. Straight-line / zero-variance guard (sigma^2 <= straight_line_variance_threshold)
    const varianceThreshold = Number(evaluationWindow.value.straight_line_variance_threshold ?? 0.0);
    const variance = calculatePopulationVariance(numericRatings);
    const uniform = variance <= varianceThreshold || isStraightLined(numericRatings);
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
        headers: getApiHeaders(true),
        body: JSON.stringify({
          course_section_id: targetSectionId,
          submission_key: key,
          start_token: startToken.value,
          dwell_time_seconds: elapsed,
          ratings: numericRatings,
          comment_text: commentText,
          professor_questions: professorQuestions,
          confirm_uniform: Boolean(confirmUniform),
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (response.ok) {
        markCourseClearedLocal(targetSectionId, payload.clearance_token);
        processedKeys.value.set(key, payload);
        await fetchQueue();
        return { ok: true, ...payload };
      }

      submissionError.value = payload.message || 'Submission rejected by server.';
      return {
        ok: false,
        status: response.status,
        code: payload.error || payload.code || 'SERVER_VALIDATION_ERROR',
        message: submissionError.value,
        ...payload,
      };
    } catch (_) {
      submissionError.value =
        'Unable to reach the institutional database to record your evaluation. Your submission key is preserved—please retry.';
      return {
        ok: false,
        status: 503,
        code: 'NETWORK_ERROR',
        message: submissionError.value,
      };
    } finally {
      submitting.value = false;
    }
  }

  function markCourseClearedLocal(courseSectionId, token) {
    const idx = courses.value.findIndex(
      (c) =>
        Number(c.course_section_id) === Number(courseSectionId) ||
        Number(c.id) === Number(courseSectionId),
    );
    if (idx !== -1) {
      courses.value[idx] = {
        ...courses.value[idx],
        status: 'COMPLETED',
        action_label: 'Clearance Signed',
        clearance_token: token || courses.value[idx].clearance_token,
        cleared_at: new Date().toISOString(),
      };
    }
  }

  async function fetchFacultySections(termId = 1) {
    try {
      const res = await fetch(`/api/faculty/sections?academic_term_id=${encodeURIComponent(termId)}`, {
        headers: getApiHeaders(),
      });
      if (res.ok) {
        const data = await res.json();
        facultySections.value = Array.isArray(data.sections) ? data.sections : [];
      }
    } catch (_) {}
    return facultySections.value;
  }

  async function fetchFacultySectionReport(courseSectionId) {
    try {
      const res = await fetch(`/api/faculty/sections/${encodeURIComponent(courseSectionId)}/report`, {
        headers: getApiHeaders(),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        facultyReports.value = {
          ...facultyReports.value,
          [Number(courseSectionId)]: data,
        };
        return { ok: true, data };
      }
      return { ok: false, status: res.status, data };
    } catch (_) {
      return { ok: false, status: 503, data: {} };
    }
  }

  async function submitFacultyTermGrades(courseSectionId) {
    try {
      const res = await fetch(`/api/faculty/sections/${encodeURIComponent(courseSectionId)}/submit-grades`, {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({ course_section_id: Number(courseSectionId) }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        await fetchFacultySections();
        if (data.double_blind_unlocked) {
          await fetchFacultySectionReport(courseSectionId);
        }
        return { ok: true, ...data };
      }
      return { ok: false, status: res.status, ...data };
    } catch (_) {
      return { ok: false, status: 503 };
    }
  }

  async function fetchAdminSections(termId = 1) {
    try {
      const res = await fetch(`/api/admin/sections?academic_term_id=${encodeURIComponent(termId)}`, {
        headers: getApiHeaders(),
      });
      if (res.ok) {
        const data = await res.json();
        sectionLocks.value = Array.isArray(data.sections) ? data.sections : [];
        if (data.summary) {
          adminSummary.value = { ...adminSummary.value, ...data.summary };
        }
      }
    } catch (_) {}
    return sectionLocks.value;
  }

  async function toggleSectionLock(courseSectionId, field, value) {
    const idx = sectionLocks.value.findIndex(
      (s) => Number(s.course_section_id) === Number(courseSectionId),
    );
    const currentVal = idx !== -1 ? Boolean(sectionLocks.value[idx][field]) : false;
    const nextValue = typeof value === 'boolean' ? value : !currentVal;

    if (field !== 'term_grades_submitted' && field !== 'registrar_lock') {
      return null;
    }

    // Optimistic UI update (#1) with snapshot rollback if API fails
    const previousSnapshot = idx !== -1 ? { ...sectionLocks.value[idx] } : null;
    if (idx !== -1) {
      const nextGrades =
        field === 'term_grades_submitted' ? nextValue : Boolean(sectionLocks.value[idx].term_grades_submitted);
      const nextLock =
        field === 'registrar_lock' ? nextValue : Boolean(sectionLocks.value[idx].registrar_lock);
      sectionLocks.value[idx] = {
        ...sectionLocks.value[idx],
        [field]: nextValue,
        double_blind_unlocked: canFacultyViewEvaluation(nextGrades, nextLock),
      };
    }

    try {
      const res = await fetch(
        `/api/admin/sections/${encodeURIComponent(courseSectionId)}/registrar-lock`,
        {
          method: 'POST',
          headers: getApiHeaders(true),
          body: JSON.stringify({
            course_section_id: Number(courseSectionId),
            [field]: nextValue,
          }),
        },
      );
      if (res.ok) {
        const updated = await res.json();
        if (idx !== -1) {
          sectionLocks.value[idx] = {
            ...sectionLocks.value[idx],
            term_grades_submitted: Boolean(updated.term_grades_submitted),
            registrar_lock: Boolean(updated.registrar_lock),
            double_blind_unlocked: Boolean(updated.double_blind_unlocked),
          };
        }
        await fetchAdminSections();
        return {
          ...(sectionLocks.value[idx] || updated),
          can_view_evaluation: canFacultyViewEvaluation(
            updated.term_grades_submitted,
            updated.registrar_lock,
          ),
        };
      }
      if (idx !== -1 && previousSnapshot) {
        sectionLocks.value[idx] = previousSnapshot;
      }
    } catch (_) {
      if (idx !== -1 && previousSnapshot) {
        sectionLocks.value[idx] = previousSnapshot;
      }
    }
    return null;
  }

  async function bulkToggleSectionLocks(sectionIds = [], patch = {}) {
    const cleanIds = sectionIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
    if (cleanIds.length === 0) {
      return { ok: false, updated_count: 0, message: 'No course sections selected.' };
    }

    // Optimistic UI update (#1, #10 Bulk Actions) with snapshot rollback
    const snapshot = sectionLocks.value.map((s) => ({ ...s }));
    sectionLocks.value = sectionLocks.value.map((sec) => {
      if (!cleanIds.includes(Number(sec.course_section_id))) {
        return sec;
      }
      const nextGrades =
        typeof patch.term_grades_submitted === 'boolean'
          ? patch.term_grades_submitted
          : Boolean(sec.term_grades_submitted);
      const nextLock =
        typeof patch.registrar_lock === 'boolean' ? patch.registrar_lock : Boolean(sec.registrar_lock);
      return {
        ...sec,
        term_grades_submitted: nextGrades,
        registrar_lock: nextLock,
        double_blind_unlocked: canFacultyViewEvaluation(nextGrades, nextLock),
      };
    });

    try {
      const res = await fetch('/api/admin/sections/bulk-lock', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({
          section_ids: cleanIds,
          ...patch,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        await fetchAdminSections();
        return { ok: true, ...data };
      }
      sectionLocks.value = snapshot;
      return { ok: false, message: data.message || 'Bulk section update failed.' };
    } catch (_) {
      sectionLocks.value = snapshot;
      return { ok: false, message: 'Network error during bulk section update. Rolled back safely.' };
    }
  }

  async function ingestRosterRows(rows = [], format = 'rows', rawContent = '') {
    if (format === 'rows' || format === 'json') {
      if (!Array.isArray(rows) || rows.length === 0) {
        return { ok: false, imported: 0, message: 'No valid roster rows provided.' };
      }
      for (const row of rows) {
        if (!row.course_code || !(row.instructor_name || row.faculty_name)) {
          return {
            ok: false,
            imported: 0,
            message: 'Atomic import aborted: every row must contain course_code and instructor_name.',
          };
        }
      }
    }

    try {
      const res = await fetch('/api/admin/rosters/import', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({
          format: format === 'csv' ? 'csv' : format === 'json' ? 'json' : 'rows',
          rows,
          content: rawContent,
          academic_term_id: evaluationWindow.value.academic_term_id || 1,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.imported !== false && !data.aborted) {
        await fetchAdminSections();
        const importedCount = Number(data.imported_rows ?? data.imported ?? rows.length);
        return {
          ok: true,
          imported: importedCount,
          message:
            data.message ||
            `Successfully committed ${importedCount} academic enrollment record(s) to the institutional database.`,
          ...data,
        };
      }
      const serverErrors = Array.isArray(data.errors) ? data.errors.filter(Boolean) : [];
      return {
        ok: false,
        imported: 0,
        errors: serverErrors,
        message:
          data.message ||
          (serverErrors.length > 0
            ? `Nothing was imported. ${serverErrors.slice(0, 3).join(' ')}${serverErrors.length > 3 ? ` (+${serverErrors.length - 3} more)` : ''}`
            : 'Atomic roster import rejected by server validation.'),
      };
    } catch (_) {
      return {
        ok: false,
        imported: 0,
        message: 'Unable to connect to the Registrar database service during roster import.',
      };
    }
  }

  async function fetchEvaluationWindow(termId = 1) {
    try {
      const res = await fetch(`/api/evaluation-window?academic_term_id=${encodeURIComponent(termId)}`, {
        headers: getApiHeaders(),
      });
      if (res.ok) {
        const data = await res.json();
        evaluationWindow.value = { ...evaluationWindow.value, ...data };
      }
    } catch (_) {}
    return evaluationWindow.value;
  }

  async function updateEvaluationWindowConfig(patch = {}) {
    const payload = {
      ...evaluationWindow.value,
      ...patch,
      evaluation_open: (patch.status ?? evaluationWindow.value.status) === 'OPEN',
    };

    const res = await fetch('/api/admin/evaluation-window', {
      method: 'POST',
      headers: getApiHeaders(true),
      body: JSON.stringify(payload),
    });

    const serverData = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(serverData.message || 'Unable to save evaluation window configuration.');
    }

    evaluationWindow.value = { ...evaluationWindow.value, ...serverData };
    return { ok: true, config: evaluationWindow.value };
  }

  function setEvaluationWindowStatus(status) {
    return updateEvaluationWindowConfig({ status });
  }

  async function fetchDeanOverview(departmentId = null, basePath = '/api/dean') {
    try {
      const q =
        departmentId !== null && departmentId !== undefined && departmentId !== 'ALL'
          ? `?department_id=${encodeURIComponent(departmentId)}`
          : '';
      const endpoint = basePath === '/api/dean' ? '/api/dean/overview' : `${basePath}/overview`;
      const response = await fetch(`${endpoint}${q}`, {
        headers: getApiHeaders(),
      });
      if (response.ok) {
        const data = await response.json();
        deanOverview.value = { ...deanOverview.value, ...data };
        return { ok: true, data: deanOverview.value };
      }
    } catch (_) {}
    return { ok: false, data: deanOverview.value };
  }

  return {
    courses,
    examPermitToken,
    queueLoading,
    activeCourseId,
    activeCourse,
    dwellSeconds,
    startToken,
    submissionKey,
    submitting,
    submissionError,
    facultySections,
    facultyReports,
    sectionLocks,
    adminSummary,
    deanOverview,
    evaluationWindow,
    queueProgress,
    dimensions: PEDAGOGICAL_DIMENSIONS,
    resetState,
    selectCourseForEvaluation,
    incrementDwellTime,
    fetchQueue,
    submitEvaluation,
    markCourseClearedLocal,
    fetchFacultySections,
    fetchFacultySectionReport,
    submitFacultyTermGrades,
    fetchAdminSections,
    toggleSectionLock,
    bulkToggleSectionLocks,
    ingestRosterRows,
    evaluateWatchlistFlag,
    canFacultyViewEvaluation,
    fetchEvaluationWindow,
    updateEvaluationWindowConfig,
    setEvaluationWindowStatus,
    fetchDeanOverview,
  };
});
