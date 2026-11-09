# EvaluaTech Frontend Standards (Vue.js 3 SPA)

These rules enforce UI and state management practices across Vue 3 components, Pinia stores, and Vite assets:

1. **Vue.js 3 & Pinia Architecture**:
   - Build all user interfaces under `resources/js/` using Vue 3 Composition API (`<script setup>`), Pinia stores (`stores/auth.js`, `stores/evaluation.js`), and Vue Router (`router/index.js`).
   - Maintain responsive layouts across both mobile ($375\times812\text{ px}$) and desktop ($1366\times768\text{ px}$) viewports.

2. **Mandatory Timing Policy & Straight-Lining UX Guards**:
   - Enforce the Mandatory Timing Policy: students may submit completed evaluations whenever ready, without artificial waiting delays or timers. Validate non-negative elapsed duration (`dwell_time_seconds >= 0`).
   - Compute client-side population variance ($\sigma^2$) across the 25 Likert ratings and trigger `StraightLineModal.vue` whenever $\sigma^2 = 0$ so students must explicitly confirm uniform ratings before submission.

3. **Duplicate-Submit Protection**:
   - Route all evaluation submissions through the `evaluation` Pinia store with in-flight button locking and persistent `submission_key` tracking across network retries.
   - Treat the Laravel server as the single source of truth for clearance status, double-blind lock state, and exception watchlists.

4. **Accessibility & Role Scoping**:
   - Provide semantic radio groups, keyboard navigation, and ARIA attributes across all Likert items and modals.
   - Render clear status banners when faculty reports are locked behind the Double-Blind grade-submission guard.

