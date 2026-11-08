<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';

const authStore = useAuthStore();
const selectedDensity = ref(authStore.uiPreferences?.density || 'comfortable');
const selectedFocus = ref(authStore.uiPreferences?.focusArea || 'analytics');

const isVisible = computed(
  () => authStore.isAuthenticated && !authStore.uiPreferences?.onboardingCompleted,
);

function applyPreferences() {
  authStore.updateUiPreferences({
    density: selectedDensity.value,
    focusArea: selectedFocus.value,
    onboardingCompleted: true,
  });
}

function dismissOnboarding() {
  authStore.updateUiPreferences({
    onboardingCompleted: true,
  });
}
</script>

<template>
  <section
    v-if="isVisible"
    class="onboarding-capture-card"
    aria-label="Workspace Onboarding Preferences"
    data-testid="workspace-onboarding-banner"
  >
    <div class="onboarding-copy">
      <span class="onboarding-pill">WORKSPACE PREFERENCES</span>
      <strong class="onboarding-title">Tailor your PLP EvaluaTech layout</strong>
      <span class="onboarding-sub">
        Choose your default table density and primary workflow focus. Saved locally to your active session.
      </span>
    </div>

    <div class="onboarding-controls">
      <label class="onboarding-select-label">
        <span>Table Density</span>
        <select v-model="selectedDensity" class="onboarding-select" data-testid="onboarding-density-select">
          <option value="comfortable">Comfortable</option>
          <option value="compact">Compact (High Density)</option>
        </select>
      </label>

      <label class="onboarding-select-label">
        <span>Workflow Focus</span>
        <select v-model="selectedFocus" class="onboarding-select" data-testid="onboarding-focus-select">
          <option value="analytics">Sentiment &amp; Watchlist</option>
          <option value="accreditation">AACCUP Area III Evidence</option>
          <option value="clearance">Queue &amp; Exam Permits</option>
        </select>
      </label>

      <button
        type="button"
        class="btn-primary btn-xs"
        data-testid="onboarding-save-btn"
        @click="applyPreferences"
      >
        Apply Layout
      </button>
      <button
        type="button"
        class="btn-secondary btn-xs"
        data-testid="onboarding-dismiss-btn"
        @click="dismissOnboarding"
      >
        Dismiss
      </button>
    </div>
  </section>
</template>

<style scoped>
.onboarding-capture-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  padding: 0.85rem 1.1rem;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  margin-bottom: 0.25rem;
}

.onboarding-copy {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  max-width: 520px;
}

.onboarding-pill {
  display: inline-block;
  width: fit-content;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #176347;
}

.onboarding-title {
  font-size: 0.92rem;
  color: #1f3028;
}

.onboarding-sub {
  font-size: 0.8rem;
  color: #4b5e53;
}

.onboarding-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.55rem;
}

.onboarding-select-label {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.72rem;
  font-weight: 700;
  color: #4b5e53;
}

.onboarding-select {
  height: 32px;
  padding: 0 0.55rem;
  border-radius: 8px;
  border: 1px solid #c6d4c8;
  background: #ffffff;
  color: #1f3028;
  font-size: 0.8rem;
}
</style>

