<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const attemptedPath = computed(() => route.query?.attempted || (route.path !== '/access-denied' ? route.path : '/'));
const requiredRoles = computed(() => route.query?.required_roles || 'Authorized Personnel');
const currentRole = computed(() => authStore.currentRole || 'guest');
const authorizedRoute = computed(() => getDefaultRouteForRole(authStore.currentRole));

function returnToAuthorizedWorkspace() {
  if (router) {
    router.push(authorizedRoute.value).catch(() => {});
  }
}
</script>

<template>
  <main class="workspace-main access-denied-workspace" aria-labelledby="access-denied-heading">
    <section class="card access-denied-card" data-testid="access-denied-card">
      <div class="access-denied-header">
        <div class="denied-icon-wrap" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <div>
          <span class="eyebrow-badge" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5;">
            PAMANTASAN NG LUNGSOD NG PASIG • Role-Based Access Control (RBAC) Guard Active
          </span>
          <h2 id="access-denied-heading" class="section-title" style="color:#991b1b;">
            403 Forbidden — Institutional Role Access Restricted
          </h2>
          <p class="section-subtitle">
            Every institutional role operates exclusively in its dedicated workspace to maintain cryptographic student anonymity,
            double-blind grading integrity, and departmental security boundaries.
          </p>
        </div>
      </div>

      <div class="denied-details-grid" style="margin: 1.25rem 0; padding: 1rem; background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px;">
        <div style="margin-bottom: 0.5rem;">
          <span style="font-weight: 600; color: #7f1d1d;">Attempted URL:</span>
          <code style="margin-left: 0.5rem; background: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid #fca5a5;" data-testid="denied-attempted-route">
            {{ attemptedPath }}
          </code>
        </div>
        <div style="margin-bottom: 0.5rem;">
          <span style="font-weight: 600; color: #7f1d1d;">Your Logged-in Role:</span>
          <span class="token-chip chip-warn" style="margin-left: 0.5rem;" data-testid="denied-current-role">
            {{ authStore.displayIdentifier || currentRole }}
          </span>
        </div>
        <div style="margin-bottom: 0.5rem;">
          <span style="font-weight: 600; color: #7f1d1d;">Authorized Role(s) for this Workspace:</span>
          <code style="margin-left: 0.5rem; color: #991b1b; font-weight: bold;" data-testid="denied-required-roles">
            {{ requiredRoles }}
          </code>
        </div>
      </div>

      <div class="denied-actions" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-primary"
          data-testid="return-authorized-workspace-btn"
          @click="returnToAuthorizedWorkspace"
        >
          [Go to My Authorized Workspace: {{ authorizedRoute }}]
        </button>

        <router-link to="/login" class="btn-secondary btn-sm" data-testid="switch-account-btn">
          Sign in with Another Account
        </router-link>
      </div>
    </section>
  </main>
</template>

<style scoped>
.access-denied-workspace {
  max-width: 800px;
  margin: 2rem auto;
  padding: 0 1rem;
}
.access-denied-header {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
}
.denied-icon-wrap {
  flex-shrink: 0;
  padding: 0.75rem;
  background: #fef2f2;
  border-radius: 12px;
  border: 1px solid #fecaca;
}
</style>
