<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore, getDefaultRouteForRole } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const clearanceStatus = computed(() => evaluationStore.queueProgress.clearanceStatus);
const isCleared = computed(() => evaluationStore.queueProgress.allCleared);

const availableRoles = [
  { value: 'student', label: 'Student (2024-10892)' },
  { value: 'faculty', label: 'Faculty (Prof. A. Dela Cruz)' },
  { value: 'chair', label: 'Department Chair (Dr. M. Santos)' },
  { value: 'dean', label: 'Dean (Dr. V. Mendoza)' },
  { value: 'admin', label: 'System Admin / PLP Registrar' },
];

const roleNavLinks = {
  student: [
    { to: '/queue', label: 'Student Queue', testId: 'nav-link-student-queue' },
    { to: '/evaluate/104', label: 'SET Survey', testId: 'nav-link-student-survey' },
  ],
  faculty: [
    { to: '/faculty', label: 'Faculty Portal', testId: 'nav-link-faculty-portal' },
  ],
  chair: [
    { to: '/chair', label: 'Department Chair Portal', testId: 'nav-link-chair-portal' },
  ],
  dean: [
    { to: '/dean', label: 'Dean Analytics', testId: 'nav-link-dean-analytics' },
  ],
  admin: [
    { to: '/admin', label: 'Admin & Locks', testId: 'nav-link-admin-console' },
  ],
};

const authorizedNavLinks = computed(() => {
  const role = String(authStore.currentRole || 'student').toLowerCase();
  return roleNavLinks[role] || roleNavLinks.student;
});

function onRoleChange(event) {
  const nextRole = event.target.value;
  authStore.switchDemoRole(nextRole);
  const targetRoute = getDefaultRouteForRole(nextRole);
  if (router) {
    router.push(targetRoute).catch(() => {});
  }
}
</script>

<template>
  <header class="navbar-header" role="banner" aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header">
    <div class="plp-top-ribbon">
      <div class="plp-top-ribbon-inner">
        <span>Republic of the Philippines • City Government of Pasig • Alkalde Joseco St., Kapasigan, Pasig City</span>
        <span class="plp-ribbon-motto">Official Reference: plpasig.edu.ph • Academic Excellence &amp; Pasigueño Integrity</span>
      </div>
    </div>

    <div class="navbar-container">
      <div class="navbar-brand-row">
        <div class="brand-identity">
          <div class="plp-crest-seal" aria-hidden="true" title="Pamantasan ng Lungsod ng Pasig Official Seal">
            <svg width="34" height="34" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="32" cy="32" r="29" stroke="#FACC15" stroke-width="2.5" stroke-dasharray="3 2" />
              <circle cx="32" cy="32" r="23" fill="#0B4F26" stroke="#EAB308" stroke-width="1.8" />
              <path d="M32 14L44 20V31C44 40 38.8 46.8 32 50C25.2 46.8 20 40 20 31V20L32 14Z" fill="#14532D" stroke="#FACC15" stroke-width="1.8" />
              <path d="M32 20L35.5 27.5L43 28.5L37.5 33.8L39 41.5L32 37.8L25 41.5L26.5 33.8L21 28.5L28.5 27.5L32 20Z" fill="#FACC15" />
            </svg>
          </div>

          <div class="brand-text-stack">
            <span class="plp-university-title">PAMANTASAN NG LUNGSOD NG PASIG</span>
            <div class="brand-sub-row">
              <span class="brand-badge">[PLP • EduMetrics / EvaluaTech]</span>
              <h1 class="brand-title">Institutional Evaluation Portal</h1>
            </div>
          </div>
        </div>

        <div class="term-pill" aria-label="Active Academic Term">
          <span class="term-label">Active Term:</span>
          <strong class="term-value">{{ authStore.activeTerm || 'Fall / 1st Semester' }}</strong>
        </div>
      </div>

      <div class="navbar-meta-row">
        <div class="user-identity" data-testid="logged-in-user">
          <span class="meta-caption">Logged in as:</span>
          <strong class="user-code">{{ authStore.displayIdentifier || '2024-10892 (Student)' }}</strong>
        </div>

        <div class="navbar-controls">
          <label class="role-switcher-label" for="demo-role-switcher">
            <span>Workspace Role:</span>
            <select
              id="demo-role-switcher"
              class="role-switcher-select"
              :value="authStore.currentRole"
              aria-label="Switch institutional role workspace"
              @change="onRoleChange"
            >
              <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
          </label>

          <nav class="quick-nav" aria-label="Primary Workspace Navigation" data-testid="navbar-quick-nav">
            <router-link
              v-for="link in authorizedNavLinks"
              :key="link.to"
              :to="link.to"
              class="nav-link"
              :data-testid="link.testId"
            >
              {{ link.label }}
            </router-link>
          </nav>

          <div
            class="clearance-badge"
            :class="isCleared ? 'clearance-cleared' : 'clearance-pending'"
            data-testid="navbar-clearance-badge"
            role="status"
          >
            <span>Clearance:</span>
            <strong>{{ authStore.currentRole === 'student' ? clearanceStatus : (authStore.user?.role_label || authStore.currentRole.toUpperCase()) }}</strong>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
