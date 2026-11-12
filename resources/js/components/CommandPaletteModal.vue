<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'open-shortcuts', 'open-quivr-brain', 'toggle-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const searchQuery = ref('');
const activeIndex = ref(0);
const searchInputRef = ref(null);

const baseCommands = computed(() => {
  const role = String(authStore.currentRole || '').toLowerCase();
  const cmds = [];

  if (role === 'student') {
    cmds.push(
      { id: 'nav-queue', group: 'Workspace', label: 'Go to My Courses', hint: '/queue', action: () => router.push('/queue') },
      { id: 'nav-survey', group: 'Workspace', label: 'Evaluate Professor', hint: '/evaluate', action: () => router.push('/evaluate') },
    );
  }
  if (role === 'faculty') {
    cmds.push({ id: 'nav-faculty', group: 'Workspace', label: 'Open My Sections', hint: '/faculty', action: () => router.push('/faculty') });
  }
  if (role === 'chair') {
    cmds.push(
      { id: 'nav-chair', group: 'Workspace', label: 'Open Department Overview', hint: '/chair', action: () => router.push('/chair') },
      { id: 'open-quivr', group: 'Analysis', label: "Professor's Evaluation Analysis", hint: 'Feedback Search', action: () => emit('open-quivr-brain') },
    );
  }
  if (role === 'dean') {
    cmds.push(
      { id: 'nav-dean', group: 'Workspace', label: 'Open College Overview', hint: '/dean', action: () => router.push('/dean') },
      { id: 'open-quivr', group: 'Analysis', label: "Professor's Evaluation Analysis", hint: 'Feedback Search', action: () => emit('open-quivr-brain') },
    );
  }
  if (role === 'admin') {
    cmds.push(
      { id: 'nav-admin', group: 'Workspace', label: 'Open Registrar Console', hint: '/admin', action: () => router.push('/admin') },
      { id: 'nav-admin-setup', group: 'Workspace', label: 'Academic Setup & Cycle Management', hint: '/admin/setup', action: () => router.push('/admin/setup') },
      { id: 'nav-setup-assignments', group: 'Academic Setup', label: 'Faculty Teaching Assignments Matrix', hint: '/admin/setup?tab=assignments', action: () => router.push('/admin/setup?tab=assignments') },
      { id: 'nav-setup-terms', group: 'Academic Setup', label: 'Academic Terms & Window Schedules', hint: '/admin/setup?tab=terms', action: () => router.push('/admin/setup?tab=terms') },
      { id: 'nav-setup-courses', group: 'Academic Setup', label: 'Course Catalog & Section Offerings', hint: '/admin/setup?tab=courses', action: () => router.push('/admin/setup?tab=courses') },
      { id: 'nav-setup-faculty', group: 'Academic Setup', label: 'Faculty Members Directory', hint: '/admin/setup?tab=faculty', action: () => router.push('/admin/setup?tab=faculty') },
      { id: 'nav-setup-students', group: 'Academic Setup', label: 'Student Directory & Clearance Roster', hint: '/admin/setup?tab=students', action: () => router.push('/admin/setup?tab=students') },
      { id: 'nav-setup-users', group: 'Academic Setup', label: 'System Accounts & Staff Roles', hint: '/admin/setup?tab=users', action: () => router.push('/admin/setup?tab=users') },
      { id: 'nav-setup-questionnaire', group: 'Academic Setup', label: 'Evaluation Questionnaire & Rubric', hint: '/admin/setup?tab=questionnaire', action: () => router.push('/admin/setup?tab=questionnaire') },
      { id: 'nav-setup-war-room', group: 'Academic Setup', label: 'Operations War Room & Campaign Telemetry', hint: '/admin/setup?tab=war-room', action: () => router.push('/admin/setup?tab=war-room') },
      { id: 'open-quivr', group: 'Analysis', label: "Professor's Evaluation Analysis", hint: 'Feedback Search', action: () => emit('open-quivr-brain') },
    );
  }

  cmds.push(
    { id: 'nav-home', group: 'Navigation', label: 'Institutional Home / Public Overview', hint: '/', action: () => router.push('/') },
    { id: 'nav-verify-permit', group: 'Tools', label: 'Verify Evaluation Completion Status (Public Portal)', hint: '/verify', action: () => router.push('/verify') },
    { id: 'nav-advocacy', group: 'Help', label: 'Evaluation Advocacy & Anonymity Protocol', hint: '/advocacy', action: () => router.push('/advocacy') },
    {
      id: 'toggle-density',
      group: 'Layout',
      label: `Switch Table Density (${authStore.uiPreferences?.density === 'compact' ? 'Comfortable' : 'Compact'})`,
      hint: 'UI Density',
      action: () => {
        const next = authStore.uiPreferences?.density === 'compact' ? 'comfortable' : 'compact';
        authStore.updateUiPreferences({ density: next });
      },
    },
    { id: 'open-shortcuts', group: 'Help', label: 'Keyboard Shortcuts & Quick Reference', hint: '?', action: () => emit('open-shortcuts') },
  );

  return cmds;
});

