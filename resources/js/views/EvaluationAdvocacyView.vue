<script setup>
import { computed, ref } from 'vue';
import EvaluationRubricModal from '../components/EvaluationRubricModal.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();
const copiedTarget = ref('');
const showAdvocacyRubricModal = ref(false);

function openAdvocacyRubricModal() {
  showAdvocacyRubricModal.value = true;
}

function closeAdvocacyRubricModal() {
  showAdvocacyRubricModal.value = false;
}

function downloadStudentGuide() {
  const guideText = `================================================================================
SCHOOL AAAAA13
EvaluaTech Student Evaluation of Teachers (SET) & Anonymity Charter
Academic Year: ${authStore.activeTerm || '1st Semester AY 2026-2027'}
================================================================================

1. INSTITUTIONAL PURPOSE & OBJECTIVE
   EvaluaTech is the accredited student evaluation platform for School AAAAA13.
   It provides students with a secure, confidential mechanism
   to assess instructional delivery, course management, and academic support.

2. ZERO-KNOWLEDGE CRYPTOGRAPHIC ANONYMITY (DUAL-TOKEN DECOUPLING)
   - Your student identity is strictly physically decoupled from your evaluation responses.
   - The system maintains two separate databases:
     a) evaluation_clearances: Records that your student account completed the evaluation
        for course clearance purposes.
     b) evaluation_vault: Stores your Likert scores and qualitative feedback without student_id,
        user_id, IP address, or sub-second timestamps.
   - Faculty members, department chairs, and college deans CANNOT view who submitted any
     specific rating or comment.

3. MANDATORY TIMING POLICY (IMMEDIATE SUBMISSION READINESS)
   - Students may submit completed evaluations whenever they are ready.
   - There is NO artificial minimum dwell-time requirement or maximum answering duration.
   - Submissions are accepted 24/7 while the official administrator evaluation window is OPEN.

4. DOUBLE-BLIND FACULTY VISIBILITY LOCK
   - Faculty members cannot view section ratings, student comments, or sentiment summaries
     until both of the following institutional criteria are fulfilled:
     a) The instructor has submitted all final term grades for the section (term_grades_submitted = true).
     b) The University Registrar has formally sealed and locked the grade records (registrar_lock = true).
   - This prevents any evaluation feedback from influencing student grading.

5. ACCREDITED 5-POINT PERFORMANCE RATING SCALE
   5 - Outstanding (4.50 - 5.00): Exemplary teaching performance exceeding institutional expectations.
   4 - Very Satisfactory (3.50 - 4.49): Commendable instruction with consistent mastery and student support.
   3 - Satisfactory (2.50 - 3.49): Standard competent delivery meeting basic curricular expectations.
   2 - Fair (1.50 - 2.49): Inconsistent delivery requiring departmental instructional coaching.
   1 - Poor (1.00 - 1.49): Deficient delivery triggering mandatory pedagogical action plans.

6. FIVE CORE PEDAGOGICAL EVALUATION CLUSTERS
   - Pedagogical Delivery (Items 1-5): Syllabus alignment, clarity of instruction, mastery of subject matter.
   - Assessment & Grading (Items 6-10): Transparent rubric usage, prompt feedback, constructive assessment.
   - Classroom Management (Items 11-15): Punctuality, structured learning environment, respect and discipline.
   - Professional Conduct (Items 16-20): Ethical integrity, respect for learners, professional demeanor.
   - Student Support & Mentorship (Items 21-25): Consultation availability, empathy, encouragement.

7. EVALUATION COMPLETION CERTIFICATE & PARTICIPATION CLEARANCE
   - Completing 100% of your enrolled course evaluations automatically unlocks your official
     Digital Evaluation Completion Certificate.
   - You can print your clearance certificate or present your signed verification hash for institutional clearance.
   - Academic staff and registrars verify clearance in real-time via the public verification portal (/verify).

School AAAAA13 * Office of the University Registrar * Institutional Accreditation`;

  const blob = new Blob([guideText], { type: 'text/plain;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', 'AAAAA13_Student_Evaluation_Charter_and_Guidelines.txt');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

const campusPortalShareUrl = computed(() => {
  const origin = typeof window !== 'undefined' && window.location?.origin
    ? window.location.origin
    : 'https://evaluatech.aaaaa13.edu';
  const dept = encodeURIComponent(authStore.user?.department_code || 'CCS');
  return `${origin}/login?utm_source=aaaaa13_campus_advocacy&dept=${dept}`;
});

const latestClearanceToken = computed(() => {
  const cleared = evaluationStore.courses.find((c) => c.clearance_token);
  return cleared?.clearance_token || evaluationStore.lastReceipt?.clearance_token || '';
});

const collegeDrives = [
  {
    code: 'CCS',
    college: 'College of Computer Studies',
    targetRate: '95.0%',
    advocacyLead: 'Student Council & Class Representatives',
    channel: 'Laboratory Bulletin & Official Class Channels',
  },
  {
    code: 'CBA',
    college: 'College of Business & Accountancy',
    targetRate: '94.0%',
    advocacyLead: 'Junior Philippine Institute of Accountants (JPIA)',
    channel: 'Departmental Advising & Clearance Desk',
  },
  {
    code: 'COED',
    college: 'College of Education',
    targetRate: '96.0%',
    advocacyLead: 'Future Educators Guild — Student Chapter',
    channel: 'Evaluation Participation Orientation',
  },
];

async function copyText(text, key) {
  copiedTarget.value = key;
  try {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    }
  } catch (_) {}
  setTimeout(() => {
    if (copiedTarget.value === key) {
      copiedTarget.value = '';
    }
  }, 2200);
}
</script>

<template>
  <main class="workspace-main advocacy-workspace" aria-labelledby="advocacy-heading" data-testid="campus-advocacy-view">
    <section class="card advocacy-hero-card">
      <div class="advocacy-header-row">
        <div>
          <span class="eyebrow-badge">CLASS LINK &amp; CLEARANCE</span>
          <h2 id="advocacy-heading" class="section-title">
            Share Portal Link &amp; Check Clearance Status
            <span class="ui-hint" tabindex="0" aria-label="Class link and clearance help" data-hint="Share the evaluation link with classmates or copy your signed evaluation clearance code.">?</span>
          </h2>
        </div>
        <div class="advocacy-term-pill tabular-nums">
          <span>Term</span>
          <strong>{{ authStore.activeTerm || '1st Semester AY 2026-2027' }}</strong>
        </div>
      </div>

      <div class="advocacy-share-grid">
        <div class="share-box">
          <span class="share-kicker">CLASS EVALUATION LINK</span>
          <h3>
            Invite Classmates
            <span class="ui-hint" tabindex="0" aria-label="Invite classmates help" data-hint="Share this link in your class group chat so classmates can finish their evaluations on time.">?</span>
          </h3>
          <div class="share-input-row">
            <input
              :value="campusPortalShareUrl"
              type="text"
              readonly
              class="form-input share-url-input"
              aria-label="Official campus advocacy share URL"
              data-testid="advocacy-share-url-input"
            />
            <button
              type="button"
              class="btn-primary"
              data-testid="copy-advocacy-link-btn"
              @click="copyText(campusPortalShareUrl, 'portal')"
            >
              {{ copiedTarget === 'portal' ? 'Copied' : 'Copy Link' }}
            </button>
          </div>
        </div>

        <div class="share-box">
          <span class="share-kicker">EVALUATION CLEARANCE</span>
          <h3>
            Your Clearance Code
            <span class="ui-hint" tabindex="0" aria-label="Clearance code help" data-hint="Generated automatically as you complete your assigned course evaluations.">?</span>
          </h3>
          <div v-if="latestClearanceToken" class="share-input-row">
            <input
              :value="latestClearanceToken"
              type="text"
              readonly
              class="form-input share-url-input"
              aria-label="Latest signed clearance token"
              data-testid="advocacy-clearance-token-input"
            />
            <button
              type="button"
              class="btn-secondary"
              data-testid="copy-clearance-token-btn"
              @click="copyText(latestClearanceToken, 'token')"
            >
              {{ copiedTarget === 'token' ? 'Copied' : 'Copy Code' }}
            </button>
          </div>
          <div v-else class="token-placeholder">
            <span>Complete a course evaluation to generate your clearance code.</span>
          </div>

          <div style="margin-top: 0.85rem;">
            <router-link
              to="/verify"
              class="btn-secondary"
              style="font-size: 0.82rem; padding: 0.4rem 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;"
              data-testid="advocacy-verify-portal-btn"
            >
              🔍 Open Public Verification Portal
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Student Evaluation Guidelines & Anonymity Charter -->
    <section class="card advocacy-guidelines-card" data-testid="advocacy-guidelines-section">
      <div class="guidelines-header-row">
        <div>
          <span class="eyebrow-badge">EVALUATION STANDARDS &amp; CHARTER</span>
          <h3 class="section-title">
            Student Evaluation Guidelines &amp; Anonymity Charter
            <span class="ui-hint" tabindex="0" aria-label="Guidelines help" data-hint="Learn how your feedback is cryptographically protected and how faculty performance is evaluated.">?</span>
          </h3>
          <p class="guidelines-subtitle">
            School AAAAA13 protects your student identity through dual-token cryptographic decoupling, double-blind grade locks, and accredited 5-point performance rubrics.
          </p>
        </div>
        <div class="guidelines-action-group">
          <button
            type="button"
            class="btn-secondary"
            data-testid="advocacy-open-rubric-btn"
            @click="openAdvocacyRubricModal"
          >
            Rating Rubric 📋
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="export-advocacy-guide-btn"
            @click="downloadStudentGuide"
          >
            Download Guide 📄
          </button>
        </div>
      </div>

      <div class="guidelines-pillars-grid">
        <div class="pillar-card">
          <span class="pillar-number">01</span>
          <h4>Complete Cryptographic Anonymity</h4>
          <p>
            Your student ID is never stored with your ratings or comments. Two physically decoupled databases ensure instructors cannot see who submitted each review.
          </p>
        </div>
        <div class="pillar-card">
          <span class="pillar-number">02</span>
          <h4>Immediate Submission Readiness</h4>
          <p>
            Submit whenever you are ready. There is no artificial minimum dwell-time waiting period or maximum answering duration during the active evaluation window.
          </p>
        </div>
        <div class="pillar-card">
          <span class="pillar-number">03</span>
          <h4>Double-Blind Grade Protection</h4>
          <p>
            Instructors cannot access section evaluation results until all term grades are submitted and formally locked by the University Registrar.
          </p>
        </div>
        <div class="pillar-card">
          <span class="pillar-number">04</span>
          <h4>Automatic Evaluation Clearance</h4>
          <p>
            Evaluating 100% of your courses automatically clears and unlocks your official digital evaluation completion certificate with verifiable cryptographic proof.
          </p>
        </div>
      </div>
    </section>

    <section class="card advocacy-drives-card">
      <div class="drives-header">
        <div>
          <span class="eyebrow-badge">COLLEGE PARTICIPATION</span>
          <h3 class="section-title">College Completion Targets</h3>
        </div>
      </div>

      <div class="drives-grid">
        <article v-for="drive in collegeDrives" :key="drive.code" class="drive-card tabular-nums">
          <div class="drive-card-top">
            <span class="drive-code">{{ drive.code }}</span>
            <span class="drive-target">Target: <strong>{{ drive.targetRate }}</strong></span>
          </div>
          <h4>{{ drive.college }}</h4>
          <p class="drive-meta"><strong>Advocacy Partner:</strong> {{ drive.advocacyLead }}</p>
          <p class="drive-meta"><strong>Distribution Channel:</strong> {{ drive.channel }}</p>
        </article>
      </div>
    </section>

    <!-- Evaluation Rubric Modal -->
    <EvaluationRubricModal
      :visible="showAdvocacyRubricModal"
      role-title="Student Body &amp; Campus Advocacy"
      @close="closeAdvocacyRubricModal"
    />
  </main>
</template>

<style scoped>
.advocacy-workspace {
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
}

.advocacy-hero-card,
.advocacy-guidelines-card,
.advocacy-drives-card {
  padding: 1.45rem 1.55rem;
}

.guidelines-header-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.guidelines-subtitle {
  margin: 0.35rem 0 0 0;
  font-size: 0.88rem;
  color: #4b5e53;
  line-height: 1.5;
  max-width: 820px;
}

.guidelines-action-group {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.guidelines-pillars-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.95rem;
}

.pillar-card {
  padding: 1.1rem 1.15rem;
  border-radius: 12px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.pillar-number {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.75rem;
  font-weight: 800;
  color: #176347;
  background: #e8efe9;
  width: fit-content;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.pillar-card h4 {
  margin: 0;
  font-size: 0.96rem;
  color: #1f3028;
  font-weight: 700;
}

.pillar-card p {
  margin: 0;
  font-size: 0.81rem;
  line-height: 1.48;
  color: #4b5e53;
}

.advocacy-header-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.2rem;
}

.advocacy-term-pill {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  padding: 0.55rem 0.85rem;
  border-radius: 10px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
  font-size: 0.78rem;
  color: #103c2e;
}

.advocacy-share-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.share-box {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.1rem 1.15rem;
  border-radius: 12px;
  background: #f6f8f4;
  border: 1px solid #dbe3da;
}

.share-kicker {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #176347;
}

.share-box h3 {
  margin: 0;
  font-size: 1rem;
  color: #1f3028;
}

.share-box p {
  margin: 0;
  font-size: 0.83rem;
  line-height: 1.5;
  color: #4b5e53;
}

.share-input-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.45rem;
}

.share-url-input {
  flex: 1;
  min-width: 180px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.78rem;
  background: #ffffff;
}

.token-placeholder {
  margin-top: auto;
  padding: 0.65rem 0.8rem;
  border-radius: 8px;
  border: 1px dashed #c6d4c8;
  background: #ffffff;
  font-size: 0.78rem;
  color: #4b5e53;
}

.drives-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.9rem;
  margin-top: 0.9rem;
}

.drive-card {
  padding: 1rem 1.1rem;
  border-radius: 12px;
  border: 1px solid #dbe3da;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.drive-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.76rem;
}

.drive-code {
  padding: 0.16rem 0.5rem;
  border-radius: 6px;
  background: #103c2e;
  color: #f4cd42;
  font-weight: 800;
}

.drive-card h4 {
  margin: 0.15rem 0;
  font-size: 0.95rem;
  color: #1f3028;
}

.drive-meta {
  margin: 0;
  font-size: 0.8rem;
  color: #4b5e53;
}

@media (max-width: 900px) {
  .advocacy-share-grid,
  .drives-grid {
    grid-template-columns: 1fr;
  }

  .guidelines-pillars-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .guidelines-pillars-grid {
    grid-template-columns: 1fr;
  }
}
</style>

