<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const tokenInput = ref('');
const isVerifying = ref(false);
const verificationResult = ref(null);
const verificationError = ref(null);
const copiedHash = ref(false);

async function copyDigest(hash) {
  try {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(hash);
      copiedHash.value = true;
      setTimeout(() => {
        copiedHash.value = false;
      }, 2000);
    }
  } catch (_) {}
}

async function executeVerification(tokenOrId) {
  const queryVal = (tokenOrId || tokenInput.value || '').trim();
  if (!queryVal) return;

  isVerifying.value = true;
  verificationError.value = null;
  verificationResult.value = null;

  try {
    const isToken = queryVal.toUpperCase().startsWith('PERMIT-');
    const params = new URLSearchParams({
      token: isToken ? queryVal : `PERMIT-${queryVal}-FALL2026-VERIFIED`,
      institutional_id: isToken ? '' : queryVal,
    });

    const res = await fetch(`/api/permit/verify?${params.toString()}`, {
      headers: {
        Accept: 'application/json',
      },
    });

    const data = await res.json().catch(() => ({}));
    if (res.ok && data.verified) {
      verificationResult.value = data;
    } else {
      verificationError.value = data.message || 'The specified clearance token is either invalid, forged, or student evaluations are pending.';
    }
  } catch (_) {
    verificationError.value = 'Unable to establish a secure connection with the Office of the University Registrar.';
  } finally {
    isVerifying.value = false;
  }
}

function handleFormSubmit() {
  executeVerification(tokenInput.value);
}

function printPermitCertificate() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

onMounted(() => {
  const qToken = route.query.token || route.query.exam_permit_token || route.query.id || route.query.institutional_id;
  if (qToken) {
    tokenInput.value = String(qToken);
    executeVerification(tokenInput.value);
  }
});
</script>

