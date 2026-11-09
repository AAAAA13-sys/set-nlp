<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DestructiveConfirmModal from '../components/DestructiveConfirmModal.vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import StraightLineModal from '../components/StraightLineModal.vue';
import { useEvaluationStore } from '../stores/evaluation.js';
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
const statusBanner = ref(null);
const networkFailureDetail = ref('');
const draftSavedLabel = ref('Autosave ready');

const formOpenedAt = ref(Date.now());
let hydratingDraft = false;

const activeSectionId = computed(
  () => evaluationStore.activeCourse?.course_section_id || Number(route?.params?.courseSectionId) || null,
);

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
  const sectionParam = Number(route?.params?.courseSectionId);
  await evaluationStore.selectCourseForEvaluation(
    Number.isFinite(sectionParam) && sectionParam > 0 ? sectionParam : null,
  );
  hydrateSectionDraft(activeSectionId.value);
  formOpenedAt.value = Date.now();
});

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

  if (!allRatingsAnswered.value) {
    const remaining = 25 - answeredCount.value;
    statusBanner.value = {
      type: 'error',
      code: 'INCOMPLETE_RATINGS',
      message: `Please rate all 25 pedagogical items across the five clusters (${remaining} item${remaining === 1 ? '' : 's'} remaining) before submitting.`,
    };
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
  const elapsed = Math.max(0, Math.floor((Date.now() - formOpenedAt.value) / 1000));
  const response = await evaluationStore.submitEvaluation({
    courseSectionId: evaluationStore.activeCourse?.course_section_id,
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
      return;
    }
    statusBanner.value = {
      type: 'error',
      code: response.code || 'SUBMISSION_REJECTED',
      message: response.message,
    };
    return;
  }

  if (activeSectionId.value) {
    clearSurveyDraft(activeSectionId.value, authStore.user?.id);
    draftSavedLabel.value = 'Submitted & cleared';
  }

  statusBanner.value = {
    type: 'success',
    code: 'CLEARANCE_ISSUED',
    message:
      'Your anonymized evaluation has been recorded in the decoupled vault and your course clearance token has been signed.',
    token: response.clearance_token,
  };
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

    <section class="card eval-header-card">
      <div class="eval-course-banner">
        <div>
          <span class="eyebrow-badge">COURSE EVALUATION</span>
          <h2 id="eval-form-heading" class="section-title">
            {{ evaluationStore.activeCourse?.course_code || 'CS-401' }} —
            {{ evaluationStore.activeCourse?.instructor_name || 'Assigned Instructor' }}
          </h2>
          <p class="section-subtitle">
            <strong>{{ evaluationStore.activeCourse?.course_title || 'Academic Course Section' }}</strong>
            <span v-if="evaluationStore.activeCourse?.section_code">
              · Section {{ evaluationStore.activeCourse.section_code }}
            </span>
            · Term: {{ evaluationStore.evaluationWindow?.term_name || '1st Semester AY 2026-2027' }}
          </p>
          <div class="eval-meta-deadline" style="font-size: 0.8rem; color: #4b5563; margin-top: 0.25rem;">
            <span>Submission Deadline: <strong>{{ formattedDeadline }}</strong></span>
          </div>
        </div>

        <div class="eval-header-tools">
          <div class="autosave-pill tabular-nums" data-testid="draft-autosave-badge">
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

          <div v-show="false" class="idempotency-box">
            <span class="token-label">FT-02 Submission Key:</span>
            <code data-testid="submission-key-code">{{ evaluationStore.submissionKey || 'Initializing…' }}</code>
          </div>
        </div>
      </div>

      <!-- Visible 5-Point Rating Scale Legend -->
      <div class="rating-scale-legend-card" role="region" aria-label="Rating Scale Legend" style="margin: 0.85rem 0; padding: 0.65rem 0.85rem; background: #f9fbf9; border: 1px solid #dce7dc; border-radius: 8px;">
        <span class="legend-title" style="font-size: 0.8rem; font-weight: 700; color: #103c2e; margin-right: 0.75rem;">Rating Scale Legend:</span>
        <span class="legend-items" style="display: inline-flex; gap: 0.6rem; flex-wrap: wrap; font-size: 0.78rem; color: #374151;">
          <span><strong>1</strong>: Strongly Disagree</span>
          <span>·</span>
          <span><strong>2</strong>: Disagree</span>
          <span>·</span>
          <span><strong>3</strong>: Neutral</span>
          <span>·</span>
          <span><strong>4</strong>: Agree</span>
          <span>·</span>
          <span><strong>5</strong>: Strongly Agree</span>
        </span>
      </div>

      <div class="live-metrics-strip tabular-nums" aria-label="Evaluation Progress">
        <div>
          <span>Answered:</span>
          <strong data-testid="live-answered-count"> {{ answeredCount }} / 25</strong>
        </div>
        <div>
          <span>Average:</span>
          <strong data-testid="live-mean-score">
            {{ answeredCount > 0 ? ` ${currentMean.toFixed(2)} / 5.00` : ' —' }}
          </strong>
        </div>
        <div class="sr-only">
          <span>Variance (σ²):</span>
          <strong data-testid="live-variance-score">
            {{ allRatingsAnswered ? ` ${currentVariance.toFixed(4)}` : ' —' }}
          </strong>
        </div>
        <div>
          <span>Status:</span>
          <strong :class="allRatingsAnswered ? 'text-success' : ''">
            {{ !allRatingsAnswered ? ` ${25 - answeredCount} remaining` : ' All 25 items rated' }}
          </strong>
        </div>
      </div>
    </section>

    <div
      v-if="statusBanner"
      class="alert-banner"
      :class="statusBanner.type === 'success' ? 'alert-success' : 'alert-error'"
      role="alert"
      data-testid="submission-status-banner"
    >
      <div>
        <strong>{{ statusBanner.code }}:</strong> {{ statusBanner.message }}
        <div v-if="statusBanner.token" style="margin-top: 0.45rem;">
          <span>Signed Clearance Token: </span>
          <code class="clearance-token-code" data-testid="issued-clearance-token">{{ statusBanner.token }}</code>
        </div>
      </div>
      <div v-if="statusBanner.type === 'success'">
        <button
          type="button"
          class="btn-primary btn-sm"
          data-testid="return-queue-success-btn"
          @click="returnToQueue"
        >
          Return to Evaluation Queue
        </button>
      </div>
    </div>

    <form class="evaluation-survey-form" @submit.prevent="handleInitialSubmit">
      <!-- Part I: 5 Clusters of 5-Point Likert Scale Questionnaire -->
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
          </div>
        </fieldset>
      </section>

      <!-- Part II: 4 Structured Questions Regarding the Professor (Max of 5 Questions) -->
      <section class="card prof-questions-card" data-testid="professor-questions-section">
        <div class="prof-questions-header">
          <div>
            <span class="eyebrow-badge">WRITTEN FEEDBACK</span>
            <h3 class="section-title">
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
        <div class="prof-question-block" data-testid="prof-question-1">
          <label class="prof-question-label">
            1. Three words that describe your learning experience with the professor:
          </label>
          <div class="three-words-grid">
            <input
              v-model="threeWords[0]"
              type="text"
              maxlength="60"
              class="form-input"
              placeholder="Word 1 (e.g., Malinaw / Clear)"
              aria-label="Descriptor Word 1"
              data-testid="three-word-1"
            />
            <input
              v-model="threeWords[1]"
              type="text"
              maxlength="60"
              class="form-input"
              placeholder="Word 2 (e.g., Approachable)"
              aria-label="Descriptor Word 2"
              data-testid="three-word-2"
            />
            <input
              v-model="threeWords[2]"
              type="text"
              maxlength="60"
              class="form-input"
              placeholder="Word 3 (e.g., Organized)"
              aria-label="Descriptor Word 3"
              data-testid="three-word-3"
            />
          </div>
        </div>

        <!-- Question 2: Constructive areas for instructional improvement (3 fixed topics) -->
        <div class="prof-question-block" data-testid="prof-question-2">
          <div class="prof-question-subhead">
            <label class="prof-question-label">
              2. Constructive areas for instructional improvement:
            </label>
            <span class="sub-topic-hint" style="font-size: 0.78rem; color: #53665a;">
              Answer the three key instructional areas below:
            </span>
          </div>

          <div
            v-for="(topic, idx) in improvementTopicPrompts"
            :key="`imp-topic-${idx}`"
            class="improvement-topic-row"
            :data-testid="`improvement-topic-row-${idx}`"
            style="display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.75rem;"
          >
            <label :for="`imp-topic-input-${idx}`" class="sub-topic-badge" style="width: fit-content; margin-bottom: 0.2rem;">
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
            />
          </div>
        </div>

        <!-- Question 3: Key teaching strengths of the prof -->
        <div class="prof-question-block" data-testid="prof-question-3">
          <label class="prof-question-label" for="prof-q3-input">
            3. Most effective teaching strategies or strengths of the professor:
          </label>
          <input
            id="prof-q3-input"
            v-model="teachingStrengths"
            type="text"
            maxlength="500"
            class="form-input"
            placeholder="Describe what the professor does well during lectures or laboratory sessions…"
          />
        </div>

        <!-- Question 4: Actionable recommendations for the prof -->
        <div class="prof-question-block" data-testid="prof-question-4">
          <label class="prof-question-label" for="prof-q4-input">
            4. Actionable recommendations for future offerings of this course:
          </label>
          <input
            id="prof-q4-input"
            v-model="courseRecommendations"
            type="text"
            maxlength="500"
            class="form-input"
            placeholder="Share constructive suggestions for syllabus pacing, assessments, or resources…"
          />
        </div>

        <!-- Optional Question 5 (Capped at Max 5 Questions) -->
        <div
          v-if="showFifthQuestion"
          class="prof-question-block"
          data-testid="prof-question-5"
        >
          <label class="prof-question-label" for="prof-q5-input">
            5. Additional observation on consultation &amp; mentorship support:
          </label>
          <input
            id="prof-q5-input"
            v-model="additionalObservation"
            type="text"
            maxlength="500"
            class="form-input"
            placeholder="Optional observation regarding consultation hours, advising, or student mentorship…"
          />
        </div>
      </section>

      <!-- Part III: Overall Narrative Synthesis -->
      <section class="card narrative-card">
        <div class="narrative-head-row">
          <div>
            <h3 class="section-title">
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
        ></textarea>

        <div class="form-submit-footer">
          <button
            type="button"
            class="btn-secondary"
            @click="returnToQueue"
          >
            Back to Courses
          </button>

          <button
            type="submit"
            class="btn-primary"
            :disabled="evaluationStore.submitting"
            data-testid="submit-evaluation-btn"
          >
            {{
              evaluationStore.submitting
                ? 'Submitting…'
                : 'Submit Evaluation'
            }}
          </button>
        </div>
      </section>
    </form>

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
  </main>
</template>

<style scoped>
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
</style>
