<script setup>
import { computed } from 'vue';
import { isDwellTimeValid } from '../utils/evaluationMath.js';

const props = defineProps({
  dwellSeconds: {
    type: Number,
    default: 0,
  },
  minSeconds: {
    type: Number,
    default: 45,
  },
  questionCount: {
    type: Number,
    default: 25,
  },
});

const isSatisfied = computed(() =>
  isDwellTimeValid(props.dwellSeconds, props.questionCount, props.minSeconds),
);

const remainingSeconds = computed(() =>
  Math.max(0, Number(props.minSeconds) - Number(props.dwellSeconds)),
);

const progressPercent = computed(() =>
  Math.min(100, Math.round((Number(props.dwellSeconds) / Math.max(1, Number(props.minSeconds))) * 100)),
);

const formattedTime = computed(() => {
  const total = Math.max(0, Math.floor(Number(props.dwellSeconds)));
  const mins = Math.floor(total / 60);
  const secs = String(total % 60).padStart(2, '0');
  return `${mins}m ${secs}s`;
});
</script>

<template>
  <div
    class="dwell-timer-badge"
    :class="isSatisfied ? 'dwell-valid' : 'dwell-counting'"
    role="timer"
    aria-live="polite"
    :aria-label="`Evaluation reading dwell timer: ${dwellSeconds} of ${minSeconds} seconds elapsed`"
    data-testid="dwell-timer-badge"
  >
    <div class="dwell-timer-info">
      <span class="dwell-status-dot" aria-hidden="true"></span>
      <div>
        <strong class="dwell-title">
          {{
            isSatisfied
              ? `Ready to submit (${formattedTime})`
              : `Please review each question carefully (${minSeconds}s minimum)`
          }}
        </strong>
        <p class="dwell-subtitle">
          <span v-if="!isSatisfied" class="tabular-nums">{{ remainingSeconds }}s remaining before you can submit</span>
          <span v-else>All {{ questionCount }} items ready for submission</span>
        </p>
      </div>
    </div>

    <div class="dwell-actions">
      <div
        class="dwell-progress-track"
        role="progressbar"
        :aria-valuenow="dwellSeconds"
        :aria-valuemin="0"
        :aria-valuemax="minSeconds"
      >
        <div class="dwell-progress-fill" :style="{ width: `${progressPercent}%` }"></div>
      </div>
    </div>
  </div>
</template>
