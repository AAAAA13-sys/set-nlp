<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { evaluateWatchlistFlag, sanitizeCsvCell } from '../utils/evaluationMath.js';

const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

onMounted(() => {
  evaluationStore.fetchDeanOverview(1); // Scoped to Department 1 (College of Computer Studies)
});

const departmentName = computed(() => 'College of Computer Studies • Department of Computer Science');
const chairIdentifier = computed(() => 'Dr. M. Santos (CHR-2017-011)');

// Department Chair Faculty Roster
const departmentFaculty = ref([
  {
    id: 1,
    course_code: 'CS-401',
    course_title: 'Algorithms and Complexity',
    faculty_name: 'Prof. A. Dela Cruz',
    enrolled_count: 42,
    evaluated_count: 39,
    mean_score: 4.62,
    negative_sentiment_pct: 5.1,
    primary_tag: 'Pedagogical Delivery',
    status: 'NORMAL',
  },
  {
    id: 2,
    course_code: 'CS-405',
    course_title: 'Artificial Intelligence & NLP',
    faculty_name: 'Dr. M. Santos',
    enrolled_count: 38,
    evaluated_count: 36,
    mean_score: 4.71,
    negative_sentiment_pct: 4.0,
    primary_tag: 'Pedagogical Delivery',
    status: 'NORMAL',
  },
  {
    id: 5,
    course_code: 'CS-408',
    course_title: 'Software Engineering & Quality Assurance',
    faculty_name: 'Prof. K. Gomez',
    enrolled_count: 40,
    evaluated_count: 38,
    mean_score: 4.48,
    negative_sentiment_pct: 6.2,
    primary_tag: 'Pedagogical Delivery',
    status: 'NORMAL',
  },
  {
    id: 4,
    course_code: 'CS-302',
    course_title: 'Data Structures and Algorithms Lab',
    faculty_name: 'Prof. T. Reyes',
    enrolled_count: 35,
    evaluated_count: 32,
    mean_score: 3.35,
    negative_sentiment_pct: 38.5,
    primary_tag: 'Assessment & Grading',
    status: 'WATCHLIST_TRIGGERED',
  },
]);

const departmentWatchlist = computed(() =>
  departmentFaculty.value.filter((f) => evaluateWatchlistFlag(f.mean_score, f.negative_sentiment_pct)),
);

const chairNotes = ref({
  4: 'Chair Recommendation: Instructor recommended for department grading rubric standardization and mid-term turnaround SLA.',
});

const planEndorsed = ref({
  4: true,
});

function endorseActionPlan(facultyId) {
  planEndorsed.value[facultyId] = true;
}
</script>

