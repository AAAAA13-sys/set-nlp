<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { calculateQueueProgress } from '../utils/evaluationMath.js';

const props = defineProps({
  studentId: {
    type: String,
    default: '',
  },
  studentName: {
    type: String,
    default: '',
  },
  activeTerm: {
    type: String,
    default: '1st Semester AY 2026-2027',
  },
  examPermitToken: {
    type: String,
    default: null,
  },
  courses: {
    type: Array,
    default: () => [],
  },
});

const authStore = useAuthStore();
const progress = computed(() => calculateQueueProgress(props.courses));

const masterPermitToken = computed(() => {
  if (!progress.value.allCleared) {
    return null;
  }
  return props.examPermitToken || `PERMIT-${props.studentId}-FALL2026-VERIFIED`;
});

const verificationResult = ref(null);
const verificationError = ref(null);
const isVerifying = ref(false);

async function verifyPermitOnServer() {
  if (!masterPermitToken.value) return;
  isVerifying.value = true;
  verificationError.value = null;

  try {
    const params = new URLSearchParams({
      token: masterPermitToken.value,
      ...(props.studentId ? { institutional_id: props.studentId } : {}),
    });
    const res = await fetch(`/api/student/permit/verify?${params.toString()}`, {
      headers: authStore.authHeaders(),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      verificationResult.value = data;
    } else {
      verificationError.value = data.message || 'Registrar signature verification failed.';
    }
  } catch (_) {
    verificationError.value = 'Unable to connect to the Office of the University Registrar verification service.';
  } finally {
    isVerifying.value = false;
  }
}

function printPermit() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}
</script>

<template>
  <section
    class="clearance-permit-card"
    :class="progress.allCleared ? 'permit-unlocked' : 'permit-gated'"
    aria-label="Student Evaluation Completion Receipt / Submission Confirmation"
    data-testid="clearance-permit-card"
  >
    <div class="permit-header">
      <div>
        <span class="permit-eyebrow">STUDENT EVALUATION PARTICIPATION STATUS</span>
        <h3 class="permit-title">
          {{ progress.allCleared ? 'Evaluation Completion Receipt / Submission Confirmation — Done' : 'Student Evaluation Participation — Pending' }}
          <span class="ui-hint" tabindex="0" data-hint="Complete all assigned course evaluations to record your evaluation completion receipt.">?</span>
        </h3>
      </div>

      <span
        class="permit-status-pill"
        :class="progress.allCleared ? 'pill-cleared' : 'pill-pending'"
      >
        {{ progress.allCleared ? 'Evaluation: Done' : 'Evaluation: Pending' }}
      </span>
    </div>

    <div class="permit-body">
      <p v-if="!progress.allCleared" class="permit-description">
        Finish your remaining <strong>{{ progress.pending }}</strong> course evaluation(s) ({{ progress.completed }} of {{ progress.total }} completed) to complete your student evaluation requirements.
      </p>

      <div v-else class="permit-verified-box">
        <p class="permit-description">
          All <strong>{{ progress.total }}</strong> course evaluations are complete. Your evaluation completion receipt is ready.
        </p>
        <div class="permit-token-grid">
          <div>
            <span class="token-label">Institution:</span>
            <strong>School AAAAA13 (AAAAA13)</strong>
          </div>
          <div>
            <span class="token-label">Student:</span>
            <strong>{{ studentId }}{{ studentName ? ` • ${studentName}` : '' }}</strong>
          </div>
          <div>
            <span class="token-label">Academic Term:</span>
            <strong>{{ activeTerm }}</strong>
          </div>
          <div>
            <span class="token-label">Completion Confirmation Token:</span>
            <code data-testid="master-permit-token" style="word-break: break-all;">{{ masterPermitToken }}</code>
          </div>
        </div>

        <!-- Official Registrar Signature & Authentication Block for Print & Export -->
        <div class="print-permit-auth-block" data-testid="print-permit-signature-block">
          <div class="auth-seal-watermark">
            <span class="seal-badge">SEAL: AAAAA13-REGISTRAR-AUTHENTICATED</span>
            <small class="seal-sub">Officially Completed for Academic Records • AY 2026-2027</small>
          </div>
          <div class="auth-signatures-row">
            <div class="sig-column">
              <div class="sig-line"></div>
              <strong class="sig-title">ATTY. UNIVERSITY REGISTRAR</strong>
              <span class="sig-caption">School AAAAA13</span>
            </div>
            <div class="sig-column">
              <div class="sig-line"></div>
              <strong class="sig-title">COLLEGE DEAN</strong>
              <span class="sig-caption">School AAAAA13</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="permit-footer">
      <div class="signed-tokens-list" aria-label="Course Evaluation Tokens">
        <span
          v-for="course in courses"
          :key="course.course_section_id || course.course_code"
          class="token-chip"
          :class="course.status === 'COMPLETED' ? 'chip-signed' : 'chip-unsigned'"
        >
          {{ course.course_code }}:
          {{ course.status === 'COMPLETED' ? (course.clearance_token || 'Evaluation Submitted') : 'PENDING' }}
        </span>
      </div>

      <div v-if="progress.allCleared" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-secondary btn-sm"
          :disabled="isVerifying"
          data-testid="verify-permit-btn"
          @click="verifyPermitOnServer"
        >
          {{ isVerifying ? 'Verifying with Registrar…' : 'Verify Completion Status' }}
        </button>

        <span
          v-if="verificationResult"
          class="token-chip"
          :class="verificationResult.verified ? 'chip-signed' : 'chip-unsigned'"
          data-testid="permit-verification-badge"
        >
          {{ verificationResult.verified ? 'SEAL: AAAAA13-REGISTRAR-AUTHENTICATED' : 'EVALUATION PENDING' }}
        </span>

        <button
          type="button"
          class="btn-primary"
          data-testid="print-permit-btn"
          @click="printPermit"
        >
          Print / Export Evaluation Completion Receipt
        </button>
      </div>

      <div
        v-if="verificationError"
        class="alert-banner alert-error"
        style="margin-top: 0.75rem; width: 100%;"
        role="alert"
      >
        {{ verificationError }}
      </div>

      <div
        v-if="verificationResult && verificationResult.verified"
        class="proctor-verification-details"
        style="margin-top: 0.75rem; padding: 0.75rem 1rem; font-size: 0.82rem; width: 100%"
        data-testid="proctor-verification-details"
      >
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
          <strong >INSTITUTIONAL EVALUATION PARTICIPATION PROTOCOL: COMPLETED (DONE)</strong>
          <span class="token-chip chip-signed" style="font-size: 0.7rem;">OFFICIAL REGISTRAR RECORD</span>
        </div>
        <div style="line-height: 1.45">
          Student <strong>{{ studentId }}</strong> has completed all {{ progress.total }} enrolled course evaluations.
          Status: <strong>{{ verificationResult.clearance_status || verificationResult.permit_status || 'EVALUATION_COMPLETED_DONE' }}</strong> •
          Seal: <strong>{{ verificationResult.registrar_seal || 'AAAAA13-REGISTRAR-AUTHENTICATED' }}</strong> •
          Portal: <a href="https://aaaaa13.edu/" target="_blank" rel="noopener" style="text-decoration: underline">https://aaaaa13.edu/</a>
        </div>
      </div>
    </div>
  </section>
</template>
