<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { debounce } from '../utils/uxHelpers.js';
import SkeletonLoader from './SkeletonLoader.vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const authStore = useAuthStore();
const queryText = ref('');
const selectedValence = ref('ALL');
const selectedCluster = ref('ALL');
const loading = ref(false);
const errorMsg = ref(null);
const brainResponse = ref({
  synthesis_summary: 'Search and filter anonymous student feedback across courses and topics.',
  total_matches: 0,
  top_pedagogical_cluster: 'Pedagogical Delivery',
  zero_student_identity_verified: true,
  results: [],
});

const endpointBase = computed(() =>
  String(authStore.currentRole || '').toLowerCase() === 'chair'
    ? '/api/chair/insights-search'
    : '/api/dean/insights-search',
);

const samplePrompts = [
  'quiz feedback rubric',
  'algorithm examples malinaw',
  'late morning lectures',
  'consultation thesis',
];

async function executeSearch() {
  if (!authStore.isAuthenticated) return;
  loading.value = true;
  errorMsg.value = null;

  const params = new URLSearchParams();
  if (queryText.value.trim()) {
    params.set('q', queryText.value.trim());
  }
  if (selectedValence.value !== 'ALL') {
    params.set('valence', selectedValence.value);
  }
  if (selectedCluster.value !== 'ALL') {
    params.set('cluster', selectedCluster.value);
  }

  try {
    const res = await fetch(`${endpointBase.value}?${params.toString()}`, {
      headers: authStore.authHeaders(),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      brainResponse.value = data;
    } else {
      errorMsg.value = data.message || 'Unable to query anonymized NLP vault insights.';
    }
  } catch (_) {
    errorMsg.value = 'Network connection interrupted while querying anonymized vault themes.';
  } finally {
    loading.value = false;
  }
}

const debouncedSearch = debounce(() => {
  executeSearch();
}, 250);

function applyPrompt(prompt) {
  queryText.value = prompt;
  executeSearch();
}

function handleKeyDown(event) {
  if (props.visible && event.key === 'Escape') {
    emit('close');
  }
}

watch(
  () => props.visible,
  (open) => {
    if (open) {
      executeSearch();
    }
  },
);

watch([selectedValence, selectedCluster], () => {
  if (props.visible) {
    executeSearch();
  }
});

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown);
  }
});
</script>

