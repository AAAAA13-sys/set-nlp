<script setup>
import { computed, ref } from 'vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits([
  'new-course',
  'open-course-sections',
  'add-section-for-course',
  'edit-course',
  'delete-course',
]);

const setupStore = useAcademicSetupStore();

const courseSearchInput = ref('');
const courseDepartmentFilter = ref('ALL');

const filteredCoursesCatalog = computed(() => {
  const query = courseSearchInput.value.trim().toLowerCase();
  const depFilter = courseDepartmentFilter.value;
  let list = setupStore.courses || [];
  if (depFilter !== 'ALL') {
    list = list.filter((c) => String(c.department_id) === String(depFilter) || c.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((c) => {
    const text = `${c.code || ''} ${c.title || ''} ${c.department_code || ''} ${c.department_name || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function getCourseSections(courseId) {
  return (setupStore.sections || []).filter((s) => Number(s.course_id) === Number(courseId));
}

function exportCoursesCatalogCsv() {
  const coursesList = setupStore.courses || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Course Code', 'Course Title', 'Department', 'Units'];
  const rows = coursesList.map((c) => [
    sanitize(c.code),
    sanitize(c.title),
    sanitize(c.department_name || c.department_code || 'College of Computer Studies'),
    sanitize(c.units ?? 3),
  ].join(','));
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `AAAAA13_Course_Catalog_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-courses">
    <div class="pane-controls-bar">
      <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
        <input
          v-model="courseSearchInput"
          type="search"
          class="form-input search-input"
          placeholder="Search course code, title, or department…"
          data-testid="course-search-input"
        />
        <select
          v-model="courseDepartmentFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="course-dept-filter-select"
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
          v-if="courseSearchInput || courseDepartmentFilter !== 'ALL'"
          type="button"
          class="btn-secondary btn-sm"
          data-testid="courses-reset-filters-btn"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          @click="courseSearchInput = ''; courseDepartmentFilter = 'ALL'"
        >
          Reset
        </button>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-courses-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
          @click="exportCoursesCatalogCsv"
        >
          📊 Export Catalog (CSV)
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="catalog-new-course-btn"
          @click="emit('new-course')"
        >
          + New Course Offering
        </button>
      </div>
    </div>

    <div class="table-responsive-wrapper">
      <table class="setup-data-table" data-testid="courses-table">
        <thead>
          <tr>
            <th scope="col">Code</th>
            <th scope="col">Course Title</th>
            <th scope="col">Academic Department</th>
            <th scope="col" class="text-center">Units</th>
            <th scope="col" class="text-center">Active Offerings</th>
            <th scope="col" class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="c in filteredCoursesCatalog"
            :key="c.id"
            :data-testid="`course-row-${c.id}`"
          >
            <td>
              <strong class="font-mono text-emerald">{{ c.code }}</strong>
            </td>
            <td>{{ c.title }}</td>
            <td>
              <span class="dept-badge">{{ c.department_code || 'CCS' }}</span>
              <span style="font-size: 0.82rem; color: #6b7280; margin-left: 0.4rem;">{{ c.department_name }}</span>
            </td>
            <td class="text-center">
              <span class="enrolled-badge">{{ c.units }} Units</span>
            </td>
            <td class="text-center">
              <button
                type="button"
                class="token-chip"
                :class="getCourseSections(c.id).length > 0 ? 'chip-signed' : 'chip-unsigned'"
                style="cursor: pointer; border: none; font-size: 0.78rem;"
                :data-testid="`course-sections-btn-${c.id}`"
                @click="emit('open-course-sections', c)"
              >
                {{ getCourseSections(c.id).length }} Sections 📋
              </button>
            </td>
            <td class="text-right">
              <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                <button
                  type="button"
                  class="btn-secondary-sm"
                  :data-testid="`add-section-for-course-btn-${c.id}`"
                  @click="emit('add-section-for-course', c)"
                >
                  + Add Section
                </button>
                <button
                  type="button"
                  class="btn-secondary-sm"
                  :data-testid="`edit-course-btn-${c.id}`"
                  @click="emit('edit-course', c)"
                >
                  Edit ✏️
                </button>
                <button
                  type="button"
                  class="btn-secondary-sm text-danger"
                  :data-testid="`delete-course-btn-${c.id}`"
                  @click="emit('delete-course', c)"
                >
                  Delete 🗑️
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredCoursesCatalog.length === 0" data-testid="courses-empty-state">
            <td colspan="6" class="empty-state-cell">
              No course offerings match your search criteria.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
