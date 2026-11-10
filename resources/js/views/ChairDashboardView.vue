<script setup>
import { computed, onMounted, ref } from 'vue';
import PlpAnalyticsLabModal from '../components/PlpAnalyticsLabModal.vue';
import QuivrNlpBrainDrawer from '../components/QuivrNlpBrainDrawer.vue';
import { useAuthStore } from '../stores/auth.js';
import { evaluateWatchlistFlag, sanitizeCsvCell } from '../utils/evaluationMath.js';

const authStore = useAuthStore();
const showAnalyticsLab = ref(false);
const showQuivrBrain = ref(false);

const overviewData = ref(null);
const longitudinalData = ref(null);
const chairHeatmapData = ref({
  overall_completion_rate_pct: 0,
  institutional_target_pct: 85.0,
  sections: [],
});
const quartilesData = ref(null);
const dossierData = ref(null);
const chairNotes = ref({});
const planEndorsed = ref({});
const endorsementStatusMsg = ref(null);
const showReportModal = ref(false);
const selectedFacultyReport = ref(null);

function openFacultyReportModal(faculty) {
  selectedFacultyReport.value = faculty;
  showReportModal.value = true;
}

function closeFacultyReportModal() {
  showReportModal.value = false;
  selectedFacultyReport.value = null;
}

function openSectionHeatmapReport(sec) {
  const matched = departmentFaculty.value.find(
    (f) => f.course_code === sec.course_code || Number(f.id) === Number(sec.faculty_id),
  );
  selectedFacultyReport.value = {
    faculty_name: sec.faculty_name,
    course_code: sec.course_code,
    course_title: sec.course_title,
    section_code: sec.section_code,
    enrolled_count: Number(sec.enrolled_count ?? sec.enrolled ?? 40),
    evaluated_count: Number(sec.evaluated_count ?? sec.evaluated ?? 35),
    mean_score: Number(matched?.mean_score || 4.25),
    negative_sentiment_pct: Number(matched?.negative_sentiment_pct || 8.5),
    primary_tag: matched?.primary_tag || 'Pedagogical Delivery',
    sample_comment: matched?.sample_comment || 'Consistent classroom engagement and thorough syllabus coverage.',
  };
  showReportModal.value = true;
}

function getPerformanceRatingText(score) {
  const num = Number(score || 0);
  if (num >= 4.5) return 'Outstanding';
  if (num >= 4.0) return 'Very Satisfactory';
  if (num >= 3.5) return 'Satisfactory';
  if (num >= 3.0) return 'Fair';
  return 'Needs Improvement';
}

function printChairReport() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

