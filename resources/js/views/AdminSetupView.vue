<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAcademicSetupStore } from '../stores/academicSetup.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { showJQueryUiError } from '../utils/jqueryUiNotification.js';

const setupStore = useAcademicSetupStore();
const evalStore = useEvaluationStore();

const activeTab = ref('assignments'); // 'terms' | 'assignments' | 'questionnaire'
const successMessage = ref('');
const errorMessage = ref('');

// Modals
const showNewTermModal = ref(false);
const showNewSectionModal = ref(false);
const showNewCourseModal = ref(false);
const sectionToDelete = ref(null);

// Forms
const termForm = ref({
  academic_year: '2026-2027',
  name: '1st Semester AY 2026-2027',
  code: '1SEM-2026',
  starts_on: '',
  ends_on: '',
  is_active: false,
});

const sectionForm = ref({
  course_id: '',
  section_code: '',
  faculty_id: '',
  enrolled_count: 35,
});

const courseForm = ref({
  department_id: 1,
  code: '',
  title: '',
  units: 3,
});

const showImportRosterModal = ref(false);
const rosterImportFormat = ref('json');
const rosterImportContent = ref('');
const rosterImporting = ref(false);

function openImportRosterModal() {
  rosterImportFormat.value = 'json';
  rosterImportContent.value = JSON.stringify([
    {
      student_id: '2024-10892',
      course_code: 'CS-401',
      course_title: 'Design and Analysis of Algorithms',
      instructor_name: 'Prof. A. Dela Cruz',
      department_name: 'Department of Computer Science',
    },
  ], null, 2);
  showImportRosterModal.value = true;
}

async function submitRosterImport() {
  if (!rosterImportContent.value.trim()) {
    flashError('Please provide roster data in CSV or JSON format.');
    return;
  }
  rosterImporting.value = true;
  try {
    let parsedRows = [];
    if (rosterImportFormat.value === 'json') {
      parsedRows = JSON.parse(rosterImportContent.value);
    } else {
      const lines = rosterImportContent.value.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
      const dataLines = lines.filter((l) => !l.toLowerCase().startsWith('student_id,'));
      parsedRows = dataLines.map((line) => {
        const [student_id, course_code, course_title, instructor_name, department_name] = line.split(',').map((c) => c.trim());
        return { student_id, course_code, course_title, instructor_name, department_name };
      });
    }

    const res = await evalStore.ingestRosterRows(
      parsedRows,
      rosterImportFormat.value,
      rosterImportContent.value,
    );

    if (res.ok) {
      showImportRosterModal.value = false;
      await setupStore.fetchOverview(setupStore.selectedTermId);
      flashSuccess(res.message || 'Successfully imported academic roster into term.');
    } else {
      flashError(res.message || 'Roster import failed.');
    }
  } catch (err) {
    flashError(`Malformed ${rosterImportFormat.value.toUpperCase()} payload: ${err.message}`);
  } finally {
    rosterImporting.value = false;
  }
}

function flashSuccess(msg) {
  successMessage.value = msg;
  errorMessage.value = '';
  setTimeout(() => {
    if (successMessage.value === msg) {
      successMessage.value = '';
    }
  }, 4000);
}

function flashError(msg, title = 'Academic Setup Error') {
  errorMessage.value = msg;
  successMessage.value = '';
  showJQueryUiError(msg, title, 4000);
}

onMounted(async () => {
  try {
    await setupStore.fetchOverview();
  } catch (err) {
    flashError(err.message || 'Failed to initialize academic setup overview.');
  }
});

async function handleTermSelect(termId) {
  try {
    await setupStore.fetchOverview(termId);
  } catch (err) {
    flashError(err.message || 'Failed to switch academic term.');
  }
}

async function handleSetActiveTerm(termId) {
  try {
    const res = await setupStore.setActiveTerm(termId);
    flashSuccess(`Term "${res.term_code}" is now the active academic term.`);
  } catch (err) {
    flashError(err.message || 'Failed to set active term.');
  }
}

function openNewTermModal() {
  termForm.value = {
    id: null,
    academic_year: '2026-2027',
    name: '',
    code: '',
    starts_on: '',
    ends_on: '',
    is_active: false,
  };
  showNewTermModal.value = true;
}

function openEditTermModal(t) {
  termForm.value = {
    id: t.id,
    academic_year: t.academic_year || '2026-2027',
    name: t.name || '',
    code: t.code || '',
    starts_on: t.starts_on || '',
    ends_on: t.ends_on || '',
    is_active: Boolean(t.is_active),
  };
  showNewTermModal.value = true;
}

