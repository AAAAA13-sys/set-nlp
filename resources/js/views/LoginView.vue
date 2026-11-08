<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { DEMO_ROLE_PROFILES, getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';

const router = useRouter();
const authStore = useAuthStore();

const selectedRole = ref('student');
const institutionalId = ref(DEMO_ROLE_PROFILES.student.institutional_id);
const password = ref('PLP-EvaluaTech-2026');
const showPassword = ref(false);
const rememberSession = ref(true);
const errorMessage = ref(null);

const activeRoleProfile = computed(() => DEMO_ROLE_PROFILES[selectedRole.value] || DEMO_ROLE_PROFILES.student);

function selectPresetRole(roleKey) {
  selectedRole.value = roleKey;
  const profile = DEMO_ROLE_PROFILES[roleKey];
  if (profile) {
    institutionalId.value = profile.institutional_id;
  }
  errorMessage.value = null;
}

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
}

async function handleLogin() {
  errorMessage.value = null;
  if (!institutionalId.value.trim()) {
    errorMessage.value = 'Please provide your PLP Institutional ID or university email.';
    return;
  }

  try {
    const user = await authStore.login({
      role: selectedRole.value,
      institutional_id: institutionalId.value.trim(),
      password: password.value,
      remember: rememberSession.value,
    });

    const targetRoute = getDefaultRouteForRole(user?.role || selectedRole.value);
    if (router) {
      router.push(targetRoute).catch(() => {});
    }
  } catch (err) {
    errorMessage.value = err?.message || 'Authentication failed. Please verify your credentials and network connection.';
  }
}

async function quickSignIn(roleKey) {
  selectPresetRole(roleKey);
  await handleLogin();
}
</script>

