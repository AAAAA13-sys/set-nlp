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

function previewWatchlistSection() {
  evaluationStore.toggleSectionLock(104, 'term_grades_submitted', true);
  evaluationStore.toggleSectionLock(104, 'registrar_lock', true);
  selectedSectionId.value = 104;
}

const planStatuses = ref({
  104: 'APPROVED',
  106: 'IN_PROGRESS',
});

const deanNotes = ref({
  104: 'Approved by Dean: Instructor enrolled in PLP Rubric Design & Assessment Turnaround Workshop.',
  106: 'Approved by Dean: Pacing timeline aligned with departmental computing syllabus.',
});

const sectionActionPlan = computed(() => {
  if (!activeSection.value) return null;
  const sec = activeSection.value;
  if (sec.action_plan) return sec.action_plan;
  const isFlagged = sec.mean_score < 3.50 || sec.negative_sentiment_pct > 35.0;
  if (!isFlagged) return null;

  const tag = sec.sample_comments?.[0]?.tag || 'Assessment & Grading';
  const secId = Number(sec.course_section_id);
  return {
    plan_id: `PLP-ACT-FAC${secId}`,
    faculty_name: sec.instructor_name,
    course_code: sec.course_code,
    primary_cluster: tag,
    urgency_level: sec.mean_score < 3.50 && sec.negative_sentiment_pct > 35.0 ? 'HIGH' : 'MODERATE',
    status: planStatuses.value[secId] || 'APPROVED',
    dean_notes: deanNotes.value[secId] || 'Dean assigned pedagogical focus: Active learning and rubric transparency.',
    timeline: 'Immediate (Weeks 1-4 of following academic term) with mid-term milestone review',
    target_milestone: {
      target_mean_score: 3.80,
      target_negative_pct: 15.0,
      target_cluster_improvement: `+0.50 points in ${tag}`,
    },
    recommended_interventions: tag.includes('Assessment')
      ? [
          'Establish a strict 14-day turnaround SLA for returning graded quizzes and programming assignments.',
          'Publish explicit analytic rubrics with criterion point distributions prior to major exams.',
          'Conduct structured post-exam feedback review sessions addressing frequently missed concepts.',
        ]
      : [
          'Enforce strict lecture start punctuality and instructional time budgeting across all scheduled blocks.',
          'Align topic pacing directly with the approved PLP departmental course syllabus timeline.',
          'Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.',
        ],
  };
});

