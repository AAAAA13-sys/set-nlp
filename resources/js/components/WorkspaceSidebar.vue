<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { getRoleTierBadge } from '../utils/uxHelpers.js';

const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false,
  },
  mobileOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'toggle-collapse',
  'close-mobile',
  'open-command-palette',
  'open-shortcuts',
  'open-quivr-brain',
]);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const currentRole = computed(() => String(authStore.currentRole || '').toLowerCase());
const tierBadge = computed(() => getRoleTierBadge(currentRole.value));

const canUseQuivrBrain = computed(() =>
  ['dean', 'chair', 'admin'].includes(currentRole.value),
);

const firstPendingSectionId = computed(() => {
  const pending = evaluationStore.courses.find((c) => c.status === 'PENDING');
  return pending?.course_section_id || evaluationStore.activeCourse?.course_section_id || null;
});

const primaryLinks = computed(() => {
  const role = currentRole.value;
  const items = [];

  if (role === 'student') {
    items.push({
      to: '/queue',
      label: 'My Courses',
      icon: 'queue',
      testId: 'sidebar-link-queue',
      matchPrefix: '/queue',
    });
    items.push({
      to: firstPendingSectionId.value ? `/evaluate/${firstPendingSectionId.value}` : '/evaluate',
      label: 'Evaluate Professor',
      icon: 'survey',
      testId: 'sidebar-link-evaluate',
      matchPrefix: '/evaluate',
    });
  } else if (role === 'faculty') {
    items.push({
      to: '/faculty',
      label: 'My Sections',
      icon: 'shield',
      testId: 'sidebar-link-faculty',
      matchPrefix: '/faculty',
    });
  } else if (role === 'chair') {
    items.push({
      to: '/chair',
      label: 'Department Overview',
      icon: 'chart',
      testId: 'sidebar-link-chair',
      matchPrefix: '/chair',
    });
  } else if (role === 'dean') {
    items.push({
      to: '/dean',
      label: 'College Overview',
      icon: 'analytics',
      testId: 'sidebar-link-dean',
      matchPrefix: '/dean',
    });
  } else if (role === 'admin') {
    items.push({
      to: '/admin',
      label: 'Registrar Console',
      icon: 'admin',
      testId: 'sidebar-link-admin',
      matchPrefix: '/admin',
    });
  }

  items.push({
    to: '/advocacy',
    label: 'Exam Permit & Share',
    icon: 'share',
    testId: 'sidebar-link-advocacy',
    matchPrefix: '/advocacy',
  });

  return items;
});

const userInitials = computed(() => {
  const raw = String(
    authStore.user?.name || authStore.user?.full_name || authStore.user?.institutional_id || 'PL',
  )
    .replace(/[^A-Za-z0-9\s]/g, ' ')
    .trim();
  const parts = raw.split(/\s+/).filter(Boolean);
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }
  return (raw.slice(0, 2) || 'PL').toUpperCase();
});

function isLinkActive(item) {
  const currentPath = String(route?.path || '');
  return currentPath === item.to || currentPath.startsWith(item.matchPrefix);
}

async function handleSignOut() {
  emit('close-mobile');
  evaluationStore.resetState();
  await authStore.logout();
  if (router) {
    router.push('/login').catch(() => {});
  }
}
</script>

