<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

/**
 * Shows Registrar-issued one-time passwords exactly once. They are never stored by the
 * server in plain text, so the administrator must download or hand them out now.
 */
const props = defineProps({
  credentials: { type: Array, default: () => [] },
});
const emit = defineEmits(['dismiss']);

const rows = computed(() => (Array.isArray(props.credentials) ? props.credentials : []));

// Neutralise spreadsheet formula injection (=, +, -, @) the same way server exports do.
function csvCell(value) {
  let text = String(value ?? '');
  if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;
  return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function downloadCsv() {
  const header = ['institutional_id', 'name', 'email', 'role', 'temporary_password'];
  const lines = [header.join(',')].concat(rows.value.map((r) => header.map((k) => csvCell(r[k])).join(',')));
  const blob = new Blob([`${lines.join('\n')}\n`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `evaluatech-one-time-passwords-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
</script>

<template>
  <section
    v-if="rows.length > 0"
    class="issued-credentials"
    role="region"
    aria-labelledby="issued-credentials-heading"
    data-testid="issued-credentials-panel"
  >
    <div class="alert-banner alert-warning">
      <AppIcon name="lock" />
      <div>
        <h3 id="issued-credentials-heading" class="issued-title">
          {{ rows.length }} new account{{ rows.length === 1 ? '' : 's' }}: one-time passwords
        </h3>
        <p class="issued-note">
          These passwords are shown only now and cannot be recovered later. Give each person their own
          password; they must replace it the first time they sign in.
        </p>
      </div>
    </div>

    <div class="issued-table-wrap">
      <table class="issued-table">
        <caption class="sr-only">One-time passwords for newly created accounts</caption>
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col">Name</th>
            <th scope="col">Role</th>
            <th scope="col">One-time password</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cred in rows" :key="cred.institutional_id" data-testid="issued-credential-row">
            <td>{{ cred.institutional_id }}</td>
            <td>{{ cred.name }}</td>
            <td>{{ cred.role }}</td>
            <td><code class="issued-secret">{{ cred.temporary_password }}</code></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="issued-actions">
      <button type="button" class="btn-primary" data-testid="download-credentials-btn" @click="downloadCsv">
        <AppIcon name="download" /> Download as CSV
      </button>
      <button type="button" class="btn-secondary" data-testid="dismiss-credentials-btn" @click="emit('dismiss')">
        I have saved these
      </button>
    </div>
  </section>
</template>

<style scoped>
.issued-credentials {
  margin-top: 1rem;
  display: grid;
  gap: 0.75rem;
}
.issued-title {
  margin: 0 0 0.25rem;
  font-size: 1rem;
  font-weight: 700;
}
.issued-note {
  margin: 0;
}
.issued-table-wrap {
  overflow-x: auto;
  max-height: 22rem;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
}
.issued-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.issued-table th,
.issued-table td {
  padding: 0.5rem 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--line-soft);
  white-space: nowrap;
}
.issued-table th {
  position: sticky;
  top: 0;
  background: var(--surface-subtle);
  color: var(--ink);
}
.issued-secret {
  font-family: var(--font-mono);
  font-size: 0.95rem;
  color: var(--ink);
}
.issued-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.issued-actions .btn-primary,
.issued-actions .btn-secondary {
  min-height: 44px;
}
</style>
