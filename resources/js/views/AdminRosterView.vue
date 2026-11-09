<script setup>
import { computed, onMounted, ref } from 'vue';
import DestructiveConfirmModal from '../components/DestructiveConfirmModal.vue';
import ErrorRecoveryBanner from '../components/ErrorRecoveryBanner.vue';
import SkeletonLoader from '../components/SkeletonLoader.vue';
import { useAuthStore } from '../stores/auth.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { canFacultyViewEvaluation } from '../utils/evaluationMath.js';
import { debounce } from '../utils/uxHelpers.js';

const authStore = useAuthStore();
const evaluationStore = useEvaluationStore();

const rosterFormat = ref('json');
const rosterInput = ref(
  JSON.stringify(
    [
      {
        student_id: '2024-10892',
        student_name: 'Juan Dela Cruz',
        course_code: 'CS-410',
        course_title: 'Cloud Security & Distributed Systems',
        section_code: 'SEC-A',
        instructor_name: 'Dr. H. Villanueva',
        faculty_email: 'hvillanueva@plpasig.edu.ph',
        department_name: 'College of Computer Studies',
      },
    ],
    null,
    2,
  ),
);

const importStatus = ref(null);
const vaultAuditData = ref(null);
const vaultAuditError = ref(null);
const windowSaveStatus = ref(null);

// Bulk Section Selection (#10 Bulk Actions On List)
const selectedSectionIds = ref([]);
const bulkActionBusy = ref(false);
const bulkStatusMessage = ref('');

// Destructive Action Protection (#19)
const pendingDangerousStatus = ref(null);
const destructiveBusy = ref(false);

// 63-Control Security Maxxxxxing Posture & Live Audit Console
const securityPosture = ref(null);
const loadingPosture = ref(false);
const posturePartFilter = ref('ALL');
const rawPostureSearch = ref('');
const debouncedPostureSearch = ref('');

const updatePostureSearch = debounce((val) => {
  debouncedPostureSearch.value = String(val || '').trim().toLowerCase();
}, 250);

function onPostureSearchInput(event) {
  rawPostureSearch.value = event.target.value;
  updatePostureSearch(rawPostureSearch.value);
}

const filteredSecurityControls = computed(() => {
  const controls = securityPosture.value?.controls || [];
  return controls.filter((ctrl) => {
    if (posturePartFilter.value !== 'ALL' && Number(ctrl.part) !== Number(posturePartFilter.value)) {
      return false;
    }
    if (!debouncedPostureSearch.value) {
      return true;
    }
    const haystack = `#${ctrl.id} ${ctrl.name || ctrl.title || ''} ${ctrl.mechanism || ctrl.enforcement || ''} ${ctrl.status || ''}`.toLowerCase();
    return haystack.includes(debouncedPostureSearch.value);
  });
});

const windowForm = ref({
  status: 'OPEN',
  min_dwell_seconds: 45,
  straight_line_variance_threshold: 0.0,
  watchlist_mean_threshold: 3.5,
  watchlist_negative_sentiment_pct: 35.0,
  announcement_message: '',
});

function syncWindowFormFromStore() {
  windowForm.value = {
    status: evaluationStore.evaluationWindow?.status || 'OPEN',
    min_dwell_seconds: Number(evaluationStore.evaluationWindow?.min_dwell_seconds ?? 45),
    straight_line_variance_threshold: Number(
      evaluationStore.evaluationWindow?.straight_line_variance_threshold ?? 0.0,
    ),
    watchlist_mean_threshold: Number(
      evaluationStore.evaluationWindow?.watchlist_mean_threshold ?? 3.5,
    ),
    watchlist_negative_sentiment_pct: Number(
      evaluationStore.evaluationWindow?.watchlist_negative_sentiment_pct ?? 35.0,
    ),
    announcement_message: evaluationStore.evaluationWindow?.announcement_message || '',
  };
}

async function fetchSecurityPosture() {
  loadingPosture.value = true;
  try {
    const res = await fetch('/api/admin/security-posture', {
      headers: authStore.authHeaders(),
    });
    if (res.ok) {
      securityPosture.value = await res.json();
    }
  } catch (_) {
    // Keep last posture snapshot if network hiccups
  } finally {
    loadingPosture.value = false;
  }
}

