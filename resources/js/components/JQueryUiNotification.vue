<template>
  <Teleport to="body">
    <aside
      v-if="jqueryUiNotificationState.visible"
      class="ui-widget ui-notification-container"
      role="alert"
      aria-live="assertive"
      data-testid="jquery-ui-error-notification"
    >
      <div class="ui-state-error ui-corner-all jquery-ui-error-box">
        <div class="jquery-ui-content-row">
          <span class="ui-icon ui-icon-alert" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </span>
          <div class="jquery-ui-text-stack">
            <strong class="jquery-ui-title">{{ jqueryUiNotificationState.title }}</strong>
            <span class="jquery-ui-message">{{ jqueryUiNotificationState.message }}</span>
          </div>
          <button
            type="button"
            class="jquery-ui-close-btn"
            aria-label="Close error notification"
            @click="dismissJQueryUiError"
          >
            &times;
          </button>
        </div>
        <div class="jquery-ui-progress-track" aria-hidden="true">
          <div
            class="jquery-ui-progress-bar"
            :style="{ animationDuration: `${jqueryUiNotificationState.timerDuration}ms` }"
          ></div>
        </div>
      </div>
    </aside>
  </Teleport>
</template>

<script setup>
import { jqueryUiNotificationState, dismissJQueryUiError } from '../utils/jqueryUiNotification.js';
</script>
