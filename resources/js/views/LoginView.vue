<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
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
    return 'Enter your PLP Institutional ID (e.g., 2024-10892) or @plpasig.edu.ph email.';
  }
  if (trimmed.length < 4) {
    return 'Identifier must be at least 4 characters.';
  }
  return '';
});

const passwordValidationHint = computed(() => {
  if (!touchedPassword.value) return '';
  if (!password.value) {
    return 'Enter your institutional passphrase.';
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
    errorMessage.value = 'Enter your PLP Institutional ID or university email address.';
    return;
  }
  if (!password.value) {
    errorMessage.value = 'Enter your institutional passphrase to sign in.';
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
      err?.message || 'Invalid institutional ID or passphrase. Please verify your credentials.';
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
    <!-- Left Column: Deep Forest Green Branding + Geometric Mesh & Archival Grain Texture -->
    <aside class="brand-column" aria-label="Pamantasan ng Lungsod ng Pasig Institutional Overview">
      <!-- Subtle tactile SVG grain + architectural coordinate mesh -->
      <svg class="brand-noise-svg" aria-hidden="true" focusable="false">
        <defs>
          <filter id="plp-archival-grain" x="0%" y="0%" width="100%" height="100%">
            <feTurbulence type="fractalNoise" baseFrequency="0.82" numOctaves="3" stitchTiles="stitch" />
            <feColorMatrix type="saturate" values="0" />
          </filter>
          <pattern id="plp-geo-mesh" width="56" height="56" patternUnits="userSpaceOnUse">
            <path d="M 56 0 L 0 0 0 56" fill="none" stroke="rgba(232, 208, 132, 0.065)" stroke-width="1" />
            <path d="M 0 56 L 56 0" fill="none" stroke="rgba(255, 255, 255, 0.028)" stroke-width="0.75" />
            <circle cx="0" cy="0" r="1.35" fill="rgba(232, 208, 132, 0.22)" />
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#plp-geo-mesh)" />
        <rect width="100%" height="100%" filter="url(#plp-archival-grain)" opacity="0.042" />
      </svg>

      <div class="brand-ambient-glow" aria-hidden="true"></div>

      <!-- Top-Left Refined Academic Tech Brand Mark -->
      <div class="brand-top-bar">
        <div class="brand-crest-mark" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M12 2.5L4.5 5.8V11.6C4.5 16.45 7.68 20.88 12 22.2C16.32 20.88 19.5 16.45 19.5 11.6V5.8L12 2.5Z"
              stroke="#E5C158"
              stroke-width="1.65"
              fill="rgba(229, 193, 88, 0.1)"
            />
            <path
              d="M9.5 9.25H14.75M9.5 12.1H13.6M9.5 14.95H14.75M9.5 9.25V14.95"
              stroke="#FAFBF9"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="brand-wordmark">
          <strong>PLP EvaluTech</strong>
          <span>PAMANTASAN NG LUNGSOD NG PASIG</span>
        </div>
      </div>

      <!-- Center Editorial Narrative & Minimalist Feature Tags -->
      <div class="brand-hero-content">
        <div class="brand-eyebrow">
          <span class="eyebrow-rule" aria-hidden="true"></span>
          <span>ACADEMIC QUALITY &amp; CRYPTOGRAPHIC GOVERNANCE</span>
        </div>

        <h1 class="brand-display-headline">
          Candid student feedback,
          <em>decoupled by design.</em>
        </h1>

        <p class="brand-lead-copy">
          Institutional faculty evaluation, cryptographic examination clearance, and
          multilingual pedagogical analytics engineered for Pamantasan ng Lungsod ng Pasig.
        </p>

        <div class="feature-tags-stack" role="list" aria-label="PLP EvaluTech Security & Analytics Pillars">
          <!-- Feature Tag 1: Shield -->
          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-icon" aria-hidden="true">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="m9 12 2 2 4-4"></path>
              </svg>
            </span>
            <div class="feature-tag-body">
              <span class="feature-tag-title">Zero-Knowledge Vault</span>
              <span class="feature-tag-desc">Dual-token separation stores zero student IDs or session metadata</span>
            </div>
          </div>

          <!-- Feature Tag 2: Eye -->
          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-icon" aria-hidden="true">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </span>
            <div class="feature-tag-body">
              <span class="feature-tag-title">Double-Blind Registrar Lock</span>
              <span class="feature-tag-desc">Faculty metrics remain sealed until official term grade finalization</span>
            </div>
          </div>

          <!-- Feature Tag 3: Brain -->
          <div class="feature-tag-item" role="listitem">
            <span class="feature-tag-icon" aria-hidden="true">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 4.44-2.04Z"></path>
                <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-4.44-2.04Z"></path>
              </svg>
            </span>
            <div class="feature-tag-body">
              <span class="feature-tag-title">calamanCy + VADER NLP</span>
              <span class="feature-tag-desc">Tagalog, Taglish &amp; English five-cluster pedagogical polarity analysis</span>
            </div>
          </div>
        </div>
      </div>

      <div class="brand-bottom-meta">
        <span>OFFICE OF THE UNIVERSITY REGISTRAR</span>
        <span class="meta-sep" aria-hidden="true">·</span>
        <span>{{ authStore.activeTerm || '1ST SEMESTER AY 2026–2027' }}</span>
      </div>
    </aside>

    <!-- Right Column: Warm Off-White (#F9F9F8) Surface + Floating Elevated Card -->
    <section class="auth-canvas">
      <div class="floating-auth-card" data-testid="login-card">
        <header class="auth-card-header">
          <div class="portal-access-label">
            <span class="portal-access-dot" aria-hidden="true"></span>
            <span>INSTITUTIONAL PORTAL ACCESS</span>
          </div>
          <h2 id="login-heading" class="auth-card-title">Sign in to your account</h2>
          <p class="auth-card-subtitle">
            Authenticate with your Pamantasan ng Lungsod ng Pasig institutional ID or university email.
          </p>
        </header>

        <div
          v-if="errorMessage || authStore.error"
          class="auth-error-banner"
          data-testid="login-error-alert"
          role="alert"
          aria-live="polite"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            <strong>Authentication failed:</strong>
            <span>{{ errorMessage || authStore.error }}</span>
          </div>
        </div>

        <form class="auth-form" @submit.prevent="handleLogin" novalidate>
          <!-- Floating Label Field 1: Institutional ID / Email -->
          <div class="floating-field-group">
            <div
              class="floating-control"
              :class="{
                'has-value': institutionalId.length > 0,
                'has-error': Boolean(idValidationHint),
              }"
            >
              <span class="field-leading-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </span>
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
                placeholder=" "
                class="floating-input"
                :aria-invalid="Boolean(idValidationHint)"
                data-testid="login-id-input"
                @blur="touchedId = true"
              />
              <label for="institutional-id-input" class="floating-label">
                Institutional ID or University Email
              </label>
            </div>
            <span v-if="idValidationHint" class="field-validation-msg" data-testid="login-id-validation">
              {{ idValidationHint }}
            </span>
          </div>

          <!-- Floating Label Field 2: Password with Leading Lock + Trailing Eye Toggle -->
          <div class="floating-field-group">
            <div
              class="floating-control has-trailing"
              :class="{
                'has-value': password.length > 0,
                'has-error': Boolean(passwordValidationHint),
              }"
            >
              <span class="field-leading-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <input
                id="password-input"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                required
                autocomplete="current-password"
                spellcheck="false"
                placeholder=" "
                class="floating-input"
                :aria-invalid="Boolean(passwordValidationHint)"
                data-testid="login-password-input"
                @blur="touchedPassword = true"
              />
              <label for="password-input" class="floating-label">
                Institutional Passphrase
              </label>
              <button
                type="button"
                class="password-eye-toggle"
                data-testid="toggle-password-btn"
                :aria-label="showPassword ? 'Hide passphrase' : 'Show passphrase'"
                :title="showPassword ? 'Hide passphrase' : 'Show passphrase'"
                @click="togglePasswordVisibility"
              >
                <svg v-if="!showPassword" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
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
              <span>Remember trusted device</span>
            </label>
            <span class="session-mode-pill" title="Unchecked sessions isolate tokens in ephemeral sessionStorage">
              {{ rememberSession ? 'Persistent' : 'Ephemeral Session' }}
            </span>
          </div>

          <button
            type="submit"
            class="primary-signin-btn"
            :disabled="authStore.loading"
            :aria-busy="authStore.loading"
            data-testid="login-submit-btn"
          >
            <span>{{ authStore.loading ? 'Signing in…' : 'Sign in' }}</span>
            <svg
              v-if="!authStore.loading"
              class="signin-arrow-icon"
              width="17"
              height="17"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.1"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </button>
        </form>

        <!-- Sleek RA 10173 Compliance Callout Card -->
        <div class="compliance-callout-card" data-testid="login-privacy-banner" role="note">
          <div class="compliance-lock-badge" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </div>
          <div class="compliance-copy">
            <strong>RA 10173 Data Privacy &amp; Cryptographic Decoupling</strong>
            <span>
              Evaluation vault records never store student IDs, IP addresses, or session identifiers.
            </span>
          </div>
        </div>

        <footer class="auth-card-footer">
          <p>Credential support: Office of the University Registrar or PLP MIS Helpdesk.</p>
        </footer>
      </div>
    </section>
  </main>
</template>

<style scoped>
.academic-login-split {
  min-height: 100dvh;
  display: grid;
  grid-template-columns: minmax(430px, 1fr) minmax(430px, 1fr);
  background: #f9f9f8;
}

/* =========================================================================
   Left Column: Rich Forest Green + Geometric Mesh + Tactile Noise
   ========================================================================= */
.brand-column {
  position: relative;
  overflow: hidden;
  background: linear-gradient(158deg, #072317 0%, #0b3322 54%, #0f3f2a 100%);
  color: #edf3ef;
  padding: 2.25rem 3.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 1.75rem;
  isolation: isolate;
}

.brand-noise-svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 0;
  mask-image: radial-gradient(ellipse 92% 88% at 35% 45%, #000 45%, rgba(0, 0, 0, 0.25) 100%);
}

.brand-ambient-glow {
  position: absolute;
  top: -12%;
  left: -10%;
  width: 460px;
  height: 460px;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(224, 184, 76, 0.11) 0%, rgba(16, 86, 55, 0.05) 48%, transparent 72%);
  pointer-events: none;
  z-index: 0;
}

.brand-top-bar,
.brand-hero-content,
.brand-bottom-meta {
  position: relative;
  z-index: 1;
}

.brand-top-bar {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.brand-crest-mark {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.09) 0%, rgba(255, 255, 255, 0.03) 100%);
  border: 1px solid rgba(229, 193, 88, 0.38);
  box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.12);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-wordmark {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.brand-wordmark strong {
  font-size: 1.02rem;
  font-weight: 700;
  letter-spacing: -0.022em;
  color: #fafbf9;
  line-height: 1.15;
}

.brand-wordmark span {
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.13em;
  color: #9ec0ae;
}

.brand-hero-content {
  max-width: 520px;
}

.brand-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  color: #dfc067;
  margin-bottom: 0.85rem;
}

.eyebrow-rule {
  width: 22px;
  height: 1px;
  background: rgba(223, 192, 103, 0.65);
}

.brand-display-headline {
  margin: 0 0 0.9rem;
  font-family: 'Newsreader', Georgia, serif;
  font-size: clamp(2rem, 2.85vw, 2.68rem);
  font-weight: 600;
  line-height: 1.09;
  letter-spacing: -0.028em;
  color: #fafbf9;
}

.brand-display-headline em {
  display: block;
  font-style: italic;
  font-weight: 500;
  color: #ebd99b;
}

.brand-lead-copy {
  margin: 0 0 1.4rem;
  font-size: 0.9rem;
  line-height: 1.58;
  letter-spacing: -0.006em;
  color: #b7d0c2;
  max-width: 48ch;
}

/* Minimalist Feature Tags (Shield, Eye, Brain) */
.feature-tags-stack {
  display: flex;
  flex-direction: column;
  gap: 0.58rem;
}

.feature-tag-item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.68rem 0.9rem;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.036);
  backdrop-filter: blur(6px);
  transition: border-color 0.18s ease, background-color 0.18s ease;
}

