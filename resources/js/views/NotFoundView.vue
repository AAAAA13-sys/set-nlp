<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const attemptedPath = computed(() => String(route?.fullPath || '/unknown'));
const homePath = computed(() =>
  authStore.isAuthenticated ? getDefaultRouteForRole(authStore.currentRole) : '/login',
);
const homeLabel = computed(() =>
  authStore.isAuthenticated ? 'Return to Institutional Workspace' : 'Go to Institutional Sign-In',
);

function goHome() {
  if (router) {
    router.push(homePath.value).catch(() => {});
  }
}
</script>

<template>
  <main class="workspace-main notfound-workspace" aria-labelledby="notfound-heading" data-testid="custom-404-view">
    <section class="card notfound-card">
      <div class="notfound-code-badge">HTTP 404 · PAGE NOT FOUND</div>
      <h2 id="notfound-heading" class="section-title notfound-title">
        This university portal address does not exist.
      </h2>
      <p class="section-subtitle notfound-subtitle">
        The requested route <code class="notfound-path">{{ attemptedPath }}</code> is not registered in the
        Pamantasan ng Lungsod ng Pasig EvaluaTech portal.
      </p>

      <div class="notfound-guidance">
        <div class="guidance-item">
          <strong>Verify your active role workspace</strong>
          <span>Use your role navigation rail or press <kbd>Ctrl + K</kbd> to jump directly to an authorized page.</span>
        </div>
        <div class="guidance-item">
          <strong>Zero-PII session intact</strong>
          <span>Your current institutional session and any autosaved SET drafts remain protected.</span>
        </div>
      </div>

      <div class="notfound-actions">
        <button
          type="button"
          class="btn-primary"
          data-testid="notfound-return-btn"
          @click="goHome"
        >
          {{ homeLabel }}
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.notfound-workspace {
  max-width: 640px;
  margin: 2.5rem auto;
  padding: 0 1rem;
}

.notfound-card {
  padding: 2rem;
  border: 1px solid #dbe3da;
  border-radius: 14px;
  background: #ffffff;
}

.notfound-code-badge {
  display: inline-block;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding: 0.22rem 0.62rem;
  border-radius: 6px;
  background: #103c2e;
  color: #f4cd42;
}

.notfound-title {
  margin: 0.75rem 0 0.45rem;
  font-size: 1.45rem;
  color: #1f3028;
}

.notfound-subtitle {
  margin: 0 0 1.25rem;
  font-size: 0.9rem;
  line-height: 1.55;
  color: #4b5e53;
}

.notfound-path {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.82rem;
  padding: 0.12rem 0.45rem;
  border-radius: 6px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  color: #103c2e;
}

.notfound-guidance {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.4rem;
}

.guidance-item {
  display: flex;
  flex-direction: column;
  gap: 0.22rem;
  padding: 0.85rem 0.95rem;
  border-radius: 10px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  font-size: 0.8rem;
  color: #3f5247;
}

.guidance-item strong {
  color: #1f3028;
}

.notfound-actions {
  display: flex;
  gap: 0.65rem;
}

@media (max-width: 640px) {
  .notfound-guidance {
    grid-template-columns: 1fr;
  }
}
</style>

