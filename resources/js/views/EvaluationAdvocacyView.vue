<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';

const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();
const copiedTarget = ref('');

const campusPortalShareUrl = computed(() => {
  const origin = typeof window !== 'undefined' && window.location?.origin
    ? window.location.origin
    : 'https://evaluatech.plpasig.edu.ph';
  const dept = encodeURIComponent(authStore.user?.department_code || 'CCS');
  return `${origin}/login?utm_source=plp_campus_advocacy&dept=${dept}`;
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
    advocacyLead: 'Future Educators Guild — PLP Chapter',
    channel: 'Pre-Final Examination Clearance Orientation',
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
          <span class="eyebrow-badge">CLASS LINK &amp; PERMIT</span>
          <h2 id="advocacy-heading" class="section-title">
            Share Portal Link &amp; Check Permit Status
            <span class="ui-hint" tabindex="0" aria-label="Class link and permit help" data-hint="Share the evaluation link with classmates or copy your signed examination clearance code.">?</span>
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
          <span class="share-kicker">EXAMINATION CLEARANCE</span>
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
  </main>
</template>

<style scoped>
.advocacy-workspace {
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
}

.advocacy-hero-card,
.advocacy-drives-card {
  padding: 1.45rem 1.55rem;
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
}
</style>

