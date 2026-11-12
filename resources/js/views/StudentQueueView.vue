<script setup>
import AppIcon from '../components/AppIcon.vue';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import ClearancePermitCard from '../components/ClearancePermitCard.vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import EvaluationRubricModal from '../components/EvaluationRubricModal.vue';
import SkeletonLoader from '../components/SkeletonLoader.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { sanitizeCsvCell } from '../utils/evaluationMath.js';
import { debounce } from '../utils/uxHelpers.js';

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const searchInput = ref('');
const debouncedSearch = ref('');
const statusFilter = ref('ALL');

const showQueueReceiptModal = ref(false);
const selectedQueueReceiptCourse = ref(null);
const showRubricModal = ref(false);

function openCourseReceiptModal(course) {
  selectedQueueReceiptCourse.value = course;
  showQueueReceiptModal.value = true;
}

function closeQueueReceiptModal() {
  showQueueReceiptModal.value = false;
  selectedQueueReceiptCourse.value = null;
}

function printQueueReceipt() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

function exportStudentChecklistCsv() {
  const courses = Array.isArray(evaluationStore.courses) ? evaluationStore.courses : [];
  const termName = evaluationStore.evaluationWindow?.term_name || authStore.activeTerm || '1st Semester AY 2026-2027';
  const studentId = authStore.user?.institutional_id || 'AAAAA13-STUDENT';
  const studentName = authStore.user?.name || 'School AAAAA13 Student';
  const termCode = termName.replace(/[^a-zA-Z0-9_-]/g, '_');

  const headers = [
    'Academic Term',
    'Student ID',
    'Student Name',
    'Course Code',
    'Course Title',
    'Section',
    'Instructor Name',
    'Evaluation Status',
    'Clearance Proof Token',
  ];

  const rows = courses.map((c) => [
    sanitizeCsvCell(termName),
    sanitizeCsvCell(studentId),
    sanitizeCsvCell(studentName),
    sanitizeCsvCell(c.course_code),
    sanitizeCsvCell(c.course_title),
    sanitizeCsvCell(c.section_code || 'SEC-A'),
    sanitizeCsvCell(c.instructor_name),
    sanitizeCsvCell(c.status),
    sanitizeCsvCell(c.clearance_token || 'PENDING_EVALUATION'),
  ]);

  const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `AAAAA13_Student_Evaluation_Checklist_${termCode}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

const progress = computed(() => evaluationStore.queueProgress);

const formattedDeadline = computed(() => {
  const endsAt = evaluationStore.evaluationWindow?.ends_at;
  if (!endsAt) return 'Open for AY 2026-2027 (Asia/Manila)';
  try {
    const d = new Date(endsAt);
    return isNaN(d.getTime())
      ? endsAt
      : d.toLocaleString('en-US', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' });
  } catch (_) {
    return endsAt;
  }
});



const updateDebouncedSearch = debounce((val) => {
  debouncedSearch.value = String(val || '').trim().toLowerCase();
}, 250);

function onSearchInput(event) {
  searchInput.value = event.target.value;
  updateDebouncedSearch(searchInput.value);
}

const filteredCourses = computed(() => {
  const list = Array.isArray(evaluationStore.courses) ? evaluationStore.courses : [];
  return list.filter((course) => {
    if (statusFilter.value !== 'ALL' && course.status !== statusFilter.value) {
      return false;
    }
    if (!debouncedSearch.value) {
      return true;
    }
    const haystack = `${course.course_code || ''} ${course.course_title || ''} ${course.instructor_name || ''}`.toLowerCase();
    return haystack.includes(debouncedSearch.value);
  });
});

async function refreshQueueData() {
  await Promise.all([
    evaluationStore.fetchQueue(),
    evaluationStore.fetchEvaluationWindow(),
  ]);
}

onMounted(() => {
  refreshQueueData();
});

const nextCourse = computed(() => {
  const list = Array.isArray(evaluationStore.courses) ? evaluationStore.courses : [];
  return list.find((c) => c.status !== 'COMPLETED') || null;
});

const orderedCourses = computed(() => {
  const here = nextCourse.value?.course_section_id;
  const rank = (c) => (c.course_section_id === here ? 0 : c.status === 'COMPLETED' ? 2 : 1);
  return [...filteredCourses.value].sort((a, b) => rank(a) - rank(b));
});

const windowOpen = computed(() => evaluationStore.evaluationWindow?.status === 'OPEN');

const statusFilterOptions = [
  { value: 'ALL', label: 'All' },
  { value: 'PENDING', label: 'To do' },
  { value: 'COMPLETED', label: 'Done' },
];

function filterCount(value) {
  if (value === 'ALL') return evaluationStore.courses.length;
  if (value === 'PENDING') return progress.value.pending;
  return progress.value.completed;
}

function startEvaluation(course) {
  evaluationStore.selectCourseForEvaluation(course.course_section_id);
  if (router) {
    router.push(`/evaluate/${course.course_section_id}`).catch(() => {});
  }
}
</script>

<template>
  <main class="workspace-main student-queue-workspace" aria-labelledby="queue-heading">
    <ErrorRecoveryBanner
      :visible="Boolean(evaluationStore.queueError)"
      title="Couldn't load your courses"
      message="Nothing you submitted is lost. Check your connection and try again."
      :detail="evaluationStore.queueError || ''"
      :retrying="evaluationStore.queueLoading"
      @retry="refreshQueueData"
      @dismiss="evaluationStore.queueError = null"
    />

    <header class="page-head" data-testid="student-queue-progress">
      <div>
        <h1 id="queue-heading">My evaluations</h1>
        <p class="page-meta">
          <span>{{ evaluationStore.evaluationWindow?.term_name || '1st Semester AY 2026-2027' }}</span>
          <span>Closes <b>{{ formattedDeadline }}</b></span>
          <span><b class="tabular-nums">{{ progress.completed }} of {{ progress.total }}</b> done</span>
          <span class="sr-only">({{ progress.completed }} of {{ progress.total }} Completed)</span>
        </p>
      </div>
      <div class="page-actions">
        <button
          type="button"
          class="btn-secondary btn-sm"
          data-testid="student-open-rubric-btn"
          @click="showRubricModal = true"
        >
          <AppIcon name="clipboard" /> Rating guide
        </button>
        <button
          type="button"
          class="btn-secondary btn-sm"
          data-testid="export-student-queue-csv-btn"
          @click="exportStudentChecklistCsv"
        >
          <AppIcon name="download" /> Checklist (CSV)
        </button>
      </div>
    </header>

    <div
      class="queue-progressbar-outer"
      role="progressbar"
      :aria-valuenow="progress.percentage"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="`Evaluation queue completion: ${progress.completed} of ${progress.total} completed`"
    >
      <div class="queue-progressbar-inner" :style="{ width: `${progress.percentage}%` }"></div>
    </div>

    <div
      v-if="evaluationStore.evaluationWindow?.announcement_message"
      class="alert-banner alert-info queue-announcement"
      data-testid="student-window-announcement"
    >
      <AppIcon name="info" />
      <div><strong>From the Registrar:</strong> {{ evaluationStore.evaluationWindow.announcement_message }}</div>
    </div>

    <div
      v-if="evaluationStore.evaluationWindow?.status && evaluationStore.evaluationWindow?.status !== 'OPEN'"
      class="alert-banner alert-error"
      data-testid="student-window-status-alert"
    >
      <AppIcon name="lock" />
      <div><strong>Evaluations are closed ({{ evaluationStore.evaluationWindow?.status }}).</strong> You can't submit until the Registrar opens the evaluation window.</div>
    </div>

    <div class="queue-layout">
      <!-- The one dominant work area: what to do next -->
      <section class="next-panel card" aria-labelledby="next-heading">
        <template v-if="nextCourse">
          <span class="here-marker"><AppIcon name="pin" :size="14" /> Next evaluation</span>
          <h2 id="next-heading" class="next-title">
            <span class="code-plate is-large">{{ nextCourse.course_code }}</span>
            <span>{{ nextCourse.course_title }}</span>
          </h2>
          <dl class="next-facts">
            <div><dt>Teacher</dt><dd>{{ nextCourse.instructor_name }}</dd></div>
            <div><dt>Section</dt><dd>{{ nextCourse.section_code || 'SEC-A' }}</dd></div>
            <div><dt>Questions</dt><dd>25 ratings + written feedback</dd></div>
            <div><dt>Takes about</dt><dd>5 minutes</dd></div>
          </dl>
          <button
            type="button"
            class="btn-primary btn-lg next-cta"
            :disabled="!windowOpen"
            data-testid="next-evaluation-btn"
            @click="startEvaluation(nextCourse)"
          >
            Start evaluation <AppIcon name="arrow-right" />
          </button>
          <p v-if="!windowOpen" class="next-note">The evaluation window isn't open right now.</p>
        </template>
        <template v-else-if="!evaluationStore.queueLoading && progress.total > 0">
          <span class="status-mark is-done">All done</span>
          <h2 id="next-heading" class="next-title"><span>You've evaluated every course this semester.</span></h2>
          <p class="next-note">Thank you. Your completion receipt is below.</p>
        </template>
        <template v-else>
          <h2 id="next-heading" class="next-title"><span>No courses to evaluate yet</span></h2>
          <p class="next-note">When the Registrar adds your enrolled courses, they'll appear here.</p>
        </template>
        <p class="anon-line">
          <AppIcon name="shield" :size="16" />
          Your answers are anonymous. The school can see that you finished, never which answers are yours.
        </p>
      </section>

      <!-- Directory of signed courses -->
      <section class="directory queue-directory" aria-label="Enrolled Course Evaluations">
        <div class="directory-head queue-filter-toolbar">
          <span>Your courses</span>
          <div class="queue-filter-pills" role="group" aria-label="Filter by status">
            <button
              v-for="opt in statusFilterOptions"
              :key="opt.value"
              type="button"
              class="queue-pill-btn"
              :class="{ 'is-active': statusFilter === opt.value }"
              :aria-pressed="statusFilter === opt.value"
              :data-testid="`queue-filter-${opt.value.toLowerCase()}`"
              @click="statusFilter = opt.value"
            >
              {{ opt.label }} <span class="tabular-nums">{{ filterCount(opt.value) }}</span>
            </button>
          </div>
        </div>
        <div v-if="evaluationStore.courses.length > 6" class="queue-search-row">
          <input
            :value="searchInput"
            type="search"
            class="form-input queue-search-input"
            placeholder="Search course or teacher"
            aria-label="Filter enrolled courses or instructors"
            data-testid="queue-search-input"
            @input="onSearchInput"
          />
        </div>

        <SkeletonLoader
          v-if="evaluationStore.queueLoading"
          :rows="4"
          label="Loading your courses…"
        />

        <table v-else class="queue-table" data-testid="student-queue-table">
          <thead class="sr-only">
            <tr>
              <th scope="col">Subject ID <span class="sr-only">Course Code</span></th>
              <th scope="col">Course and Professor <span class="sr-only">Instructor</span></th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="course in orderedCourses"
              :key="course.course_section_id || course.course_code"
              :class="{ 'is-here': nextCourse && course.course_section_id === nextCourse.course_section_id }"
              :data-testid="`queue-row-${course.course_code}`"
            >
              <td class="col-code">
                <strong class="code-plate course-code-cell">{{ course.course_code }}</strong>
              </td>
              <td class="col-title">
                <div class="title-stack">
                  <span v-if="nextCourse && course.course_section_id === nextCourse.course_section_id" class="here-marker">You are here</span>
                  <span class="row-title">{{ course.course_title }}</span>
                  <span class="row-meta">{{ course.instructor_name }} · {{ course.section_code || 'SEC-A' }}</span>
                </div>
              </td>
              <td class="col-status">
                <span
                  class="status-mark"
                  :class="course.status === 'COMPLETED' ? 'is-done' : 'is-todo'"
                  :data-testid="course.status === 'COMPLETED' ? 'evaluated-locked-badge' : undefined"
                >
                  {{ course.status === 'COMPLETED' ? 'Done' : 'To do' }}
                </span>
              </td>
              <td class="col-action">
                <button
                  v-if="course.status === 'COMPLETED'"
                  type="button"
                  class="btn-ghost btn-xs"
                  :data-testid="`queue-view-receipt-btn-${course.course_code}`"
                  title="View and print your receipt for this course"
                  @click="openCourseReceiptModal(course)"
                >
                  <AppIcon name="file" /> Receipt
                </button>
                <button
                  v-else
                  type="button"
                  class="btn-ghost btn-xs row-start-link"
                  :disabled="!windowOpen"
                  :data-testid="`start-eval-${course.course_code}`"
                  @click="startEvaluation(course)"
                >
                  Start evaluation <AppIcon name="chevron-right" :size="14" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredCourses.length === 0" data-testid="queue-empty-state">
              <td colspan="4" class="empty-state">
                <strong>{{ evaluationStore.courses.length === 0 ? 'No enrolled courses yet' : 'No courses match this filter' }}</strong>
                {{
                  evaluationStore.courses.length === 0
                    ? 'Your enrolled courses for this term will appear here. Contact the Registrar if something is missing.'
                    : 'Choose All to see every course.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <section v-if="progress.allCleared" class="all-courses-completed-card">
      <ClearancePermitCard
        :student-id="authStore.user?.institutional_id || String(authStore.user?.id || 'AAAAA13-STD-2026')"
        :student-name="authStore.user?.name || 'School AAAAA13 Student'"
        :active-term="evaluationStore.evaluationWindow?.term_name || authStore.activeTerm || '1st Semester AY 2026-2027'"
        :exam-permit-token="evaluationStore.lastReceipt?.clearance_token || null"
        :courses="evaluationStore.courses"
      />
    </section>

    <!-- Course receipt -->
    <div
      v-if="showQueueReceiptModal && selectedQueueReceiptCourse"
      class="receipt-modal-overlay"
      data-testid="student-queue-clearance-receipt-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="queue-receipt-title"
      @click.self="closeQueueReceiptModal"
    >
      <div class="modal-card print-receipt-card receipt-sheet">
        <div class="receipt-head">
          <span class="receipt-org">School AAAAA13 · Office of the University Registrar</span>
          <h2 id="queue-receipt-title">Evaluation receipt</h2>
          <p>Proof that you completed this course evaluation.</p>
        </div>

        <dl class="receipt-grid">
          <div>
            <dt>Course</dt>
            <dd><span class="code-plate">{{ selectedQueueReceiptCourse.course_code }}</span> {{ selectedQueueReceiptCourse.course_title }}</dd>
          </div>
          <div>
            <dt>Instructor</dt>
            <dd>{{ selectedQueueReceiptCourse.instructor_name }} · {{ selectedQueueReceiptCourse.section_code || 'SEC-A' }}</dd>
          </div>
          <div>
            <dt>Term</dt>
            <dd>{{ evaluationStore.evaluationWindow?.term_name || authStore.activeTerm || '1st Semester AY 2026-2027' }}</dd>
          </div>
          <div>
            <dt>Status</dt>
            <dd><span class="status-mark is-done">Completed</span></dd>
          </div>
          <div>
            <dt>Issued to</dt>
            <dd>{{ authStore.user?.name || 'Verified Student' }}</dd>
          </div>
        </dl>

        <div class="receipt-code">
          <span>Receipt code</span>
          <code data-testid="queue-receipt-clearance-token">{{ selectedQueueReceiptCourse.clearance_token || `CLR-2026-${selectedQueueReceiptCourse.course_code}-PASSED` }}</code>
          <p><AppIcon name="shield" :size="15" /> This code proves you finished the evaluation. It is not linked to your ratings or comments.</p>
        </div>

        <div class="receipt-actions no-print">
          <button type="button" class="btn-secondary btn-sm" @click="closeQueueReceiptModal">Close</button>
          <button
            type="button"
            class="btn-primary btn-sm"
            data-testid="print-queue-receipt-action-btn"
            @click="printQueueReceipt"
          >
            <AppIcon name="printer" /> Print or save as PDF
          </button>
        </div>
      </div>
    </div>

    <EvaluationRubricModal
      :visible="showRubricModal"
      role-title="Student"
      @close="showRubricModal = false"
    />
  </main>
</template>

<style scoped>
.queue-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
  gap: 24px;
  align-items: start;
}

.queue-directory { order: 1; }
.next-panel { order: 2; position: sticky; top: 84px; padding: 28px; display: flex; flex-direction: column; gap: 16px; }

.next-title {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  margin: 0;
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: -0.01em;
}

.next-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 20px;
  margin: 4px 0;
}

.next-facts dt { font-size: 0.8rem; font-weight: 600; color: var(--wf-ink-3); }
.next-facts dd { margin: 0; font-weight: 700; color: var(--wf-ink); }
.next-cta { align-self: stretch; }
.next-note { margin: 0; color: var(--wf-ink-2); }

.anon-line {
  display: flex;
  gap: 8px;
  margin: 4px 0 0;
  padding-top: 14px;
  border-top: 1px solid var(--wf-tint);
  font-size: 0.88rem;
  color: var(--wf-ink-2);
}

.queue-filter-pills { display: flex; gap: 4px; flex-wrap: wrap; }

.queue-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 2px 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: var(--wf-ink-2);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}

.queue-pill-btn:hover { background: var(--wf-panel-2); }
.queue-pill-btn.is-active { background: var(--wf-tint); border-color: var(--wf-tint-2); color: var(--wf-ink); }

.queue-search-row { padding: 12px 18px 0; }
.queue-search-input { width: 100%; }

.queue-table { width: 100%; border-collapse: collapse; }
.queue-table td { padding: 14px 12px; border-bottom: 1px solid var(--wf-tint); vertical-align: middle; }
.queue-table tr:last-child td { border-bottom: 0; }
.queue-table td.col-code { padding-left: 18px; width: 1%; }
.queue-table td.col-action { padding-right: 18px; text-align: right; width: 1%; white-space: nowrap; }
.queue-table td.col-status { width: 1%; }
.queue-table tr.is-here { background: var(--wf-tint); }
.title-stack { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.title-stack .row-title { font-weight: 700; }
.title-stack .row-meta { font-size: 0.86rem; color: var(--wf-ink-3); }

.all-courses-completed-card { margin-top: 4px; }

.row-start-link { color: var(--wf-accent); }

/* Receipt */
.receipt-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.55);
}

.receipt-sheet {
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 28px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.35);
}

.receipt-head { padding-bottom: 16px; border-bottom: 1px solid var(--wf-hair); }
.receipt-org { font-size: 0.8rem; font-weight: 700; color: var(--wf-ink-2); }
.receipt-head h2 { margin: 6px 0 2px; font-size: 1.35rem; font-weight: 800; }
.receipt-head p { margin: 0; color: var(--wf-ink-2); }

.receipt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; margin: 18px 0; }
.receipt-grid dt { font-size: 0.8rem; font-weight: 600; color: var(--wf-ink-3); }
.receipt-grid dd { margin: 2px 0 0; font-weight: 600; }

.receipt-code { padding: 14px; border: 1px solid var(--wf-hair); border-radius: 8px; background: var(--wf-panel-2); }
.receipt-code > span { font-size: 0.8rem; font-weight: 700; color: var(--wf-ink-2); }
.receipt-code code { display: block; margin: 4px 0 8px; font-family: var(--font-mono); font-size: 0.9rem; font-weight: 700; word-break: break-all; }
.receipt-code p { display: flex; gap: 6px; margin: 0; font-size: 0.82rem; color: var(--wf-ink-2); }

.receipt-actions { display: flex; justify-content: space-between; gap: 8px; margin-top: 20px; flex-wrap: wrap; }

@media (max-width: 900px) {
  .queue-announcement { order: 10; }
  .all-courses-completed-card { order: 11; }
  .queue-layout { grid-template-columns: 1fr; }
  .next-panel { order: 0; position: static; padding: 20px; }
}

@media (max-width: 640px) {
  .queue-table, .queue-table tbody, .queue-table tr, .queue-table td { display: block; width: auto; }
  .queue-table tr { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 12px; padding: 12px 14px; border-bottom: 1px solid var(--wf-tint); }
  .queue-table tr:last-child { border-bottom: 0; }
  .queue-table td { padding: 0; border: 0; }
  .queue-table td.col-code { padding: 0; grid-row: span 2; align-self: start; }
  .queue-table td.col-status { grid-column: 2; }
  .queue-table td.col-action { grid-column: 1 / -1; text-align: left; padding: 6px 0 0; }
  .queue-table td.empty-state { grid-column: 1 / -1; }
  .receipt-grid { grid-template-columns: 1fr; }
  .next-facts { grid-template-columns: 1fr 1fr; }
}
</style>
