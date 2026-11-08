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
    const el = document.querySelector(mountSelector);
    if (el) {
      app.mount(el);
    }
  }

  return { app, pinia, router };
}

if (typeof document !== 'undefined' && document.querySelector('#app')) {
  bootstrapEvaluaTechApp('#app');
}
