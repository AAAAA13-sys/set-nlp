import { createPinia } from 'pinia';
import { createApp, defineComponent, h } from 'vue';
import { RouterView } from 'vue-router';
import '../css/app.css';
import NavbarHeader from './components/NavbarHeader.vue';
import router from './router/index.js';

export const EvaluaTechAppShell = defineComponent({
  name: 'EvaluaTechAppShell',
  setup() {
    return () =>
      h('div', { class: 'evaluatech-app-shell' }, [
        h(NavbarHeader),
        h('div', { class: 'evaluatech-view-container' }, [h(RouterView)]),
      ]);
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
