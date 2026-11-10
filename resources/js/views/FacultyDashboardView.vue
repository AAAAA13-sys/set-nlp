<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import SkeletonLoader from '../components/SkeletonLoader.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { canFacultyViewEvaluation, sanitizeCsvCell } from '../utils/evaluationMath.js';

const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const selectedSectionId = ref(null);
const loadingSections = ref(false);
const loadingReport = ref(false);
const submittingGrades = ref(false);
const reportNetworkError = ref('');
const gradeStatusBanner = ref(null);
const planStatusOverrides = ref({});

const assignedSections = computed(() => evaluationStore.facultySections || []);

const activeSection = computed(
  () =>
    assignedSections.value.find(
      (s) => Number(s.course_section_id) === Number(selectedSectionId.value),
    ) || assignedSections.value[0] || null,
);

const isUnlocked = computed(() =>
  canFacultyViewEvaluation(
    Boolean(activeSection.value?.term_grades_submitted),
    Boolean(activeSection.value?.registrar_lock),
  ),
);

const unlockedReport = computed(() => {
  const secId = Number(activeSection.value?.course_section_id || 0);
  return secId > 0 ? evaluationStore.facultyReports[secId] || null : null;
});

const dimensionScores = computed(() => {
  return unlockedReport.value?.cluster_scores || activeSection.value?.dimension_scores || {};
});

const overallMeanScore = computed(() => {
  if (unlockedReport.value && typeof unlockedReport.value.overall_mean_score === 'number') {
    return unlockedReport.value.overall_mean_score;
  }
  return Number(activeSection.value?.overall_mean_score || 0);
});

const responseCount = computed(() => {
  if (unlockedReport.value && typeof unlockedReport.value.response_count === 'number') {
    return unlockedReport.value.response_count;
  }
  return Number(activeSection.value?.evaluated_count || 0);
});

const anonymizedComments = computed(() => {
  const raw = unlockedReport.value?.anonymized_comments || [];
  return raw.map((c) => ({
    excerpt: c.comment_text || c.excerpt || '',
    polarity: Number(c.sentiment_polarity ?? c.polarity ?? 0),
    valence: c.sentiment_valence || c.valence || 'Neutral',
    tag: c.pedagogical_tag || c.tag || 'Pedagogical Delivery',
    recorded_date: c.recorded_date || null,
  }));
});

const profQuestionnaireSummary = computed(
  () => unlockedReport.value?.professor_questionnaire_summary || null,
);

const sectionActionPlan = computed(() => {
  const plan = unlockedReport.value?.action_plan || null;
  if (!plan) return null;
  const secId = Number(activeSection.value?.course_section_id || 0);
  return {
    ...plan,
    course_code: activeSection.value?.course_code || plan.course_code,
    status: planStatusOverrides.value[secId] || plan.status || 'PENDING_REVIEW',
  };
});

async function loadSelectedReportIfUnlocked(sec) {
  if (!sec) return;
  reportNetworkError.value = '';
  if (canFacultyViewEvaluation(Boolean(sec.term_grades_submitted), Boolean(sec.registrar_lock))) {
    loadingReport.value = true;
    try {
      await evaluationStore.fetchFacultySectionReport(sec.course_section_id);
    } catch (err) {
      reportNetworkError.value = err?.message || 'Unable to retrieve double-blind report.';
    } finally {
      loadingReport.value = false;
    }
  }
}

onMounted(async () => {
  loadingSections.value = true;
  try {
    const sections = await evaluationStore.fetchFacultySections();
    if (sections.length > 0) {
      selectedSectionId.value = Number(sections[0].course_section_id);
      await loadSelectedReportIfUnlocked(sections[0]);
    }
  } finally {
    loadingSections.value = false;
  }
});

watch(selectedSectionId, async (newId) => {
  gradeStatusBanner.value = null;
  const sec = assignedSections.value.find((s) => Number(s.course_section_id) === Number(newId));
  if (sec) {
    await loadSelectedReportIfUnlocked(sec);
  }
});

