<script setup>
import AppIcon from '../components/AppIcon.vue';
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const router = useRouter();
const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const activeTermName = computed(() => {
  return evaluationStore.evaluationWindow?.term_name || authStore.activeTerm || '1st Semester AY 2026-2027';
});

function navigateToLogin() {
  router.push('/login');
}

function navigateToAdvocacy() {
  router.push('/advocacy');
}

function navigateToVerify() {
  router.push('/verify');
}

function navigateToWorkspace() {
  if (authStore.isAuthenticated) {
    router.push(getDefaultRouteForRole(authStore.currentRole));
  } else {
    router.push('/login');
  }
}
</script>

<template>
  <main class="landing" data-testid="landing-view-page">
    <section class="landing-hero" data-testid="landing-hero-section" aria-labelledby="landing-title">
      <div class="landing-hero-copy">
        <h1 id="landing-title">Student Evaluation of Teachers, every semester, in about five minutes a course.</h1>
        <p class="landing-lede">
          Rate each of your teachers on 25 statements and add a few words. Your answers are anonymous:
          the school records that you finished, never which answers are yours.
        </p>
        <div class="landing-actions">
          <button
            type="button"
            class="btn-primary btn-lg"
            data-testid="landing-signin-btn"
            @click="navigateToWorkspace"
          >
            {{ authStore.isAuthenticated ? 'Go to my workspace' : 'Sign in to evaluate' }}
            <AppIcon name="arrow-right" />
          </button>
          <button
            type="button"
            class="btn-secondary btn-lg"
            data-testid="landing-verify-status-btn"
            @click="navigateToVerify"
          >
            Verify a receipt
          </button>
        </div>
        <p class="landing-term">Current term: <b>{{ activeTermName }}</b></p>
        <p class="landing-hero-eyebrow">School AAAAA13 · Office of Academic Affairs</p>
      </div>

      <figure class="landing-board" aria-label="Example of a student's evaluation list">
        <figcaption class="sr-only">Example only: a student's list of courses with done and to-do marks.</figcaption>
        <div class="board-top">
          <span>My evaluations</span>
          <span class="board-count tabular-nums">3 of 5 done</span>
        </div>
        <ol class="board-list">
          <li><span class="code-plate">CS-401</span><span class="board-course">Algorithms<small>Prof. A. Dela Cruz</small></span><span class="status-mark is-done">Done</span></li>
          <li><span class="code-plate">ENG-102</span><span class="board-course">Technical Communication<small>Prof. E. Rivera</small></span><span class="status-mark is-done">Done</span></li>
          <li><span class="code-plate">CS-405</span><span class="board-course">AI &amp; NLP<small>Dr. M. Santos</small></span><span class="status-mark is-done">Done</span></li>
          <li class="is-here"><span class="code-plate">MATH-201</span><span class="board-course"><em class="here-marker">You are here</em>Discrete Mathematics<small>Dr. R. Garcia</small></span><span class="status-mark is-todo">To do</span></li>
          <li><span class="code-plate">PHYS-101</span><span class="board-course">University Physics<small>Engr. J. Luna</small></span><span class="status-mark is-todo">To do</span></li>
        </ol>
      </figure>
    </section>

    <section class="landing-section" data-testid="landing-steps-section" aria-labelledby="steps-title">
      <h2 id="steps-title">How it works</h2>
      <ol class="landing-steps">
        <li>
          <span class="step-dot" aria-hidden="true">1</span>
          <div><h3>Sign in during the evaluation window</h3><p>Use your student number or school email. The Registrar sets the opening and closing dates each semester.</p></div>
        </li>
        <li>
          <span class="step-dot" aria-hidden="true">2</span>
          <div><h3>Rate each teacher</h3><p>25 statements in five areas, from how clearly they teach to how fairly they grade. Then a few written answers.</p></div>
        </li>
        <li>
          <span class="step-dot" aria-hidden="true">3</span>
          <div><h3>Keep your receipt</h3><p>Each finished course gives you a receipt code. Deans see who has finished, never what anyone wrote.</p></div>
        </li>
      </ol>
    </section>

    <section class="landing-section landing-split" data-testid="landing-safeguards-section" aria-labelledby="safe-title">
      <div>
        <h2 id="safe-title">Your answers can't be traced back to you</h2>
        <p class="landing-lede">
          When you submit, the system writes two separate records that share no student ID, no IP address and no exact time.
        </p>
        <button
          type="button"
          class="btn-ghost"
          data-testid="landing-guidelines-btn"
          @click="navigateToAdvocacy"
        >
          <AppIcon name="book" /> Read the evaluation standards
        </button>
      </div>
      <ul class="safeguard-list">
        <li class="safeguard-card">
          <AppIcon name="user" :size="20" />
          <div><h3>Completion record</h3><p>Your name and the course, marked done. This is all your dean sees.</p></div>
        </li>
        <li class="safeguard-card">
          <AppIcon name="lock" :size="20" />
          <div><h3>Anonymous answers</h3><p>Ratings and comments with no name attached. Personal details in comments are removed before anyone reads them.</p></div>
        </li>
        <li class="safeguard-card">
          <AppIcon name="shield" :size="20" />
          <div><h3>Results after grades</h3><p>Teachers only see results once final grades are submitted and locked, so feedback can't affect your grade.</p></div>
        </li>
      </ul>
    </section>

    <footer class="landing-footer" data-testid="landing-footer-section">
      <span><b>School AAAAA13</b> · Office of Academic Affairs and Office of the University Registrar</span>
      <span>Protected under RA 10173, the Data Privacy Act of 2012</span>
      <span>Help: evaluatech@aaaaa13.edu</span>
    </footer>
  </main>
