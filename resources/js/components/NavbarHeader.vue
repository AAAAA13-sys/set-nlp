<script setup>
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
    { to: '/faculty', label: 'Faculty Portal', testId: 'nav-link-faculty-portal', exact: true },
    { to: '/advocacy', label: 'Campus Advocacy & Standards', testId: 'nav-link-faculty-advocacy' },
  ],
  chair: [
    { to: '/chair', label: 'Department Chair Portal', testId: 'nav-link-chair-portal', exact: true },
    { to: '/advocacy', label: 'Campus Advocacy & Standards', testId: 'nav-link-chair-advocacy' },
  ],
  dean: [
    { to: '/dean', label: 'Dean Analytics', testId: 'nav-link-dean-analytics', exact: true },
    { to: '/advocacy', label: 'Campus Advocacy & Standards', testId: 'nav-link-dean-advocacy' },
  ],
  admin: [
    { to: '/admin', label: 'Registrar Console', testId: 'nav-link-admin-console', exact: true },
    { to: '/admin/setup', label: 'Academic Setup', testId: 'nav-link-admin-setup' },
    { to: '/advocacy', label: 'Campus Advocacy & Standards', testId: 'nav-link-admin-advocacy' },
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
    aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header"
  >
    <div class="sch-topbar-inner">
      <!-- Left: Brand / Home Link -->
      <div class="sch-topbar-left">
        <button
          v-if="showAuthenticatedBar"
          type="button"
          class="mobile-menu-trigger"
          :aria-expanded="mobileMenuOpen"
          aria-controls="mobile-nav-menu"
          aria-label="Toggle navigation menu"
          data-testid="mobile-topbar-toggle"
          @click="toggleMobileMenu"
        >
          <svg v-if="!mobileMenuOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <router-link :to="homeDestination" class="sch-brand-link" title="EvaluaTech Home">
          <div class="sch-monogram-badge" aria-hidden="true">e.</div>
          <div class="sch-brand-stack">
            <span class="sr-only">PAMANTASAN NG LUNGSOD NG PASIG · plpasig.edu.ph</span>
            <div class="sch-brand-name">PLP EvaluTech</div>
            <h1 class="sch-portal-heading">Student Evaluation of Teachers<span class="sr-only"> Institutional Evaluation Portal</span></h1>
          </div>
        </router-link>
      </div>

      <!-- Center: Desktop Top-Bar Role Navigation Links -->
      <nav
        v-if="showAuthenticatedBar && !isEvaluationView"
        class="sch-inline-nav"
        aria-label="Primary Workspace Navigation"
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

      <!-- Right: Quick Tools, Term Chip, Role, Account & Sign Out -->
      <div class="sch-topbar-right">
        <!-- Quick Tools (Command Palette, AI Brain, Shortcuts) -->
        <template v-if="showAuthenticatedBar && !isEvaluationView">
          <button
            type="button"
            class="sch-cmdk-trigger"
            data-testid="navbar-cmdk-btn"
            title="Search or jump… (Ctrl+K)"
            @click="$emit('open-command-palette')"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span>Search or jump…</span>
            <kbd>Ctrl K</kbd>
          </button>

          <button
            v-if="canUseQuivrBrain"
            type="button"
            class="sch-tool-btn"
            data-testid="navbar-quivr-btn"
            title="Professor's Evaluation Analysis (AI)"
            @click="$emit('open-quivr-brain')"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Evaluation Analysis</span>
            <span class="sch-ai-chip">AI</span>
          </button>

          <button
            type="button"
            class="sch-tool-btn sch-tool-btn-icon"
            data-testid="navbar-shortcuts-btn"
            title="Keyboard Shortcuts &amp; Rules (?)"
            @click="$emit('open-shortcuts')"
          >
            <kbd>?</kbd>
          </button>
        </template>

        <div class="sch-term-chip" aria-label="Active Academic Term">
          <span class="sr-only">Active Term:</span>
          <span>Term:</span>
          <strong>{{ authStore.activeTerm || '1st Semester AY 2026-2027' }}</strong>
        </div>

        <template v-if="showAuthenticatedBar">
          <div
            class="sch-status-pill is-cleared"
            data-testid="navbar-clearance-badge"
            role="status"
          >
            <span>Role:</span>
            <strong>
              {{ authStore.user?.role_label || String(authStore.currentRole || '').toUpperCase() }}
            </strong>
          </div>

          <div class="sch-signed-in" data-testid="logged-in-user">
            <span class="sr-only">Signed in as: {{ authStore.displayIdentifier }}</span>
            <strong class="sch-user-id" :title="authStore.displayIdentifier">
              {{ authStore.user?.institutional_id || authStore.user?.email }}
            </strong>
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
        <template v-else>
          <router-link
            v-if="route?.path !== '/verify'"
            to="/verify"
            class="sch-nav-pill"
            style="font-size: 0.82rem; font-weight: 700; color: #103c2e; padding: 0.35rem 0.75rem; border-radius: 8px; border: 1px solid #103c2e; text-decoration: none; background: #ffffff;"
            data-testid="navbar-verify-permit-btn"
          >
            Verify Status 🔍
          </router-link>
          <router-link
            v-if="route?.path !== '/login'"
            to="/login"
            class="sch-nav-pill"
            style="font-size: 0.82rem; font-weight: 700; background: #f4cd42; color: #103c2e; padding: 0.35rem 0.75rem; border-radius: 8px; text-decoration: none; border: 1px solid #f4cd42;"
            data-testid="navbar-signin-btn"
          >
            Sign In →
          </router-link>
        </template>
      </div>
    </div>

    <!-- Mobile Dropdown Menu (Expands directly beneath top bar, NOT a sidebar) -->
    <nav
      v-if="mobileMenuOpen && showAuthenticatedBar"
      id="mobile-nav-menu"
      class="sch-mobile-dropdown-menu"
      aria-label="Mobile Workspace Navigation"
      data-testid="mobile-topbar-menu"
    >
      <div class="mobile-menu-section">
        <span class="mobile-section-heading">Navigation</span>
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
          <span>{{ link.label }}</span>
          <span v-if="isLinkActive(link)" class="mobile-active-tag">Active</span>
        </router-link>
      </div>

      <div class="mobile-menu-section">
        <span class="mobile-section-heading">Quick Actions</span>
        <button
          type="button"
          class="mobile-action-item"
          @click="$emit('open-command-palette'); closeMobileMenu()"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <span>Command Palette (Ctrl+K)</span>
        </button>

        <button
          v-if="canUseQuivrBrain"
          type="button"
          class="mobile-action-item"
          @click="$emit('open-quivr-brain'); closeMobileMenu()"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          <span>Evaluation Analysis (AI)</span>
        </button>

        <button
          type="button"
          class="mobile-action-item"
          @click="$emit('open-shortcuts'); closeMobileMenu()"
        >
          <kbd class="mobile-kbd">?</kbd>
          <span>Keyboard Shortcuts</span>
        </button>
      </div>

      <div class="mobile-menu-footer">
        <div class="mobile-user-row">
          <strong class="mobile-user-name">{{ authStore.user?.name || authStore.displayIdentifier }}</strong>
          <span class="mobile-user-role">{{ authStore.user?.role_label || String(authStore.currentRole || '').toUpperCase() }}</span>
        </div>
        <button
          type="button"
          class="mobile-logout-btn"
          @click="handleLogout"
        >
          Sign Out
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
  background: #ffffff;
  border-bottom: 1px solid #dbe3da;
  color: #1f3028;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.sch-topbar-inner {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 60px;
  padding: 0.5rem 1.35rem;
  max-width: 1440px;
  margin: 0 auto;
}

