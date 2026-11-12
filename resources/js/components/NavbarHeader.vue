<script setup>
import AppIcon from './AppIcon.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const emit = defineEmits([
  'open-mobile-sidebar',
  'open-command-palette',
  'open-shortcuts',
  'open-quivr-brain',
]);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const mobileMenuOpen = ref(false);

const clearanceStatus = computed(() => evaluationStore.queueProgress.clearanceStatus);
const isCleared = computed(() => evaluationStore.queueProgress.allCleared);
const showAuthenticatedBar = computed(
  () => authStore.isAuthenticated && route?.path !== '/login',
);

const isStudent = computed(() => String(authStore.currentRole || '').toLowerCase() === 'student');

const isEvaluationView = computed(() =>
  String(route?.path || '').startsWith('/evaluate'),
);

const canUseQuivrBrain = computed(() =>
  ['dean', 'chair', 'admin'].includes(String(authStore.currentRole || '').toLowerCase()),
);

const homeDestination = computed(() => {
  if (!authStore.isAuthenticated) {
    return '/';
  }
  const role = String(authStore.currentRole || '').toLowerCase();
  if (role === 'student') return '/queue';
  if (role === 'faculty') return '/faculty';
  if (role === 'chair') return '/chair';
  if (role === 'dean') return '/dean';
  if (role === 'admin') return '/admin';
  return '/';
});

const firstPendingSectionId = computed(() => {
  const pending = evaluationStore.courses.find((c) => c.status === 'PENDING');
  return pending?.course_section_id || evaluationStore.activeCourse?.course_section_id || null;
});

const roleNavLinks = computed(() => ({
  student: [
    { to: '/queue', label: 'My evaluations', testId: 'nav-link-student-queue' },
    ...(firstPendingSectionId.value
      ? [
          {
            to: `/evaluate/${firstPendingSectionId.value}`,
            label: 'Current evaluation',
            testId: 'nav-link-student-survey',
          },
        ]
      : []),
  ],
  faculty: [
    { to: '/faculty', label: 'My results', testId: 'nav-link-faculty-portal', exact: true },
    { to: '/advocacy', label: 'Evaluation standards', testId: 'nav-link-faculty-advocacy' },
  ],
  chair: [
    { to: '/chair', label: 'Department overview', testId: 'nav-link-chair-portal', exact: true },
    { to: '/advocacy', label: 'Evaluation standards', testId: 'nav-link-chair-advocacy' },
  ],
  dean: [
    { to: '/dean', label: 'College overview', testId: 'nav-link-dean-analytics', exact: true },
    { to: '/advocacy', label: 'Evaluation standards', testId: 'nav-link-dean-advocacy' },
  ],
  admin: [
    { to: '/admin', label: 'Registrar console', testId: 'nav-link-admin-console', exact: true },
    { to: '/admin/setup', label: 'Academic setup', testId: 'nav-link-admin-setup' },
    { to: '/advocacy', label: 'Evaluation standards', testId: 'nav-link-admin-advocacy' },
  ],
}));

const authorizedNavLinks = computed(() => {
  const role = String(authStore.currentRole || '').toLowerCase();
  return roleNavLinks.value[role] || [];
});

function isLinkActive(link) {
  const currentPath = String(route?.path || '');
  if (link.exact) {
    return currentPath === link.to;
  }
  return currentPath === link.to || currentPath.startsWith(link.to + '/');
}

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closeMobileMenu() {
  mobileMenuOpen.value = false;
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && mobileMenuOpen.value) {
    closeMobileMenu();
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown);
  }
});

