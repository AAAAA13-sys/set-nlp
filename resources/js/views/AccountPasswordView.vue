<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '../components/AppIcon.vue';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const MIN_LENGTH = 10;

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const showPasswords = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const done = ref(false);

const forced = computed(() => authStore.mustChangePassword);
const checks = computed(() => [
  { label: `At least ${MIN_LENGTH} characters`, met: newPassword.value.length >= MIN_LENGTH },
  { label: 'At least one letter and one number', met: /[A-Za-z]/.test(newPassword.value) && /\d/.test(newPassword.value) },
  { label: 'Both new password fields match', met: newPassword.value !== '' && newPassword.value === confirmPassword.value },
]);
const canSubmit = computed(() => currentPassword.value !== '' && checks.value.every((c) => c.met) && !submitting.value);

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  errorMessage.value = '';
  try {
    await authStore.changePassword({ currentPassword: currentPassword.value, newPassword: newPassword.value });
    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    done.value = true;
  } catch (err) {
    errorMessage.value = err?.message || 'Your password could not be updated.';
  } finally {
    submitting.value = false;
  }
}

function continueToWorkspace() {
  router.push(getDefaultRouteForRole(authStore.currentRole)).catch(() => {});
}

async function signOut() {
  evaluationStore.resetState();
  await authStore.logout();
  router.push('/login').catch(() => {});
}
</script>

<template>
  <main class="workspace-main account-password-workspace" aria-labelledby="account-password-heading">
    <section class="card account-password-card" data-testid="account-password-card">
      <h2 id="account-password-heading" class="section-title">
        {{ forced ? 'Set your own password' : 'Change your password' }}
      </h2>
      <p class="section-subtitle">
        <template v-if="forced">
          You signed in with a one-time password from the Office of the University Registrar.
          Choose your own password to continue.
        </template>
        <template v-else>Signed in as {{ authStore.displayIdentifier }}.</template>
      </p>

      <div v-if="done" class="alert-banner alert-success" role="status" data-testid="password-change-success">
        <AppIcon name="check-circle" />
        <div>
          <strong>Password updated.</strong>
          Use your new password the next time you sign in.
        </div>
      </div>

      <form v-if="!done" class="account-password-form" novalidate @submit.prevent="submit">
        <div v-if="errorMessage" class="alert-banner alert-error" role="alert" data-testid="password-change-error">
          <AppIcon name="alert" />
          <div>{{ errorMessage }}</div>
        </div>

        <div class="form-field">
          <label for="current-password">{{ forced ? 'One-time password' : 'Current password' }}</label>
          <input
            id="current-password"
            v-model="currentPassword"
            :type="showPasswords ? 'text' : 'password'"
            autocomplete="current-password"
            required
            data-testid="current-password-input"
          />
        </div>
        <div class="form-field">
          <label for="new-password">New password</label>
          <input
            id="new-password"
            v-model="newPassword"
            :type="showPasswords ? 'text' : 'password'"
            autocomplete="new-password"
            aria-describedby="password-rules"
            required
            data-testid="new-password-input"
          />
        </div>
        <div class="form-field">
          <label for="confirm-password">Confirm new password</label>
          <input
            id="confirm-password"
            v-model="confirmPassword"
            :type="showPasswords ? 'text' : 'password'"
            autocomplete="new-password"
            required
            data-testid="confirm-password-input"
          />
        </div>

        <ul id="password-rules" class="password-rules" aria-label="Password requirements">
          <li v-for="rule in checks" :key="rule.label" :class="{ met: rule.met }">
            <AppIcon :name="rule.met ? 'check' : 'circle'" :size="16" />
            <span>{{ rule.label }}</span>
            <span class="sr-only">{{ rule.met ? '(met)' : '(not yet met)' }}</span>
          </li>
        </ul>
        <p class="password-rules-note">Do not use your student number, employee ID, or email name.</p>

        <label class="show-passwords-toggle">
          <input v-model="showPasswords" type="checkbox" data-testid="show-passwords-toggle" />
          Show passwords
        </label>

        <div class="account-password-actions">
          <button type="submit" class="btn-primary" :disabled="!canSubmit" data-testid="save-password-btn">
            {{ submitting ? 'Saving…' : 'Save new password' }}
          </button>
          <button v-if="forced" type="button" class="btn-secondary" data-testid="password-sign-out-btn" @click="signOut">
            Sign out
          </button>
        </div>
      </form>

      <div v-else class="account-password-actions">
        <button type="button" class="btn-primary" data-testid="continue-workspace-btn" @click="continueToWorkspace">
          Continue to my workspace
          <AppIcon name="arrow-right" />
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.account-password-workspace {
  display: flex;
  justify-content: center;
}
.account-password-card {
  width: 100%;
  max-width: 34rem;
  margin-inline: auto;
}
.account-password-form {
  margin-top: 1rem;
}
.password-rules {
  list-style: none;
  margin: 0.25rem 0 0.5rem;
  padding: 0;
  display: grid;
  gap: 0.35rem;
  color: var(--ink-soft);
  font-size: 0.9rem;
}
.password-rules li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.password-rules li.met {
  color: var(--status-success-text);
}
.password-rules-note {
  margin: 0 0 0.75rem;
  color: var(--ink-soft);
  font-size: 0.9rem;
}
.show-passwords-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  font-size: 0.95rem;
  color: var(--ink);
}
.account-password-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1rem;
}
.account-password-actions .btn-primary,
.account-password-actions .btn-secondary {
  min-height: 44px;
}
</style>
