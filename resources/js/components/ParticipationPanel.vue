<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { useAuthStore } from '../stores/auth.js';
import { sanitizeCsvCell } from '../utils/evaluationMath.js';

/**
 * Evaluation Participation Tracker shared by the Dean and Chair workspaces:
 * who in the supervisor's scope has finished vs. is still pending.
 * Completion status only; it never shows or links any submitted response.
 */
const props = defineProps({
  endpoint: { type: String, required: true },
  departmentId: { type: [Number, String], default: null },
  testPrefix: { type: String, default: 'dean' },
  showBoard: { type: Boolean, default: true },
});

const emit = defineEmits(['loaded']);

const authStore = useAuthStore();
const activeTerm = computed(() => authStore.activeTerm);

const participationData = ref(null);
const participationLoading = ref(false);
const participationError = ref('');
const participationSearch = ref('');

async function fetchParticipation() {
  participationLoading.value = true;
  participationError.value = '';
  try {
    const dept = props.departmentId && props.departmentId !== 'ALL' ? Number(props.departmentId) : null;
    const url = dept ? `${props.endpoint}?department_id=${dept}` : props.endpoint;
    const res = await fetch(url, { headers: authStore.authHeaders() });
    if (res.ok) {
      participationData.value = await res.json();
      emit('loaded', { data: participationData.value, scoped: dept === null });
    } else {
      participationError.value = 'Participation data is unavailable right now.';
    }
  } catch (_) {
    participationError.value = 'Participation data is unavailable right now.';
  } finally {
    participationLoading.value = false;
  }
}

onMounted(fetchParticipation);
watch(() => props.departmentId, fetchParticipation);

const participationSummary = computed(() => {
  const d = participationData.value || {};
  const total = Number(d.total_students || 0);
  const done = Number(d.total_completed_students || 0);
  return {
    total,
    done,
    pending: Math.max(0, total - done),
    pct: total > 0 ? Math.round((done / total) * 1000) / 10 : 0,
  };
});

const filteredPendingStudents = computed(() => {
  const list = Array.isArray(participationData.value?.pending_students) ? participationData.value.pending_students : [];
  const q = participationSearch.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((s) =>
    `${s.name} ${s.institutional_id} ${s.department_code} ${(s.pending_courses || []).map((c) => c.course_code).join(' ')}`
      .toLowerCase()
      .includes(q),
  );
});