.feature-tag-item:hover {
  border-color: rgba(229, 193, 88, 0.32);
  background: rgba(255, 255, 255, 0.055);
}

.feature-tag-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: rgba(229, 193, 88, 0.11);
  border: 1px solid rgba(229, 193, 88, 0.25);
  color: #e5c158;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.feature-tag-body {
  display: flex;
  flex-direction: column;
  gap: 0.08rem;
}

.feature-tag-title {
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: -0.012em;
  color: #f8faf8;
}

.feature-tag-desc {
  font-size: 0.76rem;
  line-height: 1.38;
  color: #adc8b9;
}

.brand-bottom-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.45rem;
  padding-top: 0.85rem;
  border-top: 1px solid rgba(255, 255, 255, 0.09);
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.11em;
  color: #88ae9a;
}

.meta-sep {
  color: #dfc067;
}

/* =========================================================================
   Right Column: Warm Off-White (#F9F9F8) + Soft Floating Card
   ========================================================================= */
.auth-canvas {
  background: #f9f9f8;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.75rem 2.25rem;
}

.floating-auth-card {
  width: 100%;
  max-width: 444px;
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(15, 46, 31, 0.075);
  box-shadow:
    0 24px 56px -14px rgba(10, 39, 26, 0.085),
    0 6px 18px -4px rgba(10, 39, 26, 0.04);
  padding: 2rem 2.2rem 1.8rem;
}