async function markInstructorGradesSubmitted() {
  if (!activeSection.value) return;
  submittingGrades.value = true;
  gradeStatusBanner.value = null;

  try {
    const res = await evaluationStore.submitFacultyTermGrades(activeSection.value.course_section_id);
    if (res.ok) {
      gradeStatusBanner.value = {
        ok: true,
        message: res.double_blind_unlocked
          ? 'Term grades recorded and Registrar lock verified. Section evaluation report is now unlocked.'
          : 'Term grades recorded in the database. Evaluation summary will unlock once the Office of the University Registrar finalizes registrar_lock.',
      };
    } else {
      gradeStatusBanner.value = {
        ok: false,
        message: res.message || 'Unable to submit term grades for this section.',
      };
    }
  } finally {
    submittingGrades.value = false;
  }
}

async function updateFacultyPlanStatus(newStatus) {
  if (!activeSection.value || !sectionActionPlan.value) return;
  const secId = Number(activeSection.value.course_section_id);
  const facultyId = Number(authStore.user?.id || activeSection.value.faculty_id || 0);
  planStatusOverrides.value[secId] = newStatus;

  try {
    await fetch('/api/faculty/action-plan/status', {
      method: 'POST',
      headers: authStore.authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        plan_id: sectionActionPlan.value.plan_id,
        faculty_id: facultyId,
        status: newStatus,
        dean_notes: sectionActionPlan.value.dean_notes || '',
      }),
    });
    await evaluationStore.fetchFacultySectionReport(secId);
  } catch (_) {}
}

function getPerformanceRatingText(score) {
  const num = Number(score || 0);
  if (num >= 4.5) return 'Outstanding';
  if (num >= 4.0) return 'Very Satisfactory';
  if (num >= 3.5) return 'Satisfactory';
  if (num >= 3.0) return 'Fair';
  return 'Needs Improvement';
}