<template>
  <main class="workspace-main login-workspace" aria-labelledby="login-heading">
    <section class="card login-card" data-testid="login-card">
      <!-- PLP Institutional Seal & Header Ribbon -->
      <div class="plp-login-banner" role="region" aria-label="University Identity Header">
        <div class="plp-crest-seal" aria-hidden="true" title="Pamantasan ng Lungsod ng Pasig Official Seal">
          <svg width="42" height="42" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="32" cy="32" r="29" stroke="#FACC15" stroke-width="2.5" stroke-dasharray="3 2" />
            <circle cx="32" cy="32" r="23" fill="#0B4F26" stroke="#EAB308" stroke-width="1.8" />
            <path d="M32 14L44 20V31C44 40 38.8 46.8 32 50C25.2 46.8 20 40 20 31V20L32 14Z" fill="#14532D" stroke="#FACC15" stroke-width="1.8" />
            <path d="M32 20L35.5 27.5L43 28.5L37.5 33.8L39 41.5L32 37.8L25 41.5L26.5 33.8L21 28.5L28.5 27.5L32 20Z" fill="#FACC15" />
          </svg>
        </div>
        <div>
          <h3>PAMANTASAN NG LUNGSOD NG PASIG</h3>
          <p>Alkalde Joseco St., Kapasigan, Pasig City • Official SET &amp; calamanCy NLP Portal</p>
          <small class="plp-login-subtext">Official Reference: plpasig.edu.ph • Pasigueño Excellence &amp; Integrity</small>
        </div>
      </div>

      <div class="card-header">
        <span class="eyebrow-badge">PLP EvaluaTech • Five-Role Institutional Access Control</span>
        <h2 id="login-heading" class="section-title">
          Automated Student Evaluation of Teachers (SET) Portal
        </h2>
        <p class="section-subtitle">
          Secure institutional sign-in with Dual-Token Cryptographic Anonymity Decoupling and Double-Blind Faculty Access Controls.
        </p>
      </div>

      <!-- RA 10173 & Cryptographic Anonymity Trust Banner -->
      <div class="login-privacy-notice" data-testid="login-privacy-banner" role="note">
        <div class="privacy-icon" aria-hidden="true">🔒</div>
        <div class="privacy-text">
          <strong>RA 10173 Data Privacy &amp; Cryptographic Decoupling Active:</strong>
          <span>
            Student evaluation responses are cryptographically separated from identities into the isolated
            <code>evaluation_vault</code>. No institutional administrator, dean, chair, or instructor can trace your ratings.
          </span>
        </div>
      </div>

      <!-- Role Preset Selector Grid (Grading & Demonstration) -->
      <div class="role-preset-section">
        <label class="role-preset-label">
          <span>Select Institutional Role Account:</span>
          <small>Click to auto-populate credentials for instant role workspace access.</small>
        </label>

        <div class="role-preset-grid" role="group" aria-label="Seeded Institutional Role Accounts">
          <button
            v-for="(profile, roleKey) in DEMO_ROLE_PROFILES"
            :key="roleKey"
            type="button"
            class="role-preset-btn"
            :class="{ active: selectedRole === roleKey }"
            :data-testid="`preset-role-${roleKey}`"
            :aria-pressed="selectedRole === roleKey"
            @click="selectPresetRole(roleKey)"
          >
            <strong>{{ profile.role_label }}</strong>
            <span class="preset-id">{{ profile.institutional_id }}</span>
            <small class="preset-name">{{ profile.name }}</small>
            <span v-if="selectedRole === roleKey" class="active-indicator">● Active</span>
          </button>
        </div>
      </div>

      <!-- Live Error Message Banner -->
      <div
        v-if="errorMessage || authStore.error"
        class="login-error-alert"
        data-testid="login-error-alert"
        role="alert"
      >
        <span class="error-icon" aria-hidden="true">⚠️</span>
        <div class="error-content">
          <strong>Authentication Notice:</strong>
          <span>{{ errorMessage || authStore.error }}</span>
        </div>
      </div>

      <!-- Institutional Sign-In Form -->
      <form class="login-form" @submit.prevent="handleLogin" novalidate>
        <div class="form-field">
          <label for="institutional-id-input">
            PLP Institutional ID / University Email (@plpasig.edu.ph)
          </label>
          <div class="input-wrap">
            <input
              id="institutional-id-input"
              v-model="institutionalId"
              type="text"
              required
              autocomplete="username"
              placeholder="e.g. 2024-10892 or user@plpasig.edu.ph"
              data-testid="login-id-input"
            />
          </div>
          <span class="field-hint">Current Role Workspace: <strong>{{ activeRoleProfile.role_label }}</strong> ({{ activeRoleProfile.department_name }})</span>
        </div>

        <div class="form-field">
          <label for="password-input">Institutional Passphrase</label>
          <div class="password-input-wrapper">
            <input
              id="password-input"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="Enter institutional passphrase"
              data-testid="login-password-input"
            />
            <button
              type="button"
              class="password-toggle-btn"
              data-testid="toggle-password-btn"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="togglePasswordVisibility"
            >
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>

        <div class="form-row-between">
          <label class="checkbox-label" for="remember-session">
            <input
              id="remember-session"
              v-model="rememberSession"
              type="checkbox"
              data-testid="remember-session-input"
            />
            <span>Remember session on this institutional workstation</span>
          </label>

          <span class="term-badge-mini" aria-label="Active academic term">
            Active Term: <strong>{{ authStore.activeTerm || 'Fall / 1st Semester' }}</strong>
          </span>
        </div>

        <div class="login-actions">
          <button
            type="submit"
            class="btn-primary btn-block"
            :disabled="authStore.loading"
            data-testid="login-submit-btn"
          >
            <span v-if="authStore.loading" class="spinner" aria-hidden="true"></span>
            {{ authStore.loading ? 'Authenticating Session...' : `Sign In to ${activeRoleProfile.role_label} Workspace` }}
          </button>

          <button
            type="button"
            class="btn-secondary btn-block"
            data-testid="one-click-login-btn"
            :disabled="authStore.loading"
            @click="quickSignIn(selectedRole)"
          >
            ⚡ Instant Single-Click Demo Sign In ({{ activeRoleProfile.role_label }})
          </button>
        </div>
      </form>

      <!-- Institutional Footer Accreditation & Compliance -->
      <div class="login-footer">
        <div class="accreditation-pills">
          <span class="compliance-tag">AACCUP Level III Ready</span>
          <span class="compliance-tag">CHED CMO 46 Compliant</span>
          <span class="compliance-tag">ISO/IEC 25010 Evaluated</span>
          <span class="compliance-tag">calamanCy NLP Engine</span>
        </div>
        <p class="copyright-notice">
          © 2026 Pamantasan ng Lungsod ng Pasig • EduMetrics &amp; EvaluaTech Research Project • All Rights Reserved.
        </p>
      </div>
    </section>
  </main>
