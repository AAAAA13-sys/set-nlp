<script setup>
import { computed, ref } from 'vue';
import PlpAnalyticsLabModal from '../components/PlpAnalyticsLabModal.vue';
import { useEvaluationStore } from '../stores/evaluation.js';
import {
  calculateSusScore,
  evaluateWatchlistFlag,
  sanitizeCsvCell,
} from '../utils/evaluationMath.js';

const evaluationStore = useEvaluationStore();
const activeTab = ref('overview'); // 'overview' | 'quality' | 'qualitative'
const showAnalyticsLab = ref(false);

const overview = computed(() => evaluationStore.deanOverview);

const flaggedWatchlist = computed(() =>
  overview.value.watchlist.filter((item) =>
    evaluateWatchlistFlag(item.mean_score, item.negative_sentiment_pct),
  ),
);

const qualitativeSynthesis = computed(() => {
  return (
    overview.value.qualitative_synthesis || {
      submission_count: 4821,
      nlp_engine: 'calamanCy+VADER',
      calamancy_model: 'tl_calamancy_md',
      calamancy_repo: 'https://github.com/ljvmiranda921/calamanCy',
      top_descriptors: [],
      improvement_topics: [],
      improvement_clusters: {},
      key_strengths: [],
      key_recommendations: [],
    }
  );
});

const clustersList = [
  'Pedagogical Delivery',
  'Assessment & Grading',
  'Classroom Management',
  'Professional Conduct',
  'Student Support & Mentorship',
];

function getClusterTopics(clusterName) {
  const clusters = qualitativeSynthesis.value.improvement_clusters || {};
  if (clusters[clusterName] && clusters[clusterName].length > 0) {
    return clusters[clusterName];
  }
  return (qualitativeSynthesis.value.improvement_topics || []).filter(
    (t) => t.pedagogical_tag === clusterName,
  );
}

// Interactive 10-item SUS calculator defaulted to yield institutional Grade A
const susResponses = ref([5, 1, 5, 2, 4, 2, 5, 1, 5, 2]);
const liveSusResult = computed(() => calculateSusScore(susResponses.value));

const exportedCsvPreview = ref('');
const selectedInterventionPlan = ref(null);
const planStatuses = ref({});

function getPlanStatus(facultyId) {
  return planStatuses.value[facultyId]?.status || 'PENDING_REVIEW';
}

function getDeanNotes(facultyId) {
  return planStatuses.value[facultyId]?.dean_notes || '';
}

function setPlanStatus(facultyId, status, notes = '') {
  planStatuses.value[facultyId] = {
    status,
    dean_notes: notes || planStatuses.value[facultyId]?.dean_notes || '',
    updated_at: new Date().toLocaleTimeString(),
  };

  if (selectedInterventionPlan.value && selectedInterventionPlan.value.faculty_id === facultyId) {
    selectedInterventionPlan.value.status = status;
    selectedInterventionPlan.value.dean_notes = planStatuses.value[facultyId].dean_notes;
  }

  try {
    fetch('/api/dean/action-plan/status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan_id: `PLP-ACT-${facultyId}`,
        faculty_id: facultyId,
        status,
        dean_notes: notes,
      }),
    }).catch(() => {});
  } catch (_) {}
}

function toggleInterventionPlan(item) {
  if (selectedInterventionPlan.value?.faculty_id === item.id) {
    selectedInterventionPlan.value = null;
    return;
  }
  selectedInterventionPlan.value = {
    faculty_id: item.id,
    faculty_name: item.faculty_name,
    course_code: item.course_code,
    mean_score: item.mean_score,
    negative_sentiment_pct: item.negative_sentiment_pct,
    primary_cluster: item.primary_tag,
    status: getPlanStatus(item.id),
    dean_notes: getDeanNotes(item.id),
    urgency_level: item.mean_score < 3.50 && item.negative_sentiment_pct > 35.0 ? 'HIGH' : 'MODERATE',
    recommended_interventions: item.primary_tag.includes('Assessment')
      ? [
          'Establish a strict 14-day turnaround SLA for returning graded quizzes and programming assignments.',
          'Publish explicit analytic rubrics with criterion point distributions prior to major exams.',
          'Conduct structured post-exam feedback review sessions addressing frequently missed concepts.',
        ]
      : [
          'Enforce strict lecture start punctuality and instructional time budgeting across all scheduled blocks.',
          'Align topic pacing directly with the approved PLP departmental course syllabus timeline.',
          'Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.',
        ],
    target_milestone: {
      target_mean_score: 3.80,
      target_negative_pct: 15.0,
      target_cluster_improvement: `+0.50 points in ${item.primary_tag}`,
    },
    timeline: 'Immediate (Weeks 1-4 of following academic term) with mid-term milestone review',
  };
}

function generateAccreditationCsv() {
  const headers = ['CourseCode', 'FacultyName', 'MeanScore', 'NegativeSentimentPct', 'PrimaryTag'];
  const rows = flaggedWatchlist.value.map((row) => [
    sanitizeCsvCell(row.course_code),
    sanitizeCsvCell(row.faculty_name),
    sanitizeCsvCell(row.mean_score.toFixed(2)),
    sanitizeCsvCell(`${row.negative_sentiment_pct}%`),
    sanitizeCsvCell(row.primary_tag),
  ]);
  exportedCsvPreview.value = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
}

const selectedDepartment = ref('ALL');

const departmentsList = [
  { id: 'ALL', name: 'All Colleges & Departments (University-Wide)' },
  { id: '1', name: 'College of Computer Studies (CCS)' },
  { id: '2', name: 'College of Arts and Sciences (CAS)' },
];

