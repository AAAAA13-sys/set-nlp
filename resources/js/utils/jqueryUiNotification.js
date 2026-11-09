import { reactive } from 'vue';

export const jqueryUiNotificationState = reactive({
  visible: false,
  title: 'Validation Error',
  message: '',
  timerDuration: 4000,
});

let timerHandle = null;

export async function getJQuery() {
  if (typeof window !== 'undefined' && typeof window.document !== 'undefined') {
    if (window.$) {
      return window.$;
    }
    try {
      const mod = await import('jquery');
      const jq = mod.default || mod;
      window.$ = jq;
      window.jQuery = jq;
      return jq;
    } catch {
      return null;
    }
  }
  return null;
}

export function showJQueryUiError(message, title = 'Validation Error', duration = 4000) {
  if (timerHandle) {
    clearTimeout(timerHandle);
    timerHandle = null;
  }

  jqueryUiNotificationState.title = title || 'Validation Error';
  jqueryUiNotificationState.message = message || 'An error occurred during evaluation.';
  jqueryUiNotificationState.timerDuration = duration || 4000;
  jqueryUiNotificationState.visible = true;

  if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    getJQuery().then((jq) => {
      if (jq) {
        const $el = jq('.jquery-ui-error-box');
        if ($el.length) {
          $el.stop(true, true).css({ opacity: 0 }).show().animate({ opacity: 1 }, 180);
        }
      }
    });

    timerHandle = setTimeout(() => {
      dismissJQueryUiError();
    }, duration);
  }
}

export function dismissJQueryUiError() {
  if (timerHandle) {
    clearTimeout(timerHandle);
    timerHandle = null;
  }
  if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    getJQuery().then((jq) => {
      if (jq) {
        const $el = jq('.jquery-ui-error-box');
        if ($el.length) {
          $el.stop(true, true).slideUp(200, () => {
            jqueryUiNotificationState.visible = false;
          });
          return;
        }
      }
      jqueryUiNotificationState.visible = false;
    });
    return;
  }
  jqueryUiNotificationState.visible = false;
}

if (typeof window !== 'undefined') {
  window.showJQueryUiError = showJQueryUiError;
  window.dismissJQueryUiError = dismissJQueryUiError;
  getJQuery();
}
