<script setup>
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
  <main class="landing-page-container" data-testid="landing-view-page">
    <!-- Active Authenticated User Banner -->
    <div
      v-if="authStore.isAuthenticated"
      class="landing-auth-banner"
      data-testid="landing-auth-banner"
      style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;"
    >
      <div style="font-size: 0.9rem; color: #065f46;">
        Signed in as <strong>{{ authStore.user?.name || 'PLP User' }}</strong>
        <span class="role-badge" style="margin-left: 0.5rem; text-transform: uppercase; font-size: 0.75rem; background: #103c2e; color: #ffffff; padding: 0.15rem 0.5rem; border-radius: 4px;">
          {{ authStore.currentRole }}
        </span>
      </div>
      <button
        type="button"
        class="btn-primary"
        data-testid="landing-workspace-btn"
        style="font-size: 0.85rem; padding: 0.4rem 0.85rem; border-radius: 6px; font-weight: 600;"
        @click="navigateToWorkspace"
      >
        Go to My Workspace →
      </button>
    </div>

    <!-- Institutional Hero Section -->
    <section class="landing-hero-card" data-testid="landing-hero-section">
      <div class="landing-hero-eyebrow">
        PAMANTASAN NG LUNGSOD NG PASIG • OFFICE OF ACADEMIC AFFAIRS
      </div>
      <h1 class="landing-hero-title">
        EvaluaTech: Automated Student Evaluation of Teachers
      </h1>
      <p class="landing-hero-subtitle">
        A secure, confidential web platform for students to assess instructional delivery, providing faculty and academic supervisors with objective feedback for pedagogical development, governed by AACCUP Level III accreditation standards.
      </p>

      <div class="landing-term-badge">
        <span>Current Academic Term:</span>
        <strong>{{ activeTermName }}</strong>
      </div>

      <div class="landing-actions-row">
        <button
          v-if="!authStore.isAuthenticated"
          type="button"
          class="btn-primary landing-cta-btn"
          data-testid="landing-signin-btn"
          @click="navigateToLogin"
        >
          Sign In to Institutional Portal →
        </button>
        <button
          v-else
          type="button"
          class="btn-primary landing-cta-btn"
          data-testid="landing-workspace-btn"
          @click="navigateToWorkspace"
        >
          Enter Workspace ({{ authStore.currentRole.toUpperCase() }}) →
        </button>
        <button
          type="button"
          class="btn-secondary landing-sub-btn"
          data-testid="landing-guidelines-btn"
          @click="navigateToAdvocacy"
        >
          SET Guidelines &amp; Standards 📋
        </button>
        <button
          type="button"
          class="btn-secondary landing-sub-btn"
          data-testid="landing-verify-status-btn"
          @click="navigateToVerify"
        >
          Check Completion Status 🔍
        </button>
      </div>
    </section>

    <!-- How the Evaluation Process Works -->
    <section class="landing-steps-section" data-testid="landing-steps-section">
      <div class="landing-section-header">
        <span class="section-kicker">WORKFLOW OVERVIEW</span>
        <h2 class="section-heading">How the Student Evaluation Process Works</h2>
        <p class="section-desc">
          Evaluations are conducted entirely online through an accessible four-step workflow. Students submit feedback whenever ready, without minimum waiting periods or maximum answering durations.
        </p>
      </div>

      <div class="steps-grid">
        <div class="step-card">
          <div class="step-number-badge">1</div>
          <h3 class="step-title">Sign In with Credentials</h3>
          <p class="step-text">
            Authenticate using your official PLP student number or institutional email. Authentication is validated through secure bcrypt verification.
          </p>
        </div>

        <div class="step-card">
          <div class="step-number-badge">2</div>
          <h3 class="step-title">Access Assigned Queue</h3>
          <p class="step-text">
            View your enrolled courses and assigned faculty members. The system tracks evaluation completion progress for each course section.
          </p>
        </div>

        <div class="step-card">
          <div class="step-number-badge">3</div>
          <h3 class="step-title">Complete 2-Step Form</h3>
          <p class="step-text">
            Part I features 25 Likert rating items across 5 core pedagogical clusters. Part II provides structured prompts for constructive written feedback.
          </p>
        </div>

        <div class="step-card">
          <div class="step-number-badge">4</div>
          <h3 class="step-title">Cryptographic Anonymity</h3>
          <p class="step-text">
            Submitting marks your course clearance while decoupling your student identity from your evaluation responses, ensuring 100% confidentiality.
          </p>
        </div>
      </div>
    </section>

    <!-- Three Institutional Safeguards -->
    <section class="landing-safeguards-section" data-testid="landing-safeguards-section">
      <div class="landing-section-header">
        <span class="section-kicker">STUDENT PROTECTION &amp; INTEGRITY</span>
        <h2 class="section-heading">Three Core Institutional Safeguards</h2>
        <p class="section-desc">
          EvaluaTech is engineered with structural safeguards protecting student privacy and preserving instructional evaluation validity.
        </p>
      </div>

      <div class="safeguards-grid">
        <div class="safeguard-card">
          <div class="safeguard-icon" aria-hidden="true">🔒</div>
          <h3 class="safeguard-title">Complete Cryptographic Anonymity</h3>
          <p class="safeguard-text">
            Evaluation responses are stored in an isolated vault physically decoupled from student accounts. Zero student IDs, user IDs, or IP addresses are ever stored with rating records.
          </p>
        </div>

        <div class="safeguard-card">
          <div class="safeguard-icon" aria-hidden="true">🛡️</div>
          <h3 class="safeguard-title">Double-Blind Grade Protection</h3>
          <p class="safeguard-text">
            Faculty members cannot view ratings, score summaries, or comments until they submit all term grades and the Office of the University Registrar seals and locks the records.
          </p>
        </div>

        <div class="safeguard-card">
          <div class="safeguard-icon" aria-hidden="true">📈</div>
          <h3 class="safeguard-title">Pedagogical Development Analytics</h3>
          <p class="safeguard-text">
            calamanCy and VADER NLP sentiment analytics synthesize student suggestions into objective instructional development areas, supporting continuous teaching improvement.
          </p>
        </div>
      </div>
    </section>

    <!-- Institutional Footer & Governance Notice -->
    <footer class="landing-footer" data-testid="landing-footer-section">
      <div class="landing-footer-content">
        <div class="footer-col">
          <strong>Pamantasan ng Lungsod ng Pasig</strong>
          <p>Alkalde Jose St., Kapasigan, Pasig City, Metro Manila</p>
          <small>Office of Academic Affairs • Office of the University Registrar</small>
        </div>
        <div class="footer-col">
          <strong>Institutional Governance</strong>
          <p>AACCUP Level III Accredited Evaluation Instrument</p>
          <small>Compliant with RA 10173 (Data Privacy Act of 2012)</small>
        </div>
        <div class="footer-col text-right">
          <strong>System Support</strong>
          <p>Email: <a href="mailto:evaluatech@plpasig.edu.ph" style="color: #f4cd42;">evaluatech@plpasig.edu.ph</a></p>
          <small>EvaluaTech Version 2.0.0 (Production Release)</small>
        </div>
      </div>
    </footer>
  </main>