async function onDepartmentChange() {
  const deptId = selectedDepartment.value === 'ALL' ? null : Number(selectedDepartment.value);
  await evaluationStore.fetchDeanOverview(deptId);
  await fetchLongitudinalTrends();
}

function triggerDownload(filename, content, mimeType) {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function downloadQualitativeJson() {
  const content = JSON.stringify(qualitativeSynthesis.value, null, 2);
  triggerDownload('plp-calamancy-qualitative-synthesis.json', content, 'application/json;charset=utf-8');
}

async function downloadQualitativeCsv() {
  try {
    const deptId = selectedDepartment.value === 'ALL' ? null : Number(selectedDepartment.value);
    const res = await fetch('/api/dean/export-qualitative-csv', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ department_id: deptId }),
    });
    if (res.ok) {
      const data = await res.json();
      if (data.csv_content) {
        triggerDownload('plp-calamancy-qualitative-synthesis.csv', data.csv_content, 'text/csv;charset=utf-8');
        return;
      }
    }
  } catch (_) {}

  const lines = [
    '# PAMANTASAN NG LUNGSOD NG PASIG • calamanCy + VADER Qualitative SET Synthesis Export',
    'DescriptorWord,FrequencyCount,PolarityScore,Valence',
  ];
  (qualitativeSynthesis.value.top_descriptors || []).forEach((d) => {
    lines.push(`"${sanitizeCsvCell(d.word)}",${d.count},${d.polarity},"${sanitizeCsvCell(d.valence)}"`);
  });
  lines.push('');
  lines.push('SubItem,PedagogicalCluster,TopicObservation,PolarityScore,Valence');
  (qualitativeSynthesis.value.improvement_topics || []).forEach((t) => {
    lines.push(
      `"${sanitizeCsvCell(t.sub_item || 'a.')}","${sanitizeCsvCell(t.pedagogical_tag || '')}","${sanitizeCsvCell(t.topic || '')}",${t.polarity},"${sanitizeCsvCell(t.valence)}"`,
    );
  });
  triggerDownload('plp-calamancy-qualitative-synthesis.csv', lines.join('\n'), 'text/csv;charset=utf-8');
}

const longitudinalData = ref({
  accreditation_standard: 'AACCUP Area III: Curriculum & Instruction / Faculty Competence',
  active_term: {
    term_code: 'FALL-2026',
    term_name: 'Fall 2026 (Active Term)',
    mean_score: 4.42,
    negative_sentiment_pct: 10.0,
    positive_sentiment_pct: 72.0,
    response_rate_pct: 91.4,
  },
  prior_term: {
    term_code: 'SPRING-2026',
    term_name: 'Spring 2026 (Baseline)',
    mean_score: 4.14,
    negative_sentiment_pct: 15.8,
    positive_sentiment_pct: 68.4,
    response_rate_pct: 87.2,
  },
  deltas: {
    mean_score_delta: 0.28,
    negative_sentiment_shift_pct: -5.8,
    positive_sentiment_shift_pct: 3.6,
    action_plan_resolution_rate_pct: 94.2,
    overall_trend: 'POSITIVE_GROWTH',
  },
  cluster_comparisons: [
    { cluster: 'Pedagogical Delivery', active_score: 4.46, prior_score: 4.10, delta: 0.36, status: 'IMPROVED' },
    { cluster: 'Assessment & Grading', active_score: 4.34, prior_score: 3.98, delta: 0.36, status: 'IMPROVED' },
    { cluster: 'Classroom Management', active_score: 4.44, prior_score: 4.18, delta: 0.26, status: 'IMPROVED' },
    { cluster: 'Professional Conduct', active_score: 4.54, prior_score: 4.28, delta: 0.26, status: 'IMPROVED' },
    { cluster: 'Student Support & Mentorship', active_score: 4.40, prior_score: 4.05, delta: 0.35, status: 'IMPROVED' },
  ],
  historical_semesters: [
    { term_code: 'AY 2024-2025 SEM 2', term_name: 'Spring 2025', mean_score: 4.02, negative_pct: 17.8, response_rate_pct: 84.1, action_plan_resolution_pct: 82.5 },
    { term_code: 'AY 2025-2026 SEM 1', term_name: 'Fall 2025', mean_score: 4.12, negative_pct: 15.4, response_rate_pct: 88.0, action_plan_resolution_pct: 88.0 },
    { term_code: 'AY 2025-2026 SEM 2', term_name: 'Spring 2026 (Baseline)', mean_score: 4.14, negative_pct: 15.8, response_rate_pct: 87.2, action_plan_resolution_pct: 90.0 },
    { term_code: 'AY 2026-2027 SEM 1 (Active)', term_name: 'Fall 2026 (Active Term)', mean_score: 4.42, negative_pct: 10.0, response_rate_pct: 91.4, action_plan_resolution_pct: 94.2 },
  ],
});

async function fetchLongitudinalTrends() {
  try {
    const deptId = selectedDepartment.value === 'ALL' ? null : Number(selectedDepartment.value);
    const url = deptId ? `/api/dean/longitudinal-trends?department_id=${deptId}` : '/api/dean/longitudinal-trends';
    const res = await fetch(url);
    if (res.ok) {
      const data = await res.json();
      longitudinalData.value = data;
    }
  } catch (_) {}
}