.auth-card-header {
  margin-bottom: 1.25rem;
}

.portal-access-label {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  color: #856416;
  margin-bottom: 0.45rem;
}

.portal-access-dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: #c69a2c;
}

.auth-card-title {
  margin: 0 0 0.35rem;
  font-size: 1.56rem;
  font-weight: 800;
  letter-spacing: -0.028em;
  line-height: 1.18;
  color: #161d19;
}

.auth-card-subtitle {
  margin: 0;
  font-size: 0.84rem;
  line-height: 1.48;
  letter-spacing: -0.005em;
  color: #56635c;
}

.auth-error-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  padding: 0.68rem 0.85rem;
  margin-bottom: 1rem;
  border-radius: 11px;
  border: 1px solid #f2c2c6;
  background: #fdf3f4;
  color: #991b1b;
  font-size: 0.8rem;
  line-height: 1.4;
}

.auth-error-banner svg {
  flex-shrink: 0;
  margin-top: 0.1rem;
}

.auth-error-banner div {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 0.82rem;
}

/* Floating-Label Input System */
.floating-field-group {
  display: flex;
  flex-direction: column;
  gap: 0.28rem;
}

.floating-control {
  position: relative;
  display: flex;
  align-items: center;
  height: 52px;
  border-radius: 11px;
  border: 1px solid #dcdfd9;
  background: #fcfcfb;
  transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
}

