<script setup>
import { computed, ref } from 'vue';
import { useEvaluationStore } from '../stores/evaluation.js';
import { canFacultyViewEvaluation } from '../utils/evaluationMath.js';

const evaluationStore = useEvaluationStore();
const selectedSectionId = ref(101); // CS-401 starts locked (registrar_lock = false)

const activeSection = computed(
  () =>
    evaluationStore.sectionLocks.find(
      (s) => Number(s.course_section_id) === Number(selectedSectionId.value),
    ) || evaluationStore.sectionLocks[0],
);

const isUnlocked = computed(() =>
  canFacultyViewEvaluation(
    activeSection.value?.term_grades_submitted,
    activeSection.value?.registrar_lock,
  ),
);

function markInstructorGradesSubmitted() {
  if (!activeSection.value) return;
  evaluationStore.toggleSectionLock(
    activeSection.value.course_section_id,
    'term_grades_submitted',
    true,
  );
}
</script>

<template>
  <main class="workspace-main faculty-dashboard-workspace" aria-labelledby="faculty-heading">
    <section class="card faculty-header-card">
      <div class="header-split">
        <div>
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • Double-Blind Retaliation Mitigation Guard
          </span>
          <h2 id="faculty-heading" class="section-title">
            PLP Faculty Pedagogical Feedback &amp; calamanCy Sentiment Portal
          </h2>
          <p class="section-subtitle">
            Formula: <code>CanViewEvaluation = (TermGradesSubmitted = TRUE) ∧ (RegistrarLock = TRUE)</code>
          </p>
        </div>

        <div class="section-selector-box">
          <label for="faculty-section-select">Select Course Section:</label>
          <select
            id="faculty-section-select"
            v-model.number="selectedSectionId"
            class="role-switcher-select"
            data-testid="faculty-section-select"
          >
            <option
              v-for="sec in evaluationStore.sectionLocks"
              :key="sec.course_section_id"
              :value="sec.course_section_id"
            >
              {{ sec.course_code }} — {{ sec.instructor_name }}
              ({{ sec.term_grades_submitted && sec.registrar_lock ? 'UNLOCKED' : 'DOUBLE-BLIND LOCKED' }})
            </option>
          </select>
        </div>
      </div>
    </section>

    <!-- Double-Blind Lock Screen when (term_grades_submitted && registrar_lock) is FALSE -->
    <section
      v-if="!isUnlocked"
      class="card double-blind-lock-card"
      role="region"
      aria-labelledby="lock-banner-heading"
      data-testid="double-blind-lock-screen"
    >
      <div class="lock-badge-row">
        <span class="badge-locked">HTTP 403 • DOUBLE_BLIND_LOCKED</span>
        <span class="lock-section-code">{{ activeSection?.course_code }} ({{ activeSection?.course_title }})</span>
      </div>

      <h3 id="lock-banner-heading" class="lock-title">
        Aggregated Faculty Evaluation Report Temporally Locked
      </h3>
      <p class="lock-description">
        To protect Pamantasan ng Lungsod ng Pasig students from potential grade retaliation and preserve feedback
        authenticity, quantitative 5-cluster Likert distributions and <code>calamanCy</code> NLP sentiment summaries for
        <strong>{{ activeSection?.course_code }}</strong> remain inaccessible until both conditions below
        are verified:
      </p>

      <div class="lock-checklist">
        <div class="lock-check-item" :class="activeSection?.term_grades_submitted ? 'check-done' : 'check-pending'">
          <strong>1. Instructor Term Grades Submitted (`term_grades_submitted`):</strong>
          <span>{{ activeSection?.term_grades_submitted ? 'TRUE (Submitted)' : 'FALSE (Pending Instructor Submission)' }}</span>
        </div>

        <div class="lock-check-item" :class="activeSection?.registrar_lock ? 'check-done' : 'check-pending'">
          <strong>2. Official PLP Registrar Grade Lock (`registrar_lock`):</strong>
          <span>{{ activeSection?.registrar_lock ? 'TRUE (Verified by PLP Registrar)' : 'FALSE (Awaiting University Registrar Lock)' }}</span>
        </div>
      </div>

      <div class="lock-actions">
        <button
          v-if="!activeSection?.term_grades_submitted"
          type="button"
          class="btn-primary"
          data-testid="submit-term-grades-btn"
          @click="markInstructorGradesSubmitted"
        >
          Mark Term Grades Submitted (term_grades_submitted = true)
        </button>
        <button
          type="button"
          class="btn-secondary"
          data-testid="switch-unlocked-section-btn"
          @click="selectedSectionId = 102"
        >
          Preview Unlocked Section (CS-405 — Both Flags TRUE)
        </button>
      </div>
    </section>

    <!-- Unlocked Aggregated Evaluation Report -->
    <section
      v-else
      class="card unlocked-report-card"
      aria-labelledby="unlocked-report-heading"
      data-testid="faculty-unlocked-report"
    >
      <div class="unlocked-header">
        <div>
          <span class="badge-unlocked">DOUBLE-BLIND UNLOCKED • AGGREGATED ANONYMOUS REPORT</span>
          <h3 id="unlocked-report-heading" class="section-title">
            {{ activeSection.course_code }} — {{ activeSection.course_title }}
          </h3>
          <p class="section-subtitle">
            Instructor: <strong>{{ activeSection.instructor_name }}</strong> •
            Evaluations Logged: <strong>{{ activeSection.evaluated_count }} / {{ activeSection.enrolled_count }}</strong> •
            Overall Section Mean: <strong>{{ activeSection.mean_score.toFixed(2) }} / 5.00</strong>
          </p>
        </div>
      </div>

      <div class="dimension-averages-grid">
        <div
          v-for="(score, dimName) in activeSection.dimension_scores"
          :key="dimName"
          class="dimension-score-card"
        >
          <span class="dim-card-title">{{ dimName }}</span>
          <strong class="dim-card-value">{{ Number(score).toFixed(2) }} / 5.00</strong>
          <div class="dim-bar-track">
            <div class="dim-bar-fill" :style="{ width: `${Math.round((Number(score) / 5) * 100)}%` }"></div>
          </div>
        </div>
      </div>

      <div class="anonymized-comments-section">
        <h4>calamanCy + VADER NLP-Classified Narrative Insights (Zero Student Identifiers)</h4>
        <div
          v-for="(comment, idx) in activeSection.sample_comments"
          :key="idx"
          class="comment-insight-card"
        >
          <p class="comment-quote">"{{ comment.excerpt }}"</p>
          <div class="comment-meta">
            <span>Polarity: <strong>{{ comment.polarity >= 0 ? `+${comment.polarity}` : comment.polarity }}</strong></span>
            <span>Valence: <strong>{{ comment.valence }}</strong></span>
            <span>Pedagogical Tag: <strong>{{ comment.tag }}</strong></span>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>