</template>

<style scoped>
.landing-page-container {
  max-width: 1200px;
  width: 100%;
  box-sizing: border-box;
  margin: 0 auto;
  padding: 1.5rem 1rem 3rem;
  font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #1f2937;
  overflow-x: hidden;
}

.landing-hero-card {
  background: #103c2e;
  color: #ffffff;
  border-radius: 14px;
  padding: 2.75rem 2rem;
  box-shadow: 0 10px 25px -5px rgba(16, 60, 46, 0.25);
  margin-bottom: 2.5rem;
  border: 1px solid #144d3b;
}

.landing-hero-eyebrow {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: #f4cd42;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
}

.landing-hero-title {
  font-size: 2.1rem;
  font-weight: 800;
  line-height: 1.25;
  margin: 0 0 1rem;
  color: #ffffff;
}

.landing-hero-subtitle {
  font-size: 1.05rem;
  line-height: 1.6;
  color: #d1fae5;
  max-width: 820px;
  margin: 0 0 1.5rem;
}

.landing-term-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
  padding: 0.35rem 0.85rem;
  border-radius: 20px;
  font-size: 0.85rem;
  margin-bottom: 1.75rem;
}

.landing-term-badge span {
  color: #a7f3d0;
}

.landing-term-badge strong {
  color: #ffffff;
}

