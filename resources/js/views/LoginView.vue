<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { DEMO_ROLE_PROFILES, getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';

const router = useRouter();
const authStore = useAuthStore();

const selectedRole = ref('student');
const institutionalId = ref(DEMO_ROLE_PROFILES.student.institutional_id);
const password = ref('••••••••••••');

function selectPresetRole(roleKey) {
  selectedRole.value = roleKey;
  const profile = DEMO_ROLE_PROFILES[roleKey];
  if (profile) {
    institutionalId.value = profile.institutional_id;
  }
}

async function handleLogin() {
  const user = await authStore.login({
    role: selectedRole.value,
    institutional_id: institutionalId.value,
    password: password.value,
  });
  const target = getDefaultRouteForRole(user?.role || selectedRole.value);
  if (router) {
    router.push(target).catch(() => {});
  }
}
</script>

<template>
  <main class="workspace-main login-workspace" aria-labelledby="login-heading">
    <section class="card login-card">
      <div class="plp-login-banner">
        <div class="plp-crest-seal" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="32" cy="32" r="29" stroke="#FACC15" stroke-width="2.5" stroke-dasharray="3 2" />
            <circle cx="32" cy="32" r="23" fill="#0B4F26" stroke="#EAB308" stroke-width="1.8" />
            <path d="M32 14L44 20V31C44 40 38.8 46.8 32 50C25.2 46.8 20 40 20 31V20L32 14Z" fill="#14532D" stroke="#FACC15" stroke-width="1.8" />
            <path d="M32 20L35.5 27.5L43 28.5L37.5 33.8L39 41.5L32 37.8L25 41.5L26.5 33.8L21 28.5L28.5 27.5L32 20Z" fill="#FACC15" />
          </svg>
        </div>
        <div>
          <h3>PAMANTASAN NG LUNGSOD NG PASIG</h3>
          <p>Alkalde Joseco St., Kapasigan, Pasig City • Official SET &amp; calamanCy NLP Portal (plpasig.edu.ph)</p>
        </div>
      </div>

      <div class="card-header">
        <span class="eyebrow-badge">PLP EvaluaTech • Five-Role Institutional Access Control</span>
        <h2 id="login-heading" class="section-title">
          Automated Student Evaluation of Teachers (SET) Portal
        </h2>
        <p class="section-subtitle">
          Authenticate with your Pamantasan ng Lungsod ng Pasig credentials or select a seeded role workspace below.
          Student submissions are protected by Dual-Token Cryptographic Decoupling.
        </p>
      </div>

      <div class="role-preset-grid" role="group" aria-label="Seeded Institutional Role Accounts">
        <button
          v-for="(profile, roleKey) in DEMO_ROLE_PROFILES"
          :key="roleKey"
          type="button"
          class="role-preset-btn"
          :class="{ active: selectedRole === roleKey }"
          :data-testid="`preset-role-${roleKey}`"
          @click="selectPresetRole(roleKey)"
        >
          <strong>{{ profile.role_label }}</strong>
          <span>{{ profile.institutional_id }}</span>
          <small>{{ profile.name }}</small>
        </button>
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="form-field">
          <label for="institutional-id-input">PLP Institutional ID / University Email (@plpasig.edu.ph)</label>
          <input
            id="institutional-id-input"
            v-model="institutionalId"
            type="text"
            required
            autocomplete="username"
            data-testid="login-id-input"
          />
        </div>

        <div class="form-field">
          <label for="password-input">Institutional Passphrase</label>
          <input
            id="password-input"
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            data-testid="login-password-input"
          />
        </div>

        <div class="login-actions">
          <button
            type="submit"
            class="btn-primary"
            :disabled="authStore.loading"
            data-testid="login-submit-btn"
          >
            {{ authStore.loading ? 'Authenticating Session...' : `Sign In as ${DEMO_ROLE_PROFILES[selectedRole]?.role_label}` }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