async function updateFacultyPlanStatus(newStatus) {
  if (!activeSection.value || !sectionActionPlan.value) return;
  const secId = Number(activeSection.value.course_section_id);
  planStatuses.value[secId] = newStatus;
  try {
    await fetch('/api/faculty/action-plan/status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan_id: sectionActionPlan.value.plan_id,
        faculty_id: secId,
        status: newStatus,
        dean_notes: deanNotes.value[secId] || '',
      }),
    });
  } catch (_) {}
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
        <button
          type="button"
          class="btn-secondary"
          data-testid="preview-watchlist-plan-btn"
          @click="previewWatchlistSection"
        >
          Preview Watchlist Section &amp; Action Plan (MATH-201)
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

      <div class="prof-qualitative-summary-box" data-testid="faculty-prof-questions-summary">
        <h4>Structured Professor Questionnaire Synthesis (4-to-5 Questions • calamanCy NLP)</h4>
        <div class="metric-overview-grid">
          <div class="metric-card">
            <span class="metric-label">1. Top 3 Experience Descriptors</span>
            <strong class="metric-value">
              <template v-if="activeSection?.professor_questionnaire_summary?.top_descriptors?.length">
                {{ activeSection.professor_questionnaire_summary.top_descriptors.slice(0, 3).map(d => `${d.word}${d.count > 1 ? ` (×${d.count})` : ''}`).join(' • ') }}
              </template>
              <template v-else>
                Malinaw • Approachable • Organized
              </template>
            </strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">2. Room for Improvements (a., b.)</span>
            <span class="section-subtitle">
              <template v-if="activeSection?.professor_questionnaire_summary?.improvement_topics?.length">
                <span
                  v-for="(topic, tIdx) in activeSection.professor_questionnaire_summary.improvement_topics.slice(0, 2)"
                  :key="tIdx"
                  style="display: block; margin-bottom: 0.2rem;"
                >
                  <strong>{{ topic.sub_item || `${String.fromCharCode(97 + tIdx)}.` }}</strong> {{ topic.topic }}
                </span>
              </template>
              <template v-else>
                <strong>a.</strong> Faster return of quiz rubrics<br />
                <strong>b.</strong> More step-by-step live coding walkthroughs
              </template>
            </span>
          </div>
          <div class="metric-card">
            <span class="metric-label">3. Key Teaching Strengths</span>
            <span class="section-subtitle">
              {{ activeSection?.professor_questionnaire_summary?.key_strengths?.[0] || 'Clear algorithm explanations and practical laboratory examples.' }}
            </span>
          </div>
          <div class="metric-card">
            <span class="metric-label">4. Actionable Recommendations</span>
            <span class="section-subtitle">
              {{ activeSection?.professor_questionnaire_summary?.key_recommendations?.[0] || 'Provide practice problem sets with rubric solutions before midterms.' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Institutional Pedagogical Action Plan Card for Watchlist-Flagged Sections -->
      <div
        v-if="sectionActionPlan"
        class="card faculty-action-plan-box"
        style="margin-top: 1.25rem; background: #fafbfa; border: 1.5px solid var(--plp-green-800); border-radius: 8px; padding: 1.15rem;"
        data-testid="faculty-action-plan-card"
      >
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
          <span class="eyebrow-badge">
            PAMANTASAN NG LUNGSOD NG PASIG • Continuous Pedagogical Improvement &amp; Accreditation
          </span>
          <span
            class="token-chip"
            :class="{
              'chip-warn': sectionActionPlan.status === 'PENDING_REVIEW',
              'chip-unlocked': sectionActionPlan.status === 'IN_PROGRESS',
              'chip-signed': sectionActionPlan.status === 'APPROVED' || sectionActionPlan.status === 'RESOLVED',
            }"
            data-testid="faculty-plan-status-badge"
          >
            Plan Status: {{ sectionActionPlan.status }}
          </span>
        </div>

        <h4 style="margin: 0.2rem 0 0.4rem; color: var(--plp-green-950); font-size: 1.15rem;">
          Pedagogical Action Plan: Focus on {{ sectionActionPlan.primary_cluster }} ({{ sectionActionPlan.course_code }})
        </h4>

        <p class="section-subtitle" style="font-size:0.86rem; margin-bottom: 0.6rem;">
          Target Milestone: <strong>Mean Score ≥ {{ sectionActionPlan.target_milestone.target_mean_score }} / 5.00</strong> •
          Negative Sentiment Share: <strong>≤ {{ sectionActionPlan.target_milestone.target_negative_pct }}%</strong> •
          Timeline: <strong>{{ sectionActionPlan.timeline }}</strong>
        </p>

        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.85rem; margin-bottom: 0.75rem;">
          <strong style="font-size: 0.88rem; color: var(--plp-green-950);">Assigned Pedagogical Strategies:</strong>
          <ul style="margin: 0.4rem 0 0 1.2rem; padding: 0; font-size: 0.85rem; line-height: 1.55;">
            <li v-for="(strat, sIdx) in sectionActionPlan.recommended_interventions" :key="sIdx">
              {{ strat }}
            </li>
          </ul>
        </div>

        <div v-if="sectionActionPlan.dean_notes" style="font-size: 0.84rem; color: #0b4f26; margin-bottom: 0.75rem;">
          <strong>Dean Verification &amp; Sign-off Notes:</strong> {{ sectionActionPlan.dean_notes }}
        </div>

        <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
          <button
            v-if="sectionActionPlan.status !== 'RESOLVED'"
            type="button"
            class="btn-primary btn-sm"
            data-testid="faculty-acknowledge-plan-btn"
            @click="updateFacultyPlanStatus('IN_PROGRESS')"
          >
            {{ sectionActionPlan.status === 'IN_PROGRESS' ? 'Implementation In-Progress' : 'Acknowledge & Implement Plan' }}
          </button>

          <button
            v-if="sectionActionPlan.status === 'IN_PROGRESS'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="faculty-submit-milestone-btn"
            @click="updateFacultyPlanStatus('RESOLVED')"
          >
            Submit Milestone Resolution Evidence
          </button>

          <span
            v-if="sectionActionPlan.status === 'RESOLVED'"
            class="badge-unlocked"
            style="background: var(--plp-green-800); color: #ffffff;"
            data-testid="faculty-plan-resolved-badge"
          >
            Milestone Successfully Resolved &amp; Verified
          </span>
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
