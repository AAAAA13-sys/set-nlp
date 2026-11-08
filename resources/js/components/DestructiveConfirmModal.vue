<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Confirm Critical Institutional Action',
  },
  description: {
    type: String,
    default: 'Please review the impact before proceeding. This governance change takes effect immediately.',
  },
  impactNote: {
    type: String,
    default: '',
  },
  confirmLabel: {
    type: String,
    default: 'Confirm Action',
  },
  cancelLabel: {
    type: String,
    default: 'Cancel',
  },
  busy: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['confirm', 'cancel']);

function handleKeyDown(event) {
  if (props.visible && event.key === 'Escape' && !props.busy) {
    emit('cancel');
  }
}

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
    class="destructive-scrim"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="destructive-modal-title"
    aria-describedby="destructive-modal-desc"
    data-testid="destructive-confirm-modal"
    @click.self="!busy && $emit('cancel')"
  >
    <div class="destructive-dialog">
      <div class="destructive-header">
        <span class="destructive-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </span>
        <div>
          <span class="destructive-kicker">DESTRUCTIVE ACTION SAFEGUARD</span>
          <h3 id="destructive-modal-title" class="destructive-title">{{ title }}</h3>
        </div>
      </div>

      <p id="destructive-modal-desc" class="destructive-desc">
        {{ description }}
      </p>

      <div v-if="impactNote" class="destructive-impact-box">
        <strong>Governance Impact:</strong>
        <span>{{ impactNote }}</span>
      </div>

      <div class="destructive-actions">
        <button
          type="button"
          class="btn-secondary"
          :disabled="busy"
          data-testid="destructive-cancel-btn"
          @click="$emit('cancel')"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          class="btn-danger-solid"
          :disabled="busy"
          :aria-busy="busy"
          data-testid="destructive-confirm-btn"
          @click="$emit('confirm')"
        >
          {{ busy ? 'Processing…' : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.destructive-scrim {
  position: fixed;
  inset: 0;
  z-index: 95;
  background: rgba(16, 40, 30, 0.52);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.destructive-dialog {
  width: 100%;
  max-width: 480px;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  padding: 1.35rem 1.45rem;
  box-shadow: 0 22px 48px rgba(16, 40, 30, 0.24);
}

.destructive-header {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.destructive-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  flex-shrink: 0;
}

.destructive-kicker {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #991b1b;
}

.destructive-title {
  margin: 0.15rem 0 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #1f3028;
}

.destructive-desc {
  margin: 0.85rem 0;
  font-size: 0.86rem;
  line-height: 1.5;
  color: #3f5247;
}

.destructive-impact-box {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.7rem 0.85rem;
  border-radius: 9px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  font-size: 0.8rem;
  color: #78350f;
  margin-bottom: 1rem;
}

.destructive-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
}

.btn-danger-solid {
  padding: 0.55rem 1rem;
  border-radius: 8px;
  border: 1px solid #b91c1c;
  background: #b91c1c;
  color: #ffffff;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-danger-solid:hover:not(:disabled) {
  background: #991b1b;
}

.btn-danger-solid:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
</style>