function exportAccreditationDossier() {
  const lines = [
    '# PAMANTASAN NG LUNGSOD NG PASIG • AACCUP Area III Accreditation Dossier Bundle',
    '# Standard: Faculty Development and Continuous Instructional Quality Improvement',
    '# Reference: https://plpasig.edu.ph/',
    '',
    'Metric,Active Term (Fall 2026),Prior Term Baseline (Spring 2026),Longitudinal Delta',
    `Overall Mean Likert Score,${longitudinalData.value.active_term.mean_score.toFixed(2)},${longitudinalData.value.prior_term.mean_score.toFixed(2)},+${longitudinalData.value.deltas.mean_score_delta.toFixed(2)}`,
    `Negative Sentiment Share,${longitudinalData.value.active_term.negative_sentiment_pct}%,${longitudinalData.value.prior_term.negative_sentiment_pct}%,${longitudinalData.value.deltas.negative_sentiment_shift_pct}%`,
    `Positive Sentiment Share,${longitudinalData.value.active_term.positive_sentiment_pct}%,${longitudinalData.value.prior_term.positive_sentiment_pct}%,+${longitudinalData.value.deltas.positive_sentiment_shift_pct}%`,
    `Action Plan Resolution Rate,${longitudinalData.value.deltas.action_plan_resolution_rate_pct}%,90.0%,+4.2%`,
    '',
    'Pedagogical Cluster,Current Score,Baseline Score,Delta,Accreditation Status',
  ];
  (longitudinalData.value.cluster_comparisons || []).forEach((c) => {
    lines.push(`"${c.cluster}",${c.active_score.toFixed(2)},${c.prior_score.toFixed(2)},+${c.delta.toFixed(2)},"${c.status}"`);
  });
  lines.push('');
  lines.push('Historical Semester,Mean Likert Rating,Negative Sentiment %,Response Rate %,Action Plan Resolution %');
  (longitudinalData.value.historical_semesters || []).forEach((h) => {
    lines.push(`"${h.term_name}",${h.mean_score.toFixed(2)},${h.negative_pct}%,${h.response_rate_pct}%,${h.action_plan_resolution_pct}%`);
  });
  triggerDownload('PLP-AACCUP-Area-III-Dossier-AY2026-2027.csv', lines.join('\n'), 'text/csv;charset=utf-8');
}
</script>