.sch-topbar-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  flex-shrink: 0;
}

.sch-brand-link {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
  color: inherit;
}

.sch-monogram-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f4cd42;
  color: #103c2e;
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.15rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.sch-brand-stack {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
  min-width: 0;
  white-space: nowrap;
}

.sch-brand-name {
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #0b3322;
}

.sch-portal-heading {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: #1f3028;
}

/* Center Desktop Top-Bar Navigation */
.sch-inline-nav {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
}

.sch-nav-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.8rem;
  border-radius: 9999px;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  color: #4b5e53;
  background: transparent;
  transition: background-color 0.14s ease, color 0.14s ease;
}

.sch-nav-pill:hover {
  background: #edf3ee;
  color: #103c2e;
}

.sch-nav-pill.is-active,
.sch-nav-pill.router-link-exact-active {
  background: #103c2e;
  color: #f4cd42;
}

/* Right Section */
.sch-topbar-right {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 0.45rem;
  flex-shrink: 0;
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

.sch-tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
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

.sch-tool-btn:hover {
  background: #edf3ee;
  border-color: #176347;
  color: #103c2e;
}

.sch-tool-btn-icon {
  padding: 0 0.5rem;
}

.sch-tool-btn-icon kbd {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.72rem;
  font-weight: 700;
  color: #103c2e;
}

.sch-ai-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.08rem 0.35rem;
  border-radius: 4px;
  background: #f4cd42;
  color: #103c2e;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.04em;
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

.mobile-menu-trigger {
  display: none;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #103c2e;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

/* Mobile Dropdown Menu */
.sch-mobile-dropdown-menu {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.1rem 1.25rem 1.5rem;
  background: #ffffff;
  border-top: 1px solid #e5e7eb;
  border-bottom: 2px solid #103c2e;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
}

.mobile-menu-section {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.mobile-section-heading {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  margin-bottom: 0.2rem;
}

.mobile-nav-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  font-size: 0.92rem;
  font-weight: 600;
  color: #374151;
  text-decoration: none;
  background: #f9f9f8;
  transition: background-color 0.14s ease, color 0.14s ease;
}

.mobile-nav-item.is-active {
  background: #103c2e;
  color: #f4cd42;
}

.mobile-active-tag {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  background: #f4cd42;
  color: #103c2e;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.mobile-action-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  color: #374151;
  font-size: 0.88rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.mobile-kbd {
  font-family: monospace;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  background: #f3f4f6;
  border-radius: 4px;
  border: 1px solid #d1d5db;
}

.mobile-menu-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 0.85rem;
  border-top: 1px solid #f3f4f6;
}

.mobile-user-row {
  display: flex;
  flex-direction: column;
}

.mobile-user-name {
  font-size: 0.85rem;
  color: #111827;
}

.mobile-user-role {
  font-size: 0.75rem;
  color: #6b7280;
}

.mobile-logout-btn {
  padding: 0.45rem 0.95rem;
  border-radius: 6px;
  border: 1px solid #fca5a5;
  background: #fef2f2;
  color: #991b1b;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 1200px) {
  .sch-term-chip,
  .sch-signed-in {
    display: none;
  }
}

@media (max-width: 960px) {
  .sch-inline-nav,
  .sch-cmdk-trigger,
  .sch-tool-btn,
  .sch-status-pill,
  .sch-logout-btn {
    display: none !important;
  }

  .mobile-menu-trigger {
    display: inline-flex;
  }

  .sch-topbar-inner {
    padding: 0.55rem 0.9rem;
  }
}

@media (max-width: 640px) {
  .sch-brand-sub {
    display: none;
  }
  .sch-topbar-right .sch-nav-pill:not(.sch-nav-pill-active) {
    display: none;
  }
}

@media print {
  .sch-topbar {
    display: none !important;
  }
}
</style>
