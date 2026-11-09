<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';

const authStore = useAuthStore();
const selectedDensity = ref(authStore.uiPreferences?.density || 'comfortable');
const selectedFocus = ref(authStore.uiPreferences?.focusArea || 'analytics');

const isVisible = computed(
  () =>
    authStore.isAuthenticated &&
    Boolean(authStore.uiPreferences?.showPreferencesBanner) &&
    !authStore.uiPreferences?.onboardingCompleted,
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
      <strong class="onboarding-title">
        Display Preferences
        <span class="ui-hint" tabindex="0" aria-label="Preference settings help" data-hint="Choose your default table density and primary workflow focus. Saved locally to your active session.">?</span>
      </strong>
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
  gap: 0.65rem 1rem;
  padding: 0.6rem 1rem;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  margin-bottom: 0.15rem;
}

.onboarding-copy {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
}

.onboarding-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.14rem 0.5rem;
  border-radius: 999px;
  background: #eaf4ee;
  border: 1px solid #cce3d4;
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.07em;
  color: #176347;
}

.onboarding-title {
  font-size: 0.86rem;
  color: #1f3028;
}

.onboarding-sub {
  font-size: 0.78rem;
  color: #4b5e53;
}

.onboarding-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.onboarding-select-label {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
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