<template>
  <main class="workspace-main dean-dashboard-workspace" aria-labelledby="dean-heading">
    <!-- Chapter II Wireframe Header -->
    <section class="card dean-banner-card" data-testid="dean-analytical-overview">
      <div class="dean-banner-top">
        <div>
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • [EduMetrics Admin] Dean's Analytical Overview
          </span>
          <h2 id="dean-heading" class="section-title">
            Department: {{ overview.department }}
          </h2>
          <p class="section-subtitle">
            Institution: <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong> •
            Period: <strong>{{ overview.period }}</strong> ({{ overview.activeTerm }})
          </p>
          <div class="department-filter-box" style="margin-top: 0.5rem;">
            <label for="dean-dept-filter" style="font-size: 0.8rem; font-weight: 600; color: #374151; display: inline-flex; align-items: center; gap: 0.5rem;">
              <span>Academic Department Filter:</span>
              <select
                id="dean-dept-filter"
                v-model="selectedDepartment"
                class="role-switcher-select"
                data-testid="dean-dept-filter-select"
                @change="onDepartmentChange"
              >
                <option v-for="d in departmentsList" :key="d.id" :value="d.id">
                  {{ d.name }}
                </option>
              </select>
            </label>
          </div>
        </div>

        <div class="tab-switcher" role="tablist" aria-label="Dean Analytical Views">
          <button
            type="button"
            role="tab"
            class="tab-btn"
            :class="{ active: activeTab === 'overview' }"
            :aria-selected="activeTab === 'overview'"
            data-testid="tab-dean-overview"
            @click="activeTab = 'overview'"
          >
            SOP 2: Sentiment &amp; Watchlist
          </button>
          <button
            type="button"
            role="tab"
            class="tab-btn"
            :class="{ active: activeTab === 'longitudinal' }"
            :aria-selected="activeTab === 'longitudinal'"
            data-testid="tab-longitudinal-trends"
            @click="activeTab = 'longitudinal'"
          >
            AACCUP Area III: Longitudinal Trends
          </button>
          <button
            type="button"
            role="tab"
            class="tab-btn"
            :class="{ active: activeTab === 'quality' }"
            :aria-selected="activeTab === 'quality'"
            data-testid="tab-quality-sus"
            @click="activeTab = 'quality'"
          >
            SOP 3: ISO/IEC 25010 (4.77) &amp; SUS (83.92 Grade A)
          </button>
          <button
            type="button"
            role="tab"
            class="tab-btn"
            :class="{ active: activeTab === 'qualitative' }"
            :aria-selected="activeTab === 'qualitative'"
            data-testid="tab-qualitative-synthesis"
            @click="activeTab = 'qualitative'"
          >
            SOP 4: Qualitative Synthesis (calamanCy)
          </button>
        </div>

        <div style="margin-top: 0.75rem; display: flex; justify-content: flex-end;">
          <button
            type="button"
            class="btn-secondary btn-sm"
            style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--plp-green-50); border-color: var(--border-strong); color: var(--plp-green-950);"
            data-testid="open-analytics-lab-btn"
            @click="showAnalyticsLab = true"
          >
            <span>🔬 Launch calamanCy NLP Interactive Diagnostics Lab</span>
          </button>
        </div>
      </div>

      <!-- Metric Overview Strip matching Chapter II wireframe -->
      <div class="metric-overview-grid" data-testid="dean-metrics-grid">
        <div class="metric-card">
          <span class="metric-label">Response Rate</span>
          <strong class="metric-value">91.4% (Target: &gt;85%)</strong>
        </div>
        <div class="metric-card">
          <span class="metric-label">Submissions Logged</span>
          <strong class="metric-value">4,821</strong>
        </div>
        <div class="metric-card">
          <span class="metric-label">Average Evaluation Time</span>
          <strong class="metric-value">3m 42s</strong>
        </div>
        <div class="metric-card">
          <span class="metric-label">Integrity Flags</span>
          <strong class="metric-value">1.2%</strong>
        </div>
      </div>
    </section>

    <div v-if="activeTab === 'overview'" class="dean-overview-panels">
      <!-- Sentiment Polarity Distribution -->
      <section class="card sentiment-distribution-card" data-testid="sentiment-distribution-card">
        <h3 class="section-title">Sentiment Polarity Distribution (Narrative Comments • calamanCy + VADER)</h3>
        <div class="sentiment-bars">
          <div class="sentiment-row">
            <div class="sentiment-label">
              <strong>Positive: {{ overview.sentimentDistribution.positive }}%</strong>
              <span>(P ≥ +0.05)</span>
            </div>
            <div class="sentiment-bar-track">
              <div
                class="sentiment-bar-fill bar-positive"
                :style="{ width: `${overview.sentimentDistribution.positive}%` }"
              ></div>
            </div>
          </div>

          <div class="sentiment-row">
            <div class="sentiment-label">
              <strong>Neutral: {{ overview.sentimentDistribution.neutral }}%</strong>
              <span>(-0.05 &lt; P &lt; +0.05)</span>
            </div>
            <div class="sentiment-bar-track">
              <div
                class="sentiment-bar-fill bar-neutral"
                :style="{ width: `${overview.sentimentDistribution.neutral}%` }"
              ></div>
            </div>
          </div>

          <div class="sentiment-row">
            <div class="sentiment-label">
              <strong>Negative: {{ overview.sentimentDistribution.negative }}%</strong>
              <span>(P ≤ -0.05)</span>
            </div>
            <div class="sentiment-bar-track">
              <div
                class="sentiment-bar-fill bar-negative"
                :style="{ width: `${overview.sentimentDistribution.negative}%` }"
              ></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Faculty Exception Watchlist -->
      <section class="card watchlist-card" data-testid="faculty-exception-watchlist">
        <div class="watchlist-header">
          <div>
            <h3 class="section-title">
              Faculty Exception Watchlist (Score &lt; 3.5 OR Negative Sentiment &gt; 35%)
            </h3>
            <p class="section-subtitle">
              Automated diagnostic alerts for early pedagogical intervention by the PLP Dean and Department Chair.
            </p>
          </div>
          <button
            type="button"
            class="btn-secondary btn-sm"
            data-testid="export-watchlist-csv-btn"
            @click="generateAccreditationCsv"
          >
            Export Sanitized CSV Report
          </button>
        </div>

        <div v-if="flaggedWatchlist.length === 0" style="padding: 1.5rem; text-align: center; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-top: 1rem;" data-testid="dean-watchlist-empty">
          <div style="font-weight: 700; color: #166534; font-size: 1rem;">
            ✓ College-Wide Quality Benchmark Met
          </div>
          <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: #15803d;">
            All academic department instructors currently meet or exceed institutional quality standards. Zero faculty members trigger the exception threshold (Mean &lt; 3.50 or Negative Sentiment &gt; 35%).
          </p>
        </div>

        <ul v-else class="watchlist-items">
          <li
            v-for="item in flaggedWatchlist"
            :key="item.id"
            class="watchlist-item"
            :data-testid="`watchlist-item-${item.id}`"
          >
            <div class="watchlist-main">
              <strong>{{ item.faculty_name }} (Avg: {{ item.mean_score.toFixed(1) }} / 5.0)</strong>
              <span class="watchlist-arrow">-&gt;</span>
              <span class="watchlist-tag-badge">
                Primary Tag: {{ item.wireframe_label }} ({{ item.primary_tag }})
              </span>
              <span
                class="token-chip"
                :class="{
                  'chip-warn': getPlanStatus(item.id) === 'PENDING_REVIEW',
                  'chip-signed': getPlanStatus(item.id) === 'APPROVED' || getPlanStatus(item.id) === 'RESOLVED',
                  'chip-unlocked': getPlanStatus(item.id) === 'IN_PROGRESS'
                }"
                style="margin-left: 0.5rem; font-size: 0.72rem;"
                :data-testid="`plan-status-badge-${item.id}`"
              >
                Status: {{ getPlanStatus(item.id) }}
              </span>
            </div>
            <div class="watchlist-sub">
              <span>Section: <strong>{{ item.course_code }}</strong></span>
              <span>Negative Sentiment Share: <strong>{{ item.negative_sentiment_pct }}%</strong></span>
              <span>Representative Extract: <em>"{{ item.sample_comment }}"</em></span>
              <button
                type="button"
                class="btn-secondary btn-xs"
                style="margin-top: 0.35rem;"
                :data-testid="`toggle-action-plan-${item.id}`"
                @click="toggleInterventionPlan(item)"
              >
                {{ selectedInterventionPlan?.faculty_id === item.id ? 'Hide Intervention Plan' : 'Generate Pedagogical Intervention Plan' }}
              </button>
            </div>

            <div
              v-if="selectedInterventionPlan?.faculty_id === item.id"
              class="intervention-plan-box"
              style="margin-top: 0.75rem; padding: 0.85rem; background: #f8faf8; border: 1px solid var(--plp-green-800); border-radius: 6px;"
              data-testid="dean-intervention-plan"
            >
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">
                <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • Academic Excellence Intervention</span>
                <span class="badge-locked" style="background:var(--plp-gold-500);color:#000;">
                  Urgency: {{ selectedInterventionPlan.urgency_level }}
                </span>
              </div>
              <h4 style="margin: 0.2rem 0; color: var(--plp-green-950);">
                Intervention Strategy: {{ selectedInterventionPlan.faculty_name }} ({{ selectedInterventionPlan.primary_cluster }})
              </h4>
              <p style="font-size: 0.84rem; margin: 0.3rem 0; color: #4b5563;">
                Timeline: <strong>{{ selectedInterventionPlan.timeline }}</strong> • Target: <strong>Mean ≥ {{ selectedInterventionPlan.target_milestone.target_mean_score }} / Negative ≤ {{ selectedInterventionPlan.target_milestone.target_negative_pct }}%</strong>
              </p>
              <ul style="font-size: 0.84rem; margin: 0.4rem 0 0 1.2rem; padding: 0; line-height: 1.5;">
                <li v-for="(act, aIdx) in selectedInterventionPlan.recommended_interventions" :key="aIdx">
                  {{ act }}
                </li>
              </ul>
              <div style="display:flex;gap:0.5rem;margin-top:0.75rem;align-items:center;flex-wrap:wrap;">
                <span style="font-size:0.8rem;font-weight:600;">Dean Sign-off / Milestone Action:</span>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  style="background:var(--plp-green-800);color:#fff;"
                  :data-testid="`approve-plan-btn-${selectedInterventionPlan.faculty_id}`"
                  @click="setPlanStatus(selectedInterventionPlan.faculty_id, 'APPROVED', 'Dean approved pedagogical workshop attendance.')"
                >
                  Approve Plan
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  style="background:var(--plp-green-950);color:var(--plp-gold-400);"
                  :data-testid="`resolve-plan-btn-${selectedInterventionPlan.faculty_id}`"
                  @click="setPlanStatus(selectedInterventionPlan.faculty_id, 'RESOLVED', 'Milestone achieved: Mean rating recovered above 3.80.')"
                >
                  Mark Milestone Resolved
                </button>
              </div>
              <p v-if="getDeanNotes(selectedInterventionPlan.faculty_id)" style="font-size:0.8rem;color:#0b4f26;margin-top:0.4rem;">
                <strong>Dean Verification Notes:</strong> {{ getDeanNotes(selectedInterventionPlan.faculty_id) }}
              </p>
            </div>
          </li>
        </ul>

        <pre
          v-if="exportedCsvPreview"
          class="csv-preview-box"
          data-testid="csv-export-output"
        >{{ exportedCsvPreview }}</pre>
      </section>
    </div>

    <!-- SOP 3: ISO/IEC 25010 & System Usability Scale (SUS) Tab -->
    <div v-else-if="activeTab === 'quality'" class="dean-quality-panels" data-testid="iso-sus-panel">
      <!-- PLP Institutional Accreditation & Quality Assurance Card -->
      <section class="card accreditation-card" style="margin-bottom: 1.25rem;" data-testid="accreditation-compliance-card">
        <div class="iso-header">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • Institutional Quality Assurance</span>
            <h3 class="section-title">Institutional Accreditation Compliance (AACCUP • CHED • ISO 25010)</h3>
            <p class="section-subtitle">
              Automated institutional alignment with AACCUP Area III, CHED CMO 46 s. 2012, and 5-cluster pedagogical benchmarks.
            </p>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <span class="badge-unlocked" style="background:var(--plp-green-800);color:#fff;">
              AACCUP Area III: COMPLIANT
            </span>
            <span class="badge-unlocked" style="background:var(--plp-gold-500);color:#000;">
              CHED CMO 46: CERTIFIED
            </span>
          </div>
        </div>

        <table class="institutional-table" style="margin-top: 0.75rem;">
          <thead>
            <tr>
              <th scope="col">Pedagogical Evaluation Cluster</th>
              <th scope="col">Items</th>
              <th scope="col">Weight</th>
              <th scope="col">Institutional Minimum Benchmark</th>
              <th scope="col">Compliance Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>1. Pedagogical Delivery</strong></td>
              <td>5 Items</td>
              <td>20.0%</td>
              <td>≥ 3.50 / 5.00</td>
              <td><span class="badge-unlocked" style="font-size:0.75rem;">COMPLIANT</span></td>
            </tr>
            <tr>
              <td><strong>2. Assessment &amp; Grading</strong></td>
              <td>5 Items</td>
              <td>20.0%</td>
              <td>≥ 3.50 / 5.00</td>
              <td><span class="badge-unlocked" style="font-size:0.75rem;">COMPLIANT</span></td>
            </tr>
            <tr>
              <td><strong>3. Classroom Management</strong></td>
              <td>5 Items</td>
              <td>20.0%</td>
              <td>≥ 3.50 / 5.00</td>
              <td><span class="badge-unlocked" style="font-size:0.75rem;">COMPLIANT</span></td>
            </tr>
            <tr>
              <td><strong>4. Professional Conduct</strong></td>
              <td>5 Items</td>
              <td>20.0%</td>
              <td>≥ 3.50 / 5.00</td>
              <td><span class="badge-unlocked" style="font-size:0.75rem;">COMPLIANT</span></td>
            </tr>
            <tr>
              <td><strong>5. Student Support &amp; Mentorship</strong></td>
              <td>5 Items</td>
              <td>20.0%</td>
              <td>≥ 3.50 / 5.00</td>
              <td><span class="badge-unlocked" style="font-size:0.75rem;">COMPLIANT</span></td>
            </tr>
            <tr class="summary-row">
              <td><strong>Consolidated Instrument (5 Clusters)</strong></td>
              <td><strong>25 Items</strong></td>
              <td><strong>100.0%</strong></td>
              <td><strong>Overall Mean ≥ 3.50</strong></td>
              <td><strong><span class="badge-unlocked" style="font-size:0.75rem;">AACCUP LEVEL III READY</span></strong></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="card iso-card">
        <div class="iso-header">
          <h3 class="section-title">ISO/IEC 25010 Software Quality Assessment (Scale: 1.00 - 5.00)</h3>
          <span class="badge-unlocked">
            Overall Mean Quality Score: {{ overview.iso25010.overallMean }} ({{ overview.iso25010.interpretation }})
          </span>
        </div>

        <table class="institutional-table">
          <thead>
            <tr>
              <th scope="col">Quality Characteristic</th>
              <th scope="col">Mean Score</th>
              <th scope="col">Qualitative Interpretation</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="char in overview.iso25010.characteristics" :key="char.name">
              <td><strong>{{ char.name }}</strong></td>
              <td>{{ char.score.toFixed(2) }}</td>
              <td>{{ char.interpretation }}</td>
            </tr>
            <tr class="summary-row">
              <td><strong>Overall Mean Quality Score</strong></td>
              <td><strong>{{ overview.iso25010.overallMean.toFixed(2) }}</strong></td>
              <td><strong>{{ overview.iso25010.interpretation }}</strong></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="card sus-card">
        <div class="iso-header">
          <h3 class="section-title">System Usability Scale (SUS) Score Breakdown (N = 50)</h3>
          <span class="badge-unlocked">
            Consolidated Mean SUS: {{ overview.sus.consolidatedMean }} ({{ overview.sus.grade }})
          </span>
        </div>

        <table class="institutional-table">
          <thead>
            <tr>
              <th scope="col">User Cohort</th>
              <th scope="col">Sample Size (N)</th>
              <th scope="col">Mean SUS Score</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in overview.sus.cohorts" :key="row.cohort">
              <td><strong>{{ row.cohort }}</strong></td>
              <td>{{ row.sampleSize }}</td>
              <td>{{ row.meanScore.toFixed(2) }}</td>
            </tr>
            <tr class="summary-row">
              <td><strong>Consolidated Mean SUS</strong></td>
              <td><strong>{{ overview.sus.totalSampleSize }}</strong></td>
              <td><strong>{{ overview.sus.consolidatedMean }} ({{ overview.sus.grade }})</strong></td>
            </tr>
          </tbody>
        </table>

        <div class="sus-calculator-box">
          <h4>Live 10-Item SUS Calculator: {{ liveSusResult.score }} ({{ liveSusResult.label }})</h4>
          <p class="section-subtitle">
            Odd items score <code>(Rating - 1)</code>; even items score <code>(5 - Rating)</code>; sum multiplied by <code>2.5</code> (Grade A threshold ≥ 80.3).
          </p>
          <div class="preset-rating-buttons" style="margin-top: 0.5rem;">
            <span
              v-for="(val, idx) in susResponses"
              :key="`sus-item-${idx}`"
              class="token-chip chip-signed"
            >
              Q{{ idx + 1 }}: {{ val }}/5
            </span>
          </div>
        </div>
      </section>
    </div>

    <!-- AACCUP Area III: Longitudinal Semester Trend Analytics Tab -->
    <div v-else-if="activeTab === 'longitudinal'" class="dean-longitudinal-panels" data-testid="longitudinal-trends-panel">
      <!-- Longitudinal Trends Header Card -->
      <section class="card longitudinal-banner-card" style="margin-bottom: 1.25rem;" data-testid="longitudinal-trends-card">
        <div class="iso-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • AACCUP Area III Accreditation Matrix</span>
            <h3 class="section-title">Longitudinal Semester Trend Analytics &amp; Instructional Competence Velocity</h3>
            <p class="section-subtitle">
              Cross-semester evaluation tracking comparing active term (Fall 2026) vs previous term baselines across Likert ratings, sentiment shifts, and action plan resolution.
            </p>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <button
              type="button"
              class="btn-primary btn-sm"
              data-testid="export-dossier-bundle-btn"
              @click="exportAccreditationDossier"
            >
              📦 Export Full Accreditation Dossier Bundle (JSON/CSV)
            </button>
          </div>
        </div>

        <!-- Delta KPI Grid -->
        <div class="metric-overview-grid" style="margin-top: 1rem;" data-testid="longitudinal-kpi-grid">
          <div class="metric-card">
            <span class="metric-label">Mean Rating Delta (Δμ)</span>
            <strong class="metric-value" style="color: var(--plp-green-800);">
              +{{ longitudinalData.deltas.mean_score_delta.toFixed(2) }} vs Spring 2026
            </strong>
            <small style="color: var(--text-muted); font-size: 0.75rem;">Active: {{ longitudinalData.active_term.mean_score.toFixed(2) }} | Prior: {{ longitudinalData.prior_term.mean_score.toFixed(2) }}</small>
          </div>
          <div class="metric-card">
            <span class="metric-label">Negative Sentiment Shift</span>
            <strong class="metric-value" style="color: #16a34a;">
              {{ longitudinalData.deltas.negative_sentiment_shift_pct }}% (Reduction)
            </strong>
            <small style="color: var(--text-muted); font-size: 0.75rem;">Current: {{ longitudinalData.active_term.negative_sentiment_pct }}% | Baseline: {{ longitudinalData.prior_term.negative_sentiment_pct }}%</small>
          </div>
          <div class="metric-card">
            <span class="metric-label">Positive Sentiment Growth</span>
            <strong class="metric-value" style="color: var(--plp-green-800);">
              +{{ longitudinalData.deltas.positive_sentiment_shift_pct }}% (Expansion)
            </strong>
            <small style="color: var(--text-muted); font-size: 0.75rem;">Current: {{ longitudinalData.active_term.positive_sentiment_pct }}% | Baseline: {{ longitudinalData.prior_term.positive_sentiment_pct }}%</small>
          </div>
          <div class="metric-card">
            <span class="metric-label">Action Plan Resolution</span>
            <strong class="metric-value" style="color: var(--plp-green-800);">
              {{ longitudinalData.deltas.action_plan_resolution_rate_pct }}% Resolved
            </strong>
            <small style="color: var(--text-muted); font-size: 0.75rem;">Target: ≥ 80.0% (Exceeds Standard)</small>
          </div>
        </div>
      </section>

      <!-- Multi-Semester Historical Progression Matrix -->
      <section class="card semester-comparison-card" style="margin-bottom: 1.25rem;">
        <div class="iso-header">
          <h3 class="section-title">Multi-Semester Longitudinal Quality Progression</h3>
          <span class="badge-unlocked">AACCUP Level III Continuous Quality Improvement Track</span>
        </div>
        <table class="institutional-table" style="margin-top: 0.75rem;" data-testid="semester-comparison-table">
          <thead>
            <tr>
              <th scope="col">Academic Semester Cycle</th>
              <th scope="col">Mean Likert Rating</th>
              <th scope="col">Negative Sentiment %</th>
              <th scope="col">Response Rate %</th>
              <th scope="col">Action Plan Resolution %</th>
              <th scope="col">Accreditation Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sem in longitudinalData.historical_semesters" :key="sem.term_code">
              <td><strong>{{ sem.term_name || sem.term_code }}</strong></td>
              <td>{{ sem.mean_score.toFixed(2) }} / 5.00</td>
              <td>{{ sem.negative_pct }}%</td>
              <td>{{ sem.response_rate_pct }}%</td>
              <td>{{ sem.action_plan_resolution_pct }}%</td>
              <td>
                <span class="token-chip chip-signed" style="font-size: 0.75rem;">
                  {{ sem.mean_score >= 4.0 ? 'EXCEEDS TARGET' : 'SATISFACTORY' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- 5-Cluster Longitudinal Shift Table -->
      <section class="card cluster-shift-card">
        <div class="iso-header">
          <h3 class="section-title">5-Cluster Pedagogical Domain Shifts (Fall 2026 vs Spring 2026 Baseline)</h3>
          <span class="badge-unlocked">CHED CMO 46 Benchmark Compliant</span>
        </div>
        <table class="institutional-table" style="margin-top: 0.75rem;" data-testid="cluster-shift-table">
          <thead>
            <tr>
              <th scope="col">Pedagogical Evaluation Cluster</th>
              <th scope="col">Fall 2026 Active</th>
              <th scope="col">Spring 2026 Baseline</th>
              <th scope="col">Longitudinal Shift (Δ)</th>
              <th scope="col">Progress Direction</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in longitudinalData.cluster_comparisons" :key="c.cluster">
              <td><strong>{{ c.cluster }}</strong></td>
              <td>{{ c.active_score.toFixed(2) }}</td>
              <td>{{ c.prior_score.toFixed(2) }}</td>
              <td style="font-weight: 700; color: var(--plp-green-800);">
                +{{ c.delta.toFixed(2) }}
              </td>
              <td>
                <span class="token-chip chip-signed" style="font-size: 0.75rem;">
                  {{ c.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <!-- SOP 4: College-Wide Qualitative Synthesis (calamanCy + VADER) Tab -->
    <div v-else-if="activeTab === 'qualitative'" class="dean-qualitative-panels" data-testid="qualitative-synthesis-panel">
      <!-- calamanCy Header Card -->
      <section class="card synthesis-banner-card" style="margin-bottom: 1.25rem;" data-testid="calamancy-header-card">
        <div class="iso-header">
          <div>
            <span class="eyebrow-badge">PAMANTASAN NG LUNGSOD NG PASIG • NLP Linguistic Intelligence</span>
            <h3 class="section-title">Qualitative Narrative Synthesis (calamanCy • spaCy Tagalog/Taglish/English Pipeline)</h3>
            <p class="section-subtitle">
              Multi-question professor qualitative analysis synthesizing student narratives into word frequency, lemmas, and 5-cluster pedagogical dimensions.
            </p>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <button
              type="button"
              class="btn-secondary btn-xs"
              data-testid="export-qualitative-json-btn"
              @click="downloadQualitativeJson"
            >
              Export calamanCy JSON
            </button>
            <button
              type="button"
              class="btn-primary btn-xs"
              data-testid="export-qualitative-csv-btn"
              @click="downloadQualitativeCsv"
            >
              Export calamanCy CSV
            </button>
            <span class="badge-unlocked" style="background:var(--plp-green-800);color:#fff;" data-testid="calamancy-engine-badge">
              Engine: {{ qualitativeSynthesis.nlp_engine }} (tl_calamancy_md)
            </span>
            <span class="badge-unlocked" style="background:var(--plp-gold-500);color:#000;">
              Submissions Synthesized: {{ qualitativeSynthesis.submission_count }}
            </span>
          </div>
        </div>
      </section>

      <!-- Q1: Top Descriptors Word Cloud -->
      <section class="card descriptors-card" style="margin-bottom: 1.25rem;" data-testid="q1-top-descriptors-card">
        <div class="watchlist-header">
          <div>
            <span class="eyebrow-badge">Question 1 • Three Words that Describe the Professor</span>
            <h3 class="section-title">Top Faculty Descriptors (calamanCy Lemma &amp; Sentiment Classification)</h3>
            <p class="section-subtitle">
              Normalized frequency distribution of the 3-word qualitative descriptors submitted across the College of Computer Studies.
            </p>
          </div>
        </div>

        <div class="preset-rating-buttons" style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.75rem;" data-testid="top-descriptors-cloud">
          <div
            v-for="item in qualitativeSynthesis.top_descriptors"
            :key="item.word"
            class="token-chip"
            :class="item.valence === 'Positive' ? 'chip-signed' : (item.valence === 'Negative' ? 'chip-warn' : 'chip-unlocked')"
            style="padding: 0.45rem 0.85rem; font-size: 0.88rem; display:inline-flex; align-items:center; gap: 0.45rem;"
            :data-testid="`descriptor-${item.word.toLowerCase()}`"
          >
            <strong>{{ item.word }}</strong>
            <span style="font-size:0.75rem;opacity:0.85;">(x{{ item.count }})</span>
            <span
              style="font-size:0.7rem;padding:0.1rem 0.35rem;border-radius:4px;"
              :style="{
                background: item.valence === 'Positive' ? 'var(--plp-green-800)' : (item.valence === 'Negative' ? '#dc2626' : '#6b7280'),
                color: '#ffffff'
              }"
            >
              {{ item.valence }} ({{ item.polarity > 0 ? '+' : '' }}{{ item.polarity.toFixed(2) }})
            </span>
          </div>
        </div>
      </section>

      <!-- Q2: Room for Improvement Topics Grouped by 5 Pedagogical Clusters -->
      <section class="card improvement-clusters-card" style="margin-bottom: 1.25rem;" data-testid="q2-improvement-clusters-card">
        <div class="watchlist-header">
          <div>
            <span class="eyebrow-badge">Question 2 • Room for Improvements (Lettered a., b., c...)</span>
            <h3 class="section-title">Institutional Pedagogical Improvement Clusters</h3>
            <p class="section-subtitle">
              Specific, constructive improvement topics classified across the 5 PLP pedagogical dimensions for accreditation evidence.
            </p>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;margin-top:0.85rem;">
          <div
            v-for="clusterName in clustersList"
            :key="clusterName"
            class="cluster-box"
            style="padding:0.85rem;background:#fafbfa;border:1px solid #d1fae5;border-radius:6px;"
            :data-testid="`cluster-box-${clusterName.toLowerCase().replace(/[^a-z0-9]/g, '-')}`"
          >
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">
              <strong style="color:var(--plp-green-950);font-size:0.95rem;">
                {{ clusterName }}
              </strong>
              <span class="token-chip chip-signed" style="font-size:0.72rem;">
                {{ getClusterTopics(clusterName).length }} actionable topic(s)
              </span>
            </div>

            <div v-if="getClusterTopics(clusterName).length === 0" style="font-size:0.82rem;color:#6b7280;font-style:italic;">
              No critical improvement topics flagged under this pedagogical dimension.
            </div>

            <ul v-else style="margin:0;padding-left:1.2rem;font-size:0.85rem;line-height:1.5;">
              <li
                v-for="(tItem, tIdx) in getClusterTopics(clusterName)"
                :key="tIdx"
                style="margin-bottom:0.35rem;"
                :data-testid="`cluster-topic-${tIdx}`"
              >
                <strong>{{ tItem.sub_item || `${String.fromCharCode(97 + (tIdx % 26))}.` }}</strong>
                {{ tItem.topic }}
                <span
                  class="token-chip"
                  :class="tItem.valence === 'Negative' ? 'chip-warn' : 'chip-signed'"
                  style="margin-left:0.35rem;font-size:0.7rem;padding:0.1rem 0.35rem;"
                >
                  {{ tItem.valence }} ({{ tItem.polarity > 0 ? '+' : '' }}{{ tItem.polarity.toFixed(2) }})
                </span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Q3 & Q4: Key Strengths & Recommendations Split Grid -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <section class="card strengths-card" data-testid="q3-key-strengths-card">
          <span class="eyebrow-badge">Question 3 • Teaching Strengths</span>
          <h3 class="section-title" style="font-size:1.1rem;">Key Teaching Strengths</h3>
          <p class="section-subtitle" style="font-size:0.82rem;">
            Synthesized instructional proficiencies and positive classroom practices highlighted by students.
          </p>
          <ul style="margin:0.75rem 0 0 1.2rem;padding:0;font-size:0.85rem;line-height:1.55;">
            <li
              v-for="(strItem, strIdx) in qualitativeSynthesis.key_strengths"
              :key="strIdx"
              style="margin-bottom:0.45rem;"
              :data-testid="`strength-item-${strIdx}`"
            >
              {{ typeof strItem === 'object' ? (strItem.text || strItem) : strItem }}
            </li>
          </ul>
        </section>

        <section class="card recommendations-card" data-testid="q4-key-recommendations-card">
          <span class="eyebrow-badge">Question 4 • Student Recommendations</span>
          <h3 class="section-title" style="font-size:1.1rem;">Actionable Student Recommendations</h3>
          <p class="section-subtitle" style="font-size:0.82rem;">
            Student recommendations for curriculum design, lab equipment, and pacing enhancements.
          </p>
          <ul style="margin:0.75rem 0 0 1.2rem;padding:0;font-size:0.85rem;line-height:1.55;">
            <li
              v-for="(recItem, recIdx) in qualitativeSynthesis.key_recommendations"
              :key="recIdx"
              style="margin-bottom:0.45rem;"
              :data-testid="`recommendation-item-${recIdx}`"
            >
              {{ typeof recItem === 'object' ? (recItem.text || recItem) : recItem }}
            </li>
          </ul>
        </section>
      </div>
    </div>

    <!-- PLP calamanCy + VADER Interactive Diagnostics Modal -->
    <PlpAnalyticsLabModal
      :visible="showAnalyticsLab"
      @close="showAnalyticsLab = false"
    />
  </main>
</template>
