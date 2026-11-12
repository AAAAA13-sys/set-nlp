<script setup>
import AppIcon from '../AppIcon.vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const setupStore = useAcademicSetupStore();

function exportQuestionnaireInstrumentCsv() {
  const preview = setupStore.questionnairePreview;
  if (!preview) return;
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Part', 'Cluster ID', 'Cluster Name', 'Question Number', 'Prompt / Question Text', 'Scale / Response Format'];
  const rows = [];
  (preview.clusters || []).forEach((c) => {
    (c.items || []).forEach((item) => {
      rows.push([
        sanitize('Part I: 5-Point Likert Scale'),
        sanitize(c.cluster_id),
        sanitize(c.name || c.title),
        sanitize(item.id || item.item_number),
        sanitize(item.text || item.prompt),
        sanitize('1 (Strongly Disagree) to 5 (Strongly Agree)'),
      ].join(','));
    });
  });
  (preview.professor_qualitative_questions || []).forEach((q, idx) => {
    rows.push([
      sanitize('Part II: Qualitative Feedback'),
      sanitize('N/A'),
      sanitize('Professor Constructive Feedback'),
      sanitize(idx + 1),
      sanitize(q.question || q.title),
      sanitize(q.format || q.description || 'Open Qualitative Text'),
    ].join(','));
  });
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `AAAAA13_AACCUP3_Evaluation_Instrument_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-questionnaire">
    <div class="questionnaire-header-banner">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div class="questionnaire-badge-row">
          <span class="accreditation-seal">AAAAA13 AACCUP Level III Accredited Instrument</span>
          <span class="scale-legend">Scale: 1 (Strongly Disagree) to 5 (Strongly Agree)</span>
        </div>
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-questionnaire-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; font-weight: 600"
          @click="exportQuestionnaireInstrumentCsv"
        >
          <AppIcon name="chart" /> Export Instrument (CSV)
        </button>
      </div>
      <p class="questionnaire-note">
        This 25-item evaluation instrument is standardized across all academic colleges. Items are categorized into five core pedagogical clusters plus four qualitative professor response fields. Questions are read-only under institutional policy.
      </p>
    </div>

    <!-- Institutional Scoring Rubric & Performance Bands -->
    <article class="cluster-card" data-testid="questionnaire-rubric-card" style="margin-bottom: 1.5rem; padding: 1.25rem">
      <header class="cluster-card-header" style="margin-bottom: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h2 class="cluster-card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700">
            Institutional Evaluation Rating Rubric & Performance Bands
          </h2>
          <p class="cluster-card-desc" style="margin: 0.25rem 0 0; font-size: 0.82rem">
            School AAAAA13 — AACCUP Level III 5-Point Likert Evaluation Standards
          </p>
        </div>
        <span class="token-chip chip-signed" style="font-size: 0.75rem;">
          AACCUP Standard
        </span>
      </header>

      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="evaluation-rubric-table">
          <thead>
            <tr>
              <th scope="col" style="width: 70px;" class="text-center">Scale</th>
              <th scope="col" style="width: 140px;">Qualitative Rating</th>
              <th scope="col" style="width: 120px;" class="text-center">Score Range</th>
              <th scope="col">Pedagogical Description & Performance Standard</th>
              <th scope="col">Institutional Action & Governance</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-center"><strong class="scale-pt" style="padding: 0.2rem 0.55rem; font-weight: 700">5</strong></td>
              <td><strong >Outstanding</strong></td>
              <td class="text-center font-mono">4.50 – 5.00</td>
              <td>Consistently demonstrates exemplary pedagogical mastery, rigorous assessment, superior classroom rapport, and outstanding learner mentorship.</td>
              <td><span class="token-chip chip-signed" style="font-size: 0.72rem;">Commended for Teaching Excellence</span></td>
            </tr>
            <tr>
              <td class="text-center"><strong class="scale-pt" style="padding: 0.2rem 0.55rem; font-weight: 700">4</strong></td>
              <td><strong >Very Satisfactory</strong></td>
              <td class="text-center font-mono">3.50 – 4.49</td>
              <td>Frequently surpasses institutional teaching standards with clear delivery, timely feedback, and effective classroom management.</td>
              <td><span class="token-chip chip-signed" style="font-size: 0.72rem;">Meets Institutional Accreditation Criteria</span></td>
            </tr>
            <tr>
              <td class="text-center"><strong class="scale-pt" style="padding: 0.2rem 0.55rem; font-weight: 700">3</strong></td>
              <td><strong >Satisfactory</strong></td>
              <td class="text-center font-mono">2.50 – 3.49</td>
              <td>Meets baseline course delivery and grading expectations. Pedagogical delivery is adequate with room for instructional enrichment.</td>
              <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem;">Continuous Monitoring</span></td>
            </tr>
            <tr>
              <td class="text-center"><strong class="scale-pt" style="padding: 0.2rem 0.55rem; font-weight: 700">2</strong></td>
              <td><strong >Fair</strong></td>
              <td class="text-center font-mono">1.50 – 2.49</td>
              <td>Inconsistently achieves course objectives; notable challenges in instructional clarity, consultation availability, or assessment fairness.</td>
              <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem">Dean Watchlist / Departmental Coaching</span></td>
            </tr>
            <tr>
              <td class="text-center"><strong class="scale-pt" style="padding: 0.2rem 0.55rem; font-weight: 700">1</strong></td>
              <td><strong >Poor</strong></td>
              <td class="text-center font-mono">1.00 – 1.49</td>
              <td>Fails to meet basic instructional standards; frequent student difficulties with syllabus adherence, grading transparency, or professional conduct.</td>
              <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem">Mandatory Pedagogical Action Plan</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>

    <div v-if="setupStore.questionnairePreview" class="clusters-container">
      <!-- 5 Clusters -->
      <article
        v-for="cluster in setupStore.questionnairePreview.clusters"
        :key="cluster.cluster_id"
        class="cluster-card"
        :data-testid="`cluster-card-${cluster.cluster_id}`"
      >
        <header class="cluster-card-header">
          <div>
            <h2 class="cluster-card-title">{{ cluster.cluster_id }}. {{ cluster.name || cluster.title }}</h2>
            <p v-if="cluster.description" class="cluster-card-desc">{{ cluster.description }}</p>
          </div>
          <span class="cluster-items-count">{{ cluster.items.length }} Items</span>
        </header>

        <ol class="cluster-items-list">
          <li
            v-for="item in cluster.items"
            :key="item.id || item.item_number"
            class="cluster-question-item"
          >
            <span class="item-number">Q{{ item.id || item.item_number }}</span>
            <p class="item-text">{{ item.text || item.prompt }}</p>
            <div class="item-scale-points" aria-hidden="true">
              <span class="scale-pt">1</span>
              <span class="scale-pt">2</span>
              <span class="scale-pt">3</span>
              <span class="scale-pt">4</span>
              <span class="scale-pt">5</span>
            </div>
          </li>
        </ol>
      </article>

      <!-- Qualitative Questions -->
      <article class="cluster-card qualitative-card" data-testid="qualitative-preview-card">
        <header class="cluster-card-header">
          <div>
            <h2 class="cluster-card-title">Part II: Qualitative Professor Feedback</h2>
            <p class="cluster-card-desc">Constructive written evaluation questions analyzed by calamanCy + VADER NLP</p>
          </div>
          <span class="cluster-items-count">4 Qualitative Inquiries</span>
        </header>

        <div class="qualitative-items-list">
          <div
            v-for="q in setupStore.questionnairePreview.professor_qualitative_questions"
            :key="q.id || q.field"
            class="qualitative-item"
          >
            <strong class="qualitative-label">{{ q.question || q.title }}</strong>
            <p class="qualitative-desc">{{ q.format || q.description }}</p>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