.floating-control:hover {
  border-color: #c3cac3;
}

.floating-control:focus-within {
  background: #ffffff;
  border-color: #14633a;
  box-shadow: 0 0 0 3.5px rgba(20, 99, 58, 0.13);
}

.floating-control.has-error {
  border-color: #d92d20;
  background: #fffbfa;
}

.floating-control.has-error:focus-within {
  box-shadow: 0 0 0 3.5px rgba(217, 45, 32, 0.12);
}

.field-leading-icon {
  position: absolute;
  left: 0.88rem;
  color: #77837c;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  transition: color 0.16s ease;
}

.floating-control:focus-within .field-leading-icon {
  color: #14633a;
}

.floating-input {
  width: 100%;
  height: 100%;
  border: 0;
  border-radius: 11px;
  background: transparent;
  padding: 1.15rem 0.9rem 0.32rem 2.55rem;
  font-size: 0.9rem;
  font-weight: 500;
  letter-spacing: -0.008em;
  color: #161d19;
}

.floating-control.has-trailing .floating-input {
  padding-right: 2.8rem;
}

.floating-input:focus {
  outline: none;
}

.floating-label {
  position: absolute;
  left: 2.55rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.86rem;
  font-weight: 500;
  color: #6a7770;
  pointer-events: none;
  transform-origin: left top;
  transition: top 0.15s ease, transform 0.15s ease, font-size 0.15s ease, color 0.15s ease;
}