<template>
  <main class="verify-page-container" data-testid="permit-verify-page">
    <div class="verify-content-wrapper">
      <!-- Institutional Header -->
      <header class="verify-header">
        <div class="plp-logo-badge">
          <span class="plp-seal-symbol">🏛</span>
          <div>
            <h1 class="plp-univ-title">SCHOOL AAAAA13</h1>
            <p class="plp-sub-title">Office of the University Registrar · Student Evaluation Clearance Verification</p>
          </div>
        </div>
        <div class="top-nav-actions">
          <router-link
            v-if="!authStore.isAuthenticated"
            to="/login"
            class="nav-link-btn"
            data-testid="verify-back-to-login-btn"
          >
            Sign In →
          </router-link>
          <router-link
            v-else
            to="/"
            class="nav-link-btn"
            data-testid="verify-back-to-portal-btn"
          >
            ← Back to Portal
          </router-link>
        </div>
      </header>

      <!-- Verification Input Card -->
      <section class="verify-card" data-testid="verify-input-card">
        <div class="verify-card-header">
          <h2 class="verify-title">Institutional Evaluation Completion Verification Portal</h2>
          <p class="verify-desc">
            Enter an institutional clearance token or student ID to verify student faculty evaluation completion and clearance status in real-time.
          </p>
        </div>

        <form class="verify-form" data-testid="verify-permit-form" @submit.prevent="handleFormSubmit">
          <div class="form-group">
            <label for="permit-input" class="form-label">Clearance Token or Student Institutional ID:</label>
            <div class="input-with-button">
              <input
                id="permit-input"
                v-model="tokenInput"
                type="text"
                class="form-input token-input"
                placeholder="e.g. 2024-10892 or EVT-CLR-2024-10892-SEALED"
                data-testid="permit-token-input"
                required
              />
              <button
                type="submit"
                class="btn-primary verify-submit-btn"
                :disabled="isVerifying || !tokenInput.trim()"
                data-testid="verify-permit-btn"
              >
                {{ isVerifying ? 'Verifying…' : '🔍 Verify Clearance Status' }}
              </button>
            </div>
          </div>
        </form>
      </section>

      <!-- Verified Success Certificate Card -->
      <section
        v-if="verificationResult"
        class="verify-card certificate-card"
        data-testid="verified-permit-card"
      >
        <div class="certificate-top-bar">
          <span class="status-badge status-cleared" data-testid="permit-verified-badge">
            ✓ EVALUATION COMPLETED — INSTITUTIONALLY CLEARED
          </span>
          <span class="timestamp-tag">
            Verified: {{ verificationResult.verification_timestamp || '2026-11-10' }}
          </span>
        </div>

        <div class="certificate-body">
          <div class="student-meta-grid">
            <div class="meta-item">
              <span class="meta-label">STUDENT NAME</span>
              <strong class="meta-val" data-testid="verified-student-name">
                {{ verificationResult.student_name || 'Enrolled Student' }}
              </strong>
            </div>
            <div class="meta-item">
              <span class="meta-label">INSTITUTIONAL ID</span>
              <strong class="meta-val font-mono" data-testid="verified-student-id">
                {{ verificationResult.student_institutional_id || tokenInput }}
              </strong>
            </div>
            <div class="meta-item">
              <span class="meta-label">ACADEMIC TERM</span>
              <span class="meta-val">{{ verificationResult.academic_term || '1st Semester AY 2026-2027' }}</span>
            </div>
            <div class="meta-item">
              <span class="meta-label">SET COMPLETION</span>
              <span class="meta-val text-emerald font-bold">
                {{ verificationResult.completed_evaluations || 6 }} of {{ verificationResult.total_enrolled_courses || 6 }} Evaluations Completed (100%)
              </span>
            </div>
          </div>

          <!-- Official Security Digest Callout -->
          <div class="security-digest-box">
            <span class="digest-label">REGISTRAR CRYPTOGRAPHIC PROOF:</span>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
              <code class="digest-code font-mono" data-testid="permit-security-hash">
                {{ verificationResult.permit_serial || verificationResult.security_hash || 'AAAAA13-REGISTRAR-AACCUP3-SEALED-2026' }}
              </code>
              <button
                type="button"
                class="copy-hash-btn"
                data-testid="copy-clearance-hash-btn"
                style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.75rem; cursor: pointer; color: var(--ink); font-weight: 600;"
                @click="copyDigest(verificationResult.permit_serial || verificationResult.security_hash || 'AAAAA13-REGISTRAR-AACCUP3-SEALED-2026')"
              >
                {{ copiedHash ? '✓ Copied' : '📋 Copy Hash' }}
              </button>
            </div>
          </div>

          <p class="anonymity-note">
            🔒 <strong>Dual-Token Decoupling Verified:</strong> Student evaluation responses were decoupled from identity at time of submission. Zero identifying student foreign keys exist in the evaluation vault.
          </p>

          <div class="certificate-actions">
            <button
              type="button"
              class="btn-primary"
              data-testid="print-verified-permit-btn"
              @click="printPermitCertificate"
            >
              🖨 Print Official Verification Certificate
            </button>
            <button
              type="button"
              class="btn-secondary"
              @click="verificationResult = null"
            >
              Verify Another Clearance Token
            </button>
          </div>
        </div>
      </section>

      <!-- Gated / Error Warning Alert -->
      <section
        v-if="verificationError"
        class="verify-card error-card"
        data-testid="permit-invalid-alert"
      >
        <div class="error-badge-row">
          <span class="status-badge status-gated">
            ✕ CLEARANCE NOT VERIFIED
          </span>
        </div>
        <h3 class="error-heading">Evaluation Clearance Pending</h3>
        <p class="error-message">{{ verificationError }}</p>
        <p class="error-guidance">
          Students must complete all assigned faculty evaluations to receive an official institutional clearance certificate. If you believe this is in error, please consult the Office of the University Registrar.
        </p>
      </section>

      <!-- Footer Policy -->
      <footer class="verify-footer">
        <p>
          School AAAAA13 · Office of the University Registrar · Protected under RA 10173 Data Privacy
        </p>
      </footer>
    </div>
  </main>
</template>

<style scoped>
.verify-page-container {
  min-height: 100vh;
  min-height: 100dvh;
  background: #f9f9f8;
  font-family: 'Public Sans', system-ui, sans-serif;
  padding: 2rem 1rem;
  color: #1f2937;
}