const filteredCommands = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return baseCommands.value;
  return baseCommands.value.filter(
    (item) =>
      item.label.toLowerCase().includes(q) ||
      item.group.toLowerCase().includes(q) ||
      item.hint.toLowerCase().includes(q),
  );
});

watch(
  () => props.visible,
  async (open) => {
    if (open) {
      searchQuery.value = '';
      activeIndex.value = 0;
      await nextTick();
      searchInputRef.value?.focus?.();
    }
  },
);

function executeCommand(cmd) {
  if (!cmd) return;
  emit('close');
  Promise.resolve(cmd.action()).catch(() => {});
}

function onKeyDown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    emit('close');
    return;
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (filteredCommands.value.length > 0) {
      activeIndex.value = (activeIndex.value + 1) % filteredCommands.value.length;
    }
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (filteredCommands.value.length > 0) {
      activeIndex.value =
        (activeIndex.value - 1 + filteredCommands.value.length) % filteredCommands.value.length;
    }
  } else if (event.key === 'Enter') {
    event.preventDefault();
    executeCommand(filteredCommands.value[activeIndex.value]);
  }
}
</script>

<template>
  <div
    v-if="visible"
    class="cmdk-scrim"
    role="dialog"
    aria-modal="true"
    aria-label="Workspace Command Palette"
    data-testid="command-palette-modal"
    @click.self="$emit('close')"
    @keydown="onKeyDown"
  >
    <div class="cmdk-panel">
      <div class="cmdk-search-row">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          ref="searchInputRef"
          v-model="searchQuery"
          type="text"
          class="cmdk-input"
          placeholder="Search pages or actions…"
          aria-label="Search workspace commands"
          data-testid="command-palette-input"
        />
        <kbd class="cmdk-esc">Esc</kbd>
      </div>

      <ul class="cmdk-list" role="listbox">
        <li
          v-for="(cmd, idx) in filteredCommands"
          :key="cmd.id"
          role="option"
          :aria-selected="idx === activeIndex"
          class="cmdk-item"
          :class="{ 'is-active': idx === activeIndex }"
          :data-testid="`cmdk-item-${cmd.id}`"
          @mouseenter="activeIndex = idx"
          @click="executeCommand(cmd)"
        >
          <div class="cmdk-item-main">
            <span class="cmdk-group-tag">{{ cmd.group }}</span>
            <span class="cmdk-label">{{ cmd.label }}</span>
          </div>
          <code class="cmdk-hint">{{ cmd.hint }}</code>
        </li>
        <li v-if="filteredCommands.length === 0" class="cmdk-empty">
          No matching commands for "{{ searchQuery }}".
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.cmdk-scrim {
  position: fixed;
  inset: 0;
  z-index: 94;
  background: rgba(16, 40, 30, 0.48);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 10vh 1rem 1rem;
}

.cmdk-panel {
  width: 100%;
  max-width: 580px;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 22px 48px rgba(16, 40, 30, 0.24);
}

.cmdk-search-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e6ede7;
  color: #4b5e53;
}

.cmdk-input {
  flex: 1;
  border: 0;
  outline: none;
  font-size: 0.94rem;
  color: #1f3028;
  background: transparent;
}

.cmdk-esc {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.72rem;
  padding: 0.15rem 0.45rem;
  border-radius: 6px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #4b5e53;
}

.cmdk-list {
  list-style: none;
  margin: 0;
  padding: 0.45rem;
  max-height: 340px;
  overflow-y: auto;
}

.cmdk-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.62rem 0.75rem;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.cmdk-item.is-active {
  background: #e8f3ec;
}

.cmdk-item-main {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}

.cmdk-group-tag {
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.14rem 0.42rem;
  border-radius: 6px;
  background: #0f172a;
  color: #f4cd42;
  flex-shrink: 0;
}

.cmdk-label {
  font-size: 0.86rem;
  font-weight: 600;
  color: #1f3028;
}

.cmdk-hint {
  font-size: 0.74rem;
  color: #4b5e53;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  padding: 0.12rem 0.45rem;
  border-radius: 6px;
}

.cmdk-empty {
  padding: 1.5rem 1rem;
  text-align: center;
  font-size: 0.85rem;
  color: #4b5e53;
}
</style>