async function handleLogout() {
  closeMobileMenu();
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
    aria-label="School AAAAA13 Institutional Portal Header"
  >
    <div class="sch-topbar-inner">
      <div class="sch-topbar-left">
        <button
          v-if="showAuthenticatedBar"
          type="button"
          class="mobile-menu-trigger"
          :aria-expanded="mobileMenuOpen"
          aria-controls="mobile-nav-menu"
          :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
          data-testid="mobile-topbar-toggle"
          @click="toggleMobileMenu"
        >
          <AppIcon :name="mobileMenuOpen ? 'x' : 'menu'" :size="22" />
        </button>

        <router-link :to="homeDestination" class="sch-brand-link" title="EvaluaTech home">
          <span class="sch-monogram-badge" aria-hidden="true">e</span>
          <span class="sch-brand-stack">
            <span class="sr-only">School AAAAA13 · aaaaa13.edu</span>
            <span class="sch-brand-name">EvaluaTech</span>
            <span class="sch-portal-heading">School AAAAA13<span class="sr-only"> Student Evaluation of Teachers, Institutional Evaluation Portal</span></span>
          </span>
        </router-link>
      </div>

      <nav
        v-if="showAuthenticatedBar && !isEvaluationView"
        class="sch-inline-nav"
        aria-label="Primary"
        data-testid="navbar-quick-nav"
      >
        <router-link
          v-for="link in authorizedNavLinks"
          :key="link.to"
          :to="link.to"
          class="sch-nav-pill"
          :class="{ 'is-active': isLinkActive(link) }"
          :aria-current="isLinkActive(link) ? 'page' : undefined"
          :data-testid="link.testId"
        >
          {{ link.label }}
        </router-link>
      </nav>

      <div class="sch-topbar-right">
        <template v-if="showAuthenticatedBar && !isEvaluationView && !isStudent">
          <button
            type="button"
            class="sch-tool-btn"
            data-testid="navbar-cmdk-btn"
            title="Search or jump (Ctrl+K)"
            aria-label="Search or jump (Ctrl+K)"
            @click="$emit('open-command-palette')"
          >
            <AppIcon name="search" />
          </button>
          <button
            v-if="canUseQuivrBrain"
            type="button"
            class="sch-tool-btn has-label"
            data-testid="navbar-quivr-btn"
            title="Search and analyse written feedback"
            aria-label="Feedback analysis"
            @click="$emit('open-quivr-brain')"
          >
            <AppIcon name="message" />
            <span>Feedback analysis</span>
          </button>
          <button
            type="button"
            class="sch-tool-btn"
            data-testid="navbar-shortcuts-btn"
            title="Keyboard shortcuts and rules (?)"
            aria-label="Keyboard shortcuts and rules"
            @click="$emit('open-shortcuts')"
          >
            <AppIcon name="keyboard" />
          </button>
        </template>

        <span class="sr-only" aria-label="Active Academic Term">Active Term: {{ authStore.activeTerm || '1st Semester AY 2026-2027' }}</span>

        <template v-if="showAuthenticatedBar">
          <div class="sch-account" data-testid="logged-in-user">
            <span class="sr-only">Signed in as: {{ authStore.displayIdentifier }}</span>
            <span class="sch-user-id" :title="authStore.displayIdentifier">
              {{ authStore.user?.institutional_id || authStore.user?.email }}
            </span>
            <span
              class="sch-status-pill"
              :class="{ 'is-done': isCleared && String(authStore.currentRole || '').toLowerCase() === 'student' }"
              data-testid="navbar-clearance-badge"
              role="status"
            >
              <template v-if="isStudent">
                {{ authStore.user?.name || 'Student' }}
              </template>
              <template v-else>
                {{ authStore.user?.role_label || String(authStore.currentRole || '').toUpperCase() }}
              </template>
            </span>
          </div>

          <router-link
            to="/account/password"
            class="sch-logout-btn"
            data-testid="navbar-change-password-link"
            title="Change password"
          >
            <AppIcon name="lock" />
            <span class="sr-only">Change password</span>
          </router-link>
          <button
            type="button"
            class="sch-logout-btn"
            data-testid="navbar-logout-btn"
            @click="handleLogout"
          >
            <AppIcon name="log-out" />
            <span>Sign out</span>
          </button>
        </template>
        <template v-else>
          <router-link
            v-if="route?.path !== '/verify'"
            to="/verify"
            class="sch-nav-pill"
            data-testid="navbar-verify-permit-btn"
          >
            Verify a receipt
          </router-link>
          <router-link
            v-if="route?.path !== '/login'"
            to="/login"
            class="sch-signin-link"
            data-testid="navbar-signin-btn"
          >
            Sign in
          </router-link>
        </template>
      </div>
    </div>

    <nav
      v-if="mobileMenuOpen && showAuthenticatedBar"
      id="mobile-nav-menu"
      class="sch-mobile-dropdown-menu"
      aria-label="Mobile"
      data-testid="mobile-topbar-menu"
    >
      <router-link
        v-for="link in authorizedNavLinks"
        :key="link.to"
        :to="link.to"
        class="mobile-nav-item"
        :class="{ 'is-active': isLinkActive(link) }"
        :aria-current="isLinkActive(link) ? 'page' : undefined"
        :data-testid="`mobile-${link.testId}`"
        @click="closeMobileMenu"
      >
        {{ link.label }}
      </router-link>

      <button type="button" class="mobile-nav-item" @click="$emit('open-command-palette'); closeMobileMenu()">
        <AppIcon name="search" /> Search or jump
      </button>
      <button
        v-if="canUseQuivrBrain"
        type="button"
        class="mobile-nav-item"
        @click="$emit('open-quivr-brain'); closeMobileMenu()"
      >
        <AppIcon name="message" /> Feedback analysis
      </button>

      <div class="mobile-menu-footer">
        <span class="mobile-user-name">{{ authStore.user?.name || authStore.displayIdentifier }}</span>
        <router-link to="/account/password" class="btn-secondary btn-sm" @click="closeMobileMenu">
          <AppIcon name="lock" /> Password
        </router-link>
        <button type="button" class="btn-secondary btn-sm" @click="handleLogout">
          <AppIcon name="log-out" /> Sign out
        </button>
      </div>
    </nav>
  </header>
</template>

<style scoped>
.sch-topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  background: #0f172a;
  color: #f8fafc;
}