<template>
  <div class="sidebar-host">
    <div
      v-if="mobileOpen"
      class="sidebar-scrim"
      aria-hidden="true"
      @click="$emit('close-mobile')"
    ></div>

    <aside
      class="workspace-sidebar"
      :class="{
        'is-collapsed': collapsed,
        'is-mobile-open': mobileOpen,
      }"
      aria-label="EvaluaTech Institutional Sidebar Navigation"
      data-testid="workspace-sidebar"
    >
      <div class="sidebar-top-brand">
        <div class="sidebar-brand-identity">
          <div class="sidebar-monogram" aria-hidden="true">e.</div>
          <div v-if="!collapsed" class="sidebar-brand-copy">
            <strong class="sidebar-brand-name">PLP EvaluTech</strong>
            <span class="sidebar-brand-sub">FACULTY EVALUATION</span>
          </div>
        </div>

        <button
          type="button"
          class="sidebar-rail-btn"
          :title="collapsed ? 'Expand sidebar (Ctrl+B)' : 'Collapse sidebar (Ctrl+B)'"
          :aria-label="collapsed ? 'Expand sidebar navigation' : 'Collapse sidebar navigation'"
          data-testid="sidebar-collapse-btn"
          @click="$emit('toggle-collapse')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="9" y1="3" x2="9" y2="21"></line>
          </svg>
        </button>
      </div>

      <div v-if="!collapsed" class="sidebar-tier-strip" data-testid="sidebar-tier-badge">
        <span class="tier-chip" :class="`tone-${tierBadge.tone}`">{{ tierBadge.tierCode }}</span>
        <span class="tier-caption">{{ tierBadge.label }}</span>
      </div>

      <nav class="sidebar-nav-section" aria-label="Workspace Pages">
        <span v-if="!collapsed" class="sidebar-section-heading">WORKSPACE</span>
        <router-link
          v-for="item in primaryLinks"
          :key="item.testId"
          :to="item.to"
          class="sidebar-nav-pill"
          :class="{ 'is-active': isLinkActive(item) }"
          :title="collapsed ? item.label : undefined"
          :data-testid="item.testId"
          @click="$emit('close-mobile')"
        >
          <span class="sidebar-icon" aria-hidden="true">
            <svg v-if="item.icon === 'queue'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="8" y1="6" x2="21" y2="6"></line>
              <line x1="8" y1="12" x2="21" y2="12"></line>
              <line x1="8" y1="18" x2="21" y2="18"></line>
              <line x1="3" y1="6" x2="3.01" y2="6"></line>
              <line x1="3" y1="12" x2="3.01" y2="12"></line>
              <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <svg v-else-if="item.icon === 'survey'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <svg v-else-if="item.icon === 'shield'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <svg v-else-if="item.icon === 'chart'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <svg v-else-if="item.icon === 'analytics'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
            <svg v-else-if="item.icon === 'admin'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="18" cy="5" r="3"></circle>
              <circle cx="6" cy="12" r="3"></circle>
              <circle cx="18" cy="19" r="3"></circle>
              <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
              <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
          </span>
          <span v-if="!collapsed" class="sidebar-label">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="sidebar-nav-section sidebar-tools-section">
        <span v-if="!collapsed" class="sidebar-section-heading">QUICK TOOLS</span>

        <button
          type="button"
          class="sidebar-action-btn"
          :title="collapsed ? 'Command Palette (Ctrl+K)' : undefined"
          data-testid="sidebar-cmdk-btn"
          @click="$emit('open-command-palette'); $emit('close-mobile')"
        >
          <span class="sidebar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </span>
          <span v-if="!collapsed" class="sidebar-label">Command Palette</span>
          <kbd v-if="!collapsed" class="sidebar-kbd">Ctrl K</kbd>
        </button>

        <button
          v-if="canUseQuivrBrain"
          type="button"
          class="sidebar-action-btn"
          :title="collapsed ? 'Professor\'s Evaluation Analysis' : undefined"
          data-testid="sidebar-quivr-btn"
          @click="$emit('open-quivr-brain'); $emit('close-mobile')"
        >
          <span class="sidebar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
          </span>
          <span v-if="!collapsed" class="sidebar-label">Evaluation Analysis</span>
          <span v-if="!collapsed" class="sidebar-ai-tag">AI</span>
        </button>

        <button
          type="button"
          class="sidebar-action-btn"
          :title="collapsed ? 'Keyboard Shortcuts (?)' : undefined"
          data-testid="sidebar-shortcuts-btn"
          @click="$emit('open-shortcuts'); $emit('close-mobile')"
        >
          <span class="sidebar-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect>
              <path d="M6 8h.001M10 8h.001M14 8h.001M18 8h.001M8 12h.001M12 12h.001M16 12h.001M7 16h10"></path>
            </svg>
          </span>
          <span v-if="!collapsed" class="sidebar-label">Shortcuts &amp; Rules</span>
          <kbd v-if="!collapsed" class="sidebar-kbd">?</kbd>
        </button>
      </div>

      <div class="sidebar-footer-account">
        <div class="sidebar-account-row">
          <div class="sidebar-avatar" aria-hidden="true">{{ userInitials }}</div>
          <div v-if="!collapsed" class="sidebar-user-meta">
            <strong class="sidebar-user-name">{{ authStore.user?.name || authStore.user?.full_name || authStore.displayIdentifier }}</strong>
            <span class="sidebar-user-role">{{ authStore.user?.role_label || currentRole.toUpperCase() }}</span>
          </div>
        </div>

        <button
          type="button"
          class="sidebar-signout-btn"
          :title="collapsed ? 'Sign out' : undefined"
          data-testid="sidebar-logout-btn"
          @click="handleSignOut"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span v-if="!collapsed">Sign Out</span>
        </button>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.sidebar-host {
  display: contents;
}