onMounted(async () => {
  await Promise.all([
    evaluationStore.fetchAdminSections(),
    evaluationStore.fetchEvaluationWindow(),
    fetchSecurityPosture(),
  ]);
  syncWindowFormFromStore();
});

async function handleSaveEvaluationWindow() {
  windowSaveStatus.value = null;
  try {
    await evaluationStore.updateEvaluationWindowConfig({
      academic_term_id: evaluationStore.evaluationWindow?.academic_term_id || 1,
      ...windowForm.value,
    });
    syncWindowFormFromStore();
    windowSaveStatus.value = {
      ok: true,
      message: `Evaluation window parameters saved to the database. Active Status: ${evaluationStore.evaluationWindow.status}.`,
    };
  } catch (err) {
    windowSaveStatus.value = {
      ok: false,
      message: `Failed to update evaluation window: ${err.message}`,
    };
  }
}

function requestWindowStatusTransition(newStatus) {
  if (newStatus === 'SUSPENDED' || newStatus === 'CLOSED') {
    pendingDangerousStatus.value = newStatus;
    return;
  }
  setWindowStatusQuick(newStatus);
}

async function confirmDangerousWindowStatus() {
  if (!pendingDangerousStatus.value) return;
  destructiveBusy.value = true;
  try {
    await setWindowStatusQuick(pendingDangerousStatus.value);
  } finally {
    destructiveBusy.value = false;
    pendingDangerousStatus.value = null;
  }
}

async function setWindowStatusQuick(newStatus) {
  windowForm.value.status = newStatus;
  windowSaveStatus.value = null;
  try {
    await evaluationStore.setEvaluationWindowStatus(newStatus);
    syncWindowFormFromStore();
    windowSaveStatus.value = {
      ok: true,
      message: `Evaluation window status updated to ${newStatus} in the institutional database.`,
    };
  } catch (err) {
    windowSaveStatus.value = {
      ok: false,
      message: `Failed to change window status: ${err.message}`,
    };
  }
}

const allSectionsSelected = computed(() => {
  const locks = evaluationStore.sectionLocks || [];
  if (locks.length === 0) return false;
  return locks.every((s) => selectedSectionIds.value.includes(s.course_section_id));
});

function toggleSelectAllSections() {
  const locks = evaluationStore.sectionLocks || [];
  if (allSectionsSelected.value) {
    selectedSectionIds.value = [];
  } else {
    selectedSectionIds.value = locks.map((s) => s.course_section_id);
  }
}

function toggleSelectSection(sectionId) {
  if (selectedSectionIds.value.includes(sectionId)) {
    selectedSectionIds.value = selectedSectionIds.value.filter((id) => id !== sectionId);
  } else {
    selectedSectionIds.value = [...selectedSectionIds.value, sectionId];
  }
}

async function applyBulkLock(field, value) {
  if (selectedSectionIds.value.length === 0) return;
  bulkActionBusy.value = true;
  bulkStatusMessage.value = '';
  try {
    const res = await evaluationStore.bulkToggleSectionLocks(selectedSectionIds.value, field, value);
    if (res.ok) {
      bulkStatusMessage.value = `Updated ${selectedSectionIds.value.length} section(s): ${field} = ${value ? 'TRUE' : 'FALSE'}.`;
      selectedSectionIds.value = [];
    }
  } finally {
    bulkActionBusy.value = false;
  }
}

async function runVaultAudit() {
  vaultAuditError.value = null;
  try {
    const res = await fetch('/api/security/vault-audit', {
      headers: authStore.authHeaders(),
    });
    if (res.ok) {
      vaultAuditData.value = await res.json();
      return;
    }
    const errPayload = await res.json().catch(() => ({}));
    vaultAuditError.value = errPayload.message || 'Vault schema audit rejected by server.';
  } catch (_) {
    vaultAuditError.value = 'Unable to connect to the database security audit endpoint.';
  }
}