function exportFacultySectionCsv() {
  if (!activeSection.value || !isUnlocked.value) return;

  const courseCode = activeSection.value.course_code || 'COURSE';
  const sectionCode = activeSection.value.section_code || 'SEC';
  const termCode = authStore.activeTerm?.replace(/[^a-zA-Z0-9_-]/g, '_') || '1st_Sem_AY2026-2027';
  const facultyName = authStore.user?.name || activeSection.value.instructor_name || 'Faculty';
  const facultyInstId = authStore.user?.institutional_id || 'FAC';

  const enrolled = Number(activeSection.value.enrolled_count || 0);
  const evaluated = Number(responseCount.value || 0);
  const turnoutPct = enrolled > 0 ? ((evaluated / enrolled) * 100).toFixed(1) : '100.0';
  const meanScore = overallMeanScore.value.toFixed(2);
  const ratingText = getPerformanceRatingText(overallMeanScore.value);

  const clusters = dimensionScores.value || {};
  const cPed = Number(clusters['Pedagogical Delivery'] || 0).toFixed(2);
  const cAssess = Number(clusters['Assessment & Grading'] || 0).toFixed(2);
  const cMgmt = Number(clusters['Classroom Management'] || 0).toFixed(2);
  const cConduct = Number(clusters['Professional Conduct'] || 0).toFixed(2);
  const cSupport = Number(clusters['Student Support & Mentorship'] || 0).toFixed(2);

  const topDesc = (profQuestionnaireSummary.value?.top_descriptors || [])
    .slice(0, 3)
    .map((d) => `${d.word} (x${d.count})`)
    .join('; ');

  const strength = profQuestionnaireSummary.value?.key_strengths?.[0] || 'N/A';
  const rec = profQuestionnaireSummary.value?.key_recommendations?.[0] || 'N/A';
  const planStatus = sectionActionPlan.value?.status || 'N/A';

  const headers = [
    'Academic Term',
    'Faculty Name',
    'Faculty ID',
    'Course Code',
    'Course Title',
    'Section Code',
    'Enrolled Students',
    'Completed Evaluations',
    'Turnout Rate (%)',
    'Composite Mean Score (1-5)',
    'Performance Adjective',
    'Pedagogical Delivery (1-5)',
    'Assessment & Grading (1-5)',
    'Classroom Management (1-5)',
    'Professional Conduct (1-5)',
    'Student Support & Mentorship (1-5)',
    'Top Student Descriptors',
    'Key Teaching Strength',
    'Actionable Recommendation',
    'Pedagogical Action Plan Status',
  ];

  const row = [
    sanitizeCsvCell(authStore.activeTerm || '1st Semester AY 2026-2027'),
    sanitizeCsvCell(facultyName),
    sanitizeCsvCell(facultyInstId),
    sanitizeCsvCell(courseCode),
    sanitizeCsvCell(activeSection.value.course_title || ''),
    sanitizeCsvCell(sectionCode),
    enrolled,
    evaluated,
    turnoutPct,
    meanScore,
    sanitizeCsvCell(ratingText),
    cPed,
    cAssess,
    cMgmt,
    cConduct,
    cSupport,
    sanitizeCsvCell(topDesc),
    sanitizeCsvCell(strength),
    sanitizeCsvCell(rec),
    sanitizeCsvCell(planStatus),
  ];

  const csvContent = [headers.join(','), row.join(',')].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `PLP_Faculty_Evaluation_${courseCode}_${sectionCode}_${termCode}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function printEvaluationReport() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}
</script>

<template>
  <main class="workspace-main faculty-dashboard-workspace" aria-labelledby="faculty-heading">
    <ErrorRecoveryBanner
      :visible="Boolean(reportNetworkError)"
      title="Couldn't load faculty evaluation report"
      message="Your double-blind section state is safe. Check your connection."
      :detail="reportNetworkError"
      :retrying="loadingReport"
      @retry="loadSelectedReportIfUnlocked(activeSection)"
      @dismiss="reportNetworkError = ''"
    />

    <SkeletonLoader
      v-if="loadingSections"
      :rows="3"
      label="Loading assigned PLP faculty sections…"
    />

    <section class="card faculty-header-card">
      <div class="header-split">
        <div>
          <span class="eyebrow-badge">
            FACULTY FEEDBACK
          </span>
          <h2 id="faculty-heading" class="section-title">
            Course Evaluation Summary
            <span class="ui-hint" tabindex="0" data-hint="Feedback unlocks after final semester grades are submitted and verified by the Registrar.">?</span>
          </h2>
          <p class="section-subtitle">
            Instructor: <strong>{{ authStore.user?.name || 'Faculty Member' }}</strong>
            <span v-if="authStore.user?.institutional_id"> ({{ authStore.user.institutional_id }})</span>
          </p>
        </div>

        <div v-if="assignedSections.length > 0" class="section-selector-box">
          <label for="faculty-section-select" style="font-weight: 600; font-size: 0.84rem; margin-right: 0.5rem;">
            Section:
          </label>
          <select
            id="faculty-section-select"
            v-model.number="selectedSectionId"
            class="form-input"
            style="width: auto; min-width: 240px;"
            data-testid="faculty-section-select"
          >
            <option
              v-for="sec in assignedSections"
              :key="sec.course_section_id"
              :value="sec.course_section_id"
            >
              {{ sec.course_code }} ({{ sec.section_code }}) —
              {{ sec.term_grades_submitted && sec.registrar_lock ? 'Unlocked' : 'Locked' }}
            </option>
          </select>
        </div>
      </div>

      <div
        v-if="gradeStatusBanner"
        class="alert-banner"
        :class="gradeStatusBanner.ok ? 'alert-success' : 'alert-error'"
        style="margin-top: 1rem;"
        role="status"
      >
        {{ gradeStatusBanner.message }}
      </div>
    </section>

    <section
      v-if="!loadingSections && assignedSections.length === 0"
      class="card"
      data-testid="faculty-empty-sections"
    >
      <h3 class="section-title">No Assigned Course Sections</h3>
      <p class="section-subtitle">
        No course sections are assigned to your account for this semester.
      </p>
    </section>

    <!-- Double-Blind Lock Screen when (term_grades_submitted && registrar_lock) is FALSE -->
    <section
      v-else-if="activeSection && !isUnlocked"
      class="card double-blind-lock-card"
      role="region"
      aria-labelledby="lock-banner-heading"
      data-testid="double-blind-lock-screen"
    >
      <div class="lock-badge-row" style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.5rem;">
        <span class="badge-locked">Grades Pending<span class="sr-only"> DOUBLE_BLIND_LOCKED</span></span>
        <strong class="lock-section-code">
          {{ activeSection.course_code }} ({{ activeSection.section_code }}) — {{ activeSection.course_title }}
        </strong>
      </div>

      <h3 id="lock-banner-heading" class="section-title">
        Results Locked Until Final Grades Are Submitted
        <span class="ui-hint" tabindex="0" data-hint="Evaluation scores open once your grades are submitted and verified by the Registrar.">?</span>
      </h3>
      <p class="section-subtitle">
        To ensure fair grading, student evaluation results for
        <strong>{{ activeSection.course_code }} ({{ activeSection.section_code }})</strong> open once both steps below are completed:
      </p>

      <div class="lock-checklist">
        <div class="lock-check-item" :class="activeSection.term_grades_submitted ? 'check-done' : 'check-pending'">
          <strong>1. Final Semester Grades Submitted<span class="sr-only"> (term_grades_submitted)</span>:</strong>
          <span>{{ activeSection.term_grades_submitted ? 'Completed' : 'Pending Instructor Submission' }}</span>
        </div>

        <div class="lock-check-item" :class="activeSection.registrar_lock ? 'check-done' : 'check-pending'">
          <strong>2. Registrar Grade Verification<span class="sr-only"> (registrar_lock)</span>:</strong>
          <span>{{ activeSection.registrar_lock ? 'Verified by Registrar' : 'Awaiting Registrar Verification' }}</span>
        </div>
      </div>

      <div class="lock-actions">
        <button
          v-if="!activeSection.term_grades_submitted"
          type="button"
          class="btn-primary"
          :disabled="submittingGrades"
          data-testid="submit-term-grades-btn"
          @click="markInstructorGradesSubmitted"
        >
          {{ submittingGrades ? 'Recording Grade Status…' : 'Confirm Grade Submission Status' }}
        </button>
      </div>

      <details class="double-blind-help-details" style="margin-top: 1rem; padding: 0.75rem; background: #fdfefe; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.82rem; color: #475569;">
        <summary style="font-weight: 600; cursor: pointer; color: #103c2e;">
          Why are evaluation results locked? (Double-Blind Governance Policy)
        </summary>
        <p style="margin: 0.5rem 0 0 0; line-height: 1.45;">
          Under PLP academic governance guidelines and AACCUP accreditation standards, student evaluation ratings and qualitative comments remain confidential until the instructor has submitted final course grades and the Office of the University Registrar verifies the grade lock. This double-blind protocol protects students from grading retaliation and ensures honest, objective feedback.
        </p>
      </details>
    </section>

    <!-- Unlocked Aggregated Evaluation Report -->
    <section
      v-else-if="activeSection && isUnlocked"
      class="card unlocked-report-card"
      aria-labelledby="unlocked-report-heading"
      data-testid="faculty-unlocked-report"
    >
      <div class="unlocked-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span class="badge-unlocked">RESULTS AVAILABLE<span class="sr-only"> DOUBLE-BLIND UNLOCKED</span></span>
          <h3 id="unlocked-report-heading" class="section-title">
            {{ activeSection.course_code }} ({{ activeSection.section_code }}) — {{ activeSection.course_title }}
          </h3>
          <p class="section-subtitle tabular-nums">
            Instructor: <strong>{{ activeSection.instructor_name }}</strong> •
            Evaluations Logged: <strong>{{ responseCount }} / {{ activeSection.enrolled_count }}</strong> •
            Overall Section Mean: <strong>{{ overallMeanScore.toFixed(2) }} / 5.00</strong>
          </p>
        </div>
        <div class="unlocked-header-actions" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-faculty-section-csv-btn"
            style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.85rem; font-weight: 600;"
            @click="exportFacultySectionCsv"
          >
            <span>📥 Export CSV</span>
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="print-faculty-report-btn"
            style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.95rem; font-size: 0.85rem;"
            @click="printEvaluationReport"
          >
            <span>Print / Export Rating Sheet</span>
          </button>
        </div>
      </div>

      <div class="dimension-averages-grid tabular-nums">
        <div
          v-for="(score, dimName) in dimensionScores"
          :key="dimName"
          class="dimension-score-card"
        >
          <span class="dim-card-title">{{ dimName }}</span>
          <strong class="dim-card-value">{{ Number(score).toFixed(2) }} / 5.00</strong>
          <div class="dim-bar-track">
            <div class="dim-bar-fill" :style="{ width: `${Math.round((Number(score) / 5) * 100)}%` }"></div>
          </div>
        </div>
      </div>

      <div
        v-if="profQuestionnaireSummary"
        class="prof-qualitative-summary-box"
        data-testid="faculty-prof-questions-summary"
      >
        <h4>
          Student Written Feedback Summary
          <span class="ui-hint" tabindex="0" data-hint="Grouped student responses from the written feedback questions.">?</span>
        </h4>
        <div class="metric-overview-grid">
          <div class="metric-card">
            <span class="metric-label">1. Top Experience Descriptors</span>
            <strong class="metric-value" style="font-size: 1rem;">
              {{
                profQuestionnaireSummary.top_descriptors?.length
                  ? profQuestionnaireSummary.top_descriptors
                      .slice(0, 3)
                      .map((d) => `${d.word}${d.count > 1 ? ` (×${d.count})` : ''}`)
                      .join(' • ')
                  : 'No descriptors submitted yet'
              }}
            </strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">2. Constructive Improvement Topics</span>
            <span class="section-subtitle">
              <template v-if="profQuestionnaireSummary.improvement_topics?.length">
                <span
                  v-for="(topic, tIdx) in profQuestionnaireSummary.improvement_topics.slice(0, 2)"
                  :key="tIdx"
                  style="display: block; margin-bottom: 0.2rem;"
                >
                  <strong>{{ topic.sub_item || `${String.fromCharCode(97 + tIdx)}.` }}</strong> {{ topic.topic }}
                </span>
              </template>
              <template v-else>
                No improvement topics recorded for this section.
              </template>
            </span>
          </div>
          <div class="metric-card">
            <span class="metric-label">3. Key Teaching Strengths</span>
            <span class="section-subtitle">
              {{ profQuestionnaireSummary.key_strengths?.[0] || 'No teaching strengths recorded yet.' }}
            </span>
          </div>
          <div class="metric-card">
            <span class="metric-label">4. Actionable Recommendations</span>
            <span class="section-subtitle">
              {{ profQuestionnaireSummary.key_recommendations?.[0] || 'No recommendations recorded yet.' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Institutional Pedagogical Action Plan Card for Watchlist-Flagged Sections -->
      <div
        v-if="sectionActionPlan"
        class="card faculty-action-plan-box"
        style="margin-top: 1.25rem; background: var(--canvas); border: 1px solid var(--line); border-radius: 10px; padding: 1.15rem;"
        data-testid="faculty-action-plan-card"
      >
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
          <span class="eyebrow-badge">
            ACTION PLAN<span class="sr-only"> PAMANTASAN NG LUNGSOD NG PASIG • Continuous Pedagogical Improvement</span>
          </span>
          <span
            class="token-chip"
            :class="{
              'chip-unsigned': sectionActionPlan.status === 'PENDING_REVIEW',
              'chip-signed': sectionActionPlan.status === 'APPROVED' || sectionActionPlan.status === 'RESOLVED' || sectionActionPlan.status === 'IN_PROGRESS',
            }"
            data-testid="faculty-plan-status-badge"
          >
            Plan Status: {{ sectionActionPlan.status }}
          </span>
        </div>

        <h4 style="margin: 0.2rem 0 0.4rem; color: var(--ink); font-size: 1.1rem;">
          Pedagogical Action Plan: Focus on {{ sectionActionPlan.primary_cluster }} ({{ sectionActionPlan.course_code }})
          <span class="ui-hint" tabindex="0" data-hint="Recommended teaching adjustments based on section evaluation ratings.">?</span>
        </h4>

        <p v-if="sectionActionPlan.target_milestone" class="section-subtitle" style="font-size:0.86rem; margin-bottom: 0.6rem;">
          Target Milestone: <strong>Mean Score ≥ {{ sectionActionPlan.target_milestone.target_mean_score }} / 5.00</strong> •
          Negative Sentiment Share: <strong>≤ {{ sectionActionPlan.target_milestone.target_negative_pct }}%</strong> •
          Timeline: <strong>{{ sectionActionPlan.timeline }}</strong>
        </p>

        <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 0.85rem; margin-bottom: 0.75rem;">
          <strong style="font-size: 0.86rem; color: var(--ink);">Assigned Pedagogical Strategies:</strong>
          <ul style="margin: 0.4rem 0 0 1.2rem; padding: 0; font-size: 0.85rem; line-height: 1.55;">
            <li v-for="(strat, sIdx) in sectionActionPlan.recommended_interventions" :key="sIdx">
              {{ strat }}
            </li>
          </ul>
        </div>

        <div v-if="sectionActionPlan.dean_notes" style="font-size: 0.84rem; color: var(--forest); margin-bottom: 0.75rem;">
          <strong>Dean Verification &amp; Sign-off Notes:</strong> {{ sectionActionPlan.dean_notes }}
        </div>

        <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
          <button
            v-if="sectionActionPlan.status !== 'RESOLVED'"
            type="button"
            class="btn-primary btn-sm"
            data-testid="faculty-acknowledge-plan-btn"
            @click="updateFacultyPlanStatus('IN_PROGRESS')"
          >
            {{ sectionActionPlan.status === 'IN_PROGRESS' ? 'Implementation In Progress' : 'Acknowledge & Implement Plan' }}
          </button>

          <button
            v-if="sectionActionPlan.status === 'IN_PROGRESS'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="faculty-submit-milestone-btn"
            @click="updateFacultyPlanStatus('RESOLVED')"
          >
            Submit Milestone Resolution Evidence
          </button>

          <span
            v-if="sectionActionPlan.status === 'RESOLVED'"
            class="badge-unlocked"
            data-testid="faculty-plan-resolved-badge"
          >
            Milestone Successfully Resolved &amp; Verified
          </span>
        </div>
      </div>

      <div class="anonymized-comments-section" style="margin-top: 1.25rem;">
        <h4>
          Anonymous Student Comments
          <span class="ui-badge-count">{{ anonymizedComments.length }}</span>
          <span class="ui-hint" tabindex="0" data-hint="All student identifiers are removed before comments are saved.">?</span>
          <span class="sr-only">Zero Student Identifiers</span>
        </h4>
        <div
          v-if="anonymizedComments.length === 0"
          style="padding: 1rem; background: var(--plp-green-50); border: 1px solid var(--border-subtle); border-radius: 8px; font-size: 0.88rem; color: var(--text-secondary);"
        >
          No open-ended narrative comments have been submitted for this section yet.
        </div>
        <div
          v-for="(comment, idx) in anonymizedComments"
          :key="idx"
          class="comment-insight-card"
        >
          <p class="comment-quote">“{{ comment.excerpt }}”</p>
          <div class="comment-meta tabular-nums">
            <span>Polarity: <strong>{{ comment.polarity >= 0 ? `+${comment.polarity.toFixed(2)}` : comment.polarity.toFixed(2) }}</strong></span>
            <span>Valence: <strong>{{ comment.valence }}</strong></span>
            <span>Pedagogical Tag: <strong>{{ comment.tag }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Official Rating Sheet Print Endorsement & Signatures Block -->
      <div class="print-permit-auth-block" data-testid="print-faculty-report-auth">
        <div class="auth-seal-watermark">
          <span class="seal-badge">SEAL: PLP-ACADEMIC-AFFAIRS-EVALUATION-RECORD</span>
          <small class="seal-sub">Official Student Evaluation of Teachers (SET) Rating Sheet • AY 2026-2027</small>
        </div>
        <div class="auth-signatures-row">
          <div class="sig-column">
            <div class="sig-line"></div>
            <strong class="sig-title">{{ activeSection.instructor_name }}</strong>
            <span class="sig-caption">Faculty Member</span>
          </div>
          <div class="sig-column">
            <div class="sig-line"></div>
            <strong class="sig-title">DEPARTMENT CHAIR</strong>
            <span class="sig-caption">Department of Computer Science</span>
          </div>
          <div class="sig-column">
            <div class="sig-line"></div>
            <strong class="sig-title">DR. V. MENDOZA</strong>
            <span class="sig-caption">Dean, College of Computer Studies</span>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>