.sch-topbar-inner {
  display: flex;
  align-items: center;
  gap: 24px;
  max-width: 1320px;
  margin: 0 auto;
  min-height: 60px;
  padding: 0 32px;
}

.sch-topbar-left { display: flex; align-items: center; gap: 10px; flex: none; }

.mobile-menu-trigger {
  display: none;
  width: 44px;
  height: 44px;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #f8fafc;
  cursor: pointer;
}

.mobile-menu-trigger:hover { background: #1e293b; }

.sch-brand-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }

.sch-monogram-badge {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 7px;
  background: #abc4ff;
  color: #0f172a;
  font-weight: 800;
  font-size: 1.1rem;
}

.sch-brand-stack { display: flex; flex-direction: column; line-height: 1.15; }
.sch-brand-name { font-weight: 800; font-size: 1rem; color: #ffffff; letter-spacing: -0.005em; }
.sch-portal-heading { font-size: 0.75rem; font-weight: 600; color: #cbd5e1; margin: 0; }

.sch-inline-nav {
  display: flex;
  align-items: stretch;
  align-self: stretch;
  gap: 4px;
  flex: 0 1 auto;
  min-width: 0;
  overflow-x: auto;
  scrollbar-width: none;
}

.sch-inline-nav::-webkit-scrollbar { display: none; }

.sch-nav-pill {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  white-space: nowrap;
  padding: 0 12px;
  color: #cbd5e1;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  box-shadow: inset 0 -3px 0 transparent;
  transition: color 150ms ease, box-shadow 150ms ease;
}

.sch-nav-pill:hover { color: #ffffff; box-shadow: inset 0 -3px 0 #334155; }
.sch-nav-pill.is-active,
.sch-nav-pill.router-link-exact-active { color: #ffffff; box-shadow: inset 0 -3px 0 #abc4ff; }

.sch-topbar-right { display: flex; align-items: center; gap: 6px; margin-left: auto; flex: none; }

.sch-tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  min-width: 36px;
  justify-content: center;
  padding: 0 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #cbd5e1;
  font-size: 0.86rem;
  font-weight: 600;
  cursor: pointer;
}

.sch-tool-btn:hover { background: #1e293b; color: #ffffff; }

.sch-account {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  line-height: 1.2;
  padding: 0 10px 0 14px;
  margin-left: 6px;
  border-left: 1px solid #334155;
}

.sch-user-id { font-weight: 700; font-size: 0.86rem; color: #ffffff; font-variant-numeric: tabular-nums; }
.sch-status-pill { font-size: 0.75rem; font-weight: 600; color: #cbd5e1; white-space: nowrap; max-width: 14rem; overflow: hidden; text-overflow: ellipsis; }
.sch-status-pill.is-done { color: #86efac; }

.sch-logout-btn,
.sch-signin-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 12px;
  border: 1px solid #334155;
  border-radius: 8px;
  background: transparent;
  color: #f8fafc;
  font-size: 0.86rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
}

.sch-logout-btn:hover,
.sch-signin-link:hover { background: #1e293b; border-color: #475569; }
.sch-signin-link { background: #abc4ff; border-color: #abc4ff; color: #0f172a; }
.sch-signin-link:hover { background: #c1d3fe; border-color: #c1d3fe; }

.sch-mobile-dropdown-menu {
  display: flex;
  flex-direction: column;
  padding: 8px 14px 14px;
  background: #0f172a;
  border-top: 1px solid #1e293b;
}

.mobile-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #e2e8f0;
  font: inherit;
  font-weight: 600;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}

.mobile-nav-item:hover { background: #1e293b; }
.mobile-nav-item.is-active { color: #ffffff; box-shadow: inset 3px 0 0 #abc4ff; background: #1e293b; }

.mobile-menu-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid #1e293b;
}

.mobile-user-name { font-weight: 700; color: #ffffff; }

@media (max-width: 1440px) {
  .sch-tool-btn.has-label span { display: none; }
  .sch-status-pill { max-width: 13rem; }
  .sch-topbar-inner { gap: 16px; }
}

@media (max-width: 900px) {
  .sch-inline-nav,
  .sch-tool-btn,
  .sch-logout-btn,
  .sch-account { display: none !important; }
  .mobile-menu-trigger { display: inline-flex; }
  .sch-topbar-inner { padding: 0 10px 0 6px; gap: 8px; }
}

@media (max-width: 480px) {
  .sch-topbar-right .sch-nav-pill { display: none; }
}

@media print {
  .sch-topbar { display: none !important; }
}
</style>
