<script setup>
import { computed, ref } from 'vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits(['new-user', 'edit-user', 'toggle-user-status']);

const setupStore = useAcademicSetupStore();

const usersSearchInput = ref('');
const usersRoleFilter = ref('ALL');
const usersDepartmentFilter = ref('ALL');

const filteredSystemUsers = computed(() => {
  const query = usersSearchInput.value.trim().toLowerCase();
  const role = usersRoleFilter.value;
  const depFilter = usersDepartmentFilter.value;
  let list = setupStore.systemUsers || [];
  if (role !== 'ALL') {
    list = list.filter((u) => u.role === role);
  }
  if (depFilter !== 'ALL') {
    list = list.filter((u) => String(u.department_id) === String(depFilter) || u.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((u) => {
    const text = `${u.name || ''} ${u.institutional_id || ''} ${u.email || ''} ${u.role || ''} ${u.department_name || ''} ${u.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function exportSystemUsersCsv() {
  const list = setupStore.systemUsers || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Institutional ID', 'Full Name', 'Email', 'System Role', 'Department', 'Account Status'];
  const rows = list.map((u) => [
    sanitize(u.institutional_id),
    sanitize(u.name),
    sanitize(u.email),
    sanitize(u.role),
    sanitize(u.department_name || u.department_code || 'College of Computer Studies'),
    sanitize(u.is_active ? 'Active' : 'Inactive'),
  ].join(','));
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `PLP_System_Users_Directory_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-users">
    <div class="pane-controls-bar">
      <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <input
          v-model="usersSearchInput"
          type="search"
          class="form-input search-input"
          placeholder="Search user by name, ID, email, or role…"
          data-testid="users-search-input"
        />
        <select
          v-model="usersRoleFilter"
          class="form-input"
          style="width: auto; min-width: 190px;"
          data-testid="users-role-filter-select"
        >
          <option value="ALL">All System Roles ({{ setupStore.systemUsers.length }})</option>
          <option value="admin">Administrators (Registrar)</option>
          <option value="dean">College Deans</option>
          <option value="chair">Department Chairs</option>
          <option value="faculty">Faculty Members</option>
          <option value="student">Enrolled Students</option>
        </select>
        <select
          v-model="usersDepartmentFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="users-dept-filter-select"
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
          v-if="usersSearchInput || usersRoleFilter !== 'ALL' || usersDepartmentFilter !== 'ALL'"
          type="button"
          class="btn-secondary btn-sm"
          data-testid="users-reset-filters-btn"
          style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
          @click="usersSearchInput = ''; usersRoleFilter = 'ALL'; usersDepartmentFilter = 'ALL'"
        >
          Reset
        </button>
      </div>
      <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center;">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-users-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
          @click="exportSystemUsersCsv"
        >
          📊 Export Users (CSV)
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="add-user-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 600;"
          @click="emit('new-user')"
        >
          + New User Account
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="setup-table" data-testid="system-users-table">
        <thead>
          <tr>
            <th scope="col">User</th>
            <th scope="col">Institutional ID</th>
            <th scope="col">Email Address</th>
            <th scope="col">System Role</th>
            <th scope="col">Department / College</th>
            <th scope="col">Account Status</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="u in filteredSystemUsers"
            :key="u.id"
            :data-testid="`user-row-${u.institutional_id || u.id}`"
          >
            <td>
              <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div
                  style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.78rem; color: #ffffff;"
                  :style="{
                    background: u.role === 'admin' ? '#7c2d12' : (u.role === 'dean' ? '#1e3a8a' : (u.role === 'chair' ? '#065f46' : (u.role === 'faculty' ? '#103c2e' : '#4b5563')))
                  }"
                >
                  {{ (u.name || 'U').charAt(0).toUpperCase() }}
                </div>
                <div>
                  <strong style="color: #111827; font-size: 0.88rem;">{{ u.name }}</strong>
                </div>
              </div>
            </td>
            <td>
              <code style="font-family: monospace; font-size: 0.82rem; background: #f3f4f6; padding: 0.15rem 0.4rem; border-radius: 4px;">{{ u.institutional_id || 'N/A' }}</code>
            </td>
            <td>
              <span style="font-size: 0.82rem; color: #4b5563;">{{ u.email }}</span>
            </td>
            <td>
              <span
                class="status-badge"
                :style="{
                  fontSize: '0.72rem',
                  padding: '0.2rem 0.55rem',
                  textTransform: 'uppercase',
                  fontWeight: 700,
                  background: u.role === 'admin' ? '#fee2e2' : (u.role === 'dean' ? '#dbeafe' : (u.role === 'chair' ? '#d1fae5' : (u.role === 'faculty' ? '#e8f5e9' : '#f3f4f6'))),
                  color: u.role === 'admin' ? '#991b1b' : (u.role === 'dean' ? '#1e40af' : (u.role === 'chair' ? '#065f46' : (u.role === 'faculty' ? '#1b5e20' : '#374151'))),
                }"
                :data-testid="`user-role-badge-${u.role}`"
              >
                {{ u.role }}
              </span>
            </td>
            <td>
              <span style="font-size: 0.82rem; color: #374151;">{{ u.department_name }}</span>
              <div style="font-size: 0.72rem; color: #6b7280;">({{ u.department_code }})</div>
            </td>
            <td>
              <span
                class="status-badge"
                :class="u.is_active ? 'status-completed' : 'status-pending'"
                style="font-size: 0.72rem; padding: 0.2rem 0.55rem;"
                :data-testid="`user-status-badge-${u.id}`"
              >
                {{ u.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`edit-user-btn-${u.id}`"
                  style="font-weight: 600;"
                  @click="emit('edit-user', u)"
                >
                  Edit ✏️
                </button>
                <button
                  type="button"
                  class="btn-secondary btn-xs"
                  :data-testid="`toggle-user-status-btn-${u.id}`"
                  :style="{
                    fontWeight: 600,
                    color: u.is_active ? '#991b1b' : '#065f46',
                    borderColor: u.is_active ? '#fca5a5' : '#6ee7b7'
                  }"
                  @click="emit('toggle-user-status', u)"
                >
                  {{ u.is_active ? 'Deactivate ⏸️' : 'Activate ▶️' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredSystemUsers.length === 0" data-testid="users-empty-state">
            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
              No system user accounts match the specified search or role filter.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
