<script setup>
import AppIcon from '../components/AppIcon.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DestructiveConfirmModal from '../components/DestructiveConfirmModal.vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import EvaluationRubricModal from '../components/EvaluationRubricModal.vue';
import StraightLineModal from '../components/StraightLineModal.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { showJQueryUiError } from '../utils/jqueryUiNotification.js';
import {
  CALAMANCY_CONFIG,
  LIKERT_SCALE_OPTIONS,
  MAX_PROFESSOR_QUESTIONS,
  PEDAGOGICAL_DIMENSIONS,
  calculateClusterMeans,
  calculateMean,
  calculatePopulationVariance,
  isStraightLined,
  validateProfessorQuestions,
} from '../utils/evaluationMath.js';
import {
  clearSurveyDraft,
  debounce,
  loadSurveyDraft,
  saveSurveyDraft,
  throttle,
} from '../utils/uxHelpers.js';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

// Genuine blank student inputs across 5 clusters x 5 items = 25 items (1..5 Likert scale)
const ratings = ref(Array(25).fill(null));
const commentText = ref('');

// 4 Structured Questions Regarding the Professor (up to Max of 5 Questions)
const threeWords = ref(['', '', '']);
const improvementTopics = ref(['', '', '']);
const teachingStrengths = ref('');
const courseRecommendations = ref('');
const showFifthQuestion = ref(false);
const additionalObservation = ref('');

const improvementTopicPrompts = [
  {
    subItem: 'a.',
    title: 'a. Teaching Method & Lecture Delivery',
    placeholder: 'Suggest how the professor can improve teaching pace, clarity, or explanation of complex topics…',
  },
  {
    subItem: 'b.',
    title: 'b. Assessments, Quizzes & Grading Feedback',
    placeholder: 'Suggest how the professor can improve exams, assignment clarity, or prompt return of grades…',
  },
  {
    subItem: 'c.',
    title: 'c. Classroom Management & Student Support',
    placeholder: 'Suggest how the professor can improve punctuality, student engagement, or consultation support…',
  },
];

const showStraightLineModal = ref(false);
const showDiscardDraftModal = ref(false);
const showReceiptModal = ref(false);
const showRubricModal = ref(false);
const statusBanner = ref(null);
const networkFailureDetail = ref('');
const draftSavedLabel = ref('Autosave ready');

function openReceiptModal() {
  showReceiptModal.value = true;
}

function closeReceiptModal() {
  showReceiptModal.value = false;
}

function printCourseReceipt() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

const currentStep = ref(1);
const stepValidationMessage = ref('');

function scrollToFirstUnanswered() {
  const firstUnansweredIndex = ratings.value.findIndex(
    (val) => !Number.isInteger(val) || val < 1 || val > 5,
  );
  if (firstUnansweredIndex !== -1 && typeof document !== 'undefined') {
    setTimeout(() => {
      const el = document.querySelector(`[data-testid="likert-row-${firstUnansweredIndex + 1}"]`);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('pulse-unanswered');
        setTimeout(() => el.classList.remove('pulse-unanswered'), 2500);
      }
    }, 50);
  }
}

