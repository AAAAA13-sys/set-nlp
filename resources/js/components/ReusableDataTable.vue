<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { debounce } from '../utils/uxHelpers.js';
import SkeletonLoader from './SkeletonLoader.vue';

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
  rowKey: {
    type: String,
    default: 'id',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  selectable: {
    type: Boolean,
    default: false,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  searchPlaceholder: {
    type: String,
    default: 'Search records (press / to focus)…',
  },
  searchFields: {
    type: Array,
    default: () => [],
  },
  facets: {
    type: Array,
    default: () => [],
  },
  pageSize: {
    type: Number,
    default: 10,
  },
  emptyTitle: {
    type: String,
    default: 'No matching institutional records',
  },
  emptySubtitle: {
    type: String,
    default: 'Adjust your search query or reset active filter facets.',
  },
  tableLabel: {
    type: String,
    default: 'Institutional Data Table',
  },
});

const emit = defineEmits(['selection-change', 'bulk-action']);

const authStore = useAuthStore();
const rawSearch = ref('');
const debouncedQuery = ref('');
const activeFacet = ref('ALL');
const sortKey = ref('');
const sortDir = ref('asc');
const currentPage = ref(1);
const selectedKeys = ref([]);
const searchInputRef = ref(null);

const density = computed(() => authStore.uiPreferences?.density || 'comfortable');

const applyDebouncedSearch = debounce((val) => {
  debouncedQuery.value = String(val || '').trim().toLowerCase();
  currentPage.value = 1;
}, 250);

function onSearchInput(event) {
  rawSearch.value = event.target.value;
  applyDebouncedSearch(rawSearch.value);
}

function toggleDensity() {
  const next = density.value === 'compact' ? 'comfortable' : 'compact';
  authStore.updateUiPreferences({ density: next });
}

function selectFacet(facetValue) {
  activeFacet.value = facetValue;
  currentPage.value = 1;
}

function toggleSort(col) {
  if (!col.sortable) return;
  if (sortKey.value === col.key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = col.key;
    sortDir.value = 'asc';
  }
}

function getAriaSort(col) {
  if (!col.sortable) return undefined;
  if (sortKey.value !== col.key) return 'none';
  return sortDir.value === 'asc' ? 'ascending' : 'descending';
}

const filteredAndSortedRows = computed(() => {
  let list = Array.isArray(props.rows) ? [...props.rows] : [];

  if (activeFacet.value !== 'ALL') {
    const matchedFacet = props.facets.find((f) => f.value === activeFacet.value);
    if (matchedFacet && typeof matchedFacet.predicate === 'function') {
      list = list.filter((row) => matchedFacet.predicate(row));
    }
  }

  if (debouncedQuery.value) {
    const keysToInspect =
      props.searchFields.length > 0
        ? props.searchFields
        : props.columns.map((c) => c.key);
    list = list.filter((row) =>
      keysToInspect.some((k) =>
        String(row?.[k] ?? '')
          .toLowerCase()
          .includes(debouncedQuery.value),
      ),
    );
  }

  if (sortKey.value) {
    const key = sortKey.value;
    const factor = sortDir.value === 'asc' ? 1 : -1;
    list.sort((a, b) => {
      const va = a?.[key];
      const vb = b?.[key];
      if (typeof va === 'number' && typeof vb === 'number') {
        return (va - vb) * factor;
      }
      return String(va ?? '').localeCompare(String(vb ?? ''), undefined, { numeric: true }) * factor;
    });
  }

  return list;
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredAndSortedRows.value.length / Math.max(1, props.pageSize))),
);

const paginatedRows = computed(() => {
  const safePage = Math.min(currentPage.value, totalPages.value);
  const start = (safePage - 1) * props.pageSize;
  return filteredAndSortedRows.value.slice(start, start + props.pageSize);
});

const allVisibleSelected = computed(() => {
  if (paginatedRows.value.length === 0) return false;
  return paginatedRows.value.every((r) => selectedKeys.value.includes(r[props.rowKey]));
});

function toggleSelectAllVisible() {
  if (allVisibleSelected.value) {
    const visibleSet = new Set(paginatedRows.value.map((r) => r[props.rowKey]));
    selectedKeys.value = selectedKeys.value.filter((k) => !visibleSet.has(k));
  } else {
    const next = new Set(selectedKeys.value);
    for (const r of paginatedRows.value) {
      next.add(r[props.rowKey]);
    }
    selectedKeys.value = Array.from(next);
  }
  emit('selection-change', [...selectedKeys.value]);
}