.landing-actions-row {
  display: flex;
  gap: 0.85rem;
  flex-wrap: wrap;
  align-items: center;
}

.landing-cta-btn {
  background: #f4cd42;
  color: #103c2e;
  font-weight: 700;
  padding: 0.65rem 1.4rem;
  border-radius: 8px;
  font-size: 0.95rem;
  border: none;
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}

.landing-cta-btn:hover {
  background: #eab308;
  transform: translateY(-1px);
}

.landing-sub-btn {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.3);
  font-weight: 600;
  padding: 0.65rem 1.15rem;
  border-radius: 8px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.15s ease;
}

.landing-sub-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.landing-section-header {
  text-align: center;
  max-width: 760px;
  margin: 0 auto 2rem;
}

.section-kicker {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #103c2e;
  text-transform: uppercase;
  display: block;
  margin-bottom: 0.35rem;
}

.section-heading {
  font-size: 1.6rem;
  font-weight: 800;
  color: #111827;
  margin: 0 0 0.5rem;
}

.section-desc {
  font-size: 0.95rem;
  line-height: 1.55;
  color: #4b5563;
  margin: 0;
}

.landing-steps-section {
  margin-bottom: 3rem;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.step-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.5rem 1.25rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
  position: relative;
}

.step-number-badge {
  width: 32px;
  height: 32px;
  background: #103c2e;
  color: #f4cd42;
  font-weight: 800;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  margin-bottom: 0.85rem;
}

.step-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0 0 0.45rem;
  color: #111827;
}

.step-text {
  font-size: 0.88rem;
  line-height: 1.5;
  color: #4b5563;
  margin: 0;
}

.landing-safeguards-section {
  margin-bottom: 3rem;
  background: #f9f9f8;
  border: 1px solid #dce7dc;
  border-radius: 12px;
  padding: 2.25rem 1.5rem;
}

.safeguards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.25rem;
}

.safeguard-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.5rem;
}

.safeguard-icon {
  font-size: 1.75rem;
  margin-bottom: 0.65rem;
}

.safeguard-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #103c2e;
  margin: 0 0 0.45rem;
}

.safeguard-text {
  font-size: 0.88rem;
  line-height: 1.5;
  color: #4b5563;
  margin: 0;
}

.landing-footer {
  border-top: 2px solid #103c2e;
  padding-top: 1.75rem;
  color: #4b5563;
  font-size: 0.85rem;
}

.landing-footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
}

.footer-col p {
  margin: 0.25rem 0 0.2rem;
  line-height: 1.4;
}

.footer-col small {
  color: #6b7280;
  display: block;
}

@media (max-width: 768px) {
  .landing-hero-card {
    padding: 1.75rem 1.25rem;
  }
  .landing-hero-title {
    font-size: 1.6rem;
  }
  .landing-hero-subtitle {
    font-size: 0.95rem;
  }
  .landing-actions-row {
    flex-direction: column;
    align-items: stretch;
  }
  .landing-cta-btn, .landing-sub-btn {
    text-align: center;
  }
  .steps-grid, .safeguards-grid {
    grid-template-columns: 1fr;
  }
}
</style>
