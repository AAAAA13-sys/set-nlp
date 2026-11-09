<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { showJQueryUiError } from '../utils/jqueryUiNotification.js';
import { sanitizeRedirectPath, throttle } from '../utils/uxHelpers.js';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const institutionalId = ref('');
const password = ref('');
const showPassword = ref(false);
const rememberSession = ref(false);
const touchedId = ref(false);
const touchedPassword = ref(false);
const errorMessage = ref(null);

const idValidationHint = computed(() => {
  if (!touchedId.value) return '';
  const trimmed = institutionalId.value.trim();
  if (!trimmed) {
    return 'Enter your student number, employee ID, or university email.';
  }
  if (trimmed.length < 4) {
    return 'ID must be at least 4 characters.';
  }
  return '';
});

const passwordValidationHint = computed(() => {
  if (!touchedPassword.value) return '';
  if (!password.value) {
    return 'Enter your password.';
  }
  return '';
});

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
}

async function performLogin() {
  touchedId.value = true;
  touchedPassword.value = true;
  errorMessage.value = null;

  const trimmedId = institutionalId.value.trim();
  if (!trimmedId) {
    errorMessage.value = 'Enter your student number, employee ID, or email.';
    showJQueryUiError(errorMessage.value, 'Missing Identifier', 4000);
    return;
  }
  if (!password.value) {
    errorMessage.value = 'Enter your password to sign in.';
    showJQueryUiError(errorMessage.value, 'Missing Password', 4000);
    return;
  }

  try {
    evaluationStore.resetState();
    const user = await authStore.login({
      identifier: trimmedId,
      institutional_id: trimmedId,
      password: password.value,
      remember: rememberSession.value,
    });

    const defaultRoute = getDefaultRouteForRole(user?.role);
    const targetRoute = sanitizeRedirectPath(route?.query?.redirect, defaultRoute);

    if (router) {
      await router.push(targetRoute).catch(() => {});
    }
  } catch (err) {
    errorMessage.value =
      err?.message || 'Incorrect ID or password. Please check your credentials.';
    showJQueryUiError(errorMessage.value, 'Sign-In Failed', 4000);
  }
}

const handleLogin = throttle(() => {
  if (!authStore.loading) {
    performLogin();
  }
}, 500);
</script>

