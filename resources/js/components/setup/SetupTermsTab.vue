<script setup>
import { computed, ref } from 'vue';
import { useAcademicSetupStore } from '../../stores/academicSetup.js';

const emit = defineEmits(['new-term', 'edit-term', 'set-active-term', 'delete-term']);

const setupStore = useAcademicSetupStore();

const termSearchInput = ref('');
const termStatusFilter = ref('ALL');

const filteredTerms = computed(() => {
  const q = termSearchInput.value.trim().toLowerCase();
  return (setupStore.terms || []).filter((t) => {
    if (termStatusFilter.value === 'ACTIVE' && !t.is_active) {
      return false;
    }
    if (termStatusFilter.value === 'ARCHIVED' && t.is_active) {
      return false;
    }
    if (['OPEN', 'CLOSED', 'EXTENDED', 'REOPENED'].includes(termStatusFilter.value)) {
      if ((t.window_status || '').toUpperCase() !== termStatusFilter.value) {
        return false;
      }
    }
    if (!q) return true;
    const haystack = `${t.code || ''} ${t.name || ''} ${t.academic_year || ''} ${t.window_status || ''}`.toLowerCase();
    return haystack.includes(q);
  });
});

function exportAcademicTermsCsv() {
  const list = filteredTerms.value;
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Term Code', 'Term Name', 'Academic Year', 'Starts On', 'Ends On', 'Evaluation Window Status', 'System Status'];
  const rows = list.map((t) => [
    sanitize(t.code),
    sanitize(t.name),
    sanitize(t.academic_year),
    sanitize(t.starts_on || 'Not scheduled'),
    sanitize(t.ends_on || 'Not scheduled'),
    sanitize(t.window_status || 'CLOSED'),
    sanitize(t.is_active ? 'Active Term' : 'Archived'),
  ].join(','));
  const csv = [headers.join(','), ...rows].join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `AAAAA13_Academic_Terms_${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section class="tab-pane" data-testid="pane-terms">
    <div class="pane-controls-bar">
      <div class="pane-description">
        <p>
          Manage academic years and semester cycles. The <strong>Active System Term</strong> determines which courses appear in student evaluation queues.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button
          type="button"
          class="btn-secondary"
          data-testid="export-terms-csv-btn"
          style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
          @click="exportAcademicTermsCsv"
        >
          📊 Export Terms (CSV)
        </button>
        <button
          type="button"
          class="btn-primary"
          data-testid="new-term-btn"
          @click="emit('new-term')"
        >
          + New Academic Term
        </button>
      </div>
    </div>

    <div class="filter-controls-row" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.75rem;">
      <input
        type="search"
        v-model="termSearchInput"
        class="form-input search-input"
        placeholder="Search term code, name, academic year…"
        data-testid="terms-search-input"
        style="flex: 1; min-width: 220px;"
        aria-label="Search academic terms"
      />
      <select
        v-model="termStatusFilter"
        class="form-input"
        style="width: auto; min-width: 170px;"
        data-testid="terms-status-filter-select"
        aria-label="Filter terms by status"
      >
        <option value="ALL">All Statuses</option>
        <option value="ACTIVE">⭐ Active Term</option>
        <option value="ARCHIVED">📦 Archived</option>
        <option value="OPEN">🟢 Open Window</option>
        <option value="EXTENDED">🟡 Extended Window</option>
        <option value="REOPENED">🔵 Reopened Window</option>
        <option value="CLOSED">🔴 Closed Window</option>
      </select>
      <button
        v-if="termSearchInput || termStatusFilter !== 'ALL'"
        type="button"
        class="btn-secondary-sm"
        data-testid="terms-reset-filters-btn"
        @click="termSearchInput = ''; termStatusFilter = 'ALL';"
      >
        Reset
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
            v-for="t in filteredTerms"
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
                  @click="emit('edit-term', t)"
                >
                  Edit
                </button>
                <button
                  v-if="!t.is_active"
                  type="button"
                  class="btn-secondary-sm"
                  :data-testid="`set-active-term-btn-${t.id}`"
                  @click="emit('set-active-term', t.id)"
                >
                  Set as Active
                </button>
                <span v-else class="text-active-label">Current Active</span>
                <button
                  v-if="!t.is_active"
                  type="button"
                  class="btn-secondary-sm text-danger"
                  :data-testid="`delete-term-btn-${t.id}`"
                  @click="emit('delete-term', t)"
                >
                  Delete 🗑️
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredTerms.length === 0" data-testid="terms-empty-state">
            <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">
              <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                No Academic Terms Match Filter
              </div>
              <p style="margin: 0; font-size: 0.85rem;">
                Try adjusting your search query or reset the status filter to view all academic terms.
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