function toggleRowSelection(keyVal) {
  if (selectedKeys.value.includes(keyVal)) {
    selectedKeys.value = selectedKeys.value.filter((k) => k !== keyVal);
  } else {
    selectedKeys.value = [...selectedKeys.value, keyVal];
  }
  emit('selection-change', [...selectedKeys.value]);
}

function clearSelection() {
  selectedKeys.value = [];
  emit('selection-change', []);
}

function resetFilters() {
  rawSearch.value = '';
  debouncedQuery.value = '';
  activeFacet.value = 'ALL';
  currentPage.value = 1;
}

function handleSlashShortcut(event) {
  if (!props.searchable || event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
    return;
  }
  const tag = String(event.target?.tagName || '').toLowerCase();
  if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) {
    return;
  }
  event.preventDefault();
  searchInputRef.value?.focus?.();
}

watch(
  () => props.rows,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value;
    }
  },
);

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleSlashShortcut);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleSlashShortcut);
  }
});

defineExpose({
  clearSelection,
  selectedKeys,
});
</script>

<template>
  <div
    class="pro-table-container"
    :class="`density-${density}`"
    data-testid="reusable-data-table"
  >
    <div v-if="searchable || facets.length > 0" class="pro-table-toolbar">
      <div v-if="searchable" class="pro-search-box">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          ref="searchInputRef"
          :value="rawSearch"
          type="search"
          class="pro-search-input"
          :placeholder="searchPlaceholder"
          :aria-label="searchPlaceholder"
          data-testid="table-search-input"
          @input="onSearchInput"
        />
      </div>

      <div class="pro-toolbar-right">
        <div v-if="facets.length > 0" class="pro-facet-pills" role="group" aria-label="Filter table facets">
          <button
            type="button"
            class="pro-facet-btn"
            :class="{ 'is-active': activeFacet === 'ALL' }"
            data-testid="table-facet-all"
            @click="selectFacet('ALL')"
          >
            All ({{ rows.length }})
          </button>
          <button
            v-for="facet in facets"
            :key="facet.value"
            type="button"
            class="pro-facet-btn"
            :class="{ 'is-active': activeFacet === facet.value }"
            :data-testid="`table-facet-${String(facet.value).toLowerCase()}`"
            @click="selectFacet(facet.value)"
          >
            {{ facet.label }}
          </button>
        </div>

        <button
          type="button"
          class="pro-density-btn"
          data-testid="table-density-toggle"
          :title="`Switch to ${density === 'compact' ? 'comfortable' : 'compact'} row density`"
          @click="toggleDensity"
        >
          {{ density === 'compact' ? 'Compact' : 'Comfortable' }}
        </button>
      </div>
    </div>

    <div
      v-if="selectable && selectedKeys.length > 0"
      class="pro-bulk-bar"
      role="region"
      aria-label="Bulk selection actions"
      data-testid="table-bulk-action-bar"
    >
      <div class="pro-bulk-summary tabular-nums">
        <strong>{{ selectedKeys.length }}</strong> selected
        <button type="button" class="pro-bulk-clear" @click="clearSelection">Clear</button>
      </div>
      <div class="pro-bulk-actions">
        <slot name="bulk-actions" :selected-keys="selectedKeys" :clear-selection="clearSelection"></slot>
      </div>
    </div>

    <SkeletonLoader v-if="loading" :rows="4" :compact="density === 'compact'" />

    <div v-else-if="filteredAndSortedRows.length > 0" class="pro-table-scroll">
      <table class="pro-data-table tabular-nums" :aria-label="tableLabel">
        <thead>
          <tr>
            <th v-if="selectable" class="pro-col-check">
              <input
                type="checkbox"
                :checked="allVisibleSelected"
                aria-label="Select all visible rows"
                data-testid="table-select-all-checkbox"
                @change="toggleSelectAllVisible"
              />
            </th>
            <th
              v-for="col in columns"
              :key="col.key"
              :aria-sort="getAriaSort(col)"
              :class="[{ 'is-sortable': col.sortable }, col.align ? `text-${col.align}` : '']"
            >
              <button
                v-if="col.sortable"
                type="button"
                class="pro-sort-trigger"
                :data-testid="`table-sort-${col.key}`"
                @click="toggleSort(col)"
              >
                <span>{{ col.label }}</span>
                <span class="pro-sort-indicator" aria-hidden="true">
                  {{ sortKey === col.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                </span>
              </button>
              <span v-else>{{ col.label }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in paginatedRows"
            :key="row[rowKey]"
            :class="{ 'is-selected': selectable && selectedKeys.includes(row[rowKey]) }"
          >
            <td v-if="selectable" class="pro-col-check">
              <input
                type="checkbox"
                :checked="selectedKeys.includes(row[rowKey])"
                :aria-label="`Select row ${row[rowKey]}`"
                :data-testid="`table-row-check-${row[rowKey]}`"
                @change="toggleRowSelection(row[rowKey])"
              />
            </td>
            <td
              v-for="col in columns"
              :key="col.key"
              :class="col.align ? `text-${col.align}` : ''"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                {{ row[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="totalPages > 1" class="pro-pagination tabular-nums" data-testid="table-pagination">
        <span class="pro-page-summary">
          Showing {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredAndSortedRows.length) }}
          of {{ filteredAndSortedRows.length }}
        </span>
        <div class="pro-page-buttons">
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="currentPage <= 1"
            @click="currentPage -= 1"
          >
            Previous
          </button>
          <span class="pro-page-counter">Page {{ currentPage }} / {{ totalPages }}</span>
          <button
            type="button"
            class="btn-secondary btn-xs"
            :disabled="currentPage >= totalPages"
            @click="currentPage += 1"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <div v-else class="pro-empty-state" data-testid="table-empty-state">
      <strong>{{ emptyTitle }}</strong>
      <p>{{ emptySubtitle }}</p>
      <button
        v-if="debouncedQuery || activeFacet !== 'ALL'"
        type="button"
        class="btn-secondary btn-xs"
        data-testid="table-reset-filters-btn"
        @click="resetFilters"
      >
        Reset Filters
      </button>
    </div>
  </div>
</template>

<style scoped>
.pro-table-container {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pro-table-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
}

.pro-search-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: min(280px, 100%);
  flex: 1;
  max-width: 380px;
  padding: 0 0.75rem;
  height: 36px;
  border-radius: 9px;
  border: 1px solid #c8d6ca;
  background: #ffffff;
  color: #4b5e53;
}

.pro-search-input {
  flex: 1;
  border: 0;
  outline: none;
  background: transparent;
  font-size: 0.83rem;
  color: #1f3028;
}

.pro-toolbar-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
}

.pro-facet-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.pro-facet-btn {
  padding: 0.32rem 0.68rem;
  border-radius: 999px;
  border: 1px solid #cfd9d0;
  background: #f6f8f4;
  color: #3f5247;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease;
}

.pro-facet-btn.is-active {
  background: #103c2e;
  border-color: #103c2e;
  color: #f4cd42;
}

.pro-density-btn {
  height: 32px;
  padding: 0 0.68rem;
  border-radius: 8px;
  border: 1px solid #cfd9d0;
  background: #ffffff;
  color: #1f3028;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.pro-bulk-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  padding: 0.65rem 0.95rem;
  border-radius: 10px;
  background: #103c2e;
  color: #ffffff;
}

.pro-bulk-summary {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.83rem;
}

.pro-bulk-clear {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.28);
  color: #f4cd42;
  border-radius: 6px;
  padding: 0.16rem 0.5rem;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
}

.pro-bulk-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.pro-table-scroll {
  width: 100%;
  overflow-x: auto;
  border: 1px solid #dbe3da;
  border-radius: 12px;
  background: #ffffff;
}

.pro-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
}