.workspace-sidebar {
  width: 260px;
  min-height: 100dvh;
  position: sticky;
  top: 0;
  height: 100dvh;
  background: #103c2e;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  color: #e6f2eb;
  display: flex;
  flex-direction: column;
  padding: 1rem 0.85rem;
  gap: 0.9rem;
  flex-shrink: 0;
  z-index: 48;
  transition: width 0.18s ease;
}

.workspace-sidebar.is-collapsed {
  width: 72px;
  padding: 1rem 0.55rem;
}

.sidebar-top-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding-bottom: 0.8rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.workspace-sidebar.is-collapsed .sidebar-top-brand {
  flex-direction: column;
  gap: 0.55rem;
}

.sidebar-brand-identity {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
}

.sidebar-monogram {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: #f4cd42;
  color: #103c2e;
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.18rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-brand-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.sidebar-brand-name {
  font-size: 0.92rem;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.01em;
  white-space: nowrap;
}

.sidebar-brand-sub {
  font-size: 0.63rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  color: #a9cbb9;
  white-space: nowrap;
}

.sidebar-rail-btn {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.06);
  color: #d6ebe0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background-color 0.14s ease, color 0.14s ease;
}

.sidebar-rail-btn:hover {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

.sidebar-tier-strip {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.45rem 0.6rem;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.tier-chip {
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 0.14rem 0.42rem;
  border-radius: 6px;
  background: #f4cd42;
  color: #103c2e;
  flex-shrink: 0;
}

.tier-chip.tone-emerald {
  background: #22c55e;
  color: #07291c;
}

.tier-chip.tone-sage {
  background: #d7ece0;
  color: #103c2e;
}

.tier-caption {
  font-size: 0.72rem;
  font-weight: 600;
  color: #cde2d6;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-nav-section {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.sidebar-tools-section {
  margin-top: 0.25rem;
}

.sidebar-section-heading {
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #93bba7;
  padding: 0 0.5rem 0.2rem;
}

.sidebar-nav-pill,
.sidebar-action-btn {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  padding: 0.58rem 0.72rem;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  color: #dbece2;
  text-decoration: none;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: background-color 0.14s ease, color 0.14s ease;
}

.workspace-sidebar.is-collapsed .sidebar-nav-pill,
.workspace-sidebar.is-collapsed .sidebar-action-btn {
  justify-content: center;
  padding: 0.6rem 0;
}

.sidebar-nav-pill:hover,
.sidebar-action-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}

.sidebar-nav-pill.is-active {
  background: #f4cd42;
  color: #103c2e;
  font-weight: 800;
}

.sidebar-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-kbd,
.sidebar-ai-tag {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.66rem;
  font-weight: 700;
  padding: 0.1rem 0.36rem;
  border-radius: 5px;
  background: rgba(255, 255, 255, 0.1);
  color: #dbece2;
}

.sidebar-ai-tag {
  background: rgba(244, 205, 66, 0.2);
  color: #f4cd42;
}

.sidebar-footer-account {
  margin-top: auto;
  padding-top: 0.8rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.sidebar-account-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
}

.workspace-sidebar.is-collapsed .sidebar-account-row {
  justify-content: center;
}

.sidebar-avatar {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: rgba(244, 205, 66, 0.18);
  border: 1px solid rgba(244, 205, 66, 0.45);
  color: #f4cd42;
  font-size: 0.76rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-user-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.sidebar-user-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: #ffffff;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-user-role {
  font-size: 0.68rem;
  color: #a9cbb9;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-signout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  width: 100%;
  padding: 0.48rem 0.65rem;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.05);
  color: #e6f2eb;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.14s ease;
}

.sidebar-signout-btn:hover {
  background: rgba(220, 38, 38, 0.25);
  border-color: rgba(252, 165, 165, 0.45);
  color: #ffffff;
}

.sidebar-scrim {
  display: none;
}

@media (max-width: 900px) {
  .sidebar-scrim {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(16, 40, 30, 0.5);
    z-index: 59;
  }

  .workspace-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 268px;
    z-index: 60;
    transform: translateX(-104%);
    transition: transform 0.2s ease;
  }

  .workspace-sidebar.is-collapsed {
    width: 268px;
  }

  .workspace-sidebar.is-mobile-open {
    transform: translateX(0);
  }
}

@media print {
  .workspace-sidebar,
  .sidebar-scrim {
    display: none !important;
  }
}
</style>