<template>
  <main class="workspace-main chair-dashboard-workspace" aria-labelledby="chair-heading">
    <!-- Department Chair Identity Banner -->
    <section class="card chair-banner-card" data-testid="chair-diagnostic-workspace">
      <div class="chair-banner-top">
        <div>
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • [EduMetrics Department] Department Chair Workspace
          </span>
          <h2 id="chair-heading" class="section-title">
            {{ departmentName }}
          </h2>
          <p class="section-subtitle">
            Department Chair: <strong data-testid="chair-department-badge">{{ chairIdentifier }}</strong> •
            Active Term: <strong>Fall / 1st Semester</strong> (AY 2026-2027)
          </p>
        </div>

        <div class="scope-guard-badge" style="background:#ecfdf5;border:1px solid #a7f3d0;padding:0.5rem 0.85rem;border-radius:6px;">
          <span style="font-size:0.75rem;font-weight:700;color:var(--plp-green-950);text-transform:uppercase;">
            Departmental RBAC Scope:
          </span>
          <div style="font-size:0.82rem;color:var(--plp-green-900);">
            Strictly scoped to College of Computer Studies faculty sections.
          </div>
        </div>
      </div>

      <!-- Department KPIs -->
      <div class="kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-top:1.25rem;">
        <div class="kpi-card" style="padding:1rem;background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;">
          <span class="kpi-label" style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">Response Rate</span>
          <div class="kpi-value" style="font-size:1.5rem;font-weight:800;color:var(--plp-green-900);">93.8%</div>
          <small style="color:#059669;font-weight:600;">Above 85.0% Institutional Target</small>
        </div>

        <div class="kpi-card" style="padding:1rem;background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;">
          <span class="kpi-label" style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">Submissions Logged</span>
          <div class="kpi-value" style="font-size:1.5rem;font-weight:800;color:#1f2937;">1,420</div>
          <small style="color:#6b7280;">Across 4 Computer Science Sections</small>
        </div>

        <div class="kpi-card" style="padding:1rem;background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;">
          <span class="kpi-label" style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">Department Mean Rating</span>
          <div class="kpi-value" style="font-size:1.5rem;font-weight:800;color:var(--plp-green-900);">4.52 / 5.00</div>
          <small style="color:#059669;font-weight:600;">Very Satisfactory (AACCUP Level III)</small>
        </div>

        <div class="kpi-card" style="padding:1rem;background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;">
          <span class="kpi-label" style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">Watchlist Inquiries</span>
          <div class="kpi-value" style="font-size:1.5rem;font-weight:800;color:#dc2626;">{{ departmentWatchlist.length }}</div>
          <small style="color:#dc2626;font-weight:600;">Requires Chair Pedagogical Endorsement</small>
        </div>
      </div>
    </section>

    <!-- Department Faculty Roster Table -->
    <section class="card department-roster-card" style="margin-top:1.25rem;" aria-label="Department Faculty Performance">
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">Department Faculty Performance Overview</span>
          <h3 class="section-title">Computer Science Department Faculty Roster</h3>
          <p class="section-subtitle">
            Continuous monitoring of teaching evaluations across all department instructional blocks for AY 2026-2027.
          </p>
        </div>
      </div>

      <div class="table-responsive" style="margin-top:0.75rem;">
        <table class="institutional-table" data-testid="chair-faculty-table">
          <thead>
            <tr>
              <th scope="col">Course Code</th>
              <th scope="col">Faculty Member</th>
              <th scope="col">Evaluated / Enrolled</th>
              <th scope="col">Mean Rating (μ)</th>
              <th scope="col">Negative Sentiment %</th>
              <th scope="col">Primary Domain</th>
              <th scope="col">Standing</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in departmentFaculty" :key="f.id" :data-testid="`chair-row-${f.course_code}`">
              <td>
                <strong class="course-code-cell">{{ f.course_code }}</strong>
                <div class="course-title-sub">{{ f.course_title }}</div>
              </td>
              <td><strong>{{ f.faculty_name }}</strong></td>
              <td>{{ f.evaluated_count }} / {{ f.enrolled_count }} ({{ Math.round((f.evaluated_count / f.enrolled_count) * 100) }}%)</td>
              <td>
                <span :style="{ color: f.mean_score < 3.5 ? '#dc2626' : 'var(--plp-green-900)', fontWeight: 'bold' }">
                  {{ f.mean_score.toFixed(2) }}
                </span>
              </td>
              <td>
                <span :style="{ color: f.negative_sentiment_pct > 35.0 ? '#dc2626' : '#1f2937', fontWeight: 'bold' }">
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
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Department Watchlist Pedagogical Intervention & Chair Review -->
    <section v-if="departmentWatchlist.length > 0" class="card chair-watchlist-card" style="margin-top:1.25rem;" data-testid="chair-watchlist-card">
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5;">
            SOP 2 • Faculty Exception Watchlist (Mean &lt; 3.50 or Negative Sentiment &gt; 35%)
          </span>
          <h3 class="section-title">Department Pedagogical Interventions</h3>
          <p class="section-subtitle">
            Department Chair review and intervention endorsement for instructors requiring pedagogical support.
          </p>
        </div>
      </div>

      <div v-for="item in departmentWatchlist" :key="item.id" style="margin-top:1rem;padding:1rem;background:#fffaf0;border:1px solid #feebc8;border-radius:8px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
          <div>
            <h4 style="margin:0;font-size:1.05rem;color:#744210;">
              {{ item.faculty_name }} — {{ item.course_code }} (Mean: {{ item.mean_score.toFixed(2) }} / Negative: {{ item.negative_sentiment_pct.toFixed(1) }}%)
            </h4>
            <p style="margin:0.25rem 0 0;font-size:0.85rem;color:#975a16;">
              Primary Pedagogical Focus: <strong>{{ item.primary_tag }}</strong>
            </p>
          </div>
          <span class="token-chip chip-warn" style="font-size:0.8rem;">
            {{ planEndorsed[item.id] ? 'CHAIR ENDORSED' : 'PENDING CHAIR REVIEW' }}
          </span>
        </div>

        <div style="margin-top:0.75rem;">
          <label style="display:block;font-size:0.82rem;font-weight:600;color:#744210;margin-bottom:0.35rem;">
            Department Chair Intervention Notes &amp; Recommendation to Dean:
          </label>
          <textarea
            v-model="chairNotes[item.id]"
            rows="2"
            style="width:100%;padding:0.5rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;"
            placeholder="Add chair notes regarding assessment rubrics, lecture punctuality, or syllabus pacing..."
          ></textarea>
        </div>

        <div style="margin-top:0.5rem;display:flex;gap:0.5rem;align-items:center;">
          <button
            type="button"
            class="btn-primary btn-xs"
            data-testid="chair-endorse-plan-btn"
            @click="endorseActionPlan(item.id)"
          >
            {{ planEndorsed[item.id] ? 'Update Chair Endorsement' : 'Endorse Action Plan to Dean' }}
          </button>
          <small v-if="planEndorsed[item.id]" style="color:#059669;font-weight:600;">
            ✓ Endorsement logged and transmitted to Dean V. Mendoza.
          </small>
        </div>
      </div>
    </section>
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
