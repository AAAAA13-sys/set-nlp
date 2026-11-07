<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import ClearancePermitCard from '../components/ClearancePermitCard.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const progress = computed(() => evaluationStore.queueProgress);

onMounted(() => {
  evaluationStore.fetchQueue();
});

function startEvaluation(course) {
  evaluationStore.selectCourseForEvaluation(course.course_section_id);
  if (router) {
    router.push(`/evaluate/${course.course_section_id}`).catch(() => {});
  }
}

function completeAllPendingDemo() {
  evaluationStore.courses.forEach((course) => {
    if (course.status !== 'COMPLETED') {
      course.status = 'COMPLETED';
      course.action_label = 'Clearance Signed';
      course.clearance_token = `CLR-2026-${course.course_code.replace(/[^A-Z0-9]/g, '')}-VERIFIED`;
    }
  });
}
</script>

<template>
  <main class="workspace-main student-queue-workspace" aria-labelledby="queue-heading">
    <section class="card queue-progress-card" data-testid="student-queue-progress">
      <div class="queue-header-row">
        <div>
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • SOP 1 • Dynamic Enrollment Queue &amp; Atomic Clearance
          </span>
          <h2 id="queue-heading" class="section-title">
            My Evaluation Queue ({{ progress.completed }} of {{ progress.total }} Completed)
          </h2>
          <p class="section-subtitle">
            Complete all enrolled faculty evaluations for the active term at
            <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong> to unlock your cryptographically signed Digital Examination Permit.
          </p>
        </div>

        <div class="queue-stats-pill">
          <span>Completion: <strong>{{ progress.percentage }}%</strong></span>
          <span class="divider">|</span>
          <span>Clearance: <strong>{{ progress.clearanceStatus }}</strong></span>
          <button
            v-if="!progress.allCleared"
            type="button"
            class="btn-secondary btn-xs"
            data-testid="complete-all-demo-btn"
            @click="completeAllPendingDemo"
          >
            Simulate All 6/6 Completed
          </button>
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
    </section>

    <section class="card queue-table-card" aria-label="Enrolled Course Evaluations">
      <div class="table-responsive">
        <table class="institutional-table" data-testid="student-queue-table">
          <thead>
            <tr>
              <th scope="col">Course Code</th>
              <th scope="col">Instructor</th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="course in evaluationStore.courses"
              :key="course.course_code"
              :data-testid="`queue-row-${course.course_code}`"
            >
              <td>
                <strong class="course-code-cell">{{ course.course_code }}</strong>
                <div class="course-title-sub">{{ course.course_title }}</div>
              </td>
              <td>{{ course.instructor_name }}</td>
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
                  class="btn-signed-badge"
                  :title="course.clearance_token || 'Cryptographic clearance token issued'"
                >
                  [Clearance Signed]
                </span>
                <button
                  v-else
                  type="button"
                  class="btn-primary btn-sm"
                  :data-testid="`start-eval-${course.course_code}`"
                  @click="startEvaluation(course)"
                >
                  [Start Evaluation]
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <ClearancePermitCard
      :student-id="authStore.user?.institutional_id || '2024-10892'"
      :active-term="authStore.activeTerm || 'Fall / 1st Semester'"
      :courses="evaluationStore.courses"
    />
  </main>
</template>