async function submitNewTerm() {
  if (!termForm.value.academic_year || !termForm.value.code || !termForm.value.name) {
    flashError('Please fill in Academic Year, Code, and Term Name.');
    return;
  }
  try {
    const res = await setupStore.createOrUpdateTerm(termForm.value);
    showNewTermModal.value = false;
    flashSuccess(`Academic Term "${res.code}" saved successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to save academic term.');
  }
}

function openNewSectionModal() {
  sectionForm.value = {
    course_id: setupStore.courses[0]?.id || '',
    section_code: '',
    faculty_id: '',
    enrolled_count: 35,
  };
  showNewSectionModal.value = true;
}

async function submitNewSection() {
  if (!sectionForm.value.course_id || !sectionForm.value.section_code) {
    flashError('Course offering and Section Code are required.');
    return;
  }
  try {
    const res = await setupStore.createSection({
      ...sectionForm.value,
      academic_term_id: setupStore.selectedTermId,
    });
    showNewSectionModal.value = false;
    flashSuccess(`Section "${res.section_code}" created successfully under ${res.course_code}.`);
  } catch (err) {
    flashError(err.message || 'Failed to create course section.');
  }
}

function openNewCourseModal() {
  courseForm.value = {
    department_id: setupStore.departments[0]?.id || 1,
    code: '',
    title: '',
    units: 3,
  };
  showNewCourseModal.value = true;
}

async function submitNewCourse() {
  if (!courseForm.value.code || !courseForm.value.title) {
    flashError('Course code and Course title are required.');
    return;
  }
  try {
    const res = await setupStore.createCourse(courseForm.value);
    showNewCourseModal.value = false;
    flashSuccess(`Course "${res.code} - ${res.title}" added to academic catalog.`);
    if (showNewSectionModal.value) {
      sectionForm.value.course_id = res.course_id;
    }
  } catch (err) {
    flashError(err.message || 'Failed to create course offering.');
  }
}

async function handleFacultyAssignmentChange(sectionId, event) {
  const newFacultyId = event.target.value ? Number(event.target.value) : null;
  try {
    const res = await setupStore.assignFaculty(sectionId, newFacultyId);
    if (res.is_assigned) {
      flashSuccess(`Assigned ${res.instructor_name} to section.`);
    } else {
      flashSuccess('Instructor unassigned from section.');
    }
  } catch (err) {
    flashError(err.message || 'Failed to update faculty assignment.');
  }
}

async function handleToggleRegistrarLock(sec) {
  const nextLock = !sec.registrar_lock;
  const previousLock = sec.registrar_lock;
  const previousUnlocked = sec.double_blind_unlocked;

  // Optimistic UI update
  sec.registrar_lock = nextLock;
  sec.double_blind_unlocked = Boolean(sec.term_grades_submitted && nextLock);

  try {
    const res = await evalStore.toggleSectionLock(sec.course_section_id, 'registrar_lock', nextLock);
    if (res) {
      sec.registrar_lock = Boolean(res.registrar_lock);
      sec.double_blind_unlocked = Boolean(res.double_blind_unlocked);
      flashSuccess(
        `Registrar Lock for ${sec.course_code} (${sec.section_code}) updated to ${sec.registrar_lock ? 'LOCKED' : 'UNLOCKED'}.`
      );
    } else {
      sec.registrar_lock = previousLock;
      sec.double_blind_unlocked = previousUnlocked;
      flashError('Server did not confirm registrar lock change.');
    }
  } catch (err) {
    sec.registrar_lock = previousLock;
    sec.double_blind_unlocked = previousUnlocked;
    flashError(err.message || 'Failed to toggle registrar lock.');
  }
}

function confirmDeleteSection(sec) {
  sectionToDelete.value = sec;
}

async function executeDeleteSection() {
  if (!sectionToDelete.value) return;
  const sec = sectionToDelete.value;
  try {
    const res = await setupStore.deleteSection(sec.course_section_id);
    sectionToDelete.value = null;
    flashSuccess(`Section "${res.section_code}" deleted successfully.`);
  } catch (err) {
    sectionToDelete.value = null;
    flashError(err.message || 'Cannot delete section.', 'Deletion Prohibited');
  }
}
</script>

<template>
  <main class="setup-workspace" data-testid="admin-setup-view">
    <!-- Header Banner -->
    <header class="setup-header">
      <div class="setup-header-main">
        <div class="setup-title-group">
          <span class="setup-kicker">University Academic Operations</span>
          <h1 class="setup-title">Academic Setup &amp; Cycle Management</h1>
          <p class="setup-subtitle">
            Configure academic terms, faculty-to-section teaching assignments, and verify evaluation questionnaire readiness.
          </p>
        </div>

        <div class="setup-header-controls" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <div class="setup-term-switcher">
            <label for="active-term-picker" class="term-picker-label">Active Term:</label>
            <select
              id="active-term-picker"
              class="term-picker-select"
              :value="setupStore.selectedTermId"
              data-testid="term-selector-dropdown"
              @change="handleTermSelect(Number($event.target.value))"
            >
              <option
                v-for="t in setupStore.terms"
                :key="t.id"
                :value="t.id"
              >
                {{ t.code }} — {{ t.name }} {{ t.is_active ? '(Active Term)' : '' }}
              </option>
            </select>
          </div>

          <router-link
            to="/admin"
            class="btn-secondary"
            data-testid="goto-admin-console-btn"
            style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.82rem; font-weight: 600; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151;"
          >
            <span>← Registrar Schedule &amp; Locks</span>
          </router-link>
        </div>
      </div>

      <!-- Action Feedback -->
      <div v-if="successMessage" class="feedback-alert success" role="status" data-testid="setup-success-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>{{ successMessage }}</span>
      </div>

      <div v-if="errorMessage" class="feedback-alert error" role="alert" data-testid="setup-error-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Cycle Readiness Summary Bar -->
      <div class="readiness-bar-card" data-testid="cycle-readiness-bar">
        <div class="readiness-status-header">
          <div class="readiness-badge-group">
            <span
              class="readiness-chip"
              :class="setupStore.isCycleReady ? 'ready' : 'incomplete'"
              data-testid="readiness-chip"
            >
              {{ setupStore.isCycleReady ? '✓ Cycle Ready to Open' : '⚠ Setup Incomplete' }}
            </span>
            <span class="window-chip" :class="setupStore.readinessSummary.window_status?.toLowerCase()">
              Window: {{ setupStore.readinessSummary.window_status }}
            </span>
          </div>

          <div class="readiness-metrics-row">
            <div class="metric-item">
              <span class="metric-value">{{ setupStore.readinessSummary.total_sections }}</span>
              <span class="metric-label">Total Sections</span>
            </div>
            <div class="metric-item">
              <span class="metric-value text-emerald">{{ setupStore.readinessSummary.assigned_sections }}</span>
              <span class="metric-label">Assigned</span>
            </div>
            <div class="metric-item">
              <span
                class="metric-value"
                :class="setupStore.readinessSummary.unassigned_sections > 0 ? 'text-amber' : ''"
              >
                {{ setupStore.readinessSummary.unassigned_sections }}
              </span>
              <span class="metric-label">Unassigned</span>
            </div>
            <div class="metric-item">
              <span
                class="metric-value"
                :class="setupStore.readinessSummary.sections_with_zero_enrollment > 0 ? 'text-amber' : ''"
              >
                {{ setupStore.readinessSummary.sections_with_zero_enrollment }}
              </span>
              <span class="metric-label">Zero Enrollment</span>
            </div>
            <div class="metric-item">
              <span class="metric-value">{{ setupStore.readinessSummary.total_enrolled }}</span>
              <span class="metric-label">Total Students</span>
            </div>
          </div>
        </div>

        <!-- Actionable Validation Warnings -->
        <div
          v-if="setupStore.readinessSummary.validation_warnings?.length"
          class="readiness-warnings-box"
          data-testid="readiness-warnings-box"
        >
          <strong class="warnings-title">Required Actions Before Evaluations Open:</strong>
          <ul class="warnings-list">
            <li v-for="(warn, idx) in setupStore.readinessSummary.validation_warnings" :key="idx">
              {{ warn }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <nav class="setup-tabs" aria-label="Setup Modules">
        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'assignments' }"
          data-testid="tab-assignments-btn"
          @click="activeTab = 'assignments'"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Faculty Assignments ({{ setupStore.sections.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'terms' }"
          data-testid="tab-terms-btn"
          @click="activeTab = 'terms'"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span>Academic Terms ({{ setupStore.terms.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'questionnaire' }"
          data-testid="tab-questionnaire-btn"
          @click="activeTab = 'questionnaire'"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          <span>Questionnaire Preview (25 Items)</span>
        </button>
      </nav>
    </header>

    <!-- TAB 1: FACULTY-TO-SECTION ASSIGNMENTS (Inspired by manage_restriction.php) -->
    <section v-if="activeTab === 'assignments'" class="tab-pane" data-testid="pane-assignments">
      <div class="pane-controls-bar">
        <div class="search-filter-group">
          <input
            v-model="setupStore.filterSearch"
            type="search"
            class="table-search-input"
            placeholder="Search course code, title, section, or faculty..."
            data-testid="section-search-input"
          />

          <select
            v-model="setupStore.filterDepartmentId"
            class="filter-select"
            data-testid="department-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>

          <select
            v-model="setupStore.filterAssignment"
            class="filter-select"
            data-testid="assignment-filter-select"
          >
            <option value="ALL">All Assignment Statuses</option>
            <option value="UNASSIGNED">Unassigned Sections Only</option>
            <option value="ASSIGNED">Assigned Sections Only</option>
          </select>
        </div>

        <div class="pane-actions-group">
          <button
            type="button"
            class="btn-secondary"
            data-testid="import-roster-btn"
            @click="openImportRosterModal"
          >
            + Import Roster
          </button>
          <button
            type="button"
            class="btn-secondary"
            data-testid="add-course-btn"
            @click="openNewCourseModal"
          >
            + New Course
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="add-section-btn"
            @click="openNewSectionModal"
          >
            + Add Course Section
          </button>
        </div>
      </div>

      <!-- Sections Table -->
      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="sections-table">
          <thead>
            <tr>
              <th scope="col">Course Code &amp; Title</th>
              <th scope="col">Section</th>
              <th scope="col">Dept</th>
              <th scope="col">Assigned Faculty Instructor</th>
              <th scope="col" class="text-center">Enrolled</th>
              <th scope="col" class="text-center">Evaluations</th>
              <th scope="col" class="text-center">Grade Lock</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sec in setupStore.filteredSections"
              :key="sec.course_section_id"
              :class="{ 'row-unassigned': !sec.is_assigned }"
              :data-testid="`section-row-${sec.course_section_id}`"
            >
              <td>
                <div class="course-code-cell">
                  <strong class="font-mono text-emerald">{{ sec.course_code }}</strong>
                  <span class="course-title-sub">{{ sec.course_title }}</span>
                </div>
              </td>
              <td>
                <span class="section-tag">{{ sec.section_code }}</span>
              </td>
              <td>
                <span class="dept-badge">{{ sec.department_code }}</span>
              </td>
              <td>
                <div class="faculty-assignment-cell">
                  <select
                    class="faculty-select"
                    :value="sec.faculty_id || ''"
                    :data-testid="`faculty-select-${sec.course_section_id}`"
                    @change="handleFacultyAssignmentChange(sec.course_section_id, $event)"
                  >
                    <option value="">— Unassigned Instructor —</option>
                    <option
                      v-for="f in setupStore.facultyList"
                      :key="f.id"
                      :value="f.id"
                    >
                      {{ f.name }} ({{ f.role.toUpperCase() }} · {{ f.department_code }})
                    </option>
                  </select>
                </div>
              </td>
              <td class="text-center">
                <span
                  class="enrolled-badge"
                  :class="sec.enrolled_count === 0 ? 'zero' : ''"
                >
                  {{ sec.enrolled_count }}
                </span>
              </td>
              <td class="text-center">
                <span class="vault-count-badge">
                  {{ sec.vault_count }}
                </span>
              </td>
              <td class="text-center">
                <div class="lock-cell-action">
                  <span
                    class="lock-badge"
                    :class="sec.double_blind_unlocked ? 'unlocked' : 'locked'"
                    :title="sec.double_blind_unlocked ? 'Both term grades and registrar locks confirmed' : 'Awaiting instructor grade submission or registrar approval'"
                  >
                    {{ sec.double_blind_unlocked ? 'Unlocked' : 'Locked' }}
                  </span>
                  <button
                    type="button"
                    class="btn-lock-toggle"
                    :class="sec.registrar_lock ? 'locked' : 'unlocked'"
                    :title="sec.registrar_lock ? 'Registrar Lock Active — Click to unlock' : 'Registrar Lock Inactive — Click to lock'"
                    :data-testid="`toggle-lock-btn-${sec.course_section_id}`"
                    @click="handleToggleRegistrarLock(sec)"
                  >
                    {{ sec.registrar_lock ? '🔒 Locked' : '🔓 Inactive' }}
                  </button>
                </div>
              </td>
              <td class="text-right">
                <button
                  type="button"
                  class="btn-danger-sm"
                  :disabled="sec.vault_count > 0 || sec.cleared_count > 0"
                  :title="sec.vault_count > 0 ? 'Cannot delete section with submitted student evaluations' : 'Delete section'"
                  :data-testid="`delete-section-btn-${sec.course_section_id}`"
                  @click="confirmDeleteSection(sec)"
                >
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="setupStore.filteredSections.length === 0">
              <td colspan="8" class="empty-state-cell">
                No course sections match your filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 2: ACADEMIC TERMS (Inspired by academic_list.php) -->
    <section v-if="activeTab === 'terms'" class="tab-pane" data-testid="pane-terms">
      <div class="pane-controls-bar">
        <div class="pane-description">
          <p>
            Manage academic years and semester cycles. The <strong>Active System Term</strong> determines which courses appear in student evaluation queues.
          </p>
        </div>
        <button
          type="button"
          class="btn-primary"
          data-testid="new-term-btn"
          @click="openNewTermModal"
        >
          + New Academic Term
        </button>
      </div>

      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="terms-table">
          <thead>
            <tr>
              <th scope="col">Code</th>
              <th scope="col">Term / Semester Name</th>
              <th scope="col">Academic Year</th>
              <th scope="col">Date Range</th>
              <th scope="col" class="text-center">Evaluation Window</th>
              <th scope="col" class="text-center">System Status</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="t in setupStore.terms"
              :key="t.id"
              :class="{ 'row-active-term': t.is_active }"
              :data-testid="`term-row-${t.id}`"
            >
              <td>
                <strong class="font-mono">{{ t.code }}</strong>
              </td>
              <td>{{ t.name }}</td>
              <td>{{ t.academic_year }}</td>
              <td>
                <span v-if="t.starts_on && t.ends_on" class="date-range">
                  {{ t.starts_on }} to {{ t.ends_on }}
                </span>
                <span v-else class="text-muted">Not scheduled</span>
              </td>
              <td class="text-center">
                <span class="window-status-pill" :class="t.window_status?.toLowerCase()">
                  {{ t.window_status }}
                </span>
              </td>
              <td class="text-center">
                <span
                  class="active-status-badge"
                  :class="t.is_active ? 'active' : 'inactive'"
                >
                  {{ t.is_active ? 'Active Term' : 'Archived' }}
                </span>
              </td>
              <td class="text-right">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                  <button
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`edit-term-btn-${t.id}`"
                    @click="openEditTermModal(t)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="!t.is_active"
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`set-active-term-btn-${t.id}`"
                    @click="handleSetActiveTerm(t.id)"
                  >
                    Set as Active
                  </button>
                  <span v-else class="text-active-label">Current Active</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 3: QUESTIONNAIRE PREVIEW (Inspired by questionnaire.php) -->
    <section v-if="activeTab === 'questionnaire'" class="tab-pane" data-testid="pane-questionnaire">
      <div class="questionnaire-header-banner">
        <div class="questionnaire-badge-row">
          <span class="accreditation-seal">PLP AACCUP Level III Accredited Instrument</span>
          <span class="scale-legend">Scale: 1 (Strongly Disagree) to 5 (Strongly Agree)</span>
        </div>
        <p class="questionnaire-note">
          This 25-item evaluation instrument is standardized across all academic colleges. Items are categorized into five core pedagogical clusters plus four qualitative professor response fields. Questions are read-only under institutional policy.
        </p>
      </div>

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

    <!-- MODAL: New Academic Term -->
    <div
      v-if="showNewTermModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-term-modal-title"
      data-testid="new-term-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-term-modal-title">{{ termForm.id ? 'Edit Academic Term' : 'Create Academic Term' }}</h2>
          <button type="button" class="modal-close-btn" @click="showNewTermModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewTerm">
          <div class="form-group">
            <label for="term-year">Academic Year (YYYY-YYYY):</label>
            <input
              id="term-year"
              v-model="termForm.academic_year"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., 2026-2027"
              required
            />
          </div>

          <div class="form-group">
            <label for="term-code">Term Code:</label>
            <input
              id="term-code"
              v-model="termForm.code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., 2SEM-2026"
              required
            />
          </div>

          <div class="form-group">
            <label for="term-name">Term / Semester Name:</label>
            <input
              id="term-name"
              v-model="termForm.name"
              type="text"
              class="form-input"
              placeholder="e.g., 2nd Semester AY 2026-2027"
              required
            />
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="term-starts">Starts On (Optional):</label>
              <input
                id="term-starts"
                v-model="termForm.starts_on"
                type="date"
                class="form-input"
              />
            </div>
            <div class="form-group">
              <label for="term-ends">Ends On (Optional):</label>
              <input
                id="term-ends"
                v-model="termForm.ends_on"
                type="date"
                class="form-input"
              />
            </div>
          </div>

          <div class="form-checkbox-group">
            <input
              id="term-is-active"
              v-model="termForm.is_active"
              type="checkbox"
              class="form-checkbox"
            />
            <label for="term-is-active">Set as active system academic term immediately</label>
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewTermModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Saving...' : 'Save Academic Term' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Add Course Section -->
    <div
      v-if="showNewSectionModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-sec-modal-title"
      data-testid="new-section-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-sec-modal-title">Create Course Section</h2>
          <button type="button" class="modal-close-btn" @click="showNewSectionModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewSection">
          <div class="form-group">
            <div class="label-with-link">
              <label for="sec-course">Course Offering:</label>
              <button type="button" class="text-link-btn" @click="openNewCourseModal">+ New Course</button>
            </div>
            <select
              id="sec-course"
              v-model="sectionForm.course_id"
              class="form-input"
              required
            >
              <option value="" disabled>— Select Course —</option>
              <option
                v-for="c in setupStore.courses"
                :key="c.id"
                :value="c.id"
              >
                {{ c.code }} — {{ c.title }} ({{ c.department_code }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="sec-code">Section Code:</label>
            <input
              id="sec-code"
              v-model="sectionForm.section_code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., SEC-A or BSCS-4A"
              required
            />
          </div>

          <div class="form-group">
            <label for="sec-faculty">Assigned Instructor:</label>
            <select
              id="sec-faculty"
              v-model="sectionForm.faculty_id"
              class="form-input"
            >
              <option value="">— Unassigned (Assign Later) —</option>
              <option
                v-for="f in setupStore.facultyList"
                :key="f.id"
                :value="f.id"
              >
                {{ f.name }} ({{ f.department_code }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="sec-enrolled">Estimated Student Enrollment:</label>
            <input
              id="sec-enrolled"
              v-model.number="sectionForm.enrolled_count"
              type="number"
              min="0"
              max="200"
              class="form-input"
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewSectionModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Creating...' : 'Create Section' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Add Course Offering -->
    <div
      v-if="showNewCourseModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-course-modal-title"
      data-testid="new-course-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-course-modal-title">New Course Offering</h2>
          <button type="button" class="modal-close-btn" @click="showNewCourseModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewCourse">
          <div class="form-group">
            <label for="course-dept">Department:</label>
            <select
              id="course-dept"
              v-model="courseForm.department_id"
              class="form-input"
              required
            >
              <option
                v-for="d in setupStore.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="course-code">Course Code:</label>
            <input
              id="course-code"
              v-model="courseForm.code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., CS-420"
              required
            />
          </div>

          <div class="form-group">
            <label for="course-title">Course Title:</label>
            <input
              id="course-title"
              v-model="courseForm.title"
              type="text"
              class="form-input"
              placeholder="e.g., Information Assurance & Security"
              required
            />
          </div>

          <div class="form-group">
            <label for="course-units">Units:</label>
            <input
              id="course-units"
              v-model.number="courseForm.units"
              type="number"
              min="1"
              max="12"
              class="form-input"
              required
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewCourseModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Adding...' : 'Add Course' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Confirm Delete Section -->
    <div
      v-if="sectionToDelete"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-sec-title"
      data-testid="delete-section-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="delete-sec-title">Confirm Section Deletion</h2>
          <button type="button" class="modal-close-btn" @click="sectionToDelete = null">&times;</button>
        </header>

        <div class="setup-modal-body">
          <p>
            Are you sure you want to delete section
            <strong>{{ sectionToDelete.section_code }}</strong>
            for course <strong>{{ sectionToDelete.course_code }}</strong>?
          </p>
          <p class="modal-warning-text">
            Sections with submitted student evaluations cannot be deleted to preserve cryptographic audit trails.
          </p>
        </div>

        <footer class="setup-modal-footer">
          <button type="button" class="btn-secondary" @click="sectionToDelete = null">Cancel</button>
          <button
            type="button"
            class="btn-danger"
            :disabled="setupStore.actionBusy"
            data-testid="confirm-delete-section-btn"
            @click="executeDeleteSection"
          >
            {{ setupStore.actionBusy ? 'Deleting...' : 'Delete Section' }}
          </button>
        </footer>
      </div>
    </div>

    <!-- MODAL: Import Academic Roster (CSV / JSON) -->
    <div
      v-if="showImportRosterModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="import-roster-modal-title"
      data-testid="import-roster-modal"
    >
      <div class="setup-modal-box setup-modal-lg">
        <header class="setup-modal-header">
          <h2 id="import-roster-modal-title">Import Academic Roster</h2>
          <button type="button" class="modal-close-btn" @click="showImportRosterModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitRosterImport">
          <div class="setup-modal-body">
            <p style="font-size: 0.88rem; color: #4b5563; margin-bottom: 0.75rem;">
              Atomically ingest student enrollments, course offerings, and section faculty assignments for the selected academic term.
            </p>

            <div class="form-group">
              <label>Select Format:</label>
              <div style="display: flex; gap: 1.25rem; margin-top: 0.35rem;">
                <label style="display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;">
                  <input v-model="rosterImportFormat" type="radio" value="json" />
                  JSON Format
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;">
                  <input v-model="rosterImportFormat" type="radio" value="csv" />
                  CSV Format
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-top: 0.85rem;">
              <label for="roster-import-data">
                {{ rosterImportFormat === 'json' ? 'JSON Array of Student Enrollment Rows:' : 'CSV Rows (student_id, course_code, course_title, instructor_name, department_name):' }}
              </label>
              <textarea
                id="roster-import-data"
                v-model="rosterImportContent"
                rows="8"
                style="width: 100%; font-family: monospace; font-size: 0.82rem; padding: 0.65rem; border: 1px solid var(--line-soft); border-radius: 6px; box-sizing: border-box;"
                data-testid="academic-setup-roster-textarea"
                required
              ></textarea>
            </div>
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showImportRosterModal = false">Cancel</button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="rosterImporting"
              data-testid="submit-setup-roster-import-btn"
            >
              {{ rosterImporting ? 'Importing Roster...' : 'Import Roster Now' }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </main>
</template>

<style scoped>
.setup-workspace {
  max-width: 1360px;
  margin: 0 auto;
  padding: 1.5rem;
  color: #1f2937;
  font-family: 'Public Sans', system-ui, sans-serif;
}

.setup-header {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.setup-header-main {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.setup-kicker {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
  color: #0b3322;
}

.setup-title {
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.85rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0.2rem 0 0.4rem 0;
}

.setup-subtitle {
  font-size: 0.95rem;
  color: #4b5563;
  margin: 0;
  max-width: 720px;
}

.setup-term-switcher {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #f9f9f8;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 0.4rem 0.75rem;
}

.term-picker-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.term-picker-select {
  border: none;
  background: transparent;
  font-size: 0.88rem;
  font-weight: 600;
  color: #0b3322;
  outline: none;
  cursor: pointer;
}

/* Feedback Alerts */
.feedback-alert {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
}

.feedback-alert.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.feedback-alert.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Readiness Bar */
.readiness-bar-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.15rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.readiness-status-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.readiness-badge-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.readiness-chip {
  padding: 0.3rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.readiness-chip.ready {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.readiness-chip.incomplete {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

.window-chip {
  padding: 0.3rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  background: #f3f4f6;
  color: #4b5563;
}

.window-chip.open {
  background: #ecfdf5;
  color: #047857;
}

.window-chip.closed {
  background: #f3f4f6;
  color: #6b7280;
}

.window-chip.suspended {
  background: #fef2f2;
  color: #b91c1c;
}

.readiness-metrics-row {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.metric-item {
  display: flex;
  flex-direction: column;
}

.metric-value {
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.1;
}

.metric-label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
  font-weight: 600;
}

.text-emerald {
  color: #0b3322 !important;
}

.text-amber {
  color: #d97706 !important;
}

.readiness-warnings-box {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 6px;
  font-size: 0.85rem;
}

.warnings-title {
  color: #92400e;
  font-size: 0.82rem;
}

.warnings-list {
  margin: 0.4rem 0 0 1.25rem;
  padding: 0;
  color: #78350f;
}

/* Tabs */
.setup-tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.setup-tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.1rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 0.9rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.setup-tab-btn:hover {
  color: #0b3322;
}

.setup-tab-btn.active {
  color: #0b3322;
  border-bottom-color: #0b3322;
}

/* Pane Controls */
.pane-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.search-filter-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.table-search-input {
  width: 280px;
  padding: 0.45rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.85rem;
  outline: none;
}

.filter-select {
  padding: 0.45rem 0.65rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.85rem;
  background: #ffffff;
  outline: none;
}

.pane-actions-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.pane-description {
  font-size: 0.9rem;
  color: #4b5563;
  margin: 0;
}

/* Tables */
.table-responsive-wrapper {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #ffffff;
}

.setup-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  text-align: left;
}

.setup-data-table th {
  background: #f9f9f8;
  padding: 0.75rem 1rem;
  font-weight: 600;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #4b5563;
  border-bottom: 1px solid #e5e7eb;
}

.setup-data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.setup-data-table tbody tr:hover {
  background: #fafafa;
}

.row-unassigned {
  background: #fffdf5;
}

.row-active-term {
  background: #f0fdf4;
}

.course-code-cell {
  display: flex;
  flex-direction: column;
}

.course-title-sub {
  font-size: 0.78rem;
  color: #6b7280;
}

.section-tag {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  background: #f3f4f6;
  border-radius: 4px;
  font-family: monospace;
  font-weight: 600;
  font-size: 0.82rem;
}

.dept-badge {
  font-size: 0.75rem;
  font-weight: 600;
  color: #4b5563;
}

.faculty-select {
  padding: 0.35rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.84rem;
  background: #ffffff;
  outline: none;
  max-width: 260px;
}

.enrolled-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
  font-size: 0.8rem;
  background: #e5e7eb;
  color: #374151;
}

.enrolled-badge.zero {
  background: #fee2e2;
  color: #b91c1c;
}

.vault-count-badge {
  display: inline-block;
  font-weight: 600;
  font-size: 0.85rem;
  color: #0b3322;
}

.lock-badge {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.lock-badge.locked {
  background: #fef2f2;
  color: #991b1b;
}

.lock-badge.unlocked {
  background: #ecfdf5;
  color: #065f46;
}

.lock-cell-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
}

.btn-lock-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.btn-lock-toggle.locked {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}

.btn-lock-toggle.locked:hover {
  background: #fee2e2;
  border-color: #f87171;
}

.btn-lock-toggle.unlocked {
  background: #f3f4f6;
  color: #4b5563;
  border-color: #d1d5db;
}

.btn-lock-toggle.unlocked:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.active-status-badge {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.active-status-badge.active {
  background: #ecfdf5;
  color: #047857;
}

.active-status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.window-status-pill {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.window-status-pill.open {
  background: #ecfdf5;
  color: #065f46;
}

.window-status-pill.closed {
  background: #f3f4f6;
  color: #6b7280;
}

.window-status-pill.suspended {
  background: #fef2f2;
  color: #991b1b;
}

.date-range {
  font-size: 0.82rem;
  font-family: monospace;
  color: #4b5563;
}

.text-muted {
  color: #9ca3af;
  font-size: 0.82rem;
}

.text-active-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #047857;
}

.empty-state-cell {
  text-align: center;
  padding: 2.5rem !important;
  color: #6b7280;
}

/* Buttons */
.btn-primary {
  background: #0b3322;
  color: #ffffff;
  padding: 0.45rem 0.95rem;
  border: none;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background: #14532d;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #ffffff;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.45rem 0.95rem;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.btn-secondary:hover {
  background: #f9f9f8;
  border-color: #9ca3af;
}

.btn-secondary-sm {
  background: #ffffff;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-secondary-sm:hover {
  background: #f3f4f6;
}

.btn-danger-sm {
  background: transparent;
  color: #dc2626;
  border: 1px solid #fecaca;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger-sm:hover:not(:disabled) {
  background: #fee2e2;
}

.btn-danger-sm:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  border-color: #e5e7eb;
  color: #9ca3af;
}

.btn-danger {
  background: #dc2626;
  color: #ffffff;
  padding: 0.45rem 0.95rem;
  border: none;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger:hover {
  background: #b91c1c;
}

.text-link-btn {
  background: transparent;
  border: none;
  color: #0b3322;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  text-decoration: underline;
}

/* Questionnaire Preview */
.questionnaire-header-banner {
  background: #f9f9f8;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.1rem 1.25rem;
  margin-bottom: 1.5rem;
}

.questionnaire-badge-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.4rem;
  flex-wrap: wrap;
}

.accreditation-seal {
  font-size: 0.8rem;
  font-weight: 700;
  color: #0b3322;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.scale-legend {
  font-size: 0.8rem;
  color: #4b5563;
  font-weight: 600;
}

.questionnaire-note {
  font-size: 0.85rem;
  color: #6b7280;
  margin: 0;
}

.clusters-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.cluster-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.cluster-card-header {
  background: #fdfdfd;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cluster-card-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0;
}

.cluster-card-desc {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0.2rem 0 0 0;
}

.cluster-items-count {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
}

.cluster-items-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.cluster-question-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid #f9fafb;
}

.cluster-question-item:last-child {
  border-bottom: none;
}

.item-number {
  font-family: monospace;
  font-weight: 700;
  font-size: 0.82rem;
  color: #0b3322;
  width: 32px;
  flex-shrink: 0;
}

.item-text {
  flex: 1;
  margin: 0;
  font-size: 0.88rem;
  color: #374151;
}

.item-scale-points {
  display: flex;
  gap: 0.4rem;
  flex-shrink: 0;
}

.scale-pt {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
}

.qualitative-items-list {
  padding: 1rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.qualitative-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.qualitative-label {
  font-size: 0.88rem;
  color: #111827;
}

.qualitative-desc {
  font-size: 0.82rem;
  color: #6b7280;
  margin: 0;
}

.qualitative-subfields {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.35rem;
  flex-wrap: wrap;
}

.subfield-pill {
  background: #f3f4f6;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #374151;
  font-weight: 500;
}

/* Modal */
.setup-modal-scrim {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99;
  padding: 1rem;
}

.setup-modal-box {
  background: #ffffff;
  border-radius: 10px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.setup-modal-box.setup-modal-lg {
  max-width: 660px !important;
}

.setup-modal-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.setup-modal-header h2 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
  color: #6b7280;
}

.setup-modal-form {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.setup-modal-body {
  padding: 1.25rem;
  font-size: 0.9rem;
  color: #374151;
}

.modal-warning-text {
  font-size: 0.82rem;
  color: #991b1b;
  margin-top: 0.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.label-with-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-group label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.88rem;
  outline: none;
}

.form-input:focus {
  border-color: #0b3322;
}

.form-checkbox-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #374151;
}

.setup-modal-footer {
  padding: 0.85rem 1.25rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  background: #f9f9f8;
}

.font-mono {
  font-family: monospace;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}
</style>