</template>

<style scoped>
.landing { display: flex; flex-direction: column; gap: 72px; padding: 24px 0 8px; }

.landing-hero {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
  gap: 56px;
  align-items: center;
  min-height: calc(100vh - 200px);
}

.landing-hero-eyebrow { margin: 6px 0 0; font-size: 0.9rem; color: var(--wf-ink-3); }

.landing-hero h1 {
  margin: 0;
  font-size: clamp(2.1rem, 4vw, 3.4rem);
  line-height: 1.08;
  font-weight: 800;
  letter-spacing: -0.025em;
  max-width: 16ch;
}

.landing-lede { margin: 20px 0 0; font-size: 1.1rem; line-height: 1.6; color: var(--wf-ink-2); max-width: 54ch; }
.landing-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
.landing-term { margin: 18px 0 0; color: var(--wf-ink-2); }

.landing-board {
  margin: 0;
  background: var(--wf-panel);
  border: 1px solid var(--wf-hair);
  border-radius: 14px;
  box-shadow: 0 30px 60px -30px rgba(15, 23, 42, 0.35);
  overflow: hidden;
}

.board-top {
  display: flex;
  justify-content: space-between;
  padding: 14px 18px;
  background: var(--wf-slate);
  color: #ffffff;
  font-weight: 700;
}

.board-count { color: #cbd5e1; font-weight: 600; }
.board-list { list-style: none; margin: 0; padding: 0; }

.board-list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--wf-tint);
}

.board-list li:last-child { border-bottom: 0; }
.board-list li.is-here { background: var(--wf-tint); }
.board-course { display: flex; flex-direction: column; font-weight: 700; }
.board-course small { font-weight: 500; color: var(--wf-ink-3); font-size: 0.85rem; }
.board-course .here-marker { font-style: normal; }

.landing-section h2 { margin: 0; font-size: 1.75rem; line-height: 1.2; font-weight: 800; letter-spacing: -0.015em; }

.landing-steps {
  list-style: none;
  margin: 24px 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
  border-top: 1px solid var(--wf-hair);
  padding-top: 24px;
}

.landing-steps li { display: flex; gap: 14px; }

.step-dot {
  display: grid;
  place-items: center;
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--wf-slate);
  color: #ffffff;
  font-weight: 800;
}

.landing-steps h3, .safeguard-card h3 { margin: 4px 0 6px; font-size: 1.05rem; font-weight: 700; }
.landing-steps p, .safeguard-card p { margin: 0; color: var(--wf-ink-2); line-height: 1.55; }

.landing-split { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); gap: 48px; align-items: start; }
.landing-split .btn-ghost { margin-top: 16px; padding-left: 0; }

.safeguard-list {
  list-style: none;
  margin: 0;
  padding: 0;
  background: var(--wf-panel);
  border: 1px solid var(--wf-hair);
  border-radius: 12px;
}

.safeguard-card { display: flex; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--wf-tint); }
.safeguard-card:last-child { border-bottom: 0; }
.safeguard-card > .app-icon { margin-top: 6px; color: var(--wf-accent); }

.landing-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 32px;
  padding-top: 20px;
  border-top: 1px solid var(--wf-hair);
  font-size: 0.88rem;
  color: var(--wf-ink-2);
}

@media (max-width: 900px) {
  .landing { gap: 48px; padding-top: 8px; }
  .landing-hero, .landing-split { grid-template-columns: 1fr; gap: 32px; min-height: 0; }
  .landing-steps { grid-template-columns: 1fr; gap: 20px; }
}

@media (max-width: 640px) {
  .landing-actions .btn-primary, .landing-actions .btn-secondary { flex: 1 1 100%; }
  .board-list li { padding: 12px 14px; gap: 10px; }
}
</style>
