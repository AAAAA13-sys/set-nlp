<script setup>
defineProps({
  rows: {
    type: Number,
    default: 3,
  },
  label: {
    type: String,
    default: 'Loading institutional workspace data…',
  },
  compact: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div
    class="skeleton-loader-wrap"
    :class="{ 'is-compact': compact }"
    role="status"
    aria-live="polite"
    :aria-label="label"
    data-testid="skeleton-loader"
  >
    <span class="sr-only">{{ label }}</span>
    <div v-for="idx in rows" :key="idx" class="skeleton-row-card" aria-hidden="true">
      <div class="skeleton-line skeleton-line-title"></div>
      <div class="skeleton-line skeleton-line-body"></div>
      <div class="skeleton-line skeleton-line-meta"></div>
    </div>
  </div>
</template>

<style scoped>
.skeleton-loader-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  width: 100%;
}

.skeleton-loader-wrap.is-compact {
  gap: 0.45rem;
}

.skeleton-row-card {
  display: flex;
  flex-direction: column;
  gap: 0.42rem;
  padding: 0.85rem 1rem;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 10px;
}

.skeleton-loader-wrap.is-compact .skeleton-row-card {
  padding: 0.6rem 0.8rem;
}

.skeleton-line {
  height: 11px;
  border-radius: 6px;
  background: linear-gradient(90deg, #e7efe9 25%, #f3f7f4 50%, #e7efe9 75%);
  background-size: 200% 100%;
  animation: skeletonShimmer 1.5s ease-in-out infinite;
}

.skeleton-line-title {
  width: 42%;
  height: 13px;
}

.skeleton-line-body {
  width: 86%;
}

.skeleton-line-meta {
  width: 58%;
  height: 9px;
}

@keyframes skeletonShimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-line {
    animation: none;
    background: #e7efe9;
  }
}
</style>

