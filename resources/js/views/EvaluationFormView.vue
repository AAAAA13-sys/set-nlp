<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DwellTimerBadge from '../components/DwellTimerBadge.vue';
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
  classifyCommentPreview,
  isDwellTimeValid,
  isStraightLined,
  validateProfessorQuestions,
} from '../utils/evaluationMath.js';

const route = useRoute();
const router = useRouter();
const evaluationStore = useEvaluationStore();

// Default varied realistic ratings across the 5 clusters x 5 items = 25 items (5-point Likert scale 1..5)
const DEFAULT_VARIED_RATINGS = [
  5, 4, 5, 5, 4,
  4, 5, 4, 4, 5,
  5, 5, 4, 5, 4,
  5, 4, 5, 5, 5,
  5, 4, 5, 4, 5,
];

const ratings = ref([...DEFAULT_VARIED_RATINGS]);
const commentText = ref(
  'Explains complex algorithms very clearly and provides great practical code examples.',
);

// 4 Structured Questions Regarding the Professor (up to Max of 5 Questions)
const threeWords = ref(['Malinaw', 'Approachable', 'Organized']);
const improvementTopics = ref([
  'Faster turnaround time when returning quiz scores and rubrics',
  'More step-by-step live coding walkthroughs for dynamic programming',
]);
const teachingStrengths = ref(
  'Explains complex algorithms very clearly and provides great practical code examples.',
);
const courseRecommendations = ref(
  'Provide practice problem sets with rubric solutions prior to midterm exams.',
);
const showFifthQuestion = ref(false);
const additionalObservation = ref('');

const showStraightLineModal = ref(false);
const statusBanner = ref(null);

let timerHandle = null;

onMounted(() => {
  const sectionParam = Number(route?.params?.courseSectionId);
  if (Number.isFinite(sectionParam) && sectionParam > 0) {
    evaluationStore.selectCourseForEvaluation(sectionParam);
  }
  timerHandle = setInterval(() => {
    evaluationStore.incrementDwellTime(1);
  }, 1000);
});

onUnmounted(() => {
  if (timerHandle) {
    clearInterval(timerHandle);
    timerHandle = null;
  }
});

watch(
  () => route?.params?.courseSectionId,
  (newId) => {
    const parsed = Number(newId);
    if (Number.isFinite(parsed) && parsed > 0) {
      evaluationStore.selectCourseForEvaluation(parsed);
      statusBanner.value = null;
    }
  },
);

const currentMean = computed(() => calculateMean(ratings.value));
const currentVariance = computed(() => calculatePopulationVariance(ratings.value));
const clusterMeans = computed(() => calculateClusterMeans(ratings.value));
const straightLined = computed(() => isStraightLined(ratings.value));
const dwellValid = computed(() => isDwellTimeValid(evaluationStore.dwellSeconds, ratings.value.length, 45));
const nlpPreview = computed(() => classifyCommentPreview(commentText.value));

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

const sampleComments = [
  {
    label: 'Positive • Pedagogical Delivery (+0.78)',
    text: 'Explains complex algorithms very clearly and provides great practical code examples.',
  },
  {
    label: 'Negative • Assessment & Grading (-0.62)',
    text: 'Takes over a month to return quiz scores and provides no helpful feedback.',
  },
  {
    label: 'Negative • Classroom Management (-0.45)',
    text: 'Arrives to morning lectures 15 minutes late consistently.',
  },
  {
    label: 'Taglish (calamanCy) • Student Support & Mentorship',
    text: 'Sobrang matulungin sa consultation at laging available sa office hours para sa thesis guidance.',
  },
];

function setAllRatingsUniform(value = 5) {
  ratings.value = Array(25).fill(Number(value));
}

function setVariedRatings() {
  ratings.value = [...DEFAULT_VARIED_RATINGS];
}

function addImprovementTopic() {
  if (improvementTopics.value.length < 6) {
    improvementTopics.value.push('');
  }
}

function removeImprovementTopic(index) {
  if (improvementTopics.value.length > 2) {
    improvementTopics.value.splice(index, 1);
  }
}

function toggleFifthQuestion() {
  showFifthQuestion.value = !showFifthQuestion.value;
  if (!showFifthQuestion.value) {
    additionalObservation.value = '';
  }
}

