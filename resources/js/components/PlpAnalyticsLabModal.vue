<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
  CALAMANCY_CONFIG,
  FIVE_LIKERT_CLUSTERS,
  MAX_PROFESSOR_QUESTIONS,
  classifyCommentPreview,
  validateProfessorQuestions,
} from '../utils/evaluationMath.js';

const props = defineProps({
  visible: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['close']);

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.visible) {
    emit('close');
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

const sampleNarratives = [
  {
    id: 'taglish-delivery',
    label: 'Taglish • Pedagogical Delivery (+)',
    text: 'Napakalinaw magpaliwanag ni Prof sa complex algorithms at laging may practical code examples sa klase.',
    lemmas: ['linaw (malinaw)', 'paliwanag (magpaliwanag)', 'algorithm', 'practical', 'code', 'klase'],
  },
  {
    id: 'tagalog-mentorship',
    label: 'Tagalog • Student Support & Mentorship (+)',
    text: 'Matulungin at maagap sa konsultasyon, laging bukas ang office hours para sa thesis at capstone guidance.',
    lemmas: ['tulong (matulungin)', 'agap (maagap)', 'konsultasyon', 'bukas', 'thesis', 'gabay (guidance)'],
  },
  {
    id: 'english-grading',
    label: 'English • Assessment & Grading (-)',
    text: 'Takes over a month to return quiz scores and provides no helpful feedback.',
    lemmas: ['take (takes)', 'month', 'return', 'quiz', 'score (scores)', 'feedback'],
  },
  {
    id: 'taglish-management',
    label: 'Taglish • Classroom Management (-)',
    text: 'Laging nahuhuli ng 15 minutes sa morning lectures at medyo magulo ang sunod sa syllabus.',
    lemmas: ['huli (nahuhuli)', 'minute (minutes)', 'lecture', 'gulo (magulo)', 'syllabus'],
  },
];

const selectedSampleId = ref(sampleNarratives[0].id);
const customNarrative = ref(sampleNarratives[0].text);

const professorPayload = ref({
  threeWords: ['Malinaw', 'Matulungin', 'Organisado'],
  improvementTopics: [
    'Pacing of advanced dynamic programming lectures',
    'Faster turnaround of laboratory rubric scores',
  ],
  teachingStrengths: 'Step-by-step live coding demonstrations and bilingual Taglish explanations.',
  recommendations: 'Provide supplementary practice problem sets before departmental exams.',
  additionalObservation: 'Consistently supportive during thesis and capstone consultation hours.',
});

function selectSample(sample) {
  selectedSampleId.value = sample.id;
  customNarrative.value = sample.text;
}

const activeLemmas = computed(() => {
  const matched = sampleNarratives.find((s) => s.text === customNarrative.value);
  if (matched) {
    return matched.lemmas;
  }
  return String(customNarrative.value || '')
    .toLowerCase()
    .replace(/[^\w\s-]/g, ' ')
    .split(/\s+/)
    .filter((token) => token.length >= 4)
    .slice(0, 8);
});

const nlpAnalysis = computed(() => classifyCommentPreview(customNarrative.value));

const clusterConfidenceList = computed(() => {
  const primary = nlpAnalysis.value.pedagogical_tag;
  return FIVE_LIKERT_CLUSTERS.map((cluster) => {
    const isPrimary = cluster.name === primary;
    const confidence = isPrimary ? 88 : 24;
    return {
      clusterNumber: cluster.clusterNumber,
      name: cluster.name,
      scalePoints: cluster.scalePoints,
      isPrimary,
      confidence,
    };
  });
});

const validatedProfQuestions = computed(() =>
  validateProfessorQuestions(professorPayload.value),
);
</script>

<template>
  <div
    v-if="visible"
    class="modal-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="plp-nlp-lab-heading"
    data-testid="plp-nlp-lab-modal"
    @click.self="emit('close')"
  >
    <section
      class="modal-card plp-nlp-lab-card card"
      style="max-width: 860px; max-height: 90vh; overflow-y: auto;"
    >
      <div class="plp-seal-watermark" aria-hidden="true">PLP • PASIG • 2000</div>

    <header class="plp-lab-header">
      <div>
        <span class="eyebrow-badge">
          EVALUATION ANALYSIS
          <span class="sr-only">{{ CALAMANCY_CONFIG.framework }} ({{ CALAMANCY_CONFIG.model }})</span>
        </span>
        <h3 id="plp-nlp-lab-heading" class="section-title">
          Professor's Evaluation Analysis
          <span
            class="ui-hint"
            tabindex="0"
            data-hint="Analyzes student comments in English, Tagalog, and Taglish across the 5 teaching areas."
            aria-label="Hint: Analyzes student comments in English, Tagalog, and Taglish across the 5 teaching areas."
          >?</span>
        </h3>
        <p class="sr-only">
          Pipeline Reference: <code>{{ CALAMANCY_CONFIG.repo }}</code> •
          Engine: <strong>{{ CALAMANCY_CONFIG.engineLabel }}</strong>
        </p>
      </div>

      <button
        type="button"
        class="btn-secondary btn-sm"
        data-testid="close-plp-nlp-lab-btn"
        @click="emit('close')"
      >
        Close
      </button>
    </header>

    <div class="sample-comment-pills" role="group" aria-label="Sample Student Comments">
      <button
        v-for="sample in sampleNarratives"
        :key="sample.id"
        type="button"
        class="btn-secondary btn-xs"
        :class="{ active: selectedSampleId === sample.id }"
        :data-testid="`plp-sample-${sample.id}`"
        @click="selectSample(sample)"
      >
        {{ sample.label }}
      </button>
    </div>

    <div class="form-field">
      <label for="plp-lab-textarea">
        Sample Comment
        <span class="ui-hint" tabindex="0" data-hint="Type or paste a student comment in English, Tagalog, or Taglish to preview its rating category.">?</span>
      </label>
      <textarea
        id="plp-lab-textarea"
        v-model="customNarrative"
        rows="3"
        class="narrative-textarea"
        data-testid="plp-lab-textarea"
      ></textarea>
    </div>

    <!-- Morphological Lemmas & Polarity Strip -->
    <div class="nlp-live-preview tabular-nums" data-testid="plp-lab-polarity-strip">
      <span>Score:</span>
      <strong data-testid="plp-lab-polarity-value">
        {{ nlpAnalysis.polarity >= 0 ? `+${nlpAnalysis.polarity.toFixed(2)}` : nlpAnalysis.polarity.toFixed(2) }}
      </strong>
      <span class="badge-valence" :class="`valence-${nlpAnalysis.valence.toLowerCase()}`">
        {{ nlpAnalysis.valence }}
      </span>
      <span>Category:</span>
      <strong data-testid="plp-lab-primary-cluster">{{ nlpAnalysis.pedagogical_tag }}</strong>
    </div>

    <div class="plp-lemma-row" aria-label="Key Words">
      <span class="token-label">Key Words:<span class="sr-only"> calamanCy Morphological Lemmas</span></span>
      <span
        v-for="lemma in activeLemmas"
        :key="lemma"
        class="plp-lemma-chip"
      >
        {{ lemma }}
      </span>
    </div>

    <!-- 5-Cluster Confidence Distribution -->
    <div class="plp-clusters-breakdown" data-testid="plp-five-cluster-distribution">
      <h4>Teaching Area Breakdown</h4>
      <div
        v-for="cluster in clusterConfidenceList"
        :key="cluster.name"
        class="plp-cluster-meter-row"
      >
        <div class="sentiment-label">
          <span>
            <strong>{{ cluster.clusterNumber }}. {{ cluster.name }}</strong>
            <span class="sr-only">({{ cluster.scalePoints }}-Point Scale)</span>
          </span>
          <strong>{{ cluster.confidence }}%</strong>
        </div>
        <div class="plp-cluster-meter">
          <div
            class="plp-cluster-meter-fill"
            :style="{ width: `${cluster.confidence}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Structured 4-to-5 Professor Questions Diagnostic Breakdown -->
    <div class="prof-question-block" data-testid="plp-prof-questions-breakdown">
      <div class="prof-question-subhead">
        <strong>
          Written Feedback Summary ({{ validatedProfQuestions.questionCount }}/{{ MAX_PROFESSOR_QUESTIONS }})
        </strong>
        <span class="status-badge status-completed">
          {{ validatedProfQuestions.valid ? 'Complete' : 'Incomplete' }}
        </span>
      </div>

      <ol class="plp-prof-questions-list">
        <li>
          <strong>1. 3 words that describe the experience with the prof:</strong>
          <span
            v-for="word in validatedProfQuestions.threeWords"
            :key="word"
            class="plp-lemma-chip"
          >
            {{ word }}
          </span>
        </li>
        <li>
          <strong>2. Room for improvements of the prof:</strong>
          <ul>
            <li
              v-for="item in validatedProfQuestions.improvementTopics"
              :key="item.subItem"
            >
              <code>{{ item.subItem }}</code> {{ item.topic }}
            </li>
          </ul>
        </li>
        <li>
          <strong>3. Teaching strengths:</strong> {{ validatedProfQuestions.teachingStrengths }}
        </li>
        <li>
          <strong>4. Recommendations:</strong> {{ validatedProfQuestions.recommendations }}
        </li>
        <li v-if="validatedProfQuestions.additionalObservation">
          <strong>5. Optional 5th observation:</strong> {{ validatedProfQuestions.additionalObservation }}
        </li>
      </ol>
    </div>
  </section>
  </div>
</template>
