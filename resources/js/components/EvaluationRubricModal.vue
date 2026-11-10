<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  roleTitle: {
    type: String,
    default: 'Academic Supervisor',
  },
});

const emit = defineEmits(['close']);

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.visible) {
    emit('close');
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

function printRubric() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}
</script>

<template>
  <div
    v-if="visible"
    class="modal-backdrop"
    style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rubric-modal-title"
    data-testid="evaluation-rubric-modal"
    @click.self="emit('close')"
  >
    <div
      class="modal-card print-report-card"
      style="background: #ffffff; border-radius: 12px; max-width: 820px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); border: 1px solid #d1d5db;"
    >
      <!-- Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.25rem;">
        <div>
          <span class="eyebrow-badge">ACCREDITED EVALUATION RUBRIC · {{ roleTitle.toUpperCase() }}</span>
          <h3 id="rubric-modal-title" style="margin: 0.25rem 0 0; font-size: 1.25rem; font-weight: 700; color: #103c2e;">
            Institutional 5-Point Rating Rubric & Standards
          </h3>
          <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: #4b5563;">
            Pamantasan ng Lungsod ng Pasig · AACCUP Level III Accredited Pedagogical Evaluation Standards
          </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <button
            type="button"
            class="btn-secondary btn-xs print-hide"
            data-testid="print-rubric-modal-btn"
            style="display: inline-flex; align-items: center; gap: 0.25rem;"
            @click="printRubric"
          >
            🖨 Print Rubric
          </button>
          <button
            type="button"
            class="btn-secondary btn-xs print-hide"
            data-testid="close-rubric-modal-btn"
            aria-label="Close rubric dialog"
            @click="emit('close')"
          >
            ✕ Close
          </button>
        </div>
      </div>

      <!-- Rating Bands Table -->
      <div class="table-responsive-wrapper" style="margin-bottom: 1.25rem;">
        <table class="setup-data-table" data-testid="modal-rubric-table" style="width: 100%; border-collapse: collapse; font-size: 0.86rem;">
          <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
              <th scope="col" style="padding: 0.6rem 0.75rem; width: 65px; text-align: center;">Scale</th>
              <th scope="col" style="padding: 0.6rem 0.75rem; width: 140px;">Qualitative Rating</th>
              <th scope="col" style="padding: 0.6rem 0.75rem; width: 110px; text-align: center;">Score Range</th>
              <th scope="col" style="padding: 0.6rem 0.75rem;">Pedagogical Description & Performance Standard</th>
              <th scope="col" style="padding: 0.6rem 0.75rem; width: 180px;">Institutional Action & Governance</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.65rem 0.75rem; text-align: center;">
                <strong style="background: #059669; color: #ffffff; padding: 0.18rem 0.5rem; border-radius: 4px; font-weight: 700;">5</strong>
              </td>
              <td style="padding: 0.65rem 0.75rem;"><strong style="color: #059669;">Outstanding</strong></td>
              <td style="padding: 0.65rem 0.75rem; text-align: center; font-family: monospace;">4.50 – 5.00</td>
              <td style="padding: 0.65rem 0.75rem; color: #334155;">Consistently demonstrates exemplary pedagogical mastery, rigorous assessment, superior classroom rapport, and outstanding learner mentorship.</td>
              <td style="padding: 0.65rem 0.75rem;"><span class="token-chip chip-signed" style="font-size: 0.72rem;">Commended for Excellence</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.65rem 0.75rem; text-align: center;">
                <strong style="background: #10b981; color: #ffffff; padding: 0.18rem 0.5rem; border-radius: 4px; font-weight: 700;">4</strong>
              </td>
              <td style="padding: 0.65rem 0.75rem;"><strong style="color: #047857;">Very Satisfactory</strong></td>
              <td style="padding: 0.65rem 0.75rem; text-align: center; font-family: monospace;">3.50 – 4.49</td>
              <td style="padding: 0.65rem 0.75rem; color: #334155;">Frequently surpasses institutional teaching standards with clear delivery, timely feedback, and effective classroom management.</td>
              <td style="padding: 0.65rem 0.75rem;"><span class="token-chip chip-signed" style="font-size: 0.72rem;">Meets Accreditation Criteria</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.65rem 0.75rem; text-align: center;">
                <strong style="background: #f59e0b; color: #ffffff; padding: 0.18rem 0.5rem; border-radius: 4px; font-weight: 700;">3</strong>
              </td>
              <td style="padding: 0.65rem 0.75rem;"><strong style="color: #b45309;">Satisfactory</strong></td>
              <td style="padding: 0.65rem 0.75rem; text-align: center; font-family: monospace;">2.50 – 3.49</td>
              <td style="padding: 0.65rem 0.75rem; color: #334155;">Meets baseline course delivery and grading expectations. Pedagogical delivery is adequate with room for instructional enrichment.</td>
              <td style="padding: 0.65rem 0.75rem;"><span class="token-chip chip-unsigned" style="font-size: 0.72rem;">Continuous Monitoring</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.65rem 0.75rem; text-align: center;">
                <strong style="background: #f97316; color: #ffffff; padding: 0.18rem 0.5rem; border-radius: 4px; font-weight: 700;">2</strong>
              </td>
              <td style="padding: 0.65rem 0.75rem;"><strong style="color: #c2410c;">Fair</strong></td>
              <td style="padding: 0.65rem 0.75rem; text-align: center; font-family: monospace;">1.50 – 2.49</td>
              <td style="padding: 0.65rem 0.75rem; color: #334155;">Inconsistently achieves course objectives; notable challenges in instructional clarity, consultation availability, or assessment fairness.</td>
              <td style="padding: 0.65rem 0.75rem;"><span class="token-chip chip-unsigned" style="font-size: 0.72rem; color: #c2410c; background: #fff7ed;">Dean Watchlist / Coaching</span></td>
            </tr>
            <tr>
              <td style="padding: 0.65rem 0.75rem; text-align: center;">
                <strong style="background: #ef4444; color: #ffffff; padding: 0.18rem 0.5rem; border-radius: 4px; font-weight: 700;">1</strong>
              </td>
              <td style="padding: 0.65rem 0.75rem;"><strong style="color: #dc2626;">Poor</strong></td>
              <td style="padding: 0.65rem 0.75rem; text-align: center; font-family: monospace;">1.00 – 1.49</td>
              <td style="padding: 0.65rem 0.75rem; color: #334155;">Fails to meet basic instructional standards; frequent student difficulties with syllabus adherence, grading transparency, or professional conduct.</td>
              <td style="padding: 0.65rem 0.75rem;"><span class="token-chip chip-unsigned" style="font-size: 0.72rem; color: #dc2626; background: #fef2f2;">Mandatory Action Plan</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Five Pedagogical Clusters Strip -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
        <h4 style="margin: 0 0 0.5rem; font-size: 0.92rem; font-weight: 700; color: #103c2e;">
          Five Core Pedagogical Clusters (25 Likert Scale Items)
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.65rem; font-size: 0.82rem;">
          <div style="background: #ffffff; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="color: #103c2e; display: block;">1. Pedagogical Delivery</strong>
            <span style="color: #64748b;">Subject mastery, clear explanations, syllabus adherence, and engaging instructional methods.</span>
          </div>
          <div style="background: #ffffff; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="color: #103c2e; display: block;">2. Assessment & Grading</strong>
            <span style="color: #64748b;">Fairness in exams, rubric transparency, timely return of scores, and constructive feedback.</span>
          </div>
          <div style="background: #ffffff; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="color: #103c2e; display: block;">3. Classroom Management</strong>
            <span style="color: #64748b;">Punctuality, conducive learning environment, respectful discussion, and class time efficiency.</span>
          </div>
          <div style="background: #ffffff; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="color: #103c2e; display: block;">4. Professional Conduct</strong>
            <span style="color: #64748b;">Ethical behavior, respect for diverse learners, professional decorum, and impartial treatment.</span>
          </div>
          <div style="background: #ffffff; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="color: #103c2e; display: block;">5. Student Support & Mentorship</strong>
            <span style="color: #64748b;">Consultation hour availability, responsiveness to questions, academic encouragement, and project advice.</span>
          </div>
        </div>
      </div>

      <!-- Institutional Governance Trigger Rules -->
      <div style="border-left: 4px solid #103c2e; background: #f0fdf4; padding: 0.75rem 1rem; border-radius: 0 8px 8px 0; font-size: 0.82rem; color: #166534;">
        <strong style="display: block; margin-bottom: 0.2rem;">Institutional Action Protocols:</strong>
        <ul style="margin: 0; padding-left: 1.2rem; line-height: 1.5;">
          <li><strong>Mean Likert &lt; 3.50:</strong> Automatically triggers the Dean/Chair Priority Intervention Watchlist and mandates an institutional Pedagogical Action Plan.</li>
          <li><strong>Negative Sentiment &gt; 35%:</strong> Triggers a qualitative sentiment alert for Department Chair and Dean developmental review.</li>
        </ul>
      </div>
    </div>
  </div>
</template>