function fastForwardDwell(targetSeconds = 45) {
  const needed = Math.max(0, Number(targetSeconds) - Number(evaluationStore.dwellSeconds));
  if (needed > 0) {
    evaluationStore.incrementDwellTime(needed);
  }
}

async function handleInitialSubmit() {
  statusBanner.value = null;

  if (!dwellValid.value) {
    statusBanner.value = {
      type: 'error',
      code: 'SPEED_RUN_DETECTED',
      message:
        'Speed-Run Guard Rejected Submission (HTTP 422): Minimum reading dwell duration of 45 seconds is required for this 5-cluster SET instrument.',
    };
    return;
  }

  if (straightLined.value) {
    showStraightLineModal.value = true;
    return;
  }

  await executeSubmission(false);
}

async function confirmStraightLineSubmit() {
  showStraightLineModal.value = false;
  await executeSubmission(true);
}

async function executeSubmission(confirmUniform = false) {
  const response = await evaluationStore.submitEvaluation({
    courseSectionId: evaluationStore.activeCourse?.course_section_id,
    ratings: ratings.value,
    commentText: commentText.value,
    professorQuestions: {
      three_words: threeWords.value,
      room_for_improvements: improvementTopics.value.filter((t) => String(t).trim().length > 0),
      teaching_strengths: teachingStrengths.value,
      recommendations: courseRecommendations.value,
      additional_observation: showFifthQuestion.value ? additionalObservation.value : null,
    },
    dwellTimeSeconds: evaluationStore.dwellSeconds,
    confirmUniform,
    idempotencyKey: evaluationStore.submissionKey,
  });

  if (!response.ok) {
    if (response.code === 'ZERO_VARIANCE_CONFIRMATION_REQUIRED') {
      showStraightLineModal.value = true;
      return;
    }
    statusBanner.value = {
      type: 'error',
      code: response.code || 'SUBMISSION_REJECTED',
      message: response.message,
    };
    return;
  }

  statusBanner.value = {
    type: 'success',
    code: 'CLEARANCE_ISSUED',
    message: `Evaluation recorded anonymously in evaluation_vault! Signed Clearance Token Issued: ${response.clearance_token}`,
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
    <section class="card eval-header-card">
      <div class="eval-course-banner">
        <div>
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • 5 Clusters × 5-Point Likert Scale • {{ CALAMANCY_CONFIG.engineLabel }}
          </span>
          <h2 id="eval-form-heading" class="section-title">
            {{ evaluationStore.activeCourse?.course_code || 'MATH-201' }} —
            {{ evaluationStore.activeCourse?.instructor_name || 'Dr. R. Garcia' }}
          </h2>
          <p class="section-subtitle">
            {{ evaluationStore.activeCourse?.course_title || 'Linear Algebra and Matrix Computation' }} •
            {{ evaluationStore.activeCourse?.department_name || 'College of Computer Studies' }} (Pamantasan ng Lungsod ng Pasig)
          </p>
        </div>

        <div class="idempotency-box">
          <span class="token-label">FT-02 Idempotency Key:</span>
          <code data-testid="submission-key-code">{{ evaluationStore.submissionKey }}</code>
        </div>
      </div>

      <DwellTimerBadge
        :dwell-seconds="evaluationStore.dwellSeconds"
        :min-seconds="45"
        :question-count="ratings.length"
        @fast-forward="fastForwardDwell"
      />

      <div class="live-metrics-strip" aria-label="Real-Time Response Authenticity Metrics">
        <div>
          <span>Mean Score (μ):</span>
          <strong data-testid="live-mean-score">{{ currentMean.toFixed(2) }} / 5.00</strong>
        </div>
        <div>
          <span>Population Variance (σ²):</span>
          <strong data-testid="live-variance-score">{{ currentVariance.toFixed(4) }}</strong>
        </div>
        <div>
          <span>5-Cluster Guard:</span>
          <strong :class="straightLined ? 'text-warning' : 'text-success'">
            {{ straightLined ? 'Zero Variance (σ² = 0 — Confirmation Required)' : 'Differentiated (σ² > 0)' }}
          </strong>
        </div>
        <div class="preset-rating-buttons">
          <button
            type="button"
            class="btn-secondary btn-xs"
            data-testid="fill-straight-5s-btn"
            @click="setAllRatingsUniform(5)"
          >
            Test Straight-Line (All 5s)
          </button>
          <button
            type="button"
            class="btn-secondary btn-xs"
            data-testid="fill-varied-btn"
            @click="setVariedRatings"
          >
            Restore Varied Ratings
          </button>
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
        <strong>[{{ statusBanner.code }}]</strong> {{ statusBanner.message }}
      </div>
      <div v-if="statusBanner.token" style="margin-top: 0.5rem;">
        <span>Signed Clearance Token: </span>
        <code class="clearance-token-code" data-testid="issued-clearance-token">{{ statusBanner.token }}</code>
      </div>
      <div v-if="statusBanner.type === 'success'" style="margin-top: 0.75rem;">
        <button
          type="button"
          class="btn-primary btn-sm"
          data-testid="return-queue-success-btn"
          @click="returnToQueue"
        >
          [Return to Evaluation Queue &amp; View Updated Clearance]
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
            <span class="dimension-name">
              Cluster {{ dimension.clusterNumber }}: {{ dimension.name }}
              (Mean: {{ (clusterMeans[dimension.name] || 0).toFixed(2) }} / 5.00)
            </span>
            <small class="dimension-desc">{{ dimension.description }}</small>
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
            <span class="eyebrow-badge">
              Qualitative Professor Evaluation • {{ activeProfQuestionCount }} of {{ MAX_PROFESSOR_QUESTIONS }} Questions Active (Max {{ MAX_PROFESSOR_QUESTIONS }})
            </span>
            <h3 class="section-title">Structured Questions Regarding the Professor (calamanCy NLP)</h3>
            <p class="section-subtitle">
              Powered by <code>calamanCy</code> ({{ CALAMANCY_CONFIG.model }}) for Tagalog, Taglish, and English morphological lemmatization and VADER sentiment scoring.
            </p>
          </div>
          <button
            type="button"
            class="btn-secondary btn-xs"
            data-testid="toggle-fifth-question-btn"
            @click="toggleFifthQuestion"
          >
            {{
              showFifthQuestion
                ? 'Remove Optional 5th Question (5/5 Max Reached)'
                : '+ Add 5th Question (4/5 Active — Max 5 Questions)'
            }}
          </button>
        </div>

        <!-- Question 1: 3 words that describe the experience with the prof -->
        <div class="prof-question-block" data-testid="prof-question-1">
          <label class="prof-question-label">
            1. 3 words that describe the experience with the prof:
          </label>
          <div class="three-words-grid">
            <input
              v-model="threeWords[0]"
              type="text"
              class="form-input"
              placeholder="Word 1 (e.g., Malinaw / Clear)"
              aria-label="Descriptor Word 1"
              data-testid="three-word-1"
            />
            <input
              v-model="threeWords[1]"
              type="text"
              class="form-input"
              placeholder="Word 2 (e.g., Approachable)"
              aria-label="Descriptor Word 2"
              data-testid="three-word-2"
            />
            <input
              v-model="threeWords[2]"
              type="text"
              class="form-input"
              placeholder="Word 3 (e.g., Organized)"
              aria-label="Descriptor Word 3"
              data-testid="three-word-3"
            />
          </div>
        </div>

        <!-- Question 2: Room for improvements of the prof (a. topic 1, b. topic 2, ...) -->
        <div class="prof-question-block" data-testid="prof-question-2">
          <div class="prof-question-subhead">
            <label class="prof-question-label">
              2. Room for improvements of the prof:
            </label>
            <button
              type="button"
              class="btn-secondary btn-xs"
              data-testid="add-improvement-topic-btn"
              @click="addImprovementTopic"
            >
              + Add Topic ({{ String.fromCharCode(97 + improvementTopics.length) }}.)
            </button>
          </div>

          <div
            v-for="(_, idx) in improvementTopics"
            :key="`imp-topic-${idx}`"
            class="improvement-topic-row"
            :data-testid="`improvement-topic-row-${idx}`"
          >
            <span class="sub-topic-badge">{{ String.fromCharCode(97 + idx) }}. Topic {{ idx + 1 }}</span>
            <input
              v-model="improvementTopics[idx]"
              type="text"
              class="form-input"
              :placeholder="`${String.fromCharCode(97 + idx)}. Specific topic or instructional area for improvement...`"
              :aria-label="`Room for improvement topic ${idx + 1}`"
            />
            <button
              v-if="improvementTopics.length > 2"
              type="button"
              class="btn-secondary btn-xs"
              @click="removeImprovementTopic(idx)"
            >
              Remove
            </button>
          </div>
        </div>

        <!-- Question 3: Key teaching strengths of the prof -->
        <div class="prof-question-block" data-testid="prof-question-3">
          <label class="prof-question-label" for="prof-q3-input">
            3. Most effective teaching strategies or strengths of the prof:
          </label>
          <input
            id="prof-q3-input"
            v-model="teachingStrengths"
            type="text"
            class="form-input"
            placeholder="Describe what the professor does best during lectures or laboratory sessions..."
          />
        </div>

        <!-- Question 4: Actionable recommendations for the prof -->
        <div class="prof-question-block" data-testid="prof-question-4">
          <label class="prof-question-label" for="prof-q4-input">
            4. Actionable recommendations for the prof in future semesters:
          </label>
          <input
            id="prof-q4-input"
            v-model="courseRecommendations"
            type="text"
            class="form-input"
            placeholder="Provide constructive suggestions for future offerings of this course..."
          />
        </div>

        <!-- Optional Question 5 (Capped at Max 5 Questions) -->
        <div
          v-if="showFifthQuestion"
          class="prof-question-block"
          data-testid="prof-question-5"
        >
          <label class="prof-question-label" for="prof-q5-input">
            5. Additional observation on consultation &amp; mentorship support (Max 5 of 5 Questions):
          </label>
          <input
            id="prof-q5-input"
            v-model="additionalObservation"
            type="text"
            class="form-input"
            placeholder="Optional 5th question regarding consultation hours, advising, or student mentorship..."
          />
        </div>
      </section>

      <!-- Part III: Overall Narrative Synthesis & Live calamanCy + VADER Preview -->
      <section class="card narrative-card">
        <h3 class="section-title">Open-Ended Pedagogical Narrative Summary (calamanCy + VADER NLP)</h3>
        <p class="section-subtitle">
          Your comment and structured responses are stripped of all student identifiers, hashed with an institutional
          secret, and classified by the Python <code>calamanCy</code> + VADER engine across all 5 pedagogical clusters.
        </p>

        <div class="sample-comment-pills">
          <button
            v-for="sample in sampleComments"
            :key="sample.label"
            type="button"
            class="btn-secondary btn-xs"
            @click="commentText = sample.text"
          >
            {{ sample.label }}
          </button>
        </div>

        <label class="sr-only" for="narrative-comment-textarea">Narrative Evaluation Feedback</label>
        <textarea
          id="narrative-comment-textarea"
          v-model="commentText"
          rows="4"
          class="narrative-textarea"
          placeholder="Provide constructive feedback in English, Tagalog, or Taglish..."
          data-testid="narrative-comment-input"
        ></textarea>

        <div class="nlp-live-preview" data-testid="nlp-live-preview">
          <span>Engine: <strong>{{ nlpPreview.nlp_engine }}</strong></span>
          <span>Sentiment Polarity:</span>
          <strong>P = {{ nlpPreview.polarity >= 0 ? `+${nlpPreview.polarity.toFixed(2)}` : nlpPreview.polarity.toFixed(2) }}</strong>
          <span class="badge-valence" :class="`valence-${nlpPreview.valence.toLowerCase()}`">
            {{ nlpPreview.valence }}
          </span>
          <span>Assigned Cluster Tag:</span>
          <strong data-testid="nlp-preview-tag">{{ nlpPreview.tag }}</strong>
          <span>Prof Questions Valid:</span>
          <strong>{{ professorQuestionsValidation.valid ? `Yes (${professorQuestionsValidation.questionCount}/${MAX_PROFESSOR_QUESTIONS})` : 'Incomplete' }}</strong>
        </div>

        <div class="form-submit-footer">
          <button
            type="button"
            class="btn-secondary"
            @click="returnToQueue"
          >
            Back to Queue
          </button>

          <button
            type="submit"
            class="btn-primary"
            :disabled="evaluationStore.submitting"
            data-testid="submit-evaluation-btn"
          >
            {{
              evaluationStore.submitting
                ? 'Executing Atomic Decoupled Transaction...'
                : 'Submit Anonymized Evaluation & Sign Clearance'
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
  </main>
</template>
