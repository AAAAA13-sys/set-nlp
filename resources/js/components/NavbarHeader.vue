<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

defineEmits(['open-mobile-sidebar', 'open-command-palette']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const clearanceStatus = computed(() => evaluationStore.queueProgress.clearanceStatus);
const isCleared = computed(() => evaluationStore.queueProgress.allCleared);
const showAuthenticatedBar = computed(
  () => authStore.isAuthenticated && route?.path !== '/login',
);

const firstPendingSectionId = computed(() => {
  const pending = evaluationStore.courses.find((c) => c.status === 'PENDING');
  return pending?.course_section_id || evaluationStore.activeCourse?.course_section_id || null;
});

const roleNavLinks = computed(() => ({
  student: [
    { to: '/queue', label: 'Evaluation Queue', testId: 'nav-link-student-queue' },
    ...(firstPendingSectionId.value
      ? [
          {
            to: `/evaluate/${firstPendingSectionId.value}`,
            label: 'Active SET Form',
            testId: 'nav-link-student-survey',
          },
        ]
      : []),
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
    { to: '/admin', label: 'Registrar & System Admin', testId: 'nav-link-admin-console' },
  ],
}));

const authorizedNavLinks = computed(() => {
  const role = String(authStore.currentRole || '').toLowerCase();
  return roleNavLinks.value[role] || [];
});

async function handleLogout() {
  evaluationStore.resetState();
  await authStore.logout();
  if (router) {
    router.push('/login').catch(() => {});
  }
}
</script>

<template>
  <header
    class="navbar-header sch-topbar"
    role="banner"
    aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header"
  >
    <div class="sch-topbar-inner">
      <div class="sch-topbar-left">
        <button
          v-if="showAuthenticatedBar"
          type="button"
          class="mobile-menu-trigger"
          aria-label="Open workspace navigation drawer"
          data-testid="mobile-sidebar-toggle"
          @click="$emit('open-mobile-sidebar')"
        >
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>

        <div class="sch-brand-stack">
          <div class="sch-kicker-row">
            <span class="sch-uni-kicker">PAMANTASAN NG LUNGSOD NG PASIG</span>
            <span class="sch-domain-dot" aria-hidden="true">·</span>
            <span class="sch-domain-kicker">plpasig.edu.ph</span>
          </div>
          <h1 class="sch-portal-heading">Institutional Evaluation Portal</h1>
        </div>
      </div>

      <div class="sch-topbar-right">
        <button
          v-if="showAuthenticatedBar"
          type="button"
          class="sch-cmdk-trigger"
          data-testid="navbar-cmdk-btn"
          title="Open Command Palette (Ctrl+K)"
          @click="$emit('open-command-palette')"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <span>Search or jump…</span>
          <kbd>Ctrl K</kbd>
        </button>

        <div class="sch-term-chip" aria-label="Active Academic Term">
          <span>Active Term:</span>
          <strong>{{ authStore.activeTerm || '1st Semester AY 2026-2027' }}</strong>
        </div>

        <template v-if="showAuthenticatedBar">
          <nav class="sch-inline-nav" aria-label="Primary Workspace Navigation" data-testid="navbar-quick-nav">
            <router-link
              v-for="link in authorizedNavLinks"
              :key="link.to"
              :to="link.to"
              class="sch-nav-pill"
              :data-testid="link.testId"
            >
              {{ link.label }}
            </router-link>
          </nav>

          <div
            class="sch-status-pill"
            :class="authStore.currentRole === 'student' && !isCleared ? 'is-pending' : 'is-cleared'"
            data-testid="navbar-clearance-badge"
            role="status"
          >
            <span>{{ authStore.currentRole === 'student' ? 'Clearance:' : 'Role:' }}</span>
            <strong>
              {{
                authStore.currentRole === 'student'
                  ? clearanceStatus
                  : authStore.user?.role_label || String(authStore.currentRole || '').toUpperCase()
              }}
            </strong>
          </div>

          <div class="sch-signed-in" data-testid="logged-in-user">
            <span class="sr-only">Signed in as:</span>
            <span class="sch-signed-caption" aria-hidden="true">Signed in as:</span>
            <strong class="sch-user-id">{{ authStore.displayIdentifier }}</strong>
          </div>

          <button
            type="button"
            class="sch-logout-btn"
            data-testid="navbar-logout-btn"
            title="Sign out of current institutional workspace"
            @click="handleLogout"
          >
            Sign Out
          </button>
        </template>
      </div>
    </div>
  </header>
</template>

<style scoped>
.sch-topbar {
  position: sticky;
  top: 0;
  z-index: 40;
  background: #ffffff;
  border-bottom: 1px solid #dbe3da;
  color: #1f3028;
  box-shadow: none;
}

.sch-topbar-inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  min-height: 64px;
  padding: 0.65rem 1.35rem;
}

.sch-topbar-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.mobile-menu-trigger {
  display: none;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #103c2e;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.sch-brand-stack {
  display: flex;
  flex-direction: column;
  gap: 0.08rem;
  min-width: 0;
}

.sch-kicker-row {
  display: flex;
  align-items: center;
  gap: 0.38rem;
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #176347;
}

.sch-domain-kicker {
  font-weight: 600;
  letter-spacing: 0.02em;
  color: #4b5e53;
}

.sch-portal-heading {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: -0.015em;
  color: #1f3028;
}

.sch-topbar-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.sch-cmdk-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  height: 34px;
  padding: 0 0.65rem;
  border-radius: 9px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #4b5e53;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.14s ease, background-color 0.14s ease;
}

.sch-cmdk-trigger:hover {
  background: #edf3ee;
  border-color: #176347;
  color: #103c2e;
}

.sch-cmdk-trigger kbd {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.08rem 0.35rem;
  border-radius: 5px;
  background: #ffffff;
  border: 1px solid #cfd9d0;
  color: #103c2e;
}

.sch-term-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
  height: 32px;
  padding: 0 0.68rem;
  border-radius: 999px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  font-size: 0.74rem;
  color: #3f5247;
}

.sch-term-chip strong {
  color: #103c2e;
}

.sch-inline-nav {
  display: none;
}

.sch-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  height: 32px;
  padding: 0 0.68rem;
  border-radius: 999px;
  font-size: 0.74rem;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  color: #14532d;
}

.sch-status-pill.is-pending {
  border-color: #fde68a;
  background: #fffbeb;
  color: #854d0e;
}

.sch-signed-in {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.75rem;
  color: #4b5e53;
}

.sch-user-id {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.75rem;
  color: #1f3028;
}

.sch-logout-btn {
  height: 32px;
  padding: 0 0.72rem;
  border-radius: 8px;
  border: 1px solid #cfd9d0;
  background: #ffffff;
  color: #1f3028;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.14s ease, border-color 0.14s ease;
}

.sch-logout-btn:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #991b1b;
}

@media (max-width: 1120px) {
  .sch-signed-in,
  .sch-term-chip {
    display: none;
  }
}

@media (max-width: 900px) {
  .mobile-menu-trigger {
    display: inline-flex;
  }

  .sch-topbar-inner {
    padding: 0.55rem 0.9rem;
  }

  .sch-cmdk-trigger span,
  .sch-cmdk-trigger kbd {
    display: none;
  }
}

@media print {
  .sch-topbar {
    display: none !important;
  }
}
</style>