.floating-input:focus ~ .floating-label,
.floating-input:not(:placeholder-shown) ~ .floating-label,
.floating-control.has-value .floating-label {
  top: 0.44rem;
  transform: translateY(0);
  font-size: 0.67rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #14633a;
}

.floating-control.has-error .floating-label {
  color: #b42318;
}

.password-eye-toggle {
  position: absolute;
  right: 0.5rem;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: #64716a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.14s ease, color 0.14s ease;
}

.password-eye-toggle:hover {
  background: #eff3f0;
  color: #0e462b;
}

.field-validation-msg {
  font-size: 0.73rem;
  font-weight: 500;
  color: #b42318;
  padding-left: 0.2rem;
}

.session-options-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.05rem;
}

.trust-device-checkbox {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.79rem;
  font-weight: 500;
  color: #46544d;
  cursor: pointer;
}

.trust-device-checkbox input {
  accent-color: #14633a;
  width: 15px;
  height: 15px;
  cursor: pointer;
}

.session-mode-pill {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.16rem 0.55rem;
  border-radius: 999px;
  background: #f1f6f3;
  border: 1px solid #d5e2da;
  color: #145a36;
}

/* Primary Forest-Green Gradient CTA Button with Subtle Drop Shadow + Right Arrow */
.primary-signin-btn {
  width: 100%;
  height: 46px;
  margin-top: 0.15rem;
  padding: 0 1.25rem;
  border-radius: 999px;
  border: 1px solid #0b3d24;
  background: linear-gradient(180deg, #176940 0%, #0d482b 100%);
  box-shadow:
    0 10px 22px -6px rgba(13, 72, 43, 0.38),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  color: #ffffff;
  font-size: 0.91rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}

.primary-signin-btn:hover:not(:disabled) {
  filter: brightness(1.05);
  box-shadow:
    0 14px 26px -6px rgba(13, 72, 43, 0.44),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.signin-arrow-icon {
  transition: transform 0.16s ease;
}

.primary-signin-btn:hover:not(:disabled) .signin-arrow-icon {
  transform: translateX(3px);
}

.primary-signin-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* Sleek RA 10173 Data Privacy Callout Card */
.compliance-callout-card {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  margin-top: 1.05rem;
  padding: 0.72rem 0.85rem;
  border-radius: 12px;
  background: linear-gradient(180deg, #fcfbf7 0%, #f6f5ee 100%);
  border: 1px solid rgba(188, 152, 56, 0.34);
}

.compliance-lock-badge {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  background: rgba(20, 99, 58, 0.08);
  border: 1px solid rgba(20, 99, 58, 0.16);
  color: #145a36;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 0.06rem;
}

.compliance-copy {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  font-size: 0.74rem;
  line-height: 1.42;
  color: #46534c;
}

.compliance-copy strong {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: #1b2821;
}

.auth-card-footer {
  margin-top: 0.9rem;
  padding-top: 0.75rem;
  border-top: 1px solid #eceee9;
  font-size: 0.72rem;
  line-height: 1.4;
  color: #66736c;
  text-align: center;
}

.auth-card-footer p {
  margin: 0;
}

@media (max-width: 920px) {
  .academic-login-split {
    grid-template-columns: 1fr;
    min-height: 100dvh;
  }

  .brand-column {
    padding: 0.95rem 1.2rem;
    gap: 0.35rem;
  }

  .brand-hero-content,
  .brand-bottom-meta {
    display: none;
  }

  .auth-canvas {
    align-items: flex-start;
    padding: 1.1rem 1rem 1.5rem;
  }

  .floating-auth-card {
    padding: 1.45rem 1.25rem 1.35rem;
    border-radius: 14px;
  }

  .auth-card-title {
    font-size: 1.36rem;
  }

  .floating-control {
    height: 48px;
  }

  .primary-signin-btn {
    height: 44px;
  }
}
</style>