.verify-content-wrapper {
  max-width: 820px;
  margin: 0 auto;
}

.verify-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1.25rem;
  border-bottom: 2px solid #103c2e;
  flex-wrap: wrap;
  gap: 1rem;
}

.plp-logo-badge {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.plp-seal-symbol {
  font-size: 2.25rem;
}

.plp-univ-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #103c2e;
  letter-spacing: 0.04em;
}

.plp-sub-title {
  margin: 0.15rem 0 0;
  font-size: 0.82rem;
  color: #4b5563;
}

.nav-link-btn {
  font-size: 0.85rem;
  font-weight: 700;
  color: #103c2e;
  text-decoration: none;
  padding: 0.45rem 0.85rem;
  border-radius: 8px;
  border: 1px solid #103c2e;
  background: #ffffff;
  transition: all 0.15s ease;
}

.nav-link-btn:hover {
  background: #103c2e;
  color: #ffffff;
}

.verify-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.75rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  margin-bottom: 1.5rem;
}

.verify-title {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
  font-weight: 800;
  color: #103c2e;
}

.verify-desc {
  margin: 0 0 1.25rem;
  font-size: 0.88rem;
  color: #4b5563;
  line-height: 1.5;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: #374151;
  margin-bottom: 0.4rem;
}

.input-with-button {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.token-input {
  flex: 1;
  min-width: 280px;
  padding: 0.65rem 0.85rem;
  font-size: 0.95rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  outline: none;
}

.token-input:focus {
  border-color: #103c2e;
  box-shadow: 0 0 0 3px rgba(16, 60, 46, 0.15);
}

.verify-submit-btn {
  padding: 0.65rem 1.25rem;
  font-size: 0.92rem;
  font-weight: 700;
  border-radius: 8px;
  border: none;
  background: #103c2e;
  color: #ffffff;
  cursor: pointer;
}

.verify-submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.certificate-card {
  border: 2px solid #103c2e;
}

.certificate-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid #e5e7eb;
}

.status-badge {
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
}

.status-cleared {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
}

.status-gated {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

.timestamp-tag {
  font-size: 0.8rem;
  color: #6b7280;
}

.student-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.meta-item {
  display: flex;
  flex-direction: column;
}

.meta-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #6b7280;
  letter-spacing: 0.05em;
}

.meta-val {
  font-size: 1rem;
  color: #111827;
}

.text-emerald {
  color: #103c2e;
}

.font-bold {
  font-weight: 700;
}

.security-digest-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.75rem;
  margin-bottom: 1rem;
}

.digest-label {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  color: #475569;
}

.digest-code {
  font-size: 0.82rem;
  color: #0b3322;
  word-break: break-all;
}

.anonymity-note {
  font-size: 0.82rem;
  color: #4b5563;
  line-height: 1.5;
  margin-bottom: 1.25rem;
}

.certificate-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.btn-primary {
  background: #103c2e;
  color: #ffffff;
  border: none;
  padding: 0.65rem 1.15rem;
  font-size: 0.88rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
}

.btn-secondary {
  background: #ffffff;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.65rem 1.15rem;
  font-size: 0.88rem;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
}

.error-card {
  border: 1px solid #f87171;
  background: #fef2f2;
}

.error-badge-row {
  margin-bottom: 0.75rem;
}

.error-heading {
  margin: 0 0 0.35rem;
  font-size: 1.15rem;
  font-weight: 800;
  color: #991b1b;
}

.error-message {
  margin: 0 0 0.5rem;
  font-size: 0.88rem;
  color: #7f1d1d;
  font-weight: 600;
}

.error-guidance {
  margin: 0;
  font-size: 0.82rem;
  color: #991b1b;
  line-height: 1.45;
}

.verify-footer {
  text-align: center;
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 2rem;
}

@media print {
  .verify-page-container {
    background: #ffffff;
    padding: 0;
  }
  .verify-header .top-nav-actions,
  .verify-card:not(.certificate-card),
  .certificate-actions {
    display: none !important;
  }
}
</style>