function scrollToCluster(clusterNum) {
  if (typeof document !== 'undefined') {
    const el = document.querySelector(`[data-testid="likert-cluster-${clusterNum}"]`);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
}

function proceedToStep2() {
  stepValidationMessage.value = '';
  if (!allRatingsAnswered.value) {
    const remaining = 25 - answeredCount.value;
    stepValidationMessage.value = `Please rate all 25 pedagogical items across the 5 clusters (${remaining} remaining) before proceeding to written feedback.`;
    showJQueryUiError(stepValidationMessage.value, 'Incomplete Ratings', 4000);
    scrollToFirstUnanswered();
    return;
  }
  currentStep.value = 2;
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function returnToStep1() {
  currentStep.value = 1;
  stepValidationMessage.value = '';
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

const formOpenedAt = ref(Date.now());
let hydratingDraft = false;

const activeSectionId = computed(
  () => evaluationStore.activeCourse?.course_section_id || Number(route?.params?.courseSectionId) || null,
);

const courses = computed(() => evaluationStore.courses || []);
const progress = computed(() => evaluationStore.queueProgress);
const isCurrentCourseCompleted = computed(() => evaluationStore.activeCourse?.status === 'COMPLETED');

const nextPendingCourse = computed(() => {
  const currentId = activeSectionId.value;
  return (evaluationStore.courses || []).find(
    (c) => c.status === 'PENDING' && Number(c.course_section_id) !== Number(currentId),
  );
});

async function switchCourseSection(sectionId) {
  const targetId = Number(sectionId);
  if (!targetId || targetId === activeSectionId.value) return;
  if (activeSectionId.value) {
    saveSurveyDraft(
      activeSectionId.value,
      {
        ratings: ratings.value,
        commentText: commentText.value,
        threeWords: threeWords.value,
        improvementTopics: improvementTopics.value,
        teachingStrengths: teachingStrengths.value,
        courseRecommendations: courseRecommendations.value,
        showFifthQuestion: showFifthQuestion.value,
        additionalObservation: additionalObservation.value,
      },
      authStore.user?.id,
    );
  }
  resetBlankForm();
  if (router) {
    router.push(`/evaluate/${targetId}`).catch(() => {});
  } else {
    await evaluationStore.selectCourseForEvaluation(targetId);
    hydrateSectionDraft(targetId);
    formOpenedAt.value = Date.now();
  }
}

function resetBlankForm() {
  ratings.value = Array(25).fill(null);
  commentText.value = '';
  threeWords.value = ['', '', ''];
  improvementTopics.value = ['', '', ''];
  teachingStrengths.value = '';
  courseRecommendations.value = '';
  showFifthQuestion.value = false;
  additionalObservation.value = '';
  statusBanner.value = null;
  networkFailureDetail.value = '';
  formOpenedAt.value = Date.now();
}

function hydrateSectionDraft(sectionId) {
  if (!sectionId) return;
  const saved = loadSurveyDraft(sectionId, authStore.user?.id);
  if (!saved) {
    draftSavedLabel.value = 'Autosave ready';
    return;
  }
  hydratingDraft = true;
  if (Array.isArray(saved.ratings) && saved.ratings.length === 25) {
    ratings.value = saved.ratings.map((v) => (Number.isInteger(v) && v >= 1 && v <= 5 ? v : null));
  }
  commentText.value = String(saved.commentText || '');
  threeWords.value = Array.isArray(saved.threeWords) && saved.threeWords.length === 3 ? [...saved.threeWords] : ['', '', ''];
  improvementTopics.value =
    Array.isArray(saved.improvementTopics) && saved.improvementTopics.length === 3
      ? [...saved.improvementTopics]
      : ['', '', ''];
  teachingStrengths.value = String(saved.teachingStrengths || '');
  courseRecommendations.value = String(saved.courseRecommendations || '');
  showFifthQuestion.value = Boolean(saved.showFifthQuestion);
  additionalObservation.value = String(saved.additionalObservation || '');
  draftSavedLabel.value = 'Draft restored from session';
  setTimeout(() => {
    hydratingDraft = false;
  }, 50);
}

const persistDraftDebounced = debounce(() => {
  if (hydratingDraft || !activeSectionId.value) return;
  const ok = saveSurveyDraft(activeSectionId.value, {
    ratings: ratings.value,
    commentText: commentText.value,
    threeWords: threeWords.value,
    improvementTopics: improvementTopics.value,
    teachingStrengths: teachingStrengths.value,
    courseRecommendations: courseRecommendations.value,
    showFifthQuestion: showFifthQuestion.value,
    additionalObservation: additionalObservation.value,
  }, authStore.user?.id);
  if (ok) {
    const now = new Date();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    draftSavedLabel.value = `Draft saved (${hh}:${mm})`;
  }
}, 250);

watch(
  [ratings, commentText, threeWords, improvementTopics, teachingStrengths, courseRecommendations, showFifthQuestion, additionalObservation],
  () => {
    persistDraftDebounced();
  },
  { deep: true },
);

function confirmDiscardDraft() {
  if (activeSectionId.value) {
    clearSurveyDraft(activeSectionId.value, authStore.user?.id);
  }
  resetBlankForm();
  draftSavedLabel.value = 'Draft cleared';
  showDiscardDraftModal.value = false;
}

onMounted(async () => {
  await evaluationStore.fetchEvaluationWindow();
  if (!evaluationStore.courses || evaluationStore.courses.length === 0) {
    await evaluationStore.fetchQueue();
  }
  const sectionParam = Number(route?.params?.courseSectionId);
  const targetId =
    Number.isFinite(sectionParam) && sectionParam > 0
      ? sectionParam
      : evaluationStore.courses.find((c) => c.status === 'PENDING')?.course_section_id ||
        evaluationStore.courses[0]?.course_section_id ||
        null;

  await evaluationStore.selectCourseForEvaluation(targetId);
  hydrateSectionDraft(activeSectionId.value);
  formOpenedAt.value = Date.now();
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
  }
});

onUnmounted(() => {});

watch(
  () => route?.params?.courseSectionId,
  async (newId) => {
    const parsed = Number(newId);
    if (Number.isFinite(parsed) && parsed > 0) {
      resetBlankForm();
      await evaluationStore.selectCourseForEvaluation(parsed);
      hydrateSectionDraft(parsed);
      formOpenedAt.value = Date.now();
    }
  },
);

const formattedDeadline = computed(() => {
  const endsAt = evaluationStore.evaluationWindow?.ends_at;
  if (!endsAt) return 'Open for AY 2026-2027 (Asia/Manila)';
  try {
    const d = new Date(endsAt);
    return isNaN(d.getTime())
      ? endsAt
      : d.toLocaleString('en-US', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' }) + ' (Asia/Manila)';
  } catch (_) {
    return endsAt;
  }
});

const answeredRatings = computed(() =>
  ratings.value.filter((val) => Number.isInteger(val) && val >= 1 && val <= 5),
);
const answeredCount = computed(() => answeredRatings.value.length);
const allRatingsAnswered = computed(() => answeredCount.value === 25);

const currentMean = computed(() =>
  answeredRatings.value.length > 0 ? calculateMean(answeredRatings.value) : 0,
);
const currentVariance = computed(() =>
  allRatingsAnswered.value ? calculatePopulationVariance(ratings.value) : 0,
);
const clusterMeans = computed(() => {
  const safe = ratings.value.map((v) => (Number.isInteger(v) && v >= 1 && v <= 5 ? v : 0));
  return calculateClusterMeans(safe);
});
const straightLined = computed(
  () => allRatingsAnswered.value && isStraightLined(ratings.value),
);

const activeProfQuestionCount = computed(() => (showFifthQuestion.value ? 5 : 4));

const professorQuestionsValidation = computed(() =>
  validateProfessorQuestions({
    threeWords: threeWords.value,
    improvementTopics: improvementTopics.value,
    teachingStrengths: teachingStrengths.value,
    recommendations: courseRecommendations.value,
    additionalObservation: showFifthQuestion.value ? additionalObservation.value : '',
  }),
);

function getDimensionAnsweredCount(dimension) {
  return dimension.items.filter((item) => {
    const val = ratings.value[item.id - 1];
    return Number.isInteger(val) && val >= 1 && val <= 5;
  }).length;
}

function getDimensionMean(dimension) {
  const vals = dimension.items
    .map((item) => ratings.value[item.id - 1])
    .filter((v) => Number.isInteger(v) && v >= 1 && v <= 5);
  return vals.length > 0 ? calculateMean(vals) : null;
}


function toggleFifthQuestion() {
  showFifthQuestion.value = !showFifthQuestion.value;
  if (!showFifthQuestion.value) {
    additionalObservation.value = '';
  }
}

async function performInitialSubmit() {
  statusBanner.value = null;
  networkFailureDetail.value = '';
  stepValidationMessage.value = '';

  if (!allRatingsAnswered.value) {
    currentStep.value = 1;
    const remaining = 25 - answeredCount.value;
    stepValidationMessage.value = `Please rate all 25 pedagogical items across the five clusters (${remaining} remaining) before submitting.`;
    statusBanner.value = {
      type: 'error',
      code: 'INCOMPLETE_RATINGS',
      message: stepValidationMessage.value,
    };
    showJQueryUiError(stepValidationMessage.value, 'Incomplete Ratings', 4000);
    scrollToFirstUnanswered();
    return;
  }

  if (straightLined.value) {
    showStraightLineModal.value = true;
    return;
  }

  await executeSubmission(false);
}

const handleInitialSubmit = throttle(() => {
  if (!evaluationStore.submitting) {
    performInitialSubmit();
  }
}, 600);

async function confirmStraightLineSubmit() {
  showStraightLineModal.value = false;
  await executeSubmission(true);
}

async function executeSubmission(confirmUniform = false) {
  networkFailureDetail.value = '';
  stepValidationMessage.value = '';
  const elapsed = Math.max(0, Math.floor((Date.now() - formOpenedAt.value) / 1000));
  const response = await evaluationStore.submitEvaluation({
    courseSectionId: activeSectionId.value || evaluationStore.activeCourse?.course_section_id,
    ratings: ratings.value,
    commentText: commentText.value,
    professorQuestions: {
      three_words: threeWords.value.map((w) => w.trim()).filter(Boolean),
      room_for_improvements: improvementTopics.value
        .map((t) => String(t).trim())
        .filter((t) => t.length > 0),
      teaching_strengths: teachingStrengths.value.trim(),
      recommendations: courseRecommendations.value.trim(),
      additional_observation: showFifthQuestion.value ? additionalObservation.value.trim() : null,
    },
    dwellTimeSeconds: elapsed,
    confirmUniform,
    idempotencyKey: evaluationStore.submissionKey,
  });

  if (!response.ok) {
    if (response.code === 'ZERO_VARIANCE_CONFIRMATION_REQUIRED') {
      showStraightLineModal.value = true;
      return;
    }
    if (response.code === 'NETWORK_ERROR' || response.status === 503) {
      networkFailureDetail.value = response.message || 'Server temporarily unreachable.';
      stepValidationMessage.value = networkFailureDetail.value;
      showJQueryUiError(networkFailureDetail.value, 'Network Error', 4000);
      return;
    }
    stepValidationMessage.value = response.message || 'Submission was rejected by the server.';
    statusBanner.value = {
      type: 'error',
      code: response.code || 'SUBMISSION_REJECTED',
      message: response.message,
    };
    showJQueryUiError(stepValidationMessage.value, 'Submission Error', 4000);
    return;
  }

  if (activeSectionId.value) {
    clearSurveyDraft(activeSectionId.value, authStore.user?.id);
    draftSavedLabel.value = 'Submitted & cleared';
  }

  statusBanner.value = {
    type: 'success',
    code: 'CLEARANCE_ISSUED',
    title: 'Thank you — your evaluation was submitted.',
    message:
      'Your answers are anonymous. Your professor and school staff can see that you finished, but never which answers are yours.',
    token: response.clearance_token,
  };

  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function returnToQueue() {
  if (router) {
    router.push('/queue').catch(() => {});
  }
}
</script>

<template>
  <main class="workspace-main evaluation-form-workspace" aria-labelledby="eval-form-heading">
    <ErrorRecoveryBanner
      :visible="Boolean(networkFailureDetail)"
      title="Couldn't save changes"
      message="Your work is safe. Check your connection."
      :detail="networkFailureDetail"
      :retrying="evaluationStore.submitting"
      @retry="executeSubmission(straightLined)"
      @dismiss="networkFailureDetail = ''"
    />

    <!-- Removed enrolled-subjects-switcher per user request -->

    <section class="card eval-header-card">
      <div class="eval-top-bar">
        <button
          type="button"
          class="btn-ghost btn-sm eval-back-btn"
          data-testid="top-back-to-courses-btn"
          @click="returnToQueue"
        >
          <AppIcon name="arrow-left" /> My evaluations
        </button>
        <button
          type="button"
          class="btn-secondary btn-xs"
          data-testid="eval-open-rubric-btn"
          @click="showRubricModal = true"
        >
          <AppIcon name="clipboard" /> Rating guide
        </button>
      </div>

      <div class="eval-course-banner">
        <div class="eval-course-id">
          <span class="code-plate is-large">{{ evaluationStore.activeCourse?.course_code || 'CS-401' }}</span>
          <div>
            <h1 id="eval-form-heading" class="eval-course-title">
              {{ evaluationStore.activeCourse?.course_title || 'Academic Course Section' }}
            </h1>
            <p class="eval-course-meta">
              <span><b>{{ evaluationStore.activeCourse?.instructor_name || 'Assigned Instructor' }}</b></span>
              <span v-if="evaluationStore.activeCourse?.section_code">Section {{ evaluationStore.activeCourse.section_code }}</span>
              <span>{{ evaluationStore.evaluationWindow?.term_name || '1st Semester AY 2026-2027' }}</span>
              <span>Closes <b>{{ formattedDeadline }}</b></span>
            </p>
          </div>
        </div>

        <div class="eval-header-tools">
          <div v-show="false" class="autosave-pill tabular-nums" data-testid="draft-autosave-badge" style="display: none !important;">
            <span class="autosave-dot" aria-hidden="true"></span>
            <span>{{ draftSavedLabel }}</span>
            <button
              v-if="answeredCount > 0 || commentText.trim().length > 0"
              type="button"
              class="discard-draft-link"
              data-testid="discard-draft-btn"
              @click="showDiscardDraftModal = true"
            >
              Clear
            </button>
          </div>

          <div v-show="false" class="idempotency-box" style="display: none !important;">
            <span class="token-label">FT-02 Submission Key:</span>
            <code data-testid="submission-key-code">{{ evaluationStore.submissionKey || 'Initializing…' }}</code>
          </div>
        </div>
      </div>

      <ol v-if="!isCurrentCourseCompleted" class="step-indicator-bar" aria-label="Evaluation steps">
        <li>
          <button
            type="button"
            class="step-tab-btn"
            :class="{ 'is-active': currentStep === 1, 'is-done': answeredCount === 25 }"
            :aria-current="currentStep === 1 ? 'step' : undefined"
            @click="currentStep = 1"
          >
            <span class="step-number" aria-hidden="true"><AppIcon v-if="answeredCount === 25 && currentStep !== 1" name="check" :size="14" /><template v-else>1</template></span>
            <span class="step-label">Ratings <span class="tabular-nums step-count">{{ answeredCount }}/25</span></span>
          </button>
        </li>
        <li>
          <button
            type="button"
            class="step-tab-btn"
            :class="{ 'is-active': currentStep === 2 }"
            :aria-current="currentStep === 2 ? 'step' : undefined"
            @click="proceedToStep2"
          >
            <span class="step-number" aria-hidden="true">2</span>
            <span class="step-label">Written feedback</span>
          </button>
        </li>
      </ol>
    </section>



    <div
      v-if="statusBanner && statusBanner.type === 'success'"
      class="alert-banner alert-success"
      role="alert"
      data-testid="submission-status-banner"
    >
      <div>
        <strong>{{ statusBanner.title || statusBanner.code }}</strong> {{ statusBanner.message }}
        <div v-if="statusBanner.token" style="margin-top: 0.45rem;">
          <span>Receipt code: </span>
          <code class="clearance-token-code" data-testid="issued-clearance-token">{{ statusBanner.token }}</code>
        </div>
      </div>
      <div v-if="statusBanner.type === 'success'" class="submission-success-actions">
        <button
          v-if="nextPendingCourse"
          type="button"
          class="btn-primary btn-sm"
          data-testid="proceed-next-professor-btn"
          @click="switchCourseSection(nextPendingCourse.course_section_id)"
        >
          Next: {{ nextPendingCourse.course_code }} — {{ nextPendingCourse.instructor_name }} <AppIcon name="arrow-right" />
        </button>
        <button
          type="button"
          class="btn-secondary btn-sm"
          data-testid="print-course-receipt-btn"
          @click="openReceiptModal"
        >
          <AppIcon name="file" /> Print Receipt
        </button>
        <button
          type="button"
          class="btn-secondary btn-sm"
          data-testid="return-queue-success-btn"
          @click="returnToQueue"
        >
          Back to my evaluations
        </button>
      </div>
    </div>

    <!-- Already Completed State Card -->
    <section
      v-if="isCurrentCourseCompleted && !statusBanner"
      class="card course-already-completed-card"
      data-testid="course-already-completed"
    >
      <div class="completion-icon-badge" aria-hidden="true"><AppIcon name="check" /></div>
      <h3 class="section-title">
        You already evaluated {{ evaluationStore.activeCourse?.course_code }}
      </h3>
      <p class="section-subtitle">
        Your evaluation for <strong>{{ evaluationStore.activeCourse?.instructor_name }}</strong> was submitted anonymously. Submitted evaluations can't be changed.
      </p>
      <div v-if="evaluationStore.activeCourse?.clearance_token" class="clearance-token-strip">
        <span>Receipt code:</span>
        <code class="clearance-token-code">{{ evaluationStore.activeCourse.clearance_token }}</code>
      </div>
      <div class="completion-actions">
        <button
          v-if="nextPendingCourse"
          type="button"
          class="btn-primary"
          @click="switchCourseSection(nextPendingCourse.course_section_id)"
        >
          Next: {{ nextPendingCourse.course_code }} — {{ nextPendingCourse.instructor_name }}
        </button>
        <button
          type="button"
          class="btn-secondary"
          data-testid="print-completed-course-receipt-btn"
          @click="openReceiptModal"
        >
          <AppIcon name="file" /> Print Receipt
        </button>
        <button
          type="button"
          class="btn-secondary"
          @click="returnToQueue"
        >
          View All Courses
        </button>
      </div>
    </section>

    <form v-if="!isCurrentCourseCompleted" class="evaluation-survey-form" @submit.prevent="handleInitialSubmit">
      <!-- Part I: 5 Clusters of 5-Point Likert Scale Questionnaire (Step 1) -->
      <div v-show="currentStep === 1" class="step-1-container">
        <!-- Cluster Quick-Navigation & Progress Pills Strip -->
        <div class="cluster-quicknav-bar" data-testid="cluster-quicknav-bar">
          <div class="quicknav-header-strip">
            <span class="quicknav-label">Jump to a section</span>
            <span class="quicknav-count tabular-nums"><strong>{{ answeredCount }}</strong> of 25 rated</span>
          </div>
          <div class="quicknav-pills-row" role="navigation" aria-label="Jump to pedagogical clusters">
            <button
              v-for="dim in PEDAGOGICAL_DIMENSIONS"
              :key="`qnav-${dim.id}`"
              type="button"
              class="quicknav-pill"
              :class="{ 'is-complete': getDimensionAnsweredCount(dim) === 5 }"
              :data-testid="`quicknav-cluster-${dim.clusterNumber}`"
              :title="`Scroll to Cluster ${dim.clusterNumber}: ${dim.name} (${getDimensionAnsweredCount(dim)}/5 answered)`"
              @click="scrollToCluster(dim.clusterNumber)"
            >
              <span class="qnav-num">{{ dim.clusterNumber }}</span>
              <span class="qnav-name">{{ dim.name }}</span>
              <span class="qnav-badge tabular-nums">
                {{ getDimensionAnsweredCount(dim) === 5 ? '5/5' : `${getDimensionAnsweredCount(dim)}/5` }}
              </span>
            </button>
          </div>
        </div>

        <section
          v-for="dimension in PEDAGOGICAL_DIMENSIONS"
          :key="dimension.id"
          class="card dimension-card"
          :data-testid="`likert-cluster-${dimension.clusterNumber}`"
        >
          <fieldset class="dimension-fieldset">
            <legend class="dimension-legend">
              <span class="dimension-name tabular-nums">
                {{ dimension.clusterNumber }}. {{ dimension.name }}
                <span class="ui-badge-count">{{ getDimensionAnsweredCount(dimension) }} / 5</span>
                <span class="sr-only">({{ getDimensionAnsweredCount(dimension) }}/5)</span>
              </span>
              <small class="dimension-desc">1 = Strongly Disagree · 2 = Disagree · 3 = Neutral · 4 = Agree · 5 = Strongly Agree</small>
            </legend>

            <div
              v-for="item in dimension.items"
              :key="item.id"
              class="likert-item-row"
              :data-testid="`likert-row-${item.id}`"
            >
              <div class="likert-question">
                <span class="item-code">{{ item.code }}</span>
                <span class="item-text">{{ item.text }}</span>
              </div>

              <div
                class="likert-scale-group"
                role="radiogroup"
                :aria-label="`Question ${item.id}: ${item.text}`"
              >
                <label
                  v-for="option in LIKERT_SCALE_OPTIONS"
                  :key="option.value"
                  class="likert-radio-label"
                  :class="{ selected: ratings[item.id - 1] === option.value }"
                  :title="`${option.value} — ${option.label}`"
                >
                  <input
                    v-model.number="ratings[item.id - 1]"
                    type="radio"
                    :name="`item-${item.id}`"
                    :value="option.value"
                    :aria-label="`Rate ${option.value} (${option.label}) out of 5 for ${item.code}`"
                  />
                  <span>{{ option.value }}</span>
                </label>
              </div>
              <div class="likert-anchors" aria-hidden="true"><span>Strongly disagree</span><span>Strongly agree</span></div>
            </div>
          </fieldset>
        </section>

        <!-- Step 1 Bottom Action Row -->
        <div class="step-navigation-bar" style="margin-top: 1.25rem;">
          <div
            v-if="stepValidationMessage && currentStep === 1"
            class="sr-only"
            data-testid="step-1-validation-error"
          >
            {{ stepValidationMessage }}
          </div>

          <div style="display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <button
              type="button"
              class="btn-primary"
              style="padding: 0.65rem 1.45rem; font-weight: 700;"
              data-testid="proceed-to-feedback-btn"
              @click="proceedToStep2"
            >
              Next: written feedback <AppIcon name="arrow-right" />
            </button>
          </div>
        </div>
      </div>

      <!-- Part II: 4 Structured Questions Regarding the Professor & Additional Comments (Step 2 - "this should be a different page") -->
      <div v-show="currentStep === 2" class="step-2-container">
        <section class="card prof-questions-card" data-testid="professor-questions-section" style="text-align: left;">
          <div class="prof-questions-header" style="text-align: left;">
            <div>
              <h3 class="section-title" style="text-align: left;">
                Questions About Your Professor
                <span class="ui-hint" tabindex="0" aria-label="Language options" data-hint="Share honest, constructive feedback in English, Tagalog, or Taglish.">?</span>
              </h3>
            </div>
            <button
              type="button"
              class="btn-secondary btn-xs"
              data-testid="toggle-fifth-question-btn"
              @click="toggleFifthQuestion"
            >
              {{
                showFifthQuestion
                  ? 'Hide 5th Question'
                  : '+ Optional 5th Question'
              }}
            </button>
          </div>

          <!-- Question 1: 3 words that describe the experience with the prof -->
          <div class="prof-question-block" data-testid="prof-question-1" style="text-align: left;">
            <label class="prof-question-label" style="text-align: left; display: block; font-weight: 700; margin-bottom: 0.4rem;">
              1. Three words that describe your learning experience with the professor:
            </label>
            <div class="three-words-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.65rem;">
              <input
                v-model="threeWords[0]"
                type="text"
                maxlength="60"
                class="form-input"
                placeholder="Word 1 (e.g., Malinaw / Clear)"
                aria-label="Descriptor Word 1"
                data-testid="three-word-1"
                style="text-align: left;"
              />
              <input
                v-model="threeWords[1]"
                type="text"
                maxlength="60"
                class="form-input"
                placeholder="Word 2 (e.g., Approachable)"
                aria-label="Descriptor Word 2"
                data-testid="three-word-2"
                style="text-align: left;"
              />
              <input
                v-model="threeWords[2]"
                type="text"
                maxlength="60"
                class="form-input"
                placeholder="Word 3 (e.g., Organized)"
                aria-label="Descriptor Word 3"
                data-testid="three-word-3"
                style="text-align: left;"
              />
            </div>
          </div>

          <!-- Question 2: Constructive areas for instructional improvement (3 fixed topics) ALL LEFT ALIGNED -->
          <div class="prof-question-block" data-testid="prof-question-2" style="text-align: left; margin-top: 1.25rem;">
            <div class="prof-question-subhead" style="text-align: left; margin-bottom: 0.65rem;">
              <label class="prof-question-label" style="text-align: left; display: block; font-weight: 700;">
                2. Constructive areas for instructional improvement:
              </label>
              <span class="sub-topic-hint" style="font-size: 0.78rem; text-align: left; display: block">
                Answer the three key instructional areas below:
              </span>
            </div>

            <div
              v-for="(topic, idx) in improvementTopicPrompts"
              :key="`imp-topic-${idx}`"
              class="improvement-topic-row"
              :data-testid="`improvement-topic-row-${idx}`"
              style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; gap: 0.35rem; margin-bottom: 0.85rem;"
            >
              <label :for="`imp-topic-input-${idx}`" class="sub-topic-badge" style="width: fit-content; margin: 0 0 0.2rem 0; text-align: left; align-self: flex-start;">
                {{ topic.title }}
              </label>
              <input
                :id="`imp-topic-input-${idx}`"
                v-model="improvementTopics[idx]"
                type="text"
                maxlength="300"
                class="form-input"
                :placeholder="topic.placeholder"
                :aria-label="topic.title"
                style="text-align: left; width: 100%;"
              />
            </div>
          </div>

          <!-- Question 3: Key teaching strengths of the prof -->
          <div class="prof-question-block" data-testid="prof-question-3" style="text-align: left; margin-top: 1.25rem;">
            <label class="prof-question-label" for="prof-q3-input" style="text-align: left; display: block; font-weight: 700; margin-bottom: 0.4rem;">
              3. Most effective teaching strategies or strengths of the professor:
            </label>
            <input
              id="prof-q3-input"
              v-model="teachingStrengths"
              type="text"
              maxlength="500"
              class="form-input"
              placeholder="Describe what the professor does well during lectures or laboratory sessions…"
              style="text-align: left; width: 100%;"
            />
          </div>

          <!-- Question 4: Actionable recommendations for the prof -->
          <div class="prof-question-block" data-testid="prof-question-4" style="text-align: left; margin-top: 1.25rem;">
            <label class="prof-question-label" for="prof-q4-input" style="text-align: left; display: block; font-weight: 700; margin-bottom: 0.4rem;">
              4. Actionable recommendations for future offerings of this course:
            </label>
            <input
              id="prof-q4-input"
              v-model="courseRecommendations"
              type="text"
              maxlength="500"
              class="form-input"
              placeholder="Share constructive suggestions for syllabus pacing, assessments, or resources…"
              style="text-align: left; width: 100%;"
            />
          </div>

          <!-- Optional Question 5 (Capped at Max 5 Questions) -->
          <div
            v-if="showFifthQuestion"
            class="prof-question-block"
            data-testid="prof-question-5"
            style="text-align: left; margin-top: 1.25rem;"
          >
            <label class="prof-question-label" for="prof-q5-input" style="text-align: left; display: block; font-weight: 700; margin-bottom: 0.4rem;">
              5. Additional observation on consultation &amp; mentorship support:
            </label>
            <input
              id="prof-q5-input"
              v-model="additionalObservation"
              type="text"
              maxlength="500"
              class="form-input"
              placeholder="Optional observation regarding consultation hours, advising, or student mentorship…"
              style="text-align: left; width: 100%;"
            />
          </div>
        </section>

        <!-- Part III: Overall Narrative Synthesis -->
        <section class="card narrative-card" style="text-align: left; margin-top: 1.25rem;">
          <div class="narrative-head-row" style="text-align: left;">
            <div>
              <h3 class="section-title" style="text-align: left;">
                Additional Comments
                <span class="ui-hint" tabindex="0" aria-label="Anonymity note" data-hint="Optional overall remarks about this course. Your name is never attached to your comments.">?</span>
              </h3>
            </div>
            <span class="char-counter tabular-nums" data-testid="narrative-char-counter">
              {{ commentText.length }} / 2000
            </span>
          </div>

          <label class="sr-only" for="narrative-comment-textarea">Narrative Evaluation Feedback</label>
          <textarea
            id="narrative-comment-textarea"
            v-model="commentText"
            rows="4"
            maxlength="2000"
            class="narrative-textarea"
            placeholder="Write constructive feedback in English, Tagalog, or Taglish…"
            data-testid="narrative-comment-input"
            style="text-align: left; width: 100%;"
          ></textarea>

          <div class="form-submit-footer" style="margin-top: 1.25rem;">
            <div
              v-if="stepValidationMessage && currentStep === 2"
              class="sr-only"
              data-testid="submit-bottom-alert"
            >
              {{ stepValidationMessage }}
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 0.75rem;">
              <button
                type="button"
                class="btn-secondary"
                @click="returnToStep1"
              >
                <AppIcon name="arrow-left" /> Back to ratings
              </button>

              <button
                type="submit"
                class="btn-primary"
                style="padding: 0.65rem 1.65rem; font-weight: 700"
                :disabled="evaluationStore.submitting"
                data-testid="submit-evaluation-btn"
              >
                {{
                  evaluationStore.submitting
                    ? 'Submitting to Vault…'
                    : 'Submit Evaluation'
                }}
              </button>
            </div>
          </div>
        </section>
      </div>
    </form>

    <!-- All Courses Evaluated Completion Notice -->
    <section
      v-if="progress.allCleared"
      class="card all-courses-completed-card"
      data-testid="all-evaluations-completed-card"
      style="text-align: center; padding: 2.25rem 1.5rem; margin-top: 1.25rem"
    >
      <div class="completion-icon-badge" aria-hidden="true" style="margin: 0 auto 0.75rem;"><AppIcon name="check" /></div>
      <h3 class="section-title">All Course Evaluations Completed</h3>
      <p class="section-subtitle">
        You have successfully submitted evaluations for all <strong>{{ progress.total }} enrolled courses</strong>.
      </p>
      <p style="font-size: 0.88rem; max-width: 620px; margin: 0.75rem auto 0; line-height: 1.6">
        Thank you for taking the time to share your feedback. Your ratings and written comments have been recorded and cryptographically decoupled to support instructional excellence at School AAAAA13.
      </p>
    </section>

    <StraightLineModal
      :visible="showStraightLineModal"
      :uniform-rating="ratings[0] || 5"
      :variance="currentVariance"
      @cancel="showStraightLineModal = false"
      @confirm="confirmStraightLineSubmit"
    />

    <DestructiveConfirmModal
      :visible="showDiscardDraftModal"
      title="Discard In-Progress SET Draft?"
      description="Clearing this draft resets all 25 Likert ratings and qualitative responses for this course section."
      impact-note="Your session clearance state is unaffected; only unsaved local form inputs will be cleared."
      confirm-label="Discard Draft"
      @cancel="showDiscardDraftModal = false"
      @confirm="confirmDiscardDraft"
    />

    <!-- Modal: Official Student Evaluation Clearance Receipt -->
    <div
      v-if="showReceiptModal"
      class="modal-backdrop"
      style="position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem"
      data-testid="student-clearance-receipt-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="receipt-title"
      @click.self="closeReceiptModal"
    >
      <div
        class="modal-card print-receipt-card"
        style="max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem"
      >
        <!-- Institutional Header -->
        <div style="text-align: center; padding-bottom: 1.25rem; margin-bottom: 1.5rem">
          <div style="font-size: 0.8rem; font-weight: 700">
            School AAAAA13
          </div>
          <div style="font-size: 0.72rem; margin-top: 0.15rem">
            Office of Academic Affairs &amp; Office of the University Registrar
          </div>
          <h3 id="receipt-title" style="font-size: 1.2rem; font-weight: 800; margin: 0.75rem 0 0">
            Evaluation Completion Receipt / Submission Confirmation
          </h3>
          <div style="font-size: 0.78rem; margin-top: 0.25rem">
            Official Electronic Proof of Evaluation Completion
          </div>
        </div>

        <!-- Clearance Details Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
          <div>
            <span style="display: block; font-size: 0.72rem; font-weight: 700">Course Offering</span>
            <strong style="font-size: 0.95rem">{{ evaluationStore.activeCourse?.course_code || 'CS-301' }}</strong>
            <div style="font-size: 0.8rem">{{ evaluationStore.activeCourse?.course_title || 'Enrolled Course' }}</div>
          </div>
          <div>
            <span style="display: block; font-size: 0.72rem; font-weight: 700">Instructor</span>
            <strong style="font-size: 0.95rem">{{ evaluationStore.activeCourse?.instructor_name || 'Assigned Faculty' }}</strong>
            <div style="font-size: 0.8rem">Section: {{ evaluationStore.activeCourse?.section_code || 'SEC-A' }}</div>
          </div>
          <div>
            <span style="display: block; font-size: 0.72rem; font-weight: 700">Academic Term</span>
            <span style="font-weight: 600">{{ authStore.activeTerm || '1st Semester AY 2026-2027' }}</span>
          </div>
          <div>
            <span style="display: block; font-size: 0.72rem; font-weight: 700">Clearance Status</span>
            <span class="status-badge status-completed" style="font-size: 0.75rem; padding: 0.2rem 0.6rem;">
              <AppIcon name="check" /> OFFICIALLY CLEARED
            </span>
          </div>
        </div>

        <!-- Cryptographic Token Strip -->
        <div style="padding: 1rem; margin-bottom: 1.5rem">
          <div style="font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem">
            Cryptographic Clearance Signature Token
          </div>
          <code
            style="display: block; font-size: 0.85rem; font-weight: 700; word-break: break-all; padding: 0.5rem 0.75rem"
            data-testid="receipt-clearance-token"
          >
            {{ statusBanner?.token || evaluationStore.activeCourse?.clearance_token || 'CLR-2026-STUDENT-VERIFIED' }}
          </code>
          <div style="font-size: 0.72rem; margin-top: 0.5rem; line-height: 1.4">
            <AppIcon name="lock" /> <strong>Dual-Token Anonymity Guaranteed:</strong> This token proves your evaluation requirement has been satisfied without linking your student identity to specific rating scores or written comments.
          </div>
        </div>

        <!-- Verification Signature Block -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 1.25rem; margin-bottom: 1.5rem; font-size: 0.78rem">
          <div>
            <div style="font-weight: 700">SCHOOL AAAAA13</div>
            <div >Office of Academic Affairs</div>
            <div style="margin-top: 0.2rem">Generated: {{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</div>
          </div>
          <div style="text-align: right;">
            <div style="font-weight: 700; padding-bottom: 0.2rem; min-width: 170px; text-align: center">
              OFFICE OF THE REGISTRAR
            </div>
            <div style="font-size: 0.72rem; margin-top: 0.2rem; text-align: center">
              Electronic Validation Stamp
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; padding-top: 1.25rem; gap: 0.5rem; flex-wrap: wrap">
          <button
            type="button"
            class="btn-secondary btn-sm"
            @click="closeReceiptModal"
          >
            Close
          </button>
          <button
            type="button"
            class="btn-primary btn-sm"
            data-testid="print-receipt-action-btn"
            @click="printCourseReceipt"
          >
            <AppIcon name="printer" /> Print / Save as PDF
          </button>
        </div>
      </div>
    </div>
    <!-- Accredited Evaluation Rubric Modal -->
    <EvaluationRubricModal
      :visible="showRubricModal"
      role-title="Student"
      @close="showRubricModal = false"
    />
  </main>
</template>

<style scoped>
.cluster-quicknav-bar {
  position: sticky;
  top: 60px;
  z-index: 20;
  margin-bottom: 16px;
  padding: 10px 14px;
  background: rgba(237, 242, 251, 0.96);
  border: 1px solid var(--wf-hair);
  border-radius: var(--wf-radius);
  backdrop-filter: saturate(1.2);
}

.quicknav-header-strip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  font-size: 0.85rem;
  color: var(--wf-ink-2);
}

.quicknav-label { font-weight: 700; color: var(--wf-ink-2); }
.quicknav-count strong { color: var(--wf-ink); }

.quicknav-pills-row {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
}

.quicknav-pills-row::-webkit-scrollbar { display: none; }

.quicknav-pill {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  padding: 4px 10px;
  border: 1px solid var(--wf-hair);
  border-radius: 8px;
  background: var(--wf-panel);
  color: var(--wf-ink);
  font-size: 0.84rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.quicknav-pill:hover { border-color: var(--wf-mark); background: var(--wf-panel-2); }
.quicknav-pill.is-complete { border-color: #bfe3cb; background: var(--wf-done-bg); color: #0f3d22; }

.qnav-num { font-weight: 800; color: var(--wf-ink-3); }
.qnav-badge { font-size: 0.78rem; font-weight: 700; color: var(--wf-ink-3); }
.quicknav-pill.is-complete .qnav-badge { color: var(--wf-done); }

.eval-header-tools {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.45rem;
}

.autosave-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  font-size: 0.74rem;
  font-weight: 600;
  color: #176347;
}

.autosave-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #16a34a;
}

.discard-draft-link {
  border: 0;
  background: transparent;
  padding: 0 0 0 0.25rem;
  color: #b91c1c;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.narrative-head-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.65rem;
}

.char-counter {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.74rem;
  font-weight: 700;
  padding: 0.18rem 0.55rem;
  border-radius: 6px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  color: #3f5247;
}

@media (max-width: 768px) {
  .eval-header-tools {
    align-items: flex-start;
  }
}


.eval-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin: -8px -8px 14px;
}

.eval-course-id { display: flex; align-items: flex-start; gap: 16px; }
.eval-course-title { margin: 0; font-size: 1.5rem; font-weight: 800; line-height: 1.25; letter-spacing: -0.01em; }
.eval-course-meta { display: flex; flex-wrap: wrap; gap: 2px 16px; margin: 6px 0 0; color: var(--wf-ink-2); font-size: 0.92rem; }
.eval-course-meta b { color: var(--wf-ink); }

.step-indicator-bar {
  display: flex;
  gap: 8px;
  margin: 20px 0 0;
  padding: 16px 0 0;
  border-top: 1px solid var(--wf-tint);
  list-style: none;
}

.step-indicator-bar li { flex: 1 1 0; min-width: 0; }

.step-tab-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  padding: 8px 12px;
  border: 1px solid var(--wf-hair);
  border-radius: 8px;
  background: var(--wf-panel);
  color: var(--wf-ink-2);
  font-size: 0.92rem;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.step-tab-btn:hover { border-color: var(--wf-mark); }
.step-tab-btn.is-active { border-color: var(--wf-accent); color: var(--wf-ink); box-shadow: inset 0 0 0 1px var(--wf-accent); }

.step-tab-btn .step-number {
  display: grid;
  place-items: center;
  flex: none;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--wf-tint);
  color: var(--wf-ink);
  font-size: 0.82rem;
  font-weight: 800;
}

.step-tab-btn.is-active .step-number { background: var(--wf-accent); color: #ffffff; }
.step-tab-btn.is-done .step-number { background: var(--wf-done); color: #ffffff; }
.step-count { font-weight: 600; color: var(--wf-ink-3); margin-left: 4px; }

@media (max-width: 640px) {
  .eval-course-id { flex-direction: column; gap: 10px; }
  .eval-course-title { font-size: 1.25rem; }
  .step-tab-btn { padding: 8px 10px; font-size: 0.86rem; gap: 8px; }
  .cluster-quicknav-bar { padding: 8px 10px; }
}

.step-navigation-bar {
  padding: 1rem 0;
  border-top: 1px solid #edf2ed;
}

.improvement-topic-row {
  display: flex !important;
  flex-direction: column !important;
  align-items: flex-start !important;
  text-align: left !important;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}

.sub-topic-badge {
  display: inline-flex !important;
  align-self: flex-start !important;
  margin: 0 0 0.25rem 0 !important;
  text-align: left !important;
}

.prof-question-block {
  text-align: left !important;
}

.prof-question-label {
  text-align: left !important;
  display: block;
}

.prof-question-subhead {
  display: flex;
  align-items: flex-start;
  flex-direction: column;
  gap: 0.2rem;
  margin-bottom: 0.65rem;
  text-align: left;
}

@keyframes pulseUnanswered {
  0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); border-color: #dc2626; }
  70% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); border-color: #dc2626; }
  100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}

.pulse-unanswered {
  animation: pulseUnanswered 1.5s infinite;
  border-radius: 8px;
}

.submission-success-actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
  margin-top: 0.75rem;
}

.submission-success-actions > button,
.completion-actions > button {
  max-width: 100%;
  white-space: normal;
  text-align: left;
}

.course-already-completed-card {
  text-align: center;
  padding: 2.25rem 1.5rem;
  margin-top: 1rem;
  background: #ffffff;
  border: 1px solid #dce7dc;
  border-radius: 12px;
}

.completion-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 999px;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #047857;
  font-size: 1.4rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.85rem;
}

.clearance-token-strip {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.45rem 0.85rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  margin: 0.75rem 0 1.35rem;
  font-size: 0.82rem;
  color: #166534;
}

.completion-actions {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}
</style>
