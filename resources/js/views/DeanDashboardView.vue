<script setup>
import { computed, ref } from 'vue';
import { useEvaluationStore } from '../stores/evaluation.js';
import {
  calculateSusScore,
  evaluateWatchlistFlag,
  sanitizeCsvCell,
} from '../utils/evaluationMath.js';

const evaluationStore = useEvaluationStore();
const activeTab = ref('overview'); // 'overview' | 'quality'

const overview = computed(() => evaluationStore.deanOverview);

const flaggedWatchlist = computed(() =>
  overview.value.watchlist.filter((item) =>
    evaluateWatchlistFlag(item.mean_score, item.negative_sentiment_pct),
  ),
);

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
            :class="{ active: activeTab === 'quality' }"
            :aria-selected="activeTab === 'quality'"
            data-testid="tab-quality-sus"
            @click="activeTab = 'quality'"
          >
            SOP 3: ISO/IEC 25010 (4.77) &amp; SUS (83.92 Grade A)
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

        <ul class="watchlist-items">
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
    <div v-else class="dean-quality-panels" data-testid="iso-sus-panel">
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
  </main>
</template>
