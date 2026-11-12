<script setup>
import AppIcon from '../AppIcon.vue';
import { computed, ref } from 'vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits([
  'new-student',
  'open-student-enrollment',
  'open-student-reminder',
  'edit-student',
]);

const setupStore = useAcademicSetupStore();

const studentSearchInput = ref('');
const studentStatusFilter = ref('ALL');
const studentDepartmentFilter = ref('ALL');

const studentTrackerMetrics = computed(() => {
  const list = setupStore.studentsList || [];
  const total = list.length;
  const cleared = list.filter((s) => s.is_complete).length;
  const pending = list.filter((s) => !s.is_complete && s.enrolled_count > 0).length;
  const clearedRate = total > 0 ? Math.round((cleared / total) * 100) : 0;
  const pendingRate = total > 0 ? Math.round((pending / total) * 100) : 0;
  return { total, cleared, pending, clearedRate, pendingRate };
});

const filteredStudentsDirectory = computed(() => {
  const query = studentSearchInput.value.trim().toLowerCase();
  const filter = studentStatusFilter.value;
  const depFilter = studentDepartmentFilter.value;
  let list = setupStore.studentsList || [];
  if (depFilter !== 'ALL') {
    list = list.filter((s) => String(s.department_id) === String(depFilter) || s.department_code === depFilter);
  }
  if (filter === 'CLEARED') {
    list = list.filter((s) => s.is_complete);
  } else if (filter === 'PENDING') {
    list = list.filter((s) => !s.is_complete);
  }
  if (!query) return list;
  return list.filter((s) => {
    const text = `${s.name || ''} ${s.institutional_id || ''} ${s.email || ''} ${s.department_name || ''} ${s.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function triggerDownload(headers, rows, baseFilename) {
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `${baseFilename}_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function exportStudentsDirectoryCsv() {
  const list = setupStore.studentsList || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Student ID', 'Student Name', 'Email', 'Department', 'Enrolled Courses', 'Cleared Evaluations', 'Clearance Status'];
  const rows = list.map((s) => [
    sanitize(s.institutional_id),
    sanitize(s.name),
    sanitize(s.email),
    sanitize(s.department_name || s.department_code || 'College of Computer Studies'),
    sanitize(s.enrolled_count),
    sanitize(s.cleared_count),
    sanitize(s.clearance_status),
  ].join(','));
  triggerDownload(headers, rows, 'AAAAA13_Student_Clearance_Roster');
}

function exportPendingStudentsCsv() {
  const list = (setupStore.studentsList || []).filter((s) => !s.is_complete && s.enrolled_count > 0);
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = [
    'Student ID',
    'Student Name',
    'Email',
    'Department',
    'Enrolled Courses',
    'Cleared Evaluations',
    'Pending Evaluations',
    'Completion Rate %',
    'Follow-Up Status',
  ];
  const rows = list.map((s) => {
    const pending = Math.max(0, (s.enrolled_count || 0) - (s.cleared_count || 0));
    const rate = s.enrolled_count > 0 ? Math.round(((s.cleared_count || 0) / s.enrolled_count) * 100) : 0;
    return [
      sanitize(s.institutional_id),
      sanitize(s.name),
      sanitize(s.email),
      sanitize(s.department_name || s.department_code || 'College of Computer Studies'),
      sanitize(s.enrolled_count),
      sanitize(s.cleared_count),
      sanitize(pending),
      sanitize(`${rate}%`),
      sanitize(pending > 0 ? 'PENDING EVALUATION' : 'CLEARED'),
    ].join(',');
  });
  triggerDownload(headers, rows, 'AAAAA13_Incomplete_Student_Evaluations_FollowUp');
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-students">
    <div class="pane-controls-bar">
      <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <input
          v-model="studentSearchInput"
          type="search"
          class="form-input search-input"
          placeholder="Search student by name, student ID, or department…"
          data-testid="student-search-input"
        />
        <select
          v-model="studentStatusFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="student-status-filter-select"
        >
          <option value="ALL">All Clearance Status</option>
          <option value="CLEARED">Cleared (100%)</option>
          <option value="PENDING">Pending Evaluations</option>
        </select>
        <select
          v-model="studentDepartmentFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="student-dept-filter-select"
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
          v-if="studentSearchInput || studentStatusFilter !== 'ALL' || studentDepartmentFilter !== 'ALL'"
          type="button"
          class="btn-secondary btn-sm"
          data-testid="student-reset-filters-btn"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          @click="studentSearchInput = ''; studentStatusFilter = 'ALL'; studentDepartmentFilter = 'ALL'"
        >
          Reset
        </button>
      </div>
      <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-pending-students-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; font-weight: 700"
          @click="exportPendingStudentsCsv"
        >
          <AppIcon name="alert" /> Export Pending Evaluators (CSV)
        </button>
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-students-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; font-weight: 600"
          @click="exportStudentsDirectoryCsv"
        >
          <AppIcon name="chart" /> Export All Students (CSV)
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="add-student-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; font-weight: 600"
          @click="emit('new-student')"
        >
          + New Student
        </button>
        <span class="token-chip chip-signed">
          Total Students: {{ setupStore.studentsList.length }}
        </span>
      </div>
    </div>

    <!-- EVALUATION PARTICIPATION TRACKER BAR -->
    <div class="tracker-summary-bar" data-testid="student-tracker-summary-bar" style="display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
      <div class="tracker-card" style="flex: 1; min-width: 140px; padding: 0.65rem 0.85rem">
        <small style="font-size: 0.72rem; font-weight: 700; display: block">Total Students</small>
        <div style="font-size: 1.25rem; font-weight: 800">{{ studentTrackerMetrics.total }}</div>
      </div>
      <div class="tracker-card" style="flex: 1; min-width: 140px; padding: 0.65rem 0.85rem">
        <small style="font-size: 0.72rem; font-weight: 700; display: block"><AppIcon name="check-circle" /> Fully Cleared</small>
        <div style="font-size: 1.25rem; font-weight: 800">{{ studentTrackerMetrics.cleared }} <span style="font-size: 0.8rem; font-weight: 600;">({{ studentTrackerMetrics.clearedRate }}%)</span></div>
      </div>
      <div class="tracker-card" style="flex: 1; min-width: 140px; padding: 0.65rem 0.85rem">
        <small style="font-size: 0.72rem; font-weight: 700; display: block"><AppIcon name="alert" /> Pending Evaluators</small>
        <div style="font-size: 1.25rem; font-weight: 800">{{ studentTrackerMetrics.pending }} <span style="font-size: 0.8rem; font-weight: 600;">({{ studentTrackerMetrics.pendingRate }}%)</span></div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="institutional-table tabular-nums" data-testid="student-directory-table">
        <thead>
          <tr>
            <th scope="col">Student ID</th>
            <th scope="col">Student Name</th>
            <th scope="col">Department / College</th>
            <th scope="col">Institutional Email</th>
            <th scope="col">Enrolled Courses</th>
            <th scope="col">Evaluation Progress</th>
            <th scope="col">Clearance Status</th>
            <th scope="col" class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="std in filteredStudentsDirectory" :key="std.id" :data-testid="`student-row-${std.id}`">
            <td><code>{{ std.institutional_id }}</code></td>
            <td><strong>{{ std.name }}</strong></td>
            <td>
              <span class="dept-badge">{{ std.department_code || 'CCS' }}</span>
              <span style="margin-left: 0.35rem; font-size: 0.8rem">{{ std.department_name }}</span>
            </td>
            <td>{{ std.email }}</td>
            <td>
              <span class="enrolled-badge">{{ std.enrolled_count }} Courses</span>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="flex: 1; height: 8px; overflow: hidden; min-width: 60px">
                  <div
                    :style="{
                      width: `${std.enrolled_count > 0 ? (std.cleared_count / std.enrolled_count) * 100 : 0}%`,
                      background: std.is_complete ? '#059669' : '#eab308',
                      height: '100%'
                    }"
                  ></div>
                </div>
                <strong style="font-size: 0.82rem">
                  {{ std.cleared_count }} / {{ std.enrolled_count }}
                </strong>
              </div>
            </td>
            <td>
              <span
                class="token-chip"
                :class="std.is_complete ? 'chip-signed' : 'chip-unsigned'"
                style="font-size: 0.75rem;"
                :data-testid="`student-clearance-status-${std.id}`"
              >
                {{ std.clearance_status }}
              </span>
            </td>
            <td class="text-right">
              <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`student-courses-btn-${std.id}`"
                  style="font-size: 0.75rem; padding: 0.25rem 0.5rem; font-weight: 600"
                  @click="emit('open-student-enrollment', std)"
                >
                  Courses <AppIcon name="book" />
                </button>
                <button
                  v-if="!std.is_complete"
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`remind-student-btn-${std.id}`"
                  style="font-size: 0.75rem; padding: 0.25rem 0.5rem; font-weight: 600"
                  @click="emit('open-student-reminder', std)"
                >
                  Remind <AppIcon name="mail" />
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`edit-student-btn-${std.id}`"
                  style="font-size: 0.75rem; padding: 0.25rem 0.5rem; font-weight: 600"
                  @click="emit('edit-student', std)"
                >
                  Edit <AppIcon name="edit" />
                </button>
                <router-link
                  v-if="std.is_complete"
                  :to="`/verify?id=${encodeURIComponent(std.institutional_id)}`"
                  class="btn-secondary btn-xs"
                  :data-testid="`student-permit-verify-btn-${std.id}`"
                  style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                >
                  Status <AppIcon name="file" />
                </router-link>
                <span v-else style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.35rem">
                  {{ Math.max(0, std.enrolled_count - std.cleared_count) }} Pending
                </span>
              </div>
            </td>
          </tr>
          <tr v-if="filteredStudentsDirectory.length === 0" data-testid="students-empty-state">
            <td colspan="8" style="text-align: center; padding: 2rem">
              No student records match your search criteria for this academic term.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