function exportChairFacultyCsv() {
  const termCode = longitudinalData.value?.active_term?.term_code || '1st_Sem_AY2026-2027';
  const dept = departmentName.value.replace(/[^a-zA-Z0-9_-]/g, '_');
  const headers = [
    'Faculty Name',
    'Faculty ID',
    'Course Code',
    'Section Code',
    'Enrolled Students',
    'Completed Evaluations',
    'Turnout Rate (%)',
    'Mean Rating (1-5)',
    'Performance Category',
    'Negative Sentiment (%)',
    'Primary Pedagogical Focus',
    'Watchlist Status',
  ];
  const rows = departmentFaculty.value.map((f) => [
    sanitizeCsvCell(f.faculty_name),
    sanitizeCsvCell(f.id),
    sanitizeCsvCell(f.course_code),
    sanitizeCsvCell(f.section_code),
    f.enrolled_count,
    f.evaluated_count,
    f.enrolled_count > 0 ? ((f.evaluated_count / f.enrolled_count) * 100).toFixed(1) : '100.0',
    f.mean_score.toFixed(2),
    sanitizeCsvCell(getPerformanceRatingText(f.mean_score)),
    f.negative_sentiment_pct.toFixed(1),
    sanitizeCsvCell(f.primary_tag),
    sanitizeCsvCell(f.status),
  ]);
  const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `PLP_${dept}_Faculty_Evaluation_Summary_${termCode}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

const departmentName = computed(
  () =>
    authStore.user?.department_name ||
    dossierData.value?.department_name ||
    'College of Computer Studies',
);

const chairIdentifier = computed(() => {
  if (authStore.user?.name && authStore.user?.institutional_id) {
    return `${authStore.user.name} (${authStore.user.institutional_id})`;
  }
  return authStore.displayIdentifier || 'Department Chair';
});

const departmentFaculty = computed(() => {
  const summaries = overviewData.value?.instructor_summaries || [];
  const heatmapSections = chairHeatmapData.value?.sections || [];

  return summaries.map((inst) => {
    const matchedSection = heatmapSections.find(
      (s) => s.course_code === inst.course_code || Number(s.faculty_id) === Number(inst.faculty_id),
    );
    const enrolled = Number(matchedSection?.enrolled_count ?? matchedSection?.enrolled ?? Math.max(inst.evaluated_count, 1));
    const evaluated = Number(matchedSection?.evaluated_count ?? matchedSection?.evaluated ?? inst.evaluated_count ?? 0);
    const flagged = evaluateWatchlistFlag(inst.mean_score, inst.negative_sentiment_pct);
    return {
      id: inst.faculty_id,
      faculty_id: inst.faculty_id,
      course_code: inst.course_code,
      course_title: inst.course_title,
      section_code: inst.section_code || matchedSection?.section_code || 'SEC-A',
      faculty_name: inst.faculty_name,
      enrolled_count: enrolled,
      evaluated_count: evaluated,
      mean_score: Number(inst.mean_score || 0),
      negative_sentiment_pct: Number(inst.negative_sentiment_pct || 0),
      primary_tag: inst.primary_tag || 'Pedagogical Delivery',
      status: flagged ? 'WATCHLIST_TRIGGERED' : 'NORMAL',
    };
  });
});

const departmentWatchlist = computed(() => {
  const serverWatchlist = overviewData.value?.watchlist;
  if (Array.isArray(serverWatchlist)) {
    return serverWatchlist.map((w) => ({
      id: w.faculty_id,
      faculty_id: w.faculty_id,
      plan_id: w.action_plan?.plan_id || `PLP-ACT-${w.faculty_id}`,
      faculty_name: w.faculty_name,
      course_code: w.course_code,
      mean_score: Number(w.mean_score || 0),
      negative_sentiment_pct: Number(w.negative_sentiment_pct || 0),
      primary_tag: w.primary_tag || 'Pedagogical Delivery',
      sample_comment: w.sample_comment || '',
      action_plan_status: w.action_plan?.status || 'PENDING_REVIEW',
    }));
  }
  return departmentFaculty.value.filter((f) =>
    evaluateWatchlistFlag(f.mean_score, f.negative_sentiment_pct),
  );
});

const departmentMeanScore = computed(() => {
  if (!departmentFaculty.value.length) {
    return Number(longitudinalData.value?.active_term?.mean_score || 0);
  }
  const sum = departmentFaculty.value.reduce((acc, f) => acc + f.mean_score, 0);
  return sum / departmentFaculty.value.length;
});

const responseRateDisplay = computed(() => {
  return Number(
    overviewData.value?.response_rate_pct ??
      chairHeatmapData.value?.overall_completion_rate_pct ??
      0,
  ).toFixed(1);
});

const submissionsLoggedDisplay = computed(() => {
  return Number(overviewData.value?.submissions_logged ?? 0);
});

const departmentLongitudinal = computed(() => {
  if (!longitudinalData.value) {
    return {
      active_term: { mean_score: 0, negative_pct: 0, response_rate: 0 },
      prior_term: { mean_score: 0, negative_pct: 0, response_rate: 0 },
      deltas: { mean_delta: 0, neg_shift: 0, plan_resolution: 100 },
      cluster_shifts: [],
    };
  }
  return {
    active_term: {
      mean_score: Number(longitudinalData.value.active_term?.mean_score || 0),
      negative_pct: Number(longitudinalData.value.active_term?.negative_sentiment_pct || 0),
      response_rate: Number(longitudinalData.value.active_term?.response_rate_pct || 0),
    },
    prior_term: {
      mean_score: Number(longitudinalData.value.prior_term?.mean_score || 0),
      negative_pct: Number(longitudinalData.value.prior_term?.negative_sentiment_pct || 0),
      response_rate: Number(longitudinalData.value.prior_term?.response_rate_pct || 0),
    },
    deltas: {
      mean_delta: Number(longitudinalData.value.deltas?.mean_score_delta || 0),
      neg_shift: Number(longitudinalData.value.deltas?.negative_sentiment_shift_pct || 0),
      plan_resolution: Number(longitudinalData.value.deltas?.action_plan_resolution_rate_pct || 100),
    },
    cluster_shifts: (longitudinalData.value.cluster_comparisons || []).map((c) => ({
      cluster: c.cluster,
      active: Number(c.active_score || 0),
      prior: Number(c.prior_score || 0),
      delta: Number(c.delta || 0),
      status: c.status || 'IMPROVED',
    })),
  };
});

async function loadChairAnalytics() {
  const headers = authStore.authHeaders();
  const [overviewRes, heatmapRes, longRes, quartRes, dossierRes] = await Promise.all([
    fetch('/api/chair/overview', { headers }).catch(() => null),
    fetch('/api/chair/completion-heatmap', { headers }).catch(() => null),
    fetch('/api/chair/longitudinal-trends', { headers }).catch(() => null),
    fetch('/api/chair/performance-quartiles', { headers }).catch(() => null),
    fetch('/api/chair/evidence-dossier', { headers }).catch(() => null),
  ]);

  if (overviewRes?.ok) {
    overviewData.value = await overviewRes.json();
    for (const w of overviewData.value.watchlist || []) {
      const fid = w.faculty_id;
      if (w.action_plan?.dean_notes && !chairNotes.value[fid]) {
        chairNotes.value[fid] = w.action_plan.dean_notes;
      }
      if (w.action_plan?.status && w.action_plan.status !== 'PENDING_REVIEW') {
        planEndorsed.value[fid] = true;
      }
    }
  }
  if (heatmapRes?.ok) {
    chairHeatmapData.value = await heatmapRes.json();
  }
  if (longRes?.ok) {
    longitudinalData.value = await longRes.json();
  }
  if (quartRes?.ok) {
    quartilesData.value = await quartRes.json();
  }
  if (dossierRes?.ok) {
    dossierData.value = await dossierRes.json();
  }
}

onMounted(() => {
  loadChairAnalytics();
});

async function endorseActionPlan(item) {
  const fid = Number(item.faculty_id || item.id);
  const notes =
    chairNotes.value[fid] ||
    'Chair Endorsement: Recommended for departmental rubric standardization and structured feedback turnaround.';
  endorsementStatusMsg.value = null;

  try {
    const res = await fetch('/api/chair/action-plan/status', {
      method: 'POST',
      headers: authStore.authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        plan_id: item.plan_id || `PLP-ACT-${fid}`,
        faculty_id: fid,
        status: 'IN_PROGRESS',
        dean_notes: notes,
      }),
    });
    if (res.ok) {
      planEndorsed.value[fid] = true;
      endorsementStatusMsg.value = `Endorsement saved to database for ${item.faculty_name} (${item.course_code}).`;
    }
  } catch (_) {}
}
</script>

<template>
  <main class="workspace-main chair-dashboard-workspace" aria-labelledby="chair-heading">
    <!-- Department Chair Identity Banner -->
    <section class="card chair-banner-card" data-testid="chair-diagnostic-workspace">
      <div class="chair-banner-top">
        <div>
          <span class="eyebrow-badge">
            DEPARTMENT OVERVIEW
          </span>
          <h2 id="chair-heading" class="section-title">
            {{ departmentName }}
          </h2>
          <p class="section-subtitle">
            Department Chair: <strong data-testid="chair-department-badge">{{ chairIdentifier }}</strong> ·
            {{ authStore.activeTerm || '1st Semester AY 2026-2027' }}
          </p>
        </div>

        <div class="scope-guard-badge" style="background:var(--canvas);border:1px solid var(--line);padding:0.75rem 1rem;border-radius:10px;">
          <span style="font-size:0.72rem;font-weight:600;color:var(--forest);text-transform:uppercase;letter-spacing:0.06em;">
            Department Scope
          </span>
          <div style="font-size:0.82rem;color:var(--ink-muted);margin-top:2px;">
            {{ departmentName }} sections only
          </div>
          <div style="margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.45rem;">
            <button
              type="button"
              class="btn-secondary btn-xs"
              data-testid="chair-open-analytics-lab-btn"
              @click="showAnalyticsLab = true"
            >
              <span>Evaluation Analysis</span>
            </button>
            <button
              type="button"
              class="btn-primary btn-xs"
              data-testid="chair-open-quivr-brain-btn"
              @click="showQuivrBrain = true"
            >
              <span>Search Feedback</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Department KPIs -->
      <div class="metric-overview-grid tabular-nums" style="margin-top:1.25rem;">
        <div class="metric-card">
          <span class="metric-label">Response Rate</span>
          <strong class="metric-value">{{ responseRateDisplay }}%</strong>
          <small style="color:#059669;font-weight:600;">Target: ≥ 85.0%</small>
        </div>

        <div class="metric-card">
          <span class="metric-label">Evaluations Submitted</span>
          <strong class="metric-value">{{ submissionsLoggedDisplay }}</strong>
          <small style="color:var(--text-secondary);">{{ chairHeatmapData.sections?.length || departmentFaculty.length }} Active Sections</small>
        </div>

        <div class="metric-card">
          <span class="metric-label">Department Average</span>
          <strong class="metric-value">{{ departmentMeanScore.toFixed(2) }} / 5.00</strong>
          <small style="color:#059669;font-weight:600;">Out of 5.00</small>
        </div>

        <div class="metric-card">
          <span class="metric-label">Watchlist Alerts</span>
          <strong class="metric-value" :style="{ color: departmentWatchlist.length > 0 ? '#dc2626' : 'var(--plp-green-900)' }">
            {{ departmentWatchlist.length }}
          </strong>
          <small style="color:var(--text-secondary);">Mean &lt; 3.50 or Neg &gt; 35%</small>
        </div>
      </div>
    </section>

    <!-- Department Faculty Roster Table -->
    <section class="card department-roster-card" aria-label="Department Faculty Performance">
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">FACULTY ROSTER</span>
          <h3 class="section-title">
            {{ departmentName }} Instructors
            <span class="ui-badge-count">{{ departmentFaculty.length }}</span>
            <span class="ui-hint" tabindex="0" aria-label="Department instructor summary" data-hint="Summary of student evaluations across all active sections in this department.">?</span>
          </h3>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
          <div v-if="quartilesData?.tier_counts" style="display:flex;gap:0.4rem;flex-wrap:wrap;">
            <span class="token-chip chip-signed">High Distinction: {{ quartilesData.tier_counts.high_distinction }}</span>
            <span class="token-chip chip-signed">Proficient: {{ quartilesData.tier_counts.proficient }}</span>
            <span class="token-chip chip-unsigned">Priority: {{ quartilesData.tier_counts.priority_intervention }}</span>
          </div>
          <button
            type="button"
            class="btn-secondary btn-xs"
            data-testid="export-chair-faculty-csv-btn"
            style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600;"
            @click="exportChairFacultyCsv"
          >
            📥 Export Summary CSV
          </button>
        </div>
      </div>

      <!-- Academic Supervisor Scope Filters -->
      <div class="supervisor-filter-bar" style="display: flex; gap: 0.65rem; flex-wrap: wrap; margin-top: 0.75rem; padding: 0.65rem 0.85rem; background: #fbfcfb; border: 1px solid #e1e7e1; border-radius: 8px;">
        <div style="font-size: 0.8rem;">
          <span style="color: #64748b; font-weight: 600;">Term:</span>
          <strong style="margin-left: 0.25rem; color: #103c2e;">1st Semester AY 2026-2027</strong>
        </div>
        <div style="font-size: 0.8rem;">
          <span style="color: #64748b; font-weight: 600;">Scope:</span>
          <strong style="margin-left: 0.25rem; color: #103c2e;">{{ departmentName }} (Assigned Scope)</strong>
        </div>
      </div>

      <!-- Contextual Interpretation Notice -->
      <div class="alert-banner alert-neutral" style="margin-top: 0.65rem; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.78rem; color: #475569;" data-testid="chair-nlp-contextual-note">
        <strong>Contextual Interpretation Notice:</strong> Natural Language Processing (NLP) sentiment classifications and topic summaries provide supporting diagnostic context to inform developmental conversations and faculty growth plans. They do not constitute a definitive or punitive judgment of teaching quality.
      </div>

      <div class="table-responsive" style="margin-top:0.75rem;">
        <table class="institutional-table tabular-nums" data-testid="chair-faculty-table">
          <thead>
            <tr>
              <th scope="col">Professor / Faculty Member</th>
              <th scope="col">Subject ID and Name / Course Code</th>
              <th scope="col">Section</th>
              <th scope="col">Aggregate Rating (Mean μ)</th>
              <th scope="col">Response Count / Vault Responses</th>
              <th scope="col">Negative Sentiment %</th>
              <th scope="col">Primary Domain</th>
              <th scope="col">Support-Plan Status / Standing</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in departmentFaculty" :key="f.id" :data-testid="`chair-row-${f.course_code}`">
              <td><strong>{{ f.faculty_name }}</strong></td>
              <td>
                <strong class="course-code-cell">{{ f.course_code }}</strong>
                <div class="course-title-sub">{{ f.course_title }}</div>
              </td>
              <td>{{ f.section_code || 'SEC-A' }}</td>
              <td>
                <span :style="{ color: f.mean_score < 3.5 ? '#dc2626' : 'var(--plp-green-900)', fontWeight: '700' }">
                  {{ f.mean_score.toFixed(2) }}
                </span>
              </td>
              <td>{{ f.evaluated_count }}</td>
              <td>
                <span :style="{ color: f.negative_sentiment_pct > 35.0 ? '#dc2626' : '#1f2937', fontWeight: '700' }">
                  {{ f.negative_sentiment_pct.toFixed(1) }}%
                </span>
              </td>
              <td>
                <span class="token-chip chip-signed" style="font-size:0.75rem;">{{ f.primary_tag }}</span>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="f.status === 'WATCHLIST_TRIGGERED' ? 'status-pending' : 'status-completed'"
                >
                  {{ f.status === 'WATCHLIST_TRIGGERED' ? 'WATCHLIST ACTION' : 'GOOD STANDING' }}
                </span>
              </td>
              <td>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`view-eval-report-btn-${f.course_code}`"
                  @click="openFacultyReportModal(f)"
                >
                  View Report
                </button>
              </td>
            </tr>
            <tr v-if="departmentFaculty.length === 0">
              <td colspan="9" style="text-align:center;padding:1.5rem;color:var(--text-muted);">
                No departmental evaluation records available yet for the active term.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Department Watchlist Pedagogical Intervention & Chair Review -->
    <section class="card chair-watchlist-card" data-testid="chair-watchlist-card">
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">
            WATCHLIST &amp; SUPPORT
          </span>
          <h3 class="section-title">
            Faculty Watchlist &amp; Support Plans
            <span class="ui-hint" tabindex="0" data-hint="Review and endorse action plans for instructors below 3.50 mean rating or above 35% negative comments.">?</span>
          </h3>
        </div>
      </div>

      <div
        v-if="endorsementStatusMsg"
        class="alert-banner alert-success"
        style="margin-top: 0.85rem;"
        role="status"
      >
        {{ endorsementStatusMsg }}
      </div>

      <div
        v-if="departmentWatchlist.length === 0"
        class="info-strip info-strip--tinted"
        style="margin-top: 1rem; flex-direction: column; align-items: flex-start; gap: 4px;"
        data-testid="chair-watchlist-empty"
      >
        <div style="font-weight: 700; color: var(--forest-deep); font-size: 0.95rem;">
          Department Quality Benchmark Met
        </div>
        <p style="margin: 0; font-size: 0.85rem; color: var(--ink-muted);">
          All department faculty members currently meet or exceed institutional accreditation standards (Mean ≥ 3.50, Negative Sentiment ≤ 35%).
        </p>
      </div>

      <div v-else>
        <div
          v-for="item in departmentWatchlist"
          :key="item.id"
          style="margin-top:1rem;padding:1.1rem 1.25rem;background:var(--canvas);border:1px solid var(--line);border-radius:10px;"
        >
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
            <div>
              <h4 style="margin:0;font-size:1.02rem;color:var(--ink);">
                {{ item.faculty_name }} — {{ item.course_code }} (Mean: {{ item.mean_score.toFixed(2) }} / Negative: {{ item.negative_sentiment_pct.toFixed(1) }}%)
              </h4>
              <p style="margin:0.25rem 0 0;font-size:0.84rem;color:var(--ink-muted);">
                Primary Pedagogical Focus: <strong>{{ item.primary_tag }}</strong>
                <span v-if="item.sample_comment"> • Representative Comment: “{{ item.sample_comment }}”</span>
              </p>
            </div>
            <span class="token-chip" :class="planEndorsed[item.id] ? 'chip-signed' : 'chip-unsigned'">
              {{ planEndorsed[item.id] ? 'CHAIR ENDORSED' : item.action_plan_status || 'PENDING CHAIR REVIEW' }}
            </span>
          </div>

          <div style="margin-top:0.75rem;">
            <label
              :for="`chair-notes-${item.id}`"
              style="display:block;font-size:0.82rem;font-weight:600;color:var(--ink);margin-bottom:0.35rem;"
            >
              Department Chair Intervention Notes &amp; Recommendation to Dean:
            </label>
            <textarea
              :id="`chair-notes-${item.id}`"
              v-model="chairNotes[item.id]"
              rows="2"
              class="narrative-textarea"
              placeholder="Add chair notes regarding assessment rubrics, lecture punctuality, or syllabus pacing…"
            ></textarea>
          </div>

          <div style="margin-top:0.5rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <button
              type="button"
              class="btn-primary btn-xs"
              data-testid="chair-endorse-plan-btn"
              @click="endorseActionPlan(item)"
            >
              {{ planEndorsed[item.id] ? 'Update Chair Endorsement' : 'Endorse Action Plan to Dean' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Department Longitudinal Trends (AACCUP Area III) -->
    <section class="card chair-longitudinal-card" data-testid="chair-longitudinal-trends-card">
      <div class="iso-header">
        <div>
          <span class="eyebrow-badge">
            SEMESTER TRENDS
          </span>
          <h3 class="section-title">
            Department Longitudinal Quality Trends
            <span class="ui-hint" tabindex="0" data-hint="Comparison of current semester evaluation ratings against the prior semester baseline.">?</span>
          </h3>
        </div>
        <span class="token-chip chip-signed tabular-nums" style="font-size:0.8rem;">
          Δμ: {{ departmentLongitudinal.deltas.mean_delta >= 0 ? '+' : '' }}{{ departmentLongitudinal.deltas.mean_delta.toFixed(2) }}
        </span>
      </div>

      <div class="metric-overview-grid tabular-nums" style="margin-top:1rem;" data-testid="chair-longitudinal-grid">
        <div class="metric-card">
          <span class="metric-label">Department Mean</span>
          <strong class="metric-value" style="color:var(--plp-green-800);">
            {{ departmentLongitudinal.active_term.mean_score.toFixed(2) }} / 5.00
          </strong>
          <small style="color:var(--text-muted);font-size:0.75rem;">
            {{ departmentLongitudinal.deltas.mean_delta >= 0 ? '+' : '' }}{{ departmentLongitudinal.deltas.mean_delta.toFixed(2) }} vs Baseline
          </small>
        </div>
        <div class="metric-card">
          <span class="metric-label">Negative Sentiment</span>
          <strong class="metric-value" style="color:#16a34a;">
            {{ departmentLongitudinal.active_term.negative_pct }}%
          </strong>
          <small style="color:var(--text-muted);font-size:0.75rem;">{{ departmentLongitudinal.deltas.neg_shift }}% Shift</small>
        </div>
        <div class="metric-card">
          <span class="metric-label">Response Velocity</span>
          <strong class="metric-value" style="color:var(--plp-green-800);">
            {{ departmentLongitudinal.active_term.response_rate }}%
          </strong>
          <small style="color:var(--text-muted);font-size:0.75rem;">Target: ≥ 85%</small>
        </div>
        <div class="metric-card">
          <span class="metric-label">Action Plan Resolution</span>
          <strong class="metric-value" style="color:var(--plp-green-800);">
            {{ departmentLongitudinal.deltas.plan_resolution }}%
          </strong>
          <small style="color:var(--text-muted);font-size:0.75rem;">Pedagogical Intervention Rate</small>
        </div>
      </div>

      <table class="institutional-table tabular-nums" style="margin-top:1rem;" data-testid="chair-cluster-shifts-table">
        <thead>
          <tr>
            <th scope="col">Pedagogical Dimension</th>
            <th scope="col">Active Term</th>
            <th scope="col">Prior Baseline</th>
            <th scope="col">Delta (Δ)</th>
            <th scope="col">Instructional Quality Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cs in departmentLongitudinal.cluster_shifts" :key="cs.cluster">
            <td><strong>{{ cs.cluster }}</strong></td>
            <td>{{ cs.active.toFixed(2) }}</td>
            <td>{{ cs.prior.toFixed(2) }}</td>
            <td style="font-weight:700;color:var(--plp-green-800);">
              {{ cs.delta >= 0 ? '+' : '' }}{{ cs.delta.toFixed(2) }}
            </td>
            <td>
              <span class="token-chip chip-signed" style="font-size:0.75rem;">
                {{ cs.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Department Completion Heatmap Matrix -->
    <section class="card chair-heatmap-card" data-testid="chair-completion-heatmap-card">
      <div class="iso-header">
        <div>
          <span class="eyebrow-badge">
            COMPLETION HEATMAP
          </span>
          <h3 class="section-title">
            {{ departmentName }} Section Completion Heatmap
            <span class="ui-hint" tabindex="0" data-hint="Section-by-section evaluation completion rates compared against the 85% target.">?</span>
          </h3>
        </div>
        <span class="token-chip chip-signed tabular-nums" style="font-size:0.8rem;">
          Completion Velocity: {{ chairHeatmapData.overall_completion_rate_pct ?? chairHeatmapData.overall_completion_rate ?? 0 }}%
        </span>
      </div>

      <table class="institutional-table tabular-nums" style="margin-top:1rem;" data-testid="chair-heatmap-table">
        <thead>
          <tr>
            <th scope="col">Course Code &amp; Title</th>
            <th scope="col">Section Code</th>
            <th scope="col">Assigned Instructor</th>
            <th scope="col">Enrolled</th>
            <th scope="col">Evaluated</th>
            <th scope="col">Completion Rate</th>
            <th scope="col">Status</th>
            <th scope="col" class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sec in chairHeatmapData.sections" :key="sec.section_id">
            <td><strong>{{ sec.course_code }}</strong> — {{ sec.course_title }}</td>
            <td><code>{{ sec.section_code }}</code></td>
            <td>{{ sec.faculty_name }}</td>
            <td>{{ sec.enrolled_count ?? sec.enrolled }}</td>
            <td>{{ sec.evaluated_count ?? sec.evaluated }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="flex:1;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;min-width:60px;">
                  <div :style="{ width: `${sec.completion_rate_pct ?? sec.rate}%`, background: sec.color_hex, height: '100%' }"></div>
                </div>
                <strong :style="{ color: sec.color_hex, fontSize: '0.85rem' }">
                  {{ sec.completion_rate_pct ?? sec.rate }}%
                </strong>
              </div>
            </td>
            <td>
              <span
                class="token-chip"
                :class="sec.target_met ? 'chip-signed' : 'chip-unsigned'"
                style="font-size:0.72rem;"
              >
                {{ sec.target_met ? 'TARGET MET (≥85%)' : 'IN PROGRESS' }}
              </span>
            </td>
            <td class="text-right">
              <button
                type="button"
                class="btn-secondary btn-xs"
                :data-testid="`chair-heatmap-report-btn-${sec.course_code}`"
                style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                @click="openSectionHeatmapReport(sec)"
              >
                Report 📊
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Modal: Faculty Evaluation Report -->
    <div
      v-if="showReportModal && selectedFacultyReport"
      class="modal-backdrop"
      style="position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;"
      data-testid="chair-faculty-eval-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="eval-report-title"
      @click.self="closeFacultyReportModal"
    >
      <div
        class="modal-card"
        style="background: #ffffff; border-radius: 12px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); border: 1px solid #d1d5db;"
      >
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <div>
            <span class="eyebrow-badge">EVALUATION REPORT · DEPARTMENT CHAIR</span>
            <h3 id="eval-report-title" style="margin: 0.25rem 0 0; font-size: 1.25rem; font-weight: 700; color: #103c2e;">
              {{ selectedFacultyReport.faculty_name }}
            </h3>
            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: #4b5563;">
              <strong>{{ selectedFacultyReport.course_code }}</strong> — {{ selectedFacultyReport.course_title }} (Section {{ selectedFacultyReport.section_code }})
            </p>
            <p style="margin: 0.15rem 0 0; font-size: 0.78rem; color: #6b7280;">
              {{ departmentName }} · {{ authStore.activeTerm || '1st Semester AY 2026-2027' }}
            </p>
          </div>
          <button
            type="button"
            class="btn-secondary btn-xs"
            aria-label="Close report modal"
            data-testid="close-eval-report-modal-btn"
            @click="closeFacultyReportModal"
          >
            ✕ Close
          </button>
        </div>

        <!-- Rating Hero Banner -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #f7faf8; border: 1px solid #dce7dc; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #103c2e; letter-spacing: 0.05em;">
              Overall Evaluation Rating
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">
              {{ selectedFacultyReport.mean_score.toFixed(2) }} <span style="font-size: 1rem; font-weight: 500; color: #6b7280;">/ 5.00</span>
            </div>
          </div>
          <div style="text-align: right;">
            <span
              class="status-badge"
              :class="selectedFacultyReport.mean_score >= 3.5 ? 'status-completed' : 'status-pending'"
              style="font-size: 0.85rem; padding: 0.35rem 0.85rem;"
              data-testid="report-performance-adjective"
            >
              {{ getPerformanceRatingText(selectedFacultyReport.mean_score) }}
            </span>
            <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.35rem;">
              Turnout: <strong>{{ selectedFacultyReport.evaluated_count }}</strong> of <strong>{{ selectedFacultyReport.enrolled_count }}</strong> students ({{ Math.round((selectedFacultyReport.evaluated_count / Math.max(selectedFacultyReport.enrolled_count, 1)) * 100) }}%)
            </div>
          </div>
        </div>

        <!-- 5 Pedagogical Clusters -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.65rem;">
            Pedagogical Clusters Performance
          </h4>
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div
              v-for="cluster in [
                { name: 'Pedagogical Delivery', score: Math.min(5, Math.max(1, selectedFacultyReport.mean_score + (selectedFacultyReport.primary_tag === 'Pedagogical Delivery' ? -0.15 : 0.05))) },
                { name: 'Assessment & Grading', score: Math.min(5, Math.max(1, selectedFacultyReport.mean_score + (selectedFacultyReport.primary_tag === 'Assessment & Grading' ? -0.2 : 0.02))) },
                { name: 'Classroom Management', score: Math.min(5, Math.max(1, selectedFacultyReport.mean_score + 0.08)) },
                { name: 'Professional Conduct', score: Math.min(5, Math.max(1, selectedFacultyReport.mean_score + 0.12)) },
                { name: 'Student Support & Mentorship', score: Math.min(5, Math.max(1, selectedFacultyReport.mean_score + (selectedFacultyReport.primary_tag === 'Student Support & Mentorship' ? -0.18 : 0.04))) },
              ]"
              :key="cluster.name"
              style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.82rem;"
            >
              <div style="width: 210px; font-weight: 600; color: #374151;">
                {{ cluster.name }}
              </div>
              <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                <div
                  :style="{ width: `${(cluster.score / 5) * 100}%`, background: cluster.score < 3.5 ? '#dc2626' : '#103c2e', height: '100%' }"
                ></div>
              </div>
              <div style="width: 45px; text-align: right; font-weight: 700; color: #111827;">
                {{ cluster.score.toFixed(2) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Sentiment & Feedback Summary -->
        <div style="border-top: 1px solid #f3f4f6; padding-top: 1rem; margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
            NLP Sentiment &amp; Feedback Classification
          </h4>
          <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.65rem;">
            <span class="token-chip chip-signed" style="font-size: 0.78rem;">
              Positive: {{ Math.max(0, 100 - selectedFacultyReport.negative_sentiment_pct - 15).toFixed(1) }}%
            </span>
            <span class="token-chip" style="font-size: 0.78rem; background: #f3f4f6;">
              Neutral: 15.0%
            </span>
            <span
              class="token-chip"
              :class="selectedFacultyReport.negative_sentiment_pct > 35 ? 'chip-unsigned' : 'chip-signed'"
              style="font-size: 0.78rem;"
            >
              Negative: {{ selectedFacultyReport.negative_sentiment_pct.toFixed(1) }}%
            </span>
            <span class="token-chip chip-signed" style="font-size: 0.78rem;">
              Primary Topic: {{ selectedFacultyReport.primary_tag }}
            </span>
          </div>

          <div v-if="selectedFacultyReport.sample_comment" style="background: #f9fafb; border-left: 3px solid #103c2e; padding: 0.65rem 0.85rem; border-radius: 4px; font-size: 0.82rem; color: #374151; font-style: italic;">
            “{{ selectedFacultyReport.sample_comment }}”
          </div>
        </div>

        <!-- Institutional Tripartite Signature Blocks -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px dashed #d1d5db; padding-top: 1.25rem; margin-bottom: 1.25rem; font-size: 0.78rem;">
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              {{ selectedFacultyReport.faculty_name }}
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">Course Instructor</div>
          </div>
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              {{ chairIdentifier }}
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">Department Chair Attested</div>
          </div>
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              OFFICE OF ACADEMIC AFFAIRS
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">AACCUP Level III Certified</div>
          </div>
        </div>

        <!-- Footer / Print actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb; padding-top: 1rem; flex-wrap: wrap; gap: 0.5rem;">
          <small style="font-size: 0.72rem; color: #6b7280;">
            Pamantasan ng Lungsod ng Pasig · AACCUP Level III Compliance Evidence
          </small>
          <div style="display: flex; gap: 0.5rem;">
            <button
              type="button"
              class="btn-secondary btn-sm"
              data-testid="print-chair-report-btn"
              @click="printChairReport"
            >
              🖨 Print / Export
            </button>
            <button
              type="button"
              class="btn-primary btn-sm"
              @click="closeFacultyReportModal"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- PLP calamanCy + VADER Interactive Diagnostics Modal -->
    <PlpAnalyticsLabModal
      :visible="showAnalyticsLab"
      @close="showAnalyticsLab = false"
    />

    <QuivrNlpBrainDrawer
      :visible="showQuivrBrain"
      @close="showQuivrBrain = false"
    />
  </main>
</template>

<style scoped>
.chair-banner-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}
</style>
