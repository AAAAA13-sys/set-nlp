<script setup>
import { ref } from 'vue';
import { useEvaluationStore } from '../stores/evaluation.js';
import { canFacultyViewEvaluation } from '../utils/evaluationMath.js';

const evaluationStore = useEvaluationStore();

const rosterFormat = ref('json');
const rosterInput = ref(
  JSON.stringify(
    [
      {
        student_id: '2024-10892',
        course_code: 'CS-410',
        course_title: 'Cloud Security & Distributed Systems',
        instructor_name: 'Dr. H. Villanueva',
        department_name: 'College of Computer Studies',
      },
    ],
    null,
    2,
  ),
);

const importStatus = ref(null);

function handleRosterImport() {
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

    const result = evaluationStore.ingestRosterRows(parsedRows);
    importStatus.value = result;
  } catch (err) {
    importStatus.value = {
      ok: false,
      imported: 0,
      message: `Malformed ${rosterFormat.value.toUpperCase()} payload: ${err.message}`,
    };
  }
}

function toggleLock(sectionId, field) {
  evaluationStore.toggleSectionLock(sectionId, field);
}
</script>

<template>
  <main class="workspace-main admin-roster-workspace" aria-labelledby="admin-heading">
    <section class="card admin-header-card">
      <span class="eyebrow-badge">
        PAMANTASAN NG LUNGSOD NG PASIG • Office of the University Registrar • RBAC &amp; Grade-Lock Control
      </span>
      <h2 id="admin-heading" class="section-title">
        PLP Academic Catalog Ingestion &amp; Double-Blind Registrar Lock Console
      </h2>
      <p class="section-subtitle">
        Manage atomic CSV/JSON course enrollments for <strong>Pamantasan ng Lungsod ng Pasig</strong> and synchronize
        Registrar Grade Locks (`registrar_lock`) to release aggregated faculty evaluation reports safely.
      </p>
    </section>

    <section class="card registrar-locks-card" aria-labelledby="locks-heading">
      <h3 id="locks-heading" class="section-title">
        Course Section Double-Blind Lock Synchronization
      </h3>

      <div class="table-responsive">
        <table class="institutional-table" data-testid="registrar-locks-table">
          <thead>
            <tr>
              <th scope="col">Course Code</th>
              <th scope="col">Instructor</th>
              <th scope="col">Grades Submitted (`term_grades_submitted`)</th>
              <th scope="col">Registrar Lock (`registrar_lock`)</th>
              <th scope="col">Faculty Report Visibility</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sec in evaluationStore.sectionLocks"
              :key="sec.course_section_id"
              :data-testid="`admin-lock-row-${sec.course_code}`"
            >
              <td>
                <strong>{{ sec.course_code }}</strong>
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
                  {{ sec.term_grades_submitted ? 'TRUE (Submitted)' : 'FALSE (Pending)' }}
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
                  {{ sec.registrar_lock ? 'TRUE (Locked)' : 'FALSE (Unlocked)' }}
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
                      ? 'UNLOCKED (Accessible)'
                      : 'DOUBLE-BLIND LOCKED'
                  }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card roster-import-card" aria-labelledby="import-heading">
      <h3 id="import-heading" class="section-title">
        Atomic CSV / JSON Academic Roster Ingestion
      </h3>

      <form class="roster-import-form" @submit.prevent="handleRosterImport">
        <div class="format-radio-row">
          <label>
            <input v-model="rosterFormat" type="radio" value="json" />
            JSON Roster Payload
          </label>
          <label>
            <input v-model="rosterFormat" type="radio" value="csv" />
            CSV Roster Payload
          </label>
        </div>

        <label class="sr-only" for="roster-payload-textarea">Roster Ingestion Payload</label>
        <textarea
          id="roster-payload-textarea"
          v-model="rosterInput"
          rows="7"
          class="narrative-textarea code-textarea"
          data-testid="roster-import-textarea"
        ></textarea>

        <div
          v-if="importStatus"
          class="alert-banner"
          :class="importStatus.ok ? 'alert-success' : 'alert-error'"
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
            Execute Atomic Roster Import
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