<template>
  <div
    v-if="visible"
    class="quivr-scrim"
    role="dialog"
    aria-modal="true"
    aria-labelledby="quivr-brain-title"
    data-testid="quivr-nlp-brain-drawer"
    @click.self="$emit('close')"
  >
    <aside class="quivr-drawer">
      <div class="quivr-header">
        <div>
          <span class="quivr-kicker">EVALUATION ANALYSIS</span>
          <h3 id="quivr-brain-title" class="quivr-title">
            Professor's Evaluation Analysis
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Search anonymized student feedback by keyword, sentiment, or teaching category"
              data-hint="Search anonymized student feedback by keyword, sentiment, or teaching category."
            >?</span>
            <span class="sr-only"> calamanCy + VADER Pedagogical Insight Explorer</span>
          </h3>
        </div>
        <button
          type="button"
          class="btn-secondary btn-xs"
          data-testid="close-quivr-brain-btn"
          @click="$emit('close')"
        >
          Close
        </button>
      </div>

      <div class="quivr-controls">
        <label class="sr-only" for="quivr-query-input">Query anonymized SET vault themes</label>
        <input
          id="quivr-query-input"
          v-model="queryText"
          type="search"
          class="form-input"
          placeholder="Search anonymized themes (e.g., quiz rubric, malinaw, consultation)…"
          data-testid="quivr-query-input"
          @input="debouncedSearch"
        />

        <div class="quivr-prompt-chips">
          <button
            v-for="prompt in samplePrompts"
            :key="prompt"
            type="button"
            class="quivr-chip"
            @click="applyPrompt(prompt)"
          >
            {{ prompt }}
          </button>
        </div>

        <div class="quivr-filter-row">
          <select v-model="selectedValence" class="quivr-select" aria-label="Filter by sentiment valence" data-testid="quivr-valence-filter">
            <option value="ALL">All Valences</option>
            <option value="Positive">Positive</option>
            <option value="Neutral">Neutral</option>
            <option value="Negative">Negative</option>
          </select>

          <select v-model="selectedCluster" class="quivr-select" aria-label="Filter by pedagogical cluster" data-testid="quivr-cluster-filter">
            <option value="ALL">All 5 Pedagogical Clusters</option>
            <option value="Pedagogical Delivery">Pedagogical Delivery</option>
            <option value="Assessment & Grading">Assessment &amp; Grading</option>
            <option value="Classroom Management">Classroom Management</option>
            <option value="Professional Conduct">Professional Conduct</option>
            <option value="Student Support & Mentorship">Student Support &amp; Mentorship</option>
          </select>
        </div>
      </div>

      <div class="quivr-synthesis-box" data-testid="quivr-synthesis-summary">
        <strong>Synthesis:</strong>
        <span>{{ brainResponse.synthesis_summary }}</span>
      </div>

      <div v-if="errorMsg" class="quivr-error" role="alert">
        {{ errorMsg }}
      </div>

      <div class="quivr-results-scroll">
        <SkeletonLoader v-if="loading" :rows="4" label="Searching anonymized vault themes…" />

        <div v-else-if="brainResponse.results && brainResponse.results.length > 0" class="quivr-card-list">
          <article
            v-for="entry in brainResponse.results"
            :key="entry.vault_id"
            class="quivr-result-card tabular-nums"
          >
            <div class="quivr-card-top">
              <strong>{{ entry.course_code }} · {{ entry.faculty_name }}</strong>
              <span class="badge-valence" :class="`valence-${String(entry.sentiment_valence || 'neutral').toLowerCase()}`">
                {{ entry.sentiment_valence }} ({{ entry.sentiment_polarity >= 0 ? `+${entry.sentiment_polarity.toFixed(2)}` : entry.sentiment_polarity.toFixed(2) }})
              </span>
            </div>
            <p class="quivr-excerpt">{{ entry.excerpt }}</p>
            <div class="quivr-card-meta">
              <span>Cluster: <strong>{{ entry.pedagogical_tag }}</strong></span>
              <span>Section Mean: <strong>{{ Number(entry.mean_score).toFixed(2) }} / 5.00</strong></span>
            </div>
          </article>
        </div>

        <div v-else class="quivr-empty" data-testid="quivr-empty-state">
          <strong>No Matching Vault Themes</strong>
          <p>Broaden your search terms or reset the sentiment/cluster filters above.</p>
        </div>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.quivr-scrim {
  position: fixed;
  inset: 0;
  z-index: 93;
  background: rgba(16, 40, 30, 0.45);
  display: flex;
  justify-content: flex-end;
}

.quivr-drawer {
  width: min(540px, 100vw);
  height: 100dvh;
  background: #ffffff;
  border-left: 1px solid #dbe3da;
  display: flex;
  flex-direction: column;
  padding: 1.2rem 1.25rem;
  gap: 0.85rem;
  box-shadow: -16px 0 38px rgba(16, 40, 30, 0.16);
}

.quivr-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e6ede7;
}

.quivr-kicker {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #176347;
}

.quivr-title {
  margin: 0.15rem 0 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #1f3028;
}

.quivr-controls {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.quivr-prompt-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.38rem;
}

.quivr-chip {
  font-size: 0.74rem;
  font-weight: 600;
  padding: 0.22rem 0.58rem;
  border-radius: 999px;
  border: 1px solid #c8d6ca;
  background: #f6f8f4;
  color: #0f172a;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.quivr-chip:hover {
  background: #e8f3ec;
  border-color: #176347;
}

.quivr-filter-row {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 0.5rem;
}

.quivr-select {
  height: 36px;
  padding: 0 0.65rem;
  border-radius: 8px;
  border: 1px solid #c8d6ca;
  background: #ffffff;
  color: #1f3028;
  font-size: 0.82rem;
}

.quivr-synthesis-box {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.72rem 0.85rem;
  border-radius: 10px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  font-size: 0.82rem;
  color: #1f3028;
}

.quivr-error {
  padding: 0.65rem 0.8rem;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 0.8rem;
}

.quivr-results-scroll {
  flex: 1;
  overflow-y: auto;
  padding-right: 0.2rem;
}

.quivr-card-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.quivr-result-card {
  padding: 0.8rem 0.9rem;
  border: 1px solid #dbe3da;
  border-radius: 10px;
  background: #ffffff;
}

.quivr-card-top {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.84rem;
  color: #0f172a;
}

.quivr-excerpt {
  margin: 0.45rem 0;
  font-size: 0.84rem;
  line-height: 1.48;
  color: #1f3028;
}

.quivr-card-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.76rem;
  color: #4b5e53;
}

.quivr-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: #4b5e53;
  font-size: 0.85rem;
}
</style>

