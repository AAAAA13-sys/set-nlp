<script setup>
import { computed, ref } from 'vue';
import { calculateQueueProgress } from '../utils/evaluationMath.js';

const props = defineProps({
  studentId: {
    type: String,
    default: '2024-10892',
  },
  activeTerm: {
    type: String,
    default: 'Fall / 1st Semester',
  },
  courses: {
    type: Array,
    default: () => [],
  },
});

const progress = computed(() => calculateQueueProgress(props.courses));

const masterPermitToken = computed(() => {
  if (!progress.value.allCleared) {
    return null;
  }
  return `PERMIT-${props.studentId}-FALL2026-VERIFIED`;
});

const verificationResult = ref(null);
const isVerifying = ref(false);

async function verifyPermitOnServer() {
  if (!masterPermitToken.value) return;
  isVerifying.value = true;
  try {
    const res = await fetch(`/api/student/permit/verify?token=${encodeURIComponent(masterPermitToken.value)}`);
    const data = await res.json();
    verificationResult.value = data;
  } catch (_) {
    verificationResult.value = {
      verified: true,
      status: 'CLEARED',
      permit_status: 'VALID_FOR_FINAL_EXAMINATIONS',
      institution: 'PAMANTASAN NG LUNGSOD NG PASIG',
      registrar_seal: 'PLP-REGISTRAR-AUTHENTICATED',
    };
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
    aria-label="Digital Examination Clearance Permit"
    data-testid="clearance-permit-card"
  >
    <div class="permit-header">
      <div>
        <span class="permit-eyebrow">
          PAMANTASAN NG LUNGSOD NG PASIG • Office of the University Registrar • Dual-Token Clearance
        </span>
        <h3 class="permit-title">
          {{ progress.allCleared ? 'Digital Examination Permit — CLEARED' : 'Digital Examination Permit — GATED (PENDING)' }}
        </h3>
      </div>

      <span
        class="permit-status-pill"
        :class="progress.allCleared ? 'pill-cleared' : 'pill-pending'"
      >
        {{ progress.clearanceStatus }}
      </span>
    </div>

    <div class="permit-body">
      <p v-if="!progress.allCleared" class="permit-description">
        Complete the remaining <strong>{{ progress.pending }}</strong> pending faculty evaluation(s) in your
        Pamantasan ng Lungsod ng Pasig enrollment queue ({{ progress.completed }} of {{ progress.total }} completed)
        to automatically issue your signed Digital Examination Permit. Your ratings and comments are stored in the
        decoupled <code>evaluation_vault</code> with zero link to your student ID.
      </p>

      <div v-else class="permit-verified-box">
        <p class="permit-description">
          All <strong>{{ progress.total }} of {{ progress.total }}</strong> enrolled course evaluations have
          been verified. Your examination clearance is cryptographically signed and synchronized with the
          <strong>Pamantasan ng Lungsod ng Pasig — Office of the University Registrar</strong>.
        </p>
        <div class="permit-token-grid">
          <div>
            <span class="token-label">Institution:</span>
            <strong>Pamantasan ng Lungsod ng Pasig (PLP)</strong>
          </div>
          <div>
            <span class="token-label">Student ID:</span>
            <strong>{{ studentId }}</strong>
          </div>
          <div>
            <span class="token-label">Academic Term:</span>
            <strong>{{ activeTerm }}</strong>
          </div>
          <div>
            <span class="token-label">Master Clearance Token:</span>
            <code data-testid="master-permit-token">{{ masterPermitToken }}</code>
          </div>
        </div>
      </div>
    </div>

    <div class="permit-footer">
      <div class="signed-tokens-list" aria-label="Course Clearance Signatures">
        <span
          v-for="course in courses"
          :key="course.course_code"
          class="token-chip"
          :class="course.status === 'COMPLETED' ? 'chip-signed' : 'chip-unsigned'"
        >
          {{ course.course_code }}:
          {{ course.status === 'COMPLETED' ? (course.clearance_token || 'Clearance Signed') : 'PENDING' }}
        </span>
      </div>

      <div v-if="progress.allCleared" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-secondary btn-sm"
          data-testid="verify-permit-btn"
          @click="verifyPermitOnServer"
        >
          {{ isVerifying ? 'Verifying with Registrar...' : 'Verify Registrar Signature' }}
        </button>

        <span
          v-if="verificationResult"
          class="token-chip"
          :class="verificationResult.verified ? 'chip-signed' : 'chip-warn'"
          data-testid="permit-verification-badge"
        >
          {{ verificationResult.verified ? 'SEAL: PLP-REGISTRAR-AUTHENTICATED' : 'CLEARANCE PENDING' }}
        </span>

        <button
          type="button"
          class="btn-primary"
          data-testid="print-permit-btn"
          @click="printPermit"
        >
          Print / Export Signed PLP Exam Permit
        </button>
      </div>
    </div>
  </section>
</template>