function exportPendingStudentsCsv() {
  const list = Array.isArray(participationData.value?.pending_students) ? participationData.value.pending_students : [];
  const header = ['Student ID', 'Name', 'Email', 'Department', 'Completed', 'Enrolled', 'Still To Evaluate'];
  const rows = list.map((s) => [
    sanitizeCsvCell(s.institutional_id),
    sanitizeCsvCell(s.name),
    sanitizeCsvCell(s.email),
    sanitizeCsvCell(s.department_code),
    s.cleared_count,
    s.enrolled_count,
    sanitizeCsvCell((s.pending_courses || []).map((c) => `${c.course_code} ${c.section_code}`).join('; ')),
  ]);
  const csv = [header, ...rows].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
  const term = String(participationData.value?.term_code || 'term').replace(/[^a-zA-Z0-9_-]/g, '_');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `AAAAA13_Pending_Evaluations_${term}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

defineExpose({ refresh: fetchParticipation });
</script>

<template>
  <div class="participation-panels" :data-testid="`${testPrefix}-participation-panel`">
  <section class="card" aria-labelledby="participation-heading">
    <div class="header-split">
      <div>
        <h3 id="participation-heading" class="section-title">Who has finished their evaluations</h3>
        <p class="section-subtitle">
          {{ participationData?.term_name || activeTerm || 'Active term' }} · Shows completion status only. Submitted ratings and comments stay anonymous and are never linked to a student.
        </p>
      </div>
      <button
        type="button"
        class="btn-secondary btn-sm"
        :data-testid="`${testPrefix}-export-pending-csv-btn`"
        :disabled="!participationData?.pending_students?.length"
        @click="exportPendingStudentsCsv"
      >
        <AppIcon name="download" /> Pending list (CSV)
      </button>
    </div>

    <div class="metric-overview-grid tabular-nums" :data-testid="`${testPrefix}-participation-summary`">
      <div class="metric-card">
        <span class="metric-label">Students enrolled</span>
        <strong class="metric-value">{{ participationSummary.total }}</strong>
      </div>
      <div class="metric-card">
        <span class="metric-label">Finished all evaluations</span>
        <strong class="metric-value">{{ participationSummary.done }}</strong>
      </div>
      <div class="metric-card">
        <span class="metric-label">Still pending</span>
        <strong class="metric-value">{{ participationSummary.pending }}</strong>
      </div>
      <div class="metric-card">
        <span class="metric-label">Completion</span>
        <strong class="metric-value">{{ participationSummary.pct }}%</strong>
      </div>
    </div>

    <div
      class="queue-progressbar-outer"
      role="progressbar"
      :aria-valuenow="participationSummary.pct"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="`${participationSummary.done} of ${participationSummary.total} students finished`"
      style="margin-top: 1rem;"
    >
      <div class="queue-progressbar-inner" :style="{ width: `${participationSummary.pct}%` }"></div>
    </div>
  </section>

  <section v-if="showBoard" class="directory" style="margin-top: 1.25rem;" aria-labelledby="board-heading" :data-testid="`${testPrefix}-department-board`">
    <div class="directory-head">
      <h3 id="board-heading" class="section-title" style="font-size: 1rem;">Departments</h3>
      <span>Finished / enrolled</span>
    </div>
    <div
      v-for="dept in participationData?.department_breakdown || []"
      :key="dept.department_id ?? dept.department_code"
      class="board-row"
    >
      <span class="code-plate">{{ dept.department_code }}</span>
      <span class="board-name">{{ dept.department_name }}<small>{{ dept.pending_students }} still pending</small></span>
      <span
        class="board-bar"
        :class="{ 'is-low': dept.completion_rate_pct < 70 }"
        role="progressbar"
        :aria-valuenow="dept.completion_rate_pct"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="`${dept.department_name}: ${dept.completion_rate_pct}% finished`"
      ><span :style="{ width: `${dept.completion_rate_pct}%` }"></span></span>
      <span class="board-figure"><b>{{ dept.completed_students }}</b> / {{ dept.total_students }} · {{ dept.completion_rate_pct }}%</span>
    </div>
    <p v-if="!(participationData?.department_breakdown || []).length" class="empty-state">
      <strong>No enrolled students in this scope yet</strong>
      Departments appear here once rosters are imported for the active term.
    </p>
  </section>

  <section class="card" style="margin-top: 1.25rem;" aria-labelledby="pending-heading">
    <div class="header-split">
      <div>
        <h3 id="pending-heading" class="section-title">
          Students with pending evaluations
          <span class="ui-badge-count">{{ participationData?.total_pending_students ?? 0 }}</span>
        </h3>
      </div>
      <input
        v-model="participationSearch"
        type="search"
        class="form-input"
        placeholder="Search name, ID, department, course…"
        aria-label="Search students with pending evaluations"
        :data-testid="`${testPrefix}-participation-search`"
        style="max-width: 320px;"
      />
    </div>

    <p v-if="participationLoading" class="section-subtitle" role="status">Loading participation…</p>
    <div v-else-if="participationError" class="alert-banner alert-error" role="alert">{{ participationError }}</div>
    <div
      v-else-if="!participationData?.pending_students?.length"
      class="alert-banner alert-success"
      :data-testid="`${testPrefix}-participation-all-done`"
    >
      Every enrolled student in this scope has finished their evaluations.
    </div>
    <div v-else class="table-responsive" style="margin-top: 0.85rem;">
      <table class="institutional-table tabular-nums" :data-testid="`${testPrefix}-pending-students-table`">
        <thead>
          <tr>
            <th scope="col">Student</th>
            <th scope="col">Student ID</th>
            <th scope="col">Department</th>
            <th scope="col">Progress</th>
            <th scope="col">Still to evaluate</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in filteredPendingStudents" :key="s.student_id">
            <td><strong>{{ s.name }}</strong></td>
            <td><code>{{ s.institutional_id }}</code></td>
            <td>{{ s.department_code }}</td>
            <td>{{ s.cleared_count }} / {{ s.enrolled_count }}</td>
            <td>
              <span
                v-for="c in s.pending_courses"
                :key="c.course_section_id"
                class="code-plate"
                style="margin: 0 0.3rem 0.3rem 0;"
                :title="`${c.course_title} · ${c.instructor}`"
              >{{ c.course_code }} {{ c.section_code }}</span>
            </td>
          </tr>
          <tr v-if="filteredPendingStudents.length === 0">
            <td colspan="5">No students match “{{ participationSearch }}”.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  </div>
</template>
