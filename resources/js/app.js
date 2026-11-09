import { createPinia } from 'pinia';
import { computed, createApp, defineComponent, h, onMounted, onUnmounted, ref } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import '../css/app.css';
import CommandPaletteModal from './components/CommandPaletteModal.vue';
import JQueryUiNotification from './components/JQueryUiNotification.vue';
import NavbarHeader from './components/NavbarHeader.vue';
import QuivrNlpBrainDrawer from './components/QuivrNlpBrainDrawer.vue';
import ShortcutsCheatsheetModal from './components/ShortcutsCheatsheetModal.vue';
import WorkspaceOnboardingBanner from './components/WorkspaceOnboardingBanner.vue';
import WorkspaceSidebar from './components/WorkspaceSidebar.vue';
import router from './router/index.js';
import { useAuthStore } from './stores/auth.js';

export const EvaluaTechAppShell = defineComponent({
  name: 'EvaluaTechAppShell',
  setup() {
    const route = useRoute();
    const authStore = useAuthStore();

    const mobileSidebarOpen = ref(false);
    const showCommandPalette = ref(false);
    const showShortcutsModal = ref(false);
    const showQuivrBrain = ref(false);

    const isSplitLoginRoute = computed(() => route?.path === '/login');
    const isAuthenticatedWorkspace = computed(
      () => authStore.isAuthenticated && !isSplitLoginRoute.value,
    );
    const sidebarCollapsed = computed(
      () => Boolean(authStore.uiPreferences?.sidebarCollapsed),
    );

    function toggleSidebarCollapse() {
      authStore.updateUiPreferences({
        sidebarCollapsed: !sidebarCollapsed.value,
      });
    }

    function handleGlobalKeyDown(event) {
      if (!authStore.isAuthenticated || isSplitLoginRoute.value) {
        return;
      }

      const key = String(event.key || '').toLowerCase();
      if ((event.ctrlKey || event.metaKey) && key === 'k') {
        event.preventDefault();
        showCommandPalette.value = !showCommandPalette.value;
        return;
      }

      if ((event.ctrlKey || event.metaKey) && key === 'b') {
        event.preventDefault();
        toggleSidebarCollapse();
        return;
      }

      const tag = String(event.target?.tagName || '').toLowerCase();
      const inEditable =
        tag === 'input' ||
        tag === 'textarea' ||
        tag === 'select' ||
        Boolean(event.target?.isContentEditable);

      if (!inEditable && event.key === '?' && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        showShortcutsModal.value = !showShortcutsModal.value;
      }
    }

    onMounted(() => {
      if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeyDown);
      }
    });

    onUnmounted(() => {
      if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeyDown);
      }
    });

    return () => {
      if (isSplitLoginRoute.value) {
        return h('div', { id: 'main-workspace', class: 'evaluatech-login-shell' }, [
          h(RouterView),
          h(JQueryUiNotification),
        ]);
      }

      if (isAuthenticatedWorkspace.value) {
        const isStudent = authStore.currentRole === 'student';
        return h('div', { class: `evaluatech-app-shell ${isStudent ? 'app-shell-student' : 'app-shell-authenticated'}` }, [
          h('div', { class: 'app-body' }, [
            h(NavbarHeader, {
              onOpenCommandPalette: () => {
                showCommandPalette.value = true;
              },
              onOpenShortcuts: () => {
                showShortcutsModal.value = true;
              },
              onOpenQuivrBrain: () => {
                showQuivrBrain.value = true;
              },
            }),
            h('div', { id: 'main-workspace', class: 'evaluatech-view-container' }, [
              !isStudent ? h(WorkspaceOnboardingBanner) : null,
              h(RouterView),
            ]),
          ]),
          h(CommandPaletteModal, {
            visible: showCommandPalette.value,
            onClose: () => {
              showCommandPalette.value = false;
            },
            onOpenShortcuts: () => {
              showShortcutsModal.value = true;
            },
            onOpenQuivrBrain: () => {
              showQuivrBrain.value = true;
            },
            onToggleSidebar: () => {},
          }),
          h(ShortcutsCheatsheetModal, {
            visible: showShortcutsModal.value,
            onClose: () => {
              showShortcutsModal.value = false;
            },
          }),
          h(QuivrNlpBrainDrawer, {
            visible: showQuivrBrain.value,
            onClose: () => {
              showQuivrBrain.value = false;
            },
          }),
          h(JQueryUiNotification),
        ]);
      }

      const canRenderUnauthenticatedView =
        !Array.isArray(route?.meta?.roles) || route.meta.roles.length === 0;

      return h('div', { class: 'evaluatech-app-shell' }, [
        h(NavbarHeader),
        h('div', { id: 'main-workspace', class: 'evaluatech-view-container' }, [
          canRenderUnauthenticatedView ? h(RouterView) : null,
        ]),
        h(JQueryUiNotification),
      ]);
    };
  },
});

export function bootstrapEvaluaTechApp(mountSelector = '#app') {
  const app = createApp(EvaluaTechAppShell);
  const pinia = createPinia();

  app.use(pinia);
  app.use(router);

  if (typeof document !== 'undefined') {
    const el = typeof mountSelector === 'string' ? document.querySelector(mountSelector) : mountSelector;
    if (el) {
      router.isReady().then(() => {
        app.mount(el);
      }).catch(() => {
        app.mount(el);
      });
    }
  }

  return { app, pinia, router };
}

function initApp() {
  const el = document.querySelector('#app');
  if (el) {
    bootstrapEvaluaTechApp(el);
  }
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
  } else {
    initApp();
  }
}
