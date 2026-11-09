<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import ClearancePermitCard from '../components/ClearancePermitCard.vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import SkeletonLoader from '../components/SkeletonLoader.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { debounce } from '../utils/uxHelpers.js';

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const searchInput = ref('');
const debouncedSearch = ref('');
const statusFilter = ref('ALL');

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
      title="Couldn't load evaluation queue"
      message="Your clearance progress is safe. Check your connection."
      :detail="evaluationStore.queueError || ''"
      :retrying="evaluationStore.queueLoading"
      @retry="refreshQueueData"
      @dismiss="evaluationStore.queueError = null"
    />

    <section class="card queue-progress-card" data-testid="student-queue-progress">
      <div class="queue-header-row">
        <div>
          <span class="eyebrow-badge">COURSE EVALUATIONS</span>
          <h2 id="queue-heading" class="section-title">
            My Courses
            <span class="ui-badge-count">{{ progress.completed }} / {{ progress.total }}</span>
            <span class="ui-hint" tabindex="0" aria-label="Course evaluation guidance" data-hint="Complete evaluations for all enrolled courses.">?</span>
            <span class="sr-only">({{ progress.completed }} of {{ progress.total }} Completed)</span>
          </h2>
          <div class="queue-meta-strip" style="font-size: 0.8rem; color: #4b5563; margin-top: 0.35rem;">
            <span>Term: <strong>{{ evaluationStore.evaluationWindow?.term_name || '1st Semester AY 2026-2027' }}</strong></span>
            <span style="margin: 0 0.4rem;">·</span>
            <span>Deadline: <strong>{{ formattedDeadline }}</strong></span>
            <span style="margin: 0 0.4rem;">·</span>
            <span>Completed: <strong>{{ progress.completed }} of {{ progress.total }}</strong></span>
          </div>
        </div>

        <div class="queue-stats-pill tabular-nums">
          <span>Progress: <strong>{{ progress.percentage }}%</strong></span>
          <span class="divider" aria-hidden="true">·</span>
          <span>Status: <strong>{{ progress.completed === progress.total ? 'Completed' : `${progress.total - progress.completed} Remaining` }}</strong></span>
        </div>
      </div>

      <div
        class="queue-progressbar-outer"
        role="progressbar"
        :aria-valuenow="progress.percentage"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="`Evaluation queue completion: ${progress.completed} of ${progress.total} completed`"
      >
        <div
          class="queue-progressbar-inner"
          :style="{ width: `${progress.percentage}%` }"
        ></div>
      </div>

      <div
        v-if="evaluationStore.evaluationWindow?.announcement_message"
        class="alert-banner alert-success"
        style="margin-top: 0.85rem;"
        data-testid="student-window-announcement"
      >
        <div>
          <strong>Registrar Notice:</strong>
          {{ evaluationStore.evaluationWindow.announcement_message }}
        </div>
      </div>

      <div
        v-if="evaluationStore.evaluationWindow?.status && evaluationStore.evaluationWindow?.status !== 'OPEN'"
        class="alert-banner alert-error"
        style="margin-top: 0.5rem;"
        data-testid="student-window-status-alert"
      >
        <div>
          <strong>Status ({{ evaluationStore.evaluationWindow?.status }}):</strong>
          Evaluations are currently closed for this semester.
        </div>
      </div>
    </section>

    <section class="card queue-table-card" aria-label="Enrolled Course Evaluations">
      <div class="queue-filter-toolbar">
        <input
          :value="searchInput"
          type="search"
          class="form-input queue-search-input"
          placeholder="Search course or professor…"
          aria-label="Filter enrolled courses or instructors"
          data-testid="queue-search-input"
          @input="onSearchInput"
        />

        <div class="queue-filter-pills" role="group" aria-label="Filter queue status">
          <button
            type="button"
            class="queue-pill-btn"
            :class="{ 'is-active': statusFilter === 'ALL' }"
            data-testid="queue-filter-all"
            @click="statusFilter = 'ALL'"
          >
            All ({{ evaluationStore.courses.length }})
          </button>
          <button
            type="button"
            class="queue-pill-btn"
            :class="{ 'is-active': statusFilter === 'PENDING' }"
            data-testid="queue-filter-pending"
            @click="statusFilter = 'PENDING'"
          >
            Pending ({{ progress.pending }})
          </button>
          <button
            type="button"
            class="queue-pill-btn"
            :class="{ 'is-active': statusFilter === 'COMPLETED' }"
            data-testid="queue-filter-completed"
            @click="statusFilter = 'COMPLETED'"
          >
            Completed ({{ progress.completed }})
          </button>
        </div>
      </div>

      <SkeletonLoader
        v-if="evaluationStore.queueLoading"
        :rows="4"
        label="Loading enrolled PLP course sections…"
      />

      <div v-else class="table-responsive">
        <table class="institutional-table" data-testid="student-queue-table">
          <thead>
            <tr>
              <th scope="col">Subject ID <span class="sr-only">Course Code</span></th>
              <th scope="col">Subject Name</th>
              <th scope="col">Professor <span class="sr-only">Instructor</span></th>
              <th scope="col">Section</th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="course in filteredCourses"
              :key="course.course_section_id || course.course_code"
              :data-testid="`queue-row-${course.course_code}`"
            >
              <td>
                <strong class="course-code-cell">{{ course.course_code }}</strong>
              </td>
              <td>
                <div class="course-title-sub" style="font-weight: 600; color: #1f2937;">{{ course.course_title }}</div>
              </td>
              <td>{{ course.instructor_name }}</td>
              <td>{{ course.section_code || 'SEC-A' }}</td>
              <td>
                <span
                  class="status-badge"
                  :class="course.status === 'COMPLETED' ? 'status-completed' : 'status-pending'"
                >
                  {{ course.status }}
                </span>
              </td>
              <td>
                <span
                  v-if="course.status === 'COMPLETED'"
                  class="badge-evaluated-locked"
                  data-testid="evaluated-locked-badge"
                  :title="course.clearance_token || 'Evaluation submitted and permanently locked.'"
                >
                  ✓ Evaluated
                </span>
                <button
                  v-else
                  type="button"
                  class="btn-primary btn-sm"
                  :disabled="evaluationStore.evaluationWindow?.status !== 'OPEN'"
                  :data-testid="`start-eval-${course.course_code}`"
                  @click="startEvaluation(course)"
                >
                  Start Evaluation
                </button>
              </td>
            </tr>
            <tr
              v-if="!evaluationStore.queueLoading && filteredCourses.length === 0"
              data-testid="queue-empty-state"
            >
              <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.25rem;">
                  {{ evaluationStore.courses.length === 0 ? 'No Enrolled Course Sections Found' : 'No Matching Course Sections' }}
                </div>
                <p style="margin: 0; font-size: 0.85rem;">
                  {{
                    evaluationStore.courses.length === 0
                      ? 'No course enrollments are assigned to your student record for the active term. Contact the Office of the University Registrar if you need roster assistance.'
                      : 'Reset your search or status filter above to view all enrolled courses.'
                  }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section
      v-if="progress.allCleared"
      class="card all-courses-completed-card"
      style="text-align: center; padding: 2rem 1.5rem; margin-top: 1rem; background: #ffffff; border: 1px solid #dce7dc; border-radius: 12px;"
    >
      <div style="font-size: 2rem; margin-bottom: 0.5rem; color: #166534;" aria-hidden="true">✓</div>
      <h3 class="section-title">All Course Evaluations Completed</h3>
      <p class="section-subtitle">
        You have evaluated all <strong>{{ progress.total }} enrolled courses</strong> for this semester.
      </p>
      <p style="font-size: 0.85rem; color: #4b5563; max-width: 600px; margin: 0.5rem auto 0; line-height: 1.5;">
        Thank you for your valuable feedback to the faculty of Pamantasan ng Lungsod ng Pasig.
      </p>
    </section>

    <!-- Digital Examination Clearance Permit (Unlocked on 100% Completion) -->
    <div v-if="progress.allCleared" style="margin-top: 1.25rem;">
      <ClearancePermitCard
        :student-id="authStore.user?.institutional_id || String(authStore.user?.id || 'PLP-STD-2026')"
        :student-name="authStore.user?.name || 'PLP Student'"
        :active-term="evaluationStore.evaluationWindow?.term_name || authStore.activeTerm || '1st Semester AY 2026-2027'"
        :exam-permit-token="evaluationStore.lastReceipt?.clearance_token || null"
        :courses="evaluationStore.courses"
      />
    </div>
  </main>
</template>

<style scoped>
.queue-filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  margin-bottom: 0.9rem;
}

.queue-search-input {
  max-width: 340px;
  flex: 1;
  min-width: min(240px, 100%);
}

.queue-filter-pills {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.queue-pill-btn {
  padding: 0.32rem 0.68rem;
  border-radius: 999px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #3f5247;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
}

.queue-pill-btn.is-active {
  background: #103c2e;
  border-color: #103c2e;
  color: #f4cd42;
}

.badge-evaluated-locked {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.35rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.78rem;
  font-weight: 700;
  background: #e8f5e9;
  color: #1b5e20;
  border: 1px solid #c8e6c9;
  cursor: default;
  user-select: none;
}
</style>
