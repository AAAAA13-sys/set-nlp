<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

function handleKeyDown(event) {
  if (props.visible && event.key === 'Escape') {
    emit('close');
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown);
  }
});
</script>

<template>
  <div
    v-if="visible"
    class="cheatsheet-scrim"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cheatsheet-heading"
    data-testid="shortcuts-cheatsheet-modal"
    @click.self="$emit('close')"
  >
    <div class="cheatsheet-dialog">
      <div class="cheatsheet-header">
        <div>
          <span class="cheatsheet-kicker">QUICK REFERENCE</span>
          <h3 id="cheatsheet-heading" class="cheatsheet-title">
            Keyboard Shortcuts &amp; Evaluation Rules
          </h3>
        </div>
        <button
          type="button"
          class="btn-secondary btn-xs"
          data-testid="close-cheatsheet-btn"
          @click="$emit('close')"
        >
          Close (Esc)
        </button>
      </div>

      <div class="cheatsheet-grid">
        <section class="cheatsheet-col">
          <h4>Global Navigation</h4>
          <ul class="shortcut-list">
            <li><span>Open Command Palette</span><kbd>Ctrl + K</kbd></li>
            <li><span>Collapse / Expand Sidebar</span><kbd>Ctrl + B</kbd></li>
            <li><span>Focus Table Filter Input</span><kbd>/</kbd></li>
            <li><span>Open Shortcuts Reference</span><kbd>?</kbd></li>
            <li><span>Close Active Modal / Drawer</span><kbd>Esc</kbd></li>
          </ul>
        </section>

        <section class="cheatsheet-col">
          <h4>Integrity &amp; Decoupling Rules</h4>
          <ul class="shortcut-list">
            <li><span>Survey Submission Timing</span><code>Ready (&ge; 0s)</code></li>
            <li><span>Straight-Line Guard</span><code>&sigma;&sup2; = 0.0</code></li>
            <li><span>Double-Blind Unlock</span><code>Grades &amp;&amp; Lock</code></li>
            <li><span>Watchlist Mean Cutoff</span><code>&mu; &lt; 3.50</code></li>
            <li><span>Watchlist Negative Share</span><code>&gt; 35.0%</code></li>
          </ul>
        </section>

        <section class="cheatsheet-col">
          <h4>Analysis &amp; Security</h4>
          <ul class="shortcut-list">
            <li><span>Comment Analysis</span><code>Evaluation Analysis<span class="sr-only"> calamanCy</span></code></li>
            <li><span>Polarity Scoring Range</span><code>[-1.0, +1.0]</code></li>
            <li><span>Vault Student Foreign Keys</span><code>0 Columns</code></li>
            <li><span>Token Default Storage</span><code>sessionStorage</code></li>
            <li><span>Auth Rate-Limit Ceiling</span><code>8 / 60s</code></li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cheatsheet-scrim {
  position: fixed;
  inset: 0;
  z-index: 92;
  background: rgba(16, 40, 30, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.cheatsheet-dialog {
  width: 100%;
  max-width: 820px;
  background: #ffffff;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  padding: 1.3rem 1.45rem;
  box-shadow: 0 20px 44px rgba(16, 40, 30, 0.22);
}

.cheatsheet-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid #e6ede7;
}

.cheatsheet-kicker {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #176347;
}

.cheatsheet-title {
  margin: 0.15rem 0 0;
  font-size: 1.12rem;
  font-weight: 800;
  color: #1f3028;
}

.cheatsheet-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.cheatsheet-col {
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  border-radius: 10px;
  padding: 0.85rem 0.95rem;
}

.cheatsheet-col h4 {
  margin: 0 0 0.65rem;
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #103c2e;
}

.shortcut-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.shortcut-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #1f3028;
}

kbd,
.shortcut-list code {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.72rem;
  font-weight: 700;
  background: #ffffff;
  border: 1px solid #c8d5ca;
  border-radius: 6px;
  padding: 0.14rem 0.42rem;
  color: #103c2e;
  white-space: nowrap;
}

@media (max-width: 800px) {
  .cheatsheet-grid {
    grid-template-columns: 1fr;
  }
}
</style>

