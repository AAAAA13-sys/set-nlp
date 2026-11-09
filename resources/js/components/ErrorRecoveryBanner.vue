<script setup>
import { throttle } from '../utils/uxHelpers.js';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "Couldn't save changes",
  },
  message: {
    type: String,
    default: 'Your work is safe. Check your connection.',
  },
  detail: {
    type: String,
    default: '',
  },
  retrying: {
    type: Boolean,
    default: false,
  },
  dismissible: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['retry', 'dismiss']);

const handleRetry = throttle(() => {
  if (!props.retrying) {
    emit('retry');
  }
}, 800);
</script>

<template>
  <div
    v-if="visible"
    class="error-recovery-banner"
    role="alert"
    aria-live="assertive"
    data-testid="graceful-error-recovery-banner"
  >
    <div class="error-recovery-main">
      <span class="error-recovery-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </span>
      <div class="error-recovery-copy">
        <strong class="error-recovery-title">
          {{ title }}
          <span
            class="ui-hint"
            tabindex="0"
            role="note"
            aria-label="Recovery details"
            data-hint="Local evaluation drafts remain safely stored in your browser while the connection retries."
          >?</span>
        </strong>
        <span class="error-recovery-sub">
          {{ message }}
          <template v-if="detail"> — {{ detail }}</template>
        </span>
      </div>
    </div>

    <div class="error-recovery-actions">
      <button
        type="button"
        class="error-retry-btn"
        :disabled="retrying"
        :aria-busy="retrying"
        data-testid="error-recovery-retry-btn"
        @click="handleRetry"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="23 4 23 10 17 10"></polyline>
          <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
        </svg>
        <span>{{ retrying ? 'Retrying…' : 'Retry' }}</span>
      </button>

      <button
        v-if="dismissible"
        type="button"
        class="error-dismiss-btn"
        data-testid="error-recovery-dismiss-btn"
        aria-label="Dismiss connection notice"
        @click="$emit('dismiss')"
      >
        Dismiss
      </button>
    </div>
  </div>
</template>

<style scoped>
.error-recovery-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  padding: 0.82rem 1rem;
  background: #fef2f2;
  border: 1px solid #f5c2c7;
  border-radius: 12px;
  color: #7f1d1d;
}

.error-recovery-main {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  min-width: 0;
}

.error-recovery-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: #dc2626;
  color: #ffffff;
  flex-shrink: 0;
  margin-top: 0.05rem;
}

.error-recovery-copy {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
}

.error-recovery-title {
  font-size: 0.88rem;
  font-weight: 800;
  color: #7f1d1d;
}

.error-recovery-sub {
  font-size: 0.8rem;
  color: #991b1b;
  line-height: 1.4;
}

.error-recovery-actions {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.error-retry-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.38rem 0.78rem;
  border-radius: 8px;
  border: 1px solid #dc2626;
  background: #ffffff;
  color: #991b1b;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.error-retry-btn:hover:not(:disabled) {
  background: #dc2626;
  color: #ffffff;
}

.error-retry-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.error-dismiss-btn {
  padding: 0.38rem 0.62rem;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: #7f1d1d;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
}

.error-dismiss-btn:hover {
  background: #fee2e2;
}
</style>