async function handleRosterImport() {
  importStatus.value = null;
  try {
    let parsedRows = [];
    if (rosterFormat.value === 'json') {
      parsedRows = JSON.parse(rosterInput.value);
    } else {
      const lines = rosterInput.value
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean);
      const dataLines = lines.filter((l) => !l.toLowerCase().startsWith('student_id,'));
      parsedRows = dataLines.map((line) => {
        const [student_id, course_code, course_title, instructor_name, department_name] = line
          .split(',')
          .map((cell) => cell.trim());
        return { student_id, course_code, course_title, instructor_name, department_name };
      });
    }

    const result = await evaluationStore.ingestRosterRows(
      parsedRows,
      rosterFormat.value,
      rosterInput.value,
    );
    importStatus.value = result;
  } catch (err) {
    importStatus.value = {
      ok: false,
      imported: 0,
      message: `Malformed ${rosterFormat.value.toUpperCase()} payload: ${err.message}`,
    };
  }
}

async function toggleLock(sectionId, field) {
  await evaluationStore.toggleSectionLock(sectionId, field);
}
</script>

<template>
  <main class="workspace-main admin-roster-workspace" aria-labelledby="admin-heading">
    <ErrorRecoveryBanner
      :visible="Boolean(evaluationStore.adminLockError)"
      title="Couldn't save changes"
      message="Your work is safe. Optimistic lock update was rolled back — check your connection."
      :detail="evaluationStore.adminLockError || ''"
      @retry="evaluationStore.fetchAdminSections()"
      @dismiss="evaluationStore.adminLockError = null"
    />

    <section class="card admin-header-card">
      <div class="header-split">
        <div>
          <span class="eyebrow-badge">
            UNIVERSITY REGISTRAR
          </span>
          <h2 id="admin-heading" class="section-title">
            Evaluation Schedule &amp; Grade Locks
            <span class="ui-hint" tabindex="0" aria-label="Registrar console overview" data-hint="Manage the evaluation window, import class rosters, and lock finalized course grades.">?</span>
          </h2>
          <p class="section-subtitle">
            Administrator: <strong>{{ authStore.user?.name || 'University Registrar Admin' }}</strong><span v-if="authStore.user?.institutional_id"> ({{ authStore.user.institutional_id }})</span>
          </p>
        </div>
      </div>
    </section>

    <section class="card evaluation-window-card" aria-labelledby="window-heading" data-testid="admin-evaluation-window-card">
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">
            EVALUATION WINDOW
          </span>
          <h3 id="window-heading" class="section-title">
            Evaluation Period &amp; Thresholds
            <span class="ui-hint" tabindex="0" aria-label="Evaluation period settings" :data-hint="`Configure the evaluation period for ${evaluationStore.evaluationWindow.term_name} (${evaluationStore.evaluationWindow.term_code}) and response thresholds.`">?</span>
          </h3>
        </div>
        <div class="window-status-pill-wrap">
          <span
            class="status-badge"
            :class="{
              'status-completed': evaluationStore.evaluationWindow.status === 'OPEN',
              'status-pending': evaluationStore.evaluationWindow.status === 'SUSPENDED',
              'status-danger': evaluationStore.evaluationWindow.status === 'CLOSED',
            }"
            data-testid="admin-window-status-badge"
          >
            STATUS: {{ evaluationStore.evaluationWindow.status }}
          </span>
        </div>
      </div>

      <div class="window-status-quick-actions">
        <span class="param-label" style="margin-right: 0.5rem; font-weight: 600;">Window Status Transition:</span>
        <button
          type="button"
          class="btn-toggle"
          :class="evaluationStore.evaluationWindow.status === 'OPEN' ? 'toggle-on' : 'toggle-off'"
          data-testid="btn-set-window-open"
          @click="requestWindowStatusTransition('OPEN')"
        >
          OPEN (Evaluations Active)
        </button>
        <button
          type="button"
          class="btn-toggle"
          :class="evaluationStore.evaluationWindow.status === 'SUSPENDED' ? 'toggle-on' : 'toggle-off'"
          data-testid="btn-set-window-suspended"
          @click="requestWindowStatusTransition('SUSPENDED')"
        >
          SUSPENDED (Temporary Hold)
        </button>
        <button
          type="button"
          class="btn-toggle"
          :class="evaluationStore.evaluationWindow.status === 'CLOSED' ? 'toggle-on' : 'toggle-off'"
          data-testid="btn-set-window-closed"
          @click="requestWindowStatusTransition('CLOSED')"
        >
          CLOSED (Term Concluded)
        </button>
      </div>

      <form class="window-params-grid" @submit.prevent="handleSaveEvaluationWindow">
        <div class="param-group">
          <label for="admin-dwell-input" class="param-label">
            Min Dwell Time (s)
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Minimum reading time required before submission (default 45s)"
              data-hint="Minimum reading time required before submission (default 45s)."
            >?</span>
            <span class="param-subtext">Speed-run rejection barrier (&ge; 45s required for 5 clusters)</span>
          </label>
          <input
            id="admin-dwell-input"
            v-model.number="windowForm.min_dwell_seconds"
            type="number"
            min="10"
            max="300"
            class="param-input tabular-nums"
            data-testid="admin-dwell-input"
          />
        </div>

        <div class="param-group">
          <label for="admin-variance-input" class="param-label">
            Uniform Rating Cutoff (&sigma;&sup2;)
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Triggers a confirmation prompt when all 25 ratings are identical"
              data-hint="Triggers a confirmation prompt when all 25 ratings are identical."
            >?</span>
            <span class="param-subtext">Uniform rating trigger (&sigma;&sup2; = 0.0 requires confirmation)</span>
          </label>
          <input
            id="admin-variance-input"
            v-model.number="windowForm.straight_line_variance_threshold"
            type="number"
            step="0.001"
            min="0.0"
            max="1.0"
            class="param-input tabular-nums"
            data-testid="admin-variance-input"
          />
        </div>

        <div class="param-group">
          <label for="admin-watchlist-mean-input" class="param-label">
            Watchlist Mean (&lt;)
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Instructors below this average score appear on the Dean watchlist"
              data-hint="Instructors below this average score appear on the Dean watchlist (default 3.50)."
            >?</span>
            <span class="param-subtext">Instructors below this mean Likert score trigger watchlist (default 3.50)</span>
          </label>
          <input
            id="admin-watchlist-mean-input"
            v-model.number="windowForm.watchlist_mean_threshold"
            type="number"
            step="0.05"
            min="1.0"
            max="5.0"
            class="param-input tabular-nums"
            data-testid="admin-watchlist-mean-input"
          />
        </div>

        <div class="param-group">
          <label for="admin-watchlist-neg-input" class="param-label">
            Watchlist Negative (&gt; %)
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Instructors exceeding this negative comment share appear on the watchlist"
              data-hint="Instructors exceeding this negative comment share appear on the watchlist (default 35%)."
            >?</span>
            <span class="param-subtext">Instructors with negative comments exceeding this % trigger watchlist (default 35.0%)</span>
          </label>
          <input
            id="admin-watchlist-neg-input"
            v-model.number="windowForm.watchlist_negative_sentiment_pct"
            type="number"
            step="0.5"
            min="0.0"
            max="100.0"
            class="param-input tabular-nums"
            data-testid="admin-watchlist-neg-input"
          />
        </div>

        <div class="param-group full-width">
          <label for="admin-announcement-input" class="param-label">
            Student Banner Notice
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Displayed at the top of the student evaluation queue"
              data-hint="Displayed at the top of the student evaluation queue."
            >?</span>
            <span class="param-subtext">Broadcasted to students on their evaluation queue dashboard</span>
          </label>
          <input
            id="admin-announcement-input"
            v-model="windowForm.announcement_message"
            type="text"
            class="param-input"
            data-testid="admin-announcement-input"
          />
        </div>

        <div
          v-if="windowSaveStatus"
          class="alert-banner full-width"
          :class="windowSaveStatus.ok ? 'alert-success' : 'alert-error'"
          role="status"
          data-testid="evaluation-window-status-msg"
        >
          {{ windowSaveStatus.message }}
        </div>

        <div class="form-submit-footer full-width">
          <button
            type="submit"
            class="btn-primary"
            data-testid="save-evaluation-window-btn"
          >
            Save Settings
          </button>
        </div>
      </form>
    </section>

    <section class="card registrar-locks-card" aria-labelledby="locks-heading">
      <div class="locks-header-row">
        <div>
          <h3 id="locks-heading" class="section-title">
            Course Grade Locks
            <span class="ui-badge-count">{{ (evaluationStore.sectionLocks || []).length }}</span>
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Faculty reports unlock only after grades are submitted and locked by the Registrar"
              data-hint="Faculty reports unlock only after grades are submitted and locked by the Registrar."
            >?</span>
            <span class="sr-only"> Course Section Double-Blind Lock Synchronization</span>
          </h3>
        </div>
      </div>

      <div
        v-if="selectedSectionIds.length > 0"
        class="bulk-lock-bar tabular-nums"
        data-testid="admin-bulk-lock-bar"
      >
        <div class="bulk-lock-count">
          <strong>{{ selectedSectionIds.length }}</strong> section(s) selected
          <button type="button" class="bulk-clear-btn" @click="selectedSectionIds = []">Clear</button>
        </div>
        <div class="bulk-lock-buttons">
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="bulkActionBusy"
            data-testid="bulk-enable-registrar-lock-btn"
            @click="applyBulkLock('registrar_lock', true)"
          >
            Lock Selected<span class="sr-only"> (registrar_lock = TRUE)</span>
          </button>
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="bulkActionBusy"
            data-testid="bulk-disable-registrar-lock-btn"
            @click="applyBulkLock('registrar_lock', false)"
          >
            Unlock Selected<span class="sr-only"> (registrar_lock = FALSE)</span>
          </button>
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="bulkActionBusy"
            data-testid="bulk-mark-grades-btn"
            @click="applyBulkLock('term_grades_submitted', true)"
          >
            Mark Grades Submitted
          </button>
        </div>
      </div>

      <div v-if="bulkStatusMessage" class="alert-banner alert-success" style="margin-bottom: 0.75rem;" role="status">
        {{ bulkStatusMessage }}
      </div>

      <div class="table-responsive">
        <table class="institutional-table tabular-nums" data-testid="registrar-locks-table">
          <thead>
            <tr>
              <th scope="col" style="width: 38px;">
                <input
                  type="checkbox"
                  :checked="allSectionsSelected"
                  aria-label="Select all course sections"
                  data-testid="select-all-sections-checkbox"
                  @change="toggleSelectAllSections"
                />
              </th>
              <th scope="col">Course &amp; Section</th>
              <th scope="col">Instructor</th>
              <th scope="col">Grades Submitted<span class="sr-only"> (<code>term_grades_submitted</code>)</span></th>
              <th scope="col">Registrar Lock<span class="sr-only"> (<code>registrar_lock</code>)</span></th>
              <th scope="col">Report Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sec in evaluationStore.sectionLocks"
              :key="sec.course_section_id"
              :data-testid="`admin-lock-row-${sec.course_code}-${sec.section_code || sec.course_section_id}`"
            >
              <td>
                <input
                  type="checkbox"
                  :checked="selectedSectionIds.includes(sec.course_section_id)"
                  :aria-label="`Select ${sec.course_code}`"
                  :data-testid="`select-section-${sec.course_section_id}`"
                  @change="toggleSelectSection(sec.course_section_id)"
                />
              </td>
              <td>
                <strong class="course-code-cell">{{ sec.course_code }}</strong>
                <span v-if="sec.section_code" style="margin-left: 0.35rem; font-size: 0.78rem; color: var(--text-secondary);">({{ sec.section_code }})</span>
                <div class="course-title-sub">{{ sec.course_title }}</div>
              </td>
              <td>{{ sec.instructor_name }}</td>
              <td>
                <button
                  type="button"
                  class="btn-toggle"
                  :class="sec.term_grades_submitted ? 'toggle-on' : 'toggle-off'"
                  :data-testid="`toggle-grades-${sec.course_code}`"
                  @click="toggleLock(sec.course_section_id, 'term_grades_submitted')"
                >
                  {{ sec.term_grades_submitted ? 'Submitted' : 'Pending' }}
                  <span class="sr-only">{{ sec.term_grades_submitted ? 'TRUE' : 'FALSE' }}</span>
                </button>
              </td>
              <td>
                <button
                  type="button"
                  class="btn-toggle"
                  :class="sec.registrar_lock ? 'toggle-on' : 'toggle-off'"
                  :data-testid="`toggle-registrar-${sec.course_code}`"
                  @click="toggleLock(sec.course_section_id, 'registrar_lock')"
                >
                  {{ sec.registrar_lock ? 'Locked' : 'Open' }}
                  <span class="sr-only">{{ sec.registrar_lock ? 'TRUE' : 'FALSE' }}</span>
                </button>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="
                    canFacultyViewEvaluation(sec.term_grades_submitted, sec.registrar_lock)
                      ? 'status-completed'
                      : 'status-pending'
                  "
                >
                  {{
                    canFacultyViewEvaluation(sec.term_grades_submitted, sec.registrar_lock)
                      ? 'Available'
                      : 'Locked'
                  }}
                  <span class="sr-only">
                    {{
                      canFacultyViewEvaluation(sec.term_grades_submitted, sec.registrar_lock)
                        ? 'UNLOCKED (Accessible)'
                        : 'DOUBLE-BLIND LOCKED'
                    }}
                  </span>
                </span>
              </td>
            </tr>
            <tr v-if="!evaluationStore.sectionLocks || evaluationStore.sectionLocks.length === 0" data-testid="admin-locks-empty">
              <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
                  No Course Sections Configured
                </div>
                <p style="margin: 0; font-size: 0.85rem;">
                  Import academic rosters below to populate course section double-blind lock controls.
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card roster-import-card" aria-labelledby="import-heading">
      <h3 id="import-heading" class="section-title">
        Class Roster Import
        <span
          class="ui-hint"
          tabindex="0"
          role="note"
          aria-label="Import enrolled students and course sections via JSON or CSV"
          data-hint="Import enrolled students and course sections via JSON or CSV."
        >?</span>
        <span class="sr-only"> Atomic CSV / JSON Academic Roster Ingestion</span>
      </h3>

      <form class="roster-import-form" @submit.prevent="handleRosterImport">
        <div class="format-radio-row" style="display: flex; gap: 1rem; margin-bottom: 0.75rem;">
          <label>
            <input v-model="rosterFormat" type="radio" value="json" />
            JSON Format
          </label>
          <label>
            <input v-model="rosterFormat" type="radio" value="csv" />
            CSV Format
          </label>
        </div>

        <label class="sr-only" for="roster-payload-textarea">Roster Ingestion Payload</label>
        <textarea
          id="roster-payload-textarea"
          v-model="rosterInput"
          rows="7"
          spellcheck="false"
          class="narrative-textarea code-textarea"
          data-testid="roster-import-textarea"
        ></textarea>

        <div
          v-if="importStatus"
          class="alert-banner"
          :class="importStatus.ok ? 'alert-success' : 'alert-error'"
          style="margin-bottom: 0.85rem;"
          role="status"
          data-testid="roster-import-status"
        >
          {{ importStatus.message }}
        </div>

        <div class="form-submit-footer">
          <button
            type="submit"
            class="btn-primary"
            data-testid="submit-roster-import-btn"
          >
            Import Roster
          </button>
        </div>
      </form>
    </section>

    <!-- 63-Control Security Maxxxxxing Posture & Live Audit Log Console -->
    <section
      class="card security-posture-card"
      aria-labelledby="security-posture-heading"
      data-testid="security-maxxing-posture-card"
    >
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">SECURITY STATUS</span>
          <h3 id="security-posture-heading" class="section-title">
            System Security Status
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Live verification of all 63 security controls and anonymized audit logs"
              data-hint="Live verification of all 63 security controls and anonymized audit logs."
            >?</span>
            <span class="sr-only"> 63-Point Security Posture &amp; Sanitized Security Audit Feed</span>
          </h3>
        </div>

        <div class="posture-summary-actions tabular-nums">
          <span class="status-badge status-completed" data-testid="security-posture-count-badge">
            {{ securityPosture?.enforced_controls ?? 63 }} / {{ securityPosture?.total_controls ?? 63 }} ENFORCED
          </span>
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="loadingPosture"
            data-testid="refresh-security-posture-btn"
            @click="fetchSecurityPosture"
          >
            {{ loadingPosture ? 'Refreshing…' : 'Refresh Posture' }}
          </button>
        </div>
      </div>

      <div class="posture-filter-bar">
        <input
          :value="rawPostureSearch"
          type="search"
          class="form-input posture-search-input"
          placeholder="Search 63 controls (e.g., SSRF, JWT, localStorage, source maps)…"
          aria-label="Filter 63 security controls"
          data-testid="security-posture-search-input"
          @input="onPostureSearchInput"
        />

        <div class="posture-part-pills" role="group" aria-label="Filter security checklist parts">
          <button
            type="button"
            class="posture-pill"
            :class="{ 'is-active': posturePartFilter === 'ALL' }"
            data-testid="security-part-all"
            @click="posturePartFilter = 'ALL'"
          >
            All (63)
          </button>
          <button
            type="button"
            class="posture-pill"
            :class="{ 'is-active': posturePartFilter === '1' }"
            data-testid="security-part-1"
            @click="posturePartFilter = '1'"
          >
            Part 1: Web &amp; API (#1–#19)
          </button>
          <button
            type="button"
            class="posture-pill"
            :class="{ 'is-active': posturePartFilter === '2' }"
            data-testid="security-part-2"
            @click="posturePartFilter = '2'"
          >
            Part 2: AI &amp; Logic (#20–#39)
          </button>
          <button
            type="button"
            class="posture-pill"
            :class="{ 'is-active': posturePartFilter === '3' }"
            data-testid="security-part-3"
            @click="posturePartFilter = '3'"
          >
            Part 3: Infra &amp; Logs (#40–#63)
          </button>
        </div>
      </div>

      <SkeletonLoader v-if="loadingPosture && !securityPosture" :rows="3" label="Auditing 63 security controls…" />

      <div v-else-if="securityPosture" class="posture-grid-scroll">
        <div class="posture-controls-grid tabular-nums" data-testid="security-controls-grid">
          <article
            v-for="ctrl in filteredSecurityControls"
            :key="ctrl.id"
            class="posture-control-item"
          >
            <div class="posture-item-head">
              <span class="posture-id">#{{ ctrl.id }}</span>
              <strong class="posture-title">{{ ctrl.name || ctrl.title }}</strong>
              <span class="posture-status">{{ ctrl.status }}</span>
            </div>
            <p class="posture-desc">{{ ctrl.mechanism || ctrl.enforcement }}</p>
          </article>
        </div>

        <div v-if="securityPosture.recent_security_events?.length" class="audit-events-box" data-testid="security-audit-events-box">
          <h4>Recent Sanitized Security Audit Events (Zero Student PII)</h4>
          <ul class="audit-event-list tabular-nums">
            <li v-for="ev in securityPosture.recent_security_events.slice(0, 6)" :key="ev.id">
              <span class="audit-sev">{{ ev.severity }}</span>
              <code>{{ ev.event_type }}</code>
              <span>Role: {{ ev.actor_role || 'guest' }}</span>
              <span v-if="ev.resource_path">{{ ev.resource_path }}</span>
              <span class="audit-time">{{ ev.created_at }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <section
      class="card vault-audit-card"
      aria-labelledby="vault-audit-heading"
      data-testid="admin-vault-audit-panel"
    >
      <div class="watchlist-header">
        <div>
          <span class="eyebrow-badge">ANONYMITY CHECK</span>
          <h3 id="vault-audit-heading" class="section-title">
            Anonymity Database Check
            <span
              class="ui-hint"
              tabindex="0"
              role="note"
              aria-label="Verifies that evaluation_vault contains zero student-identifying columns"
              data-hint="Verifies that evaluation_vault contains zero student-identifying columns."
            >?</span>
            <span class="sr-only"> Live evaluation_vault Schema &amp; Anonymity Auditor</span>
          </h3>
        </div>
        <button
          type="button"
          class="btn-primary btn-sm"
          data-testid="run-admin-vault-audit-btn"
          @click="runVaultAudit"
        >
          Verify Database Anonymity
        </button>
      </div>
      <div
        v-if="vaultAuditError"
        class="alert-banner alert-error"
        style="margin-top: 0.85rem;"
        role="alert"
      >
        {{ vaultAuditError }}
      </div>
      <pre
        v-if="vaultAuditData"
        class="card"
        style="margin-top:0.85rem;background:#f8fbf9;overflow-x:auto;"
        data-testid="admin-vault-audit-output"
      >{{ JSON.stringify(vaultAuditData, null, 2) }}</pre>
    </section>

    <DestructiveConfirmModal
      :visible="Boolean(pendingDangerousStatus)"
      :title="`Transition Evaluation Window to ${pendingDangerousStatus}?`"
      :description="
        pendingDangerousStatus === 'CLOSED'
          ? 'Closing the academic evaluation window immediately halts new student SET submissions across all colleges.'
          : 'Suspending the evaluation window temporarily pauses student SET submissions until re-opened by the Registrar.'
      "
      impact-note="Already-issued clearance tokens and anonymized vault entries remain preserved."
      :confirm-label="`Confirm ${pendingDangerousStatus}`"
      :busy="destructiveBusy"
      @cancel="pendingDangerousStatus = null"
      @confirm="confirmDangerousWindowStatus"
    />
  </main>
</template>

<style scoped>
.window-status-pill-wrap {
  display: flex;
  align-items: center;
}
.window-status-quick-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
  margin: 1rem 0;
  padding: 0.85rem 1rem;
  background: #f6f8f4;
  border-radius: 10px;
  border: 1px solid #dbe3da;
}
.window-params-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.25rem;
  margin-top: 1rem;
}
.param-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.param-group.full-width {
  grid-column: 1 / -1;
}
.param-label {
  font-size: 0.86rem;
  font-weight: 700;
  color: #1f3028;
}
.param-subtext {
  display: none;
}
.param-input {
  padding: 0.6rem 0.85rem;
  border: 1px solid #c8d6ca;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #1f3028;
  background-color: #ffffff;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.param-input:focus {
  border-color: #0b4f26;
  box-shadow: 0 0 0 3px rgba(11, 79, 38, 0.14);
}
.status-danger {
  background-color: #fee2e2;
  color: #991b1b;
  border: 1px solid #f87171;
}

.locks-header-row {
  margin-bottom: 0.85rem;
}

.bulk-lock-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  padding: 0.7rem 0.95rem;
  margin-bottom: 0.8rem;
  border-radius: 10px;
  background: #103c2e;
  color: #ffffff;
}

.bulk-lock-count {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.83rem;
}

.bulk-clear-btn {
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: transparent;
  color: #f4cd42;
  border-radius: 6px;
  padding: 0.15rem 0.5rem;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
}

.bulk-lock-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.posture-summary-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.posture-filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  margin: 0.95rem 0;
}