</template>

<style scoped>
.login-workspace {
  max-width: 760px;
  margin: 1.75rem auto;
  padding: 0 1rem;
}

.plp-login-banner {
  display: flex;
  align-items: center;
  gap: 1.15rem;
  padding: 1.1rem 1.25rem;
  background: linear-gradient(135deg, var(--plp-green-950) 0%, var(--plp-green-900) 100%);
  color: #ffffff;
  border-radius: var(--radius-sm);
  border-left: 5px solid var(--plp-gold-500);
  margin-bottom: 1.25rem;
  box-shadow: 0 4px 12px rgba(11, 79, 38, 0.15);
}

.plp-login-banner h3 {
  margin: 0;
  font-family: 'Fraunces', 'Georgia', serif;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: 0.03em;
  color: var(--plp-gold-400);
}

.plp-login-banner p {
  margin: 0.2rem 0 0;
  font-size: 0.85rem;
  color: #e2f1e7;
}

.plp-login-subtext {
  display: block;
  font-size: 0.76rem;
  color: #a7f3d0;
  margin-top: 0.2rem;
}

.login-privacy-notice {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding: 0.85rem 1rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: var(--radius-sm);
  margin: 1rem 0;
  font-size: 0.82rem;
  color: var(--plp-green-950);
}

.privacy-icon {
  font-size: 1.1rem;
  line-height: 1;
}

.privacy-text code {
  background: #dcfce7;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  font-weight: bold;
}

.role-preset-section {
  margin: 1.25rem 0;
}

.role-preset-label {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--plp-green-950);
}

.role-preset-label small {
  font-weight: 400;
  color: var(--text-muted);
}

.role-preset-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 0.65rem;
}

.role-preset-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 0.75rem 0.85rem;
  background: #ffffff;
  border: 1.5px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}

.role-preset-btn:hover {
  border-color: var(--plp-green-700);
  background: #f8faf8;
  transform: translateY(-1px);
}

.role-preset-btn.active {
  border-color: var(--plp-green-900);
  background: #f0fdf4;
  box-shadow: 0 0 0 2px rgba(11, 79, 38, 0.18);
}

.role-preset-btn strong {
  font-size: 0.84rem;
  color: var(--plp-green-950);
}

.preset-id {
  font-size: 0.76rem;
  font-family: 'JetBrains Mono', monospace;
  color: #4b5563;
  margin-top: 0.15rem;
}

.preset-name {
  font-size: 0.72rem;
  color: #6b7280;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.active-indicator {
  margin-top: 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--plp-green-800);
}

.login-error-alert {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding: 0.85rem 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: var(--radius-sm);
  color: #991b1b;
  font-size: 0.84rem;
  margin-bottom: 1rem;
}

.field-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 0.25rem;
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-wrapper input {
  width: 100%;
  padding-right: 4rem;
}

.password-toggle-btn {
  position: absolute;
  right: 0.5rem;
  background: none;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.25rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}

.password-toggle-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.form-row-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin: 0.25rem 0 1rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.82rem;
  color: #374151;
  cursor: pointer;
}

.term-badge-mini {
  font-size: 0.75rem;
  color: var(--plp-green-900);
  background: var(--plp-green-50);
  border: 1px solid #a7f3d0;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
}

.login-actions {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-top: 0.5rem;
}

.btn-block {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
  padding: 0.8rem;
  font-size: 0.95rem;
}

.login-footer {
  margin-top: 2rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border-subtle);
  text-align: center;
}

.accreditation-pills {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.compliance-tag {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--plp-green-950);
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}

.copyright-notice {
  font-size: 0.74rem;
  color: #6b7280;
  margin: 0;
}
</style>