<template>
  <main class="academic-login-split" aria-labelledby="login-heading" data-testid="login-split-screen">
    <!-- Left Column: Clean Deep Forest Green Story Panel (dms.css .login-story) -->
    <aside class="brand-column" aria-label="Pamantasan ng Lungsod ng Pasig Faculty Evaluation">
      <div class="brand-top-bar">
        <div class="brand-crest-mark" aria-hidden="true">e.</div>
        <div class="brand-wordmark">
          <strong>PLP EvaluTech</strong>
          <span>STUDENT EVALUATION SYSTEM</span>
        </div>
      </div>

      <div class="brand-hero-content">
        <span class="brand-eyebrow">Pamantasan ng Lungsod ng Pasig</span>

        <h1 class="brand-display-headline">
          Candid student feedback,<br />decoupled by design.
        </h1>

        <p class="brand-lead-copy">
          Evaluate your professors honestly each semester to complete your academic clearance.
        </p>

        <div class="feature-tags-stack" role="list" aria-label="Student Privacy Guarantees">
          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-dot" aria-hidden="true"></span>
            <span><strong>100% anonymous</strong> · Your name and student number are never linked to your ratings</span>
          </div>

          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-dot" aria-hidden="true"></span>
            <span><strong>Fair to students</strong> · Professors only see results after final grades are submitted</span>
          </div>

          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-dot" aria-hidden="true"></span>
            <span><strong>Academic clearance</strong> · Complete evaluations for all enrolled courses seamlessly</span>
          </div>
        </div>
      </div>

      <div class="brand-bottom-meta">
        PAMANTASAN NG LUNGSOD NG PASIG · STUDENT EVALUATION OF TEACHERS
      </div>
    </aside>

    <!-- Right Column: Clean Sign-In Form (dms.css .login-form) -->
    <section class="auth-canvas">
      <div class="floating-auth-card" data-testid="login-card">
        <header class="auth-card-header">
          <span class="portal-access-label">STUDENT &amp; FACULTY ACCESS</span>
          <h2 id="login-heading" class="auth-card-title">
            Sign in to your account
            <span class="ui-hint" tabindex="0" aria-label="Sign-in instructions" data-hint="Use your PLP student number (e.g., 2024-10892), employee ID, or university email.">?</span>
          </h2>
          <p class="auth-card-subtitle">
            Use your PLP ID or university email.
          </p>
        </header>

        <div
          v-if="errorMessage || authStore.error"
          class="auth-error-banner"
          data-testid="login-error-alert"
          role="alert"
          aria-live="polite"
        >
          <span>{{ errorMessage || authStore.error }}</span>
        </div>

        <form class="auth-form" @submit.prevent="handleLogin" novalidate>
          <div class="form-field-group">
            <label for="institutional-id-input" class="form-field-label floating-label">
              Student / Employee ID or Email
            </label>
            <input
              id="institutional-id-input"
              v-model="institutionalId"
              type="text"
              name="username"
              required
              autocomplete="username"
              autocapitalize="none"
              autocorrect="off"
              spellcheck="false"
              placeholder="e.g. 2024-10892 or you@plpasig.edu.ph"
              class="clean-form-input"
              :class="{ 'is-invalid': Boolean(idValidationHint) }"
              :aria-invalid="Boolean(idValidationHint)"
              data-testid="login-id-input"
              @blur="touchedId = true"
            />
            <span v-if="idValidationHint" class="field-validation-msg" data-testid="login-id-validation">
              {{ idValidationHint }}
            </span>
          </div>

          <div class="form-field-group">
            <label for="password-input" class="form-field-label floating-label">
              Password
            </label>
            <div class="password-input-wrap">
              <input
                id="password-input"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                required
                autocomplete="current-password"
                spellcheck="false"
                placeholder="Enter your password"
                class="clean-form-input has-toggle"
                :class="{ 'is-invalid': Boolean(passwordValidationHint) }"
                :aria-invalid="Boolean(passwordValidationHint)"
                data-testid="login-password-input"
                @blur="touchedPassword = true"
              />
              <button
                type="button"
                class="password-eye-toggle"
                data-testid="toggle-password-btn"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :title="showPassword ? 'Hide password' : 'Show password'"
                @click="togglePasswordVisibility"
              >
                <svg v-if="!showPassword" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <svg v-else width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                  <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                  <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                  <line x1="2" y1="2" x2="22" y2="22" />
                </svg>
              </button>
            </div>
            <span v-if="passwordValidationHint" class="field-validation-msg" data-testid="login-password-validation">
              {{ passwordValidationHint }}
            </span>
          </div>

          <div class="session-options-row">
            <label class="trust-device-checkbox" for="remember-session">
              <input
                id="remember-session"
                v-model="rememberSession"
                type="checkbox"
                data-testid="remember-session-input"
              />
              <span>Keep me signed in on this device</span>
            </label>
          </div>

          <button
            type="submit"
            class="primary-signin-btn"
            :disabled="authStore.loading"
            :aria-busy="authStore.loading"
            data-testid="login-submit-btn"
          >
            {{ authStore.loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="login-help-text">
          Need help signing in? Contact the Office of the University Registrar.
        </p>

        <p class="login-policy-text" data-testid="login-privacy-banner" role="note">
          Protected under RA 10173 Data Privacy. Your identity is never attached to your evaluations.
          <span class="sr-only">Cryptographic Decoupling</span>
        </p>
      </div>
    </section>
  </main>
</template>

<style scoped>
.academic-login-split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
}

/* Left Story Column (matches dms.css .login-story) */
.brand-column {
  background: #103c2e;
  color: #ffffff;
  padding: clamp(28px, 4.5vh, 48px) clamp(32px, 4vw, 56px);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.brand-top-bar {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-crest-mark {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: #f4cd42;
  color: #103c2e;
  font-weight: 700;
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-wordmark {
  display: flex;
  flex-direction: column;
}

.brand-wordmark strong {
  font-size: 18px;
  font-weight: 600;
  line-height: 24px;
  color: #ffffff;
}

.brand-wordmark span {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: rgba(255, 255, 255, 0.65);
}

.brand-hero-content {
  max-width: 520px;
  padding: 16px 0;
}

.brand-eyebrow {
  display: block;
  font-size: 12px;
  font-weight: 600;
  line-height: 18px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #f4cd42;
}

.brand-display-headline {
  font-size: clamp(30px, 3vw, 40px);
  font-weight: 600;
  line-height: 1.24;
  letter-spacing: -0.01em;
  color: #ffffff;
  margin: 14px 0 16px;
}

.brand-lead-copy {
  font-size: 15px;
  line-height: 23px;
  color: rgba(255, 255, 255, 0.78);
  margin: 0 0 22px;
  max-width: 46ch;
}

.feature-tags-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 18px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
}

.feature-tag-item {
  display: flex;
  align-items: baseline;
  gap: 10px;
  font-size: 14px;
  line-height: 21px;
  color: #cce0d5;
}

.feature-tag-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #f4cd42;
  flex-shrink: 0;
  transform: translateY(-1px);
}

.feature-tag-item strong {
  color: #ffffff;
  font-weight: 600;
}

.brand-bottom-meta {
  font-size: 12px;
  font-weight: 600;
  line-height: 18px;
  letter-spacing: 0.04em;
  color: rgba(255, 255, 255, 0.58);
}

/* Right Form Column (matches dms.css .login-form) */
.auth-canvas {
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(24px, 4vh, 48px) clamp(24px, 4vw, 64px);
}

.floating-auth-card {
  width: 100%;
  max-width: 420px;
}

.auth-card-header {
  margin-bottom: 22px;
}

.portal-access-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  line-height: 18px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #176347;
}

.auth-card-title {
  font-size: clamp(22px, 2.2vw, 26px);
  line-height: 1.28;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: #1f3028;
  margin: 8px 0 10px;
}

.auth-card-subtitle {
  font-size: 15px;
  line-height: 22px;
  color: #53665a;
  margin: 0;
}

.auth-error-banner {
  padding: 11px 14px;
  margin-bottom: 16px;
  border-radius: 8px;
  border: 1px solid #f1c6c6;
  background: #fdf4f4;
  color: #991b1b;
  font-size: 14px;
  line-height: 20px;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-field-group {
  display: flex;
  flex-direction: column;
}

.form-field-label {
  font-size: 15px;
  font-weight: 600;
  line-height: 22px;
  color: #1f3028;
  margin-bottom: 6px;
}

.clean-form-input {
  width: 100%;
  min-height: 44px;
  padding: 10px 12px;
  border: 1px solid #dbe3da;
  border-radius: 8px;
  background: #ffffff;
  color: #1f3028;
  font-size: 15px;
  line-height: 22px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.clean-form-input::placeholder {
  color: #7a8c80;
}

.clean-form-input:focus {
  outline: none;
  border-color: #176347;
  box-shadow: 0 0 0 3px rgba(23, 99, 71, 0.12);
}

.clean-form-input.is-invalid {
  border-color: #dc2626;
}

.password-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.clean-form-input.has-toggle {
  padding-right: 44px;
}

.password-eye-toggle {
  position: absolute;
  right: 8px;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #53665a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.password-eye-toggle:hover {
  color: #103c2e;
  background: #f6f8f4;
}

.field-validation-msg {
  font-size: 13px;
  color: #b42318;
  margin-top: 5px;
}

.session-options-row {
  display: flex;
  align-items: center;
  margin-top: -2px;
}

.trust-device-checkbox {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #53665a;
  cursor: pointer;
  user-select: none;
}

.trust-device-checkbox input {
  width: 16px;
  height: 16px;
  accent-color: #176347;
  cursor: pointer;
}

.primary-signin-btn {
  width: 100%;
  min-height: 44px;
  padding: 11px 18px;
  border: 1px solid #176347;
  border-radius: 8px;
  background: #176347;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  line-height: 22px;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.primary-signin-btn:hover:not(:disabled) {
  background: #103c2e;
  border-color: #103c2e;
}

.primary-signin-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.login-help-text {
  margin: 18px 0 0;
  font-size: 14px;
  line-height: 21px;
  color: #53665a;
}

.login-policy-text {
  margin: 10px 0 0;
  font-size: 13px;
  line-height: 19px;
  color: #687a6f;
}

@media (max-width: 920px) {
  .academic-login-split {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr;
  }

  .brand-column {
    padding: 14px 20px;
  }

  .brand-hero-content,
  .brand-bottom-meta {
    display: none;
  }

  .auth-canvas {
    padding: 28px 20px;
    align-items: flex-start;
  }
}
</style>