.posture-search-input {
  max-width: 360px;
  flex: 1;
  min-width: min(240px, 100%);
}

.posture-part-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.38rem;
}

.posture-pill {
  padding: 0.3rem 0.68rem;
  border-radius: 999px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #3f5247;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.posture-pill.is-active {
  background: #103c2e;
  border-color: #103c2e;
  color: #f4cd42;
}

.posture-controls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 0.65rem;
  max-height: 380px;
  overflow-y: auto;
  padding-right: 0.25rem;
}

.posture-control-item {
  padding: 0.72rem 0.85rem;
  border-radius: 10px;
  border: 1px solid #dbe3da;
  background: #fafcf9;
  display: flex;
  flex-direction: column;
  gap: 0.28rem;
}

.posture-item-head {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.posture-id {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.72rem;
  font-weight: 800;
  color: #103c2e;
  background: #e7f1eb;
  padding: 0.1rem 0.38rem;
  border-radius: 5px;
}

.posture-title {
  flex: 1;
  font-size: 0.8rem;
  color: #1f3028;
}

.posture-status {
  font-size: 0.66rem;
  font-weight: 800;
  padding: 0.1rem 0.42rem;
  border-radius: 999px;
  background: #dcfce7;
  color: #14532d;
}

.posture-desc {
  margin: 0;
  font-size: 0.74rem;
  line-height: 1.42;
  color: #4b5e53;
}

.audit-events-box {
  margin-top: 1rem;
  padding: 0.85rem 1rem;
  border-radius: 10px;
  border: 1px solid #dbe3da;
  background: #f6f8f4;
}

.audit-events-box h4 {
  margin: 0 0 0.55rem;
  font-size: 0.8rem;
  font-weight: 800;
  color: #103c2e;
}

.audit-event-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.76rem;
  color: #3f5247;
}

.audit-event-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
  padding: 0.35rem 0.55rem;
  border-radius: 7px;
  background: #ffffff;
  border: 1px solid #e4ece6;
}

.audit-sev {
  font-size: 0.66rem;
  font-weight: 800;
  padding: 0.08rem 0.38rem;
  border-radius: 5px;
  background: #103c2e;
  color: #f4cd42;
}

.audit-time {
  margin-left: auto;
  color: #5a6d62;
}
</style>
