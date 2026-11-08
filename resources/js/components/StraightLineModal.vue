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
        <span class="badge-warning">Authenticity Guard • Zero Variance (σ² = 0)</span>
        <h2 id="straight-line-modal-title" class="modal-title">
          Uniform Rating Pattern Detected (Straight-Lining Check)
        </h2>
      </div>

      <div class="modal-body">
        <p>
          You selected an identical rating of <strong>{{ uniformRating }} / 5</strong> across all
          <strong>20 pedagogical criteria</strong>, resulting in a population rating variance of
          <code>σ² = {{ variance.toFixed(2) }}</code>.
        </p>
        <p>
          To ensure high diagnostic reliability for curriculum improvement, please verify whether you
          intentionally rated every dimension identically or would like to review individual items across
          <em>Pedagogical Delivery</em>, <em>Assessment &amp; Grading</em>, <em>Classroom Management</em>,
          and <em>Professional Conduct</em>.
        </p>
        <div class="modal-notice">
          <strong>Cryptographic Anonymity Guarantee:</strong> Confirming a uniform assessment records an
          anonymized aggregate flag (<code>is_straight_lined = true</code>) in the decoupled vault without
          attaching your student identity.
        </div>
      </div>

      <div class="modal-footer">
        <button
          type="button"
          class="btn-secondary"
          data-testid="modal-review-btn"
          @click="emit('cancel')"
        >
          Review &amp; Differentiate Ratings
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="modal-confirm-uniform-btn"
          @click="emit('confirm')"
        >
          Confirm Uniform Assessment (confirm_uniform = true)
        </button>
      </div>
    </div>
  </div>
</template>