.pro-data-table th {
  text-align: left;
  padding: 0.72rem 0.9rem;
  background: #f6f8f4;
  border-bottom: 1px solid #dbe3da;
  font-size: 0.74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #3f5247;
  white-space: nowrap;
}

.pro-data-table td {
  padding: 0.78rem 0.9rem;
  border-bottom: 1px solid #edf2ee;
  color: #1f3028;
  vertical-align: middle;
}

.density-compact .pro-data-table th {
  padding: 0.48rem 0.72rem;
}

.density-compact .pro-data-table td {
  padding: 0.48rem 0.72rem;
  font-size: 0.8rem;
}

.pro-data-table tbody tr:last-child td {
  border-bottom: 0;
}

.pro-data-table tbody tr.is-selected {
  background: #eef6f1;
}

.pro-col-check {
  width: 38px;
  text-align: center;
}

.pro-sort-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 0;
  background: transparent;
  padding: 0;
  font: inherit;
  color: inherit;
  text-transform: inherit;
  letter-spacing: inherit;
  cursor: pointer;
}

.pro-sort-indicator {
  font-size: 0.78rem;
  color: #176347;
}

.pro-pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.65rem 0.9rem;
  border-top: 1px solid #dbe3da;
  background: #fafcf9;
  font-size: 0.78rem;
  color: #4b5e53;
}

.pro-page-buttons {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.pro-page-counter {
  font-weight: 700;
  color: #1f3028;
}

.pro-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 2rem 1rem;
  border: 1px dashed #c8d6ca;
  border-radius: 12px;
  background: #fafcf9;
  text-align: center;
  color: #4b5e53;
  font-size: 0.85rem;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}
</style>

