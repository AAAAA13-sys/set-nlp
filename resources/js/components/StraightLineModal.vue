<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  uniformRating: {
    type: Number,
    default: 5,
  },
  variance: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['confirm', 'cancel']);

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.visible) {
    emit('cancel');
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <div
    v-if="visible"
    class="modal-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="straight-line-modal-title"
    data-testid="straight-line-modal"
  >
    <div class="modal-card">
      <div class="modal-header">
        <h2 id="straight-line-modal-title" class="section-title">
          All 25 items have the same rating ({{ uniformRating }} / 5)
          <span class="ui-hint" tabindex="0" data-hint="Uniform ratings (zero variance) require confirmation before submission.">?</span>
        </h2>
      </div>

      <div class="modal-body">
        <p>
          You selected <strong>{{ uniformRating }} out of 5</strong> for every question. Would you like to submit these ratings as is, or go back and adjust individual items?
        </p>
        <p class="sr-only">
          is_straight_lined = true · σ² = {{ variance.toFixed(2) }}
        </p>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn-secondary"
          data-testid="modal-review-btn"
          @click="emit('cancel')"
        >
          Go Back &amp; Review
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="modal-confirm-uniform-btn"
          @click="emit('confirm')"
        >
          Submit Ratings
        </button>
      </div>
    </div>
  </div>
</template>
