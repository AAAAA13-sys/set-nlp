<script setup>
import { computed, ref } from 'vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits([
  'new-faculty',
  'open-faculty-dossier',
  'view-faculty-assignments',
  'quick-assign-faculty',
  'edit-faculty',
]);

const setupStore = useAcademicSetupStore();

const facultySearchInput = ref('');
const facultyDepartmentFilter = ref('ALL');

const filteredFacultyDirectory = computed(() => {
  const query = facultySearchInput.value.trim().toLowerCase();
  const depFilter = facultyDepartmentFilter.value;
  let list = setupStore.facultyList || [];
  if (depFilter !== 'ALL') {
    list = list.filter((f) => String(f.department_id) === String(depFilter) || f.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((f) => {
    const text = `${f.name || ''} ${f.institutional_id || ''} ${f.email || ''} ${f.department_name || ''} ${f.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function getFacultyAssignedSectionsCount(facultyId) {
  return (setupStore.sections || []).filter((s) => Number(s.faculty_id) === Number(facultyId)).length;
}

function quickAssignFaculty(faculty) {
  setupStore.filterAssignment = 'ALL';
  setupStore.filterSearch = faculty.name || '';
  emit('quick-assign-faculty', faculty);
}

function exportFacultyDirectoryCsv() {
  const list = setupStore.facultyList || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Institutional ID', 'Faculty Name', 'Department', 'Email', 'Role', 'Assigned Sections'];
  const rows = list.map((f) => [
    sanitize(f.institutional_id),
    sanitize(f.name),
    sanitize(f.department_name || f.department_code || 'College of Computer Studies'),
    sanitize(f.email),
    sanitize(f.role),
    sanitize(getFacultyAssignedSectionsCount(f.id)),
  ].join(','));
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `PLP_Faculty_Directory_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-faculty">
    <div class="pane-controls-bar">
      <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
        <input
          v-model="facultySearchInput"
          type="search"
          class="form-input search-input"
          placeholder="Search faculty by name, employee ID, or department…"
          data-testid="faculty-search-input"
        />
        <select
          v-model="facultyDepartmentFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="faculty-dept-filter-select"
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
        <button
          v-if="facultySearchInput || facultyDepartmentFilter !== 'ALL'"
          type="button"
          class="btn-secondary btn-sm"
          data-testid="faculty-reset-filters-btn"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          @click="facultySearchInput = ''; facultyDepartmentFilter = 'ALL'"
        >
          Reset
        </button>
      </div>
      <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center;">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-faculty-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
          @click="exportFacultyDirectoryCsv"
        >
          📊 Export Faculty (CSV)
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="add-faculty-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 600;"
          @click="emit('new-faculty')"
        >
          + New Faculty
        </button>
        <span class="token-chip chip-signed">
          Total Instructors: {{ setupStore.facultyList.length }}
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="institutional-table tabular-nums" data-testid="faculty-directory-table">
        <thead>
          <tr>
            <th scope="col">Institutional ID</th>
            <th scope="col">Faculty Member Name</th>
            <th scope="col">Department</th>
            <th scope="col">Institutional Email</th>
            <th scope="col">Academic Role</th>
            <th scope="col">Assigned Sections</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fac in filteredFacultyDirectory" :key="fac.id" :data-testid="`faculty-row-${fac.id}`">
            <td><code>{{ fac.institutional_id }}</code></td>
            <td><strong>{{ fac.name }}</strong></td>
            <td>
              <span class="dept-badge">{{ fac.department_code || 'CCS' }}</span>
              <span style="margin-left: 0.35rem; font-size: 0.8rem; color: #4b5563;">{{ fac.department_name }}</span>
            </td>
            <td>{{ fac.email }}</td>
            <td>
              <span class="role-pill" style="font-size: 0.75rem; text-transform: uppercase;">
                {{ fac.role }}
              </span>
            </td>
            <td>
              <span
                class="token-chip"
                :class="getFacultyAssignedSectionsCount(fac.id) > 0 ? 'chip-signed' : 'chip-unsigned'"
              >
                {{ getFacultyAssignedSectionsCount(fac.id) }} Sections
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`faculty-dossier-report-btn-${fac.id}`"
                  style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                  @click="emit('open-faculty-dossier', fac)"
                >
                  Report 📊
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`view-faculty-assignments-btn-${fac.id}`"
                  style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                  @click="emit('view-faculty-assignments', fac)"
                >
                  Assignments 📚
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`assign-faculty-shortcut-btn-${fac.id}`"
                  @click="quickAssignFaculty(fac)"
                >
                  Sections →
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`edit-faculty-btn-${fac.id}`"
                  style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                  @click="emit('edit-faculty', fac)"
                >
                  Edit ✏️
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredFacultyDirectory.length === 0" data-testid="faculty-empty-state">
            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
              No faculty members match your search criteria.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
