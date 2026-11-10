<script setup>
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits([
  'import-roster',
  'new-course',
  'new-section',
  'faculty-assignment-change',
  'toggle-registrar-lock',
  'open-section-roster',
  'open-section-report',
  'delete-section',
]);

const setupStore = useAcademicSetupStore();

function handleAssignmentChange(courseSectionId, event) {
  emit('faculty-assignment-change', {
    courseSectionId,
    facultyId: event.target.value ? Number(event.target.value) : null,
  });
}

function exportAssignmentsMatrixCsv() {
  const list = setupStore.sections || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Course Code', 'Course Title', 'Section', 'Assigned Instructor', 'Enrolled Students', 'Status', 'Registrar Lock'];
  const rows = list.map((sec) => [
    sanitize(sec.course_code),
    sanitize(sec.course_title),
    sanitize(sec.section_code || 'SEC-A'),
    sanitize(sec.instructor_name || 'Unassigned'),
    sanitize(sec.enrolled_count ?? 35),
    sanitize(sec.is_assigned ? 'Assigned' : 'Unassigned'),
    sanitize(sec.registrar_lock ? 'Locked' : 'Open'),
  ].join(','));
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `PLP_Faculty_Assignments_Matrix_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-assignments">
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

        <select
          v-model="setupStore.filterSectionCode"
          class="filter-select"
          data-testid="class-filter-select"
        >
          <option value="ALL">All Sections / Classes</option>
          <option
            v-for="c in setupStore.uniqueSectionCodes"
            :key="c"
            :value="c"
          >
            Section: {{ c }}
          </option>
        </select>

        <button
          v-if="setupStore.filterSearch || setupStore.filterDepartmentId !== 'ALL' || setupStore.filterAssignment !== 'ALL' || setupStore.filterSectionCode !== 'ALL'"
          type="button"
          class="btn-secondary btn-sm"
          data-testid="assignments-reset-filters-btn"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          @click="setupStore.filterSearch = ''; setupStore.filterDepartmentId = 'ALL'; setupStore.filterAssignment = 'ALL'; setupStore.filterSectionCode = 'ALL'"
        >
          Reset
        </button>
      </div>

      <div class="pane-actions-group">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-assignments-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
          @click="exportAssignmentsMatrixCsv"
        >
          📊 Export Assignments (CSV)
        </button>
        <button
          type="button"
          class="btn-secondary"
          data-testid="import-roster-btn"
          @click="emit('import-roster')"
        >
          + Import Roster
        </button>
        <button
          type="button"
          class="btn-secondary"
          data-testid="add-course-btn"
          @click="emit('new-course')"
        >
          + New Course
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="add-section-btn"
          @click="emit('new-section')"
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
                  @change="handleAssignmentChange(sec.course_section_id, $event)"
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
                  @click="emit('toggle-registrar-lock', sec)"
                >
                  {{ sec.registrar_lock ? '🔒 Locked' : '🔓 Inactive' }}
                </button>
              </div>
            </td>
            <td class="text-right">
              <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                <button
                  type="button"
                  class="btn-secondary-sm"
                  style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #103c2e; background: #ecfdf5; color: #103c2e; font-weight: 700;"
                  :data-testid="`setup-section-roster-btn-${sec.course_code}`"
                  title="View section student roster and clearance checklist"
                  @click="emit('open-section-roster', sec)"
                >
                  Roster 👥
                </button>
                <button
                  v-if="sec.vault_count > 0 || sec.cleared_count > 0"
                  type="button"
                  class="btn-secondary-sm"
                  style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #103c2e; background: #f0fdf4; color: #103c2e; font-weight: 700;"
                  :data-testid="`setup-view-report-btn-${sec.course_code}`"
                  title="View section evaluation breakdown report"
                  @click="emit('open-section-report', sec)"
                >
                  Report 📊
                </button>
                <button
                  type="button"
                  class="btn-danger-sm"
                  :disabled="sec.vault_count > 0 || sec.cleared_count > 0"
                  :title="sec.vault_count > 0 ? 'Cannot delete section with submitted student evaluations' : 'Delete section'"
                  :data-testid="`delete-section-btn-${sec.course_section_id}`"
                  @click="emit('delete-section', sec)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="setupStore.filteredSections.length === 0" data-testid="assignments-empty-state">
            <td colspan="8" class="empty-state-cell">
              No course sections match your filters.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
