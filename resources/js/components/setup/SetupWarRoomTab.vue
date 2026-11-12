<template>
  <div class="space-y-8 animate-fade-in" data-testid="war-room-workspace">
    <!-- Header Ribbon -->
    <div class="bg-white border border-stone-200 rounded-xl p-6 shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
              Operations War Room
            </span>
            <span class="text-xs text-stone-500 font-mono">AAAAA13-OPS-STAGE-8</span>
          </div>
          <h2 class="text-xl font-bold text-stone-900 tracking-tight font-serif">
            Evaluation Campaign Command & Turnout Velocity
          </h2>
          <p class="text-xs text-stone-600 mt-1 max-w-2xl">
            Real-time campaign telemetry, automated 6-point pre-flight diagnostics, departmental turnout leaderboard towards the institutional &ge;85.0% threshold, and privacy-preserving reminder dispatches.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-4 py-2 text-xs font-semibold rounded-lg border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 transition-colors shadow-sm inline-flex items-center gap-1.5"
            :disabled="loading"
            data-testid="btn-refresh-war-room"
            @click="loadWarRoomData"
          >
            <span v-if="loading" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-stone-400 border-t-transparent rounded-full"></span>
            <span>{{ loading ? 'Auditing...' : 'Refresh Telemetry' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 6-Point Pre-Flight Diagnostic Audit Section -->
    <div class="bg-white border border-stone-200 rounded-xl p-6 shadow-sm" data-testid="preflight-audit-card">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-stone-100">
        <div>
          <h3 class="text-base font-bold text-stone-900 flex items-center gap-2">
            <span>6-Point Pre-Launch Diagnostic Audit</span>
            <span
              class="px-2 py-0.5 text-xs font-semibold rounded-full"
              :class="{
                'bg-emerald-100 text-emerald-800 border border-emerald-300': audit.overall_verdict === 'READY_FOR_LAUNCH',
                'bg-amber-100 text-amber-800 border border-amber-300': audit.overall_verdict === 'ACTION_REQUIRED',
                'bg-rose-100 text-rose-800 border border-rose-300': audit.overall_verdict === 'BLOCKED',
              }"
              data-testid="preflight-verdict-badge"
            >
              {{ formatVerdict(audit.overall_verdict) }}
            </span>
          </h3>
          <p class="text-xs text-stone-500 mt-0.5">
            Automated institutional verification covering term window, rosters, questionnaire, locks, and NLP engines.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-right">
            <span class="text-xs text-stone-500">Readiness Score</span>
            <div class="text-lg font-bold text-stone-900 font-mono" data-testid="readiness-score-text">
              {{ audit.readiness_score_pct || 0 }}%
            </div>
          </div>
          <div class="w-24 bg-stone-100 rounded-full h-2.5 overflow-hidden">
            <div
              class="h-2.5 rounded-full transition-all duration-500"
              :class="{
                'bg-emerald-600': (audit.readiness_score_pct || 0) >= 80,
                'bg-amber-500': (audit.readiness_score_pct || 0) >= 50 && (audit.readiness_score_pct || 0) < 80,
                'bg-rose-500': (audit.readiness_score_pct || 0) < 50,
              }"
              :style="{ width: `${audit.readiness_score_pct || 0}%` }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Audit Checks Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" data-testid="audit-checks-grid">
        <div
          v-for="(chk, key) in audit.checks"
          :key="key"
          class="border rounded-lg p-4 transition-all"
          :class="{
            'bg-emerald-50/40 border-emerald-200': chk.status === 'PASS',
            'bg-amber-50/40 border-amber-200': chk.status === 'WARN',
            'bg-rose-50/40 border-rose-200': chk.status === 'FAIL',
          }"
          :data-testid="`check-card-${chk.id}`"
        >
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="font-semibold text-xs text-stone-900 flex items-center gap-1.5">
              <span class="w-5 h-5 rounded-full bg-stone-200 text-stone-700 flex items-center justify-center text-[10px] font-mono">
                {{ chk.id }}
              </span>
              <span>{{ chk.title }}</span>
            </div>
            <span
              class="px-2 py-0.5 text-[10px] font-bold tracking-wider rounded uppercase"
              :class="{
                'bg-emerald-600 text-white': chk.status === 'PASS',
                'bg-amber-500 text-white': chk.status === 'WARN',
                'bg-rose-600 text-white': chk.status === 'FAIL',
              }"
            >
              {{ chk.status }}
            </span>
          </div>
          <p class="text-xs text-stone-600 mb-2 leading-relaxed">
            {{ chk.message }}
          </p>
          <div class="text-[11px] text-stone-500 bg-white/70 rounded p-2 border border-stone-200/60 font-mono space-y-0.5">
            <div v-for="(val, k) in chk.details" :key="k" class="flex justify-between">
              <span class="text-stone-400 capitalize">{{ formatDetailKey(k) }}:</span>
              <span class="text-stone-800 font-medium">{{ val }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Live Turnout Velocity & Department Leaderboard -->
    <div class="bg-white border border-stone-200 rounded-xl p-6 shadow-sm" data-testid="turnout-leaderboard-card">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-stone-100">
        <div>
          <h3 class="text-base font-bold text-stone-900 flex items-center gap-2">
            <span>Institutional Turnout Velocity Leaderboard</span>
            <span class="text-xs font-normal text-stone-500">Target: &ge;85.0% Institutional Participation</span>
          </h3>
          <p class="text-xs text-stone-500 mt-0.5">
            Live evaluation completion velocity tracked by academic department with projected completion gap.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="px-3 py-1 text-xs font-semibold rounded-full border"
            :class="{
              'bg-emerald-50 text-emerald-800 border-emerald-300': velocity.target_status === 'TARGET_MET',
              'bg-amber-50 text-amber-800 border-amber-300': velocity.target_status !== 'TARGET_MET',
            }"
            data-testid="velocity-target-badge"
          >
            {{ velocity.target_status === 'TARGET_MET' ? 'Institutional Target Achieved' : `Gap: ${velocity.gap_pct || 0}% (${velocity.target_evaluations_needed || 0} evals needed)` }}
          </span>
        </div>
      </div>

      <!-- KPI Summary Ribbon -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg bg-stone-50 border border-stone-200">
          <span class="text-xs text-stone-500 block">Total Enrolled Evaluations</span>
          <span class="text-xl font-bold text-stone-900 font-mono" data-testid="stat-total-enrolled">
            {{ summary.total_enrolled_evaluations || 0 }}
          </span>
        </div>
        <div class="p-4 rounded-lg bg-emerald-50/50 border border-emerald-200">
          <span class="text-xs text-emerald-700 block">Completed Evaluations</span>
          <span class="text-xl font-bold text-emerald-900 font-mono" data-testid="stat-completed-evals">
            {{ summary.completed_evaluations || 0 }}
          </span>
        </div>
        <div class="p-4 rounded-lg bg-amber-50/50 border border-amber-200">
          <span class="text-xs text-amber-700 block">Pending Evaluations</span>
          <span class="text-xl font-bold text-amber-900 font-mono" data-testid="stat-pending-evals">
            {{ summary.pending_evaluations || 0 }}
          </span>
        </div>
        <div class="p-4 rounded-lg bg-stone-50 border border-stone-200">
          <span class="text-xs text-stone-500 block">Current Turnout</span>
          <div class="flex items-baseline gap-2">
            <span class="text-xl font-bold text-stone-900 font-mono" data-testid="stat-turnout-pct">
              {{ summary.turnout_pct || 0 }}%
            </span>
            <span class="text-xs text-stone-400">/ 85.0% Goal</span>
          </div>
        </div>
      </div>

      <!-- Department Leaderboard Table -->
      <div class="overflow-x-auto border border-stone-200 rounded-lg">
        <table class="w-full text-left text-xs" data-testid="departments-table">
          <thead class="bg-stone-50 border-b border-stone-200 text-stone-600 font-semibold uppercase tracking-wider text-[11px]">
            <tr>
              <th class="py-3 px-4 w-12 text-center">Rank</th>
              <th class="py-3 px-4">Department</th>
              <th class="py-3 px-4">College</th>
              <th class="py-3 px-4 text-center">Enrolled</th>
              <th class="py-3 px-4 text-center">Completed</th>
              <th class="py-3 px-4 text-center">Pending</th>
              <th class="py-3 px-4 text-center w-36">Turnout Progress</th>
              <th class="py-3 px-4 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-200">
            <tr
              v-for="dept in leaderboard"
              :key="dept.department_id"
              class="hover:bg-stone-50/60 transition-colors"
              :data-testid="`dept-row-${dept.department_id}`"
            >
              <td class="py-3 px-4 text-center font-mono font-bold text-stone-700">
                #{{ dept.rank }}
              </td>
              <td class="py-3 px-4">
                <div class="font-semibold text-stone-900">{{ dept.department_name }}</div>
                <div class="text-[11px] text-stone-400 font-mono">{{ dept.department_code }}</div>
              </td>
              <td class="py-3 px-4 text-stone-600">
                {{ dept.college_name }}
              </td>
              <td class="py-3 px-4 text-center font-mono text-stone-800">
                {{ dept.total_enrolled }}
              </td>
              <td class="py-3 px-4 text-center font-mono font-semibold text-emerald-700">
                {{ dept.completed }}
              </td>
              <td class="py-3 px-4 text-center font-mono text-amber-700">
                {{ dept.pending }}
              </td>
              <td class="py-3 px-4 text-center">
                <div class="flex items-center gap-2">
                  <div class="w-full bg-stone-100 rounded-full h-2 overflow-hidden">
                    <div
                      class="h-2 rounded-full"
                      :class="{
                        'bg-emerald-600': dept.turnout_pct >= 85,
                        'bg-amber-500': dept.turnout_pct >= 70 && dept.turnout_pct < 85,
                        'bg-rose-500': dept.turnout_pct < 70,
                      }"
                      :style="{ width: `${Math.min(100, dept.turnout_pct)}%` }"
                    ></div>
                  </div>
                  <span class="font-mono font-bold text-[11px] text-stone-900 min-w-[36px]">
                    {{ dept.turnout_pct }}%
                  </span>
                </div>
              </td>
              <td class="py-3 px-4 text-center">
                <span
                  class="px-2 py-0.5 text-[10px] font-bold rounded-full border"
                  :class="{
                    'bg-emerald-50 text-emerald-800 border-emerald-300': dept.status === 'MET',
                    'bg-amber-50 text-amber-800 border-amber-300': dept.status === 'ON_TRACK',
                    'bg-rose-50 text-rose-800 border-rose-300': dept.status === 'NEEDS_BOOST',
                  }"
                >
                  {{ dept.status }}
                </span>
              </td>
            </tr>
            <tr v-if="leaderboard.length === 0">
              <td colspan="8" class="py-6 text-center text-stone-400">
                No active department turnout data found for this term.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Non-Coercive Reminder Batches Section -->
    <div class="bg-white border border-stone-200 rounded-xl p-6 shadow-sm" data-testid="reminder-batch-card">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-4 border-b border-stone-100">
        <div>
          <h3 class="text-base font-bold text-stone-900 flex items-center gap-2">
            <span>Non-Coercive Participation Reminders</span>
            <span class="text-xs text-stone-500 font-mono">Zero Response Links</span>
          </h3>
          <p class="text-xs text-stone-600 mt-0.5 max-w-2xl">
            In compliance with the University Charter, evaluations are non-coercive and 100% anonymous. Reminders notify students of pending course evaluations for institutional follow-up without connecting responses to student identities.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-4 py-2 text-xs font-semibold rounded-lg bg-[#0f172a] hover:bg-[#0f172a] text-white transition-colors shadow-sm inline-flex items-center gap-1.5"
            :disabled="loadingReminders"
            data-testid="btn-compile-reminders"
            @click="compileReminders"
          >
            <span v-if="loadingReminders" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
            <span>{{ loadingReminders ? 'Compiling Notice...' : 'Compile Reminder Notice' }}</span>
          </button>
        </div>
      </div>

      <!-- Reminder Notice Preview -->
      <div v-if="reminderBatch.recipients && reminderBatch.recipients.length > 0" class="space-y-4" data-testid="reminder-preview-area">
        <div class="p-4 bg-stone-50 border border-stone-200 rounded-lg">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-stone-800">Subject: {{ reminderBatch.reminder_subject }}</span>
            <span class="text-xs text-stone-500 font-mono">{{ reminderBatch.total_pending_students }} Recipients ({{ reminderBatch.total_pending_evaluations }} Pending Evals)</span>
          </div>
          <div class="text-xs font-mono bg-white p-3 border border-stone-200 rounded whitespace-pre-wrap text-stone-700 leading-relaxed max-h-48 overflow-y-auto">
            {{ reminderBatch.reminder_template }}
          </div>
        </div>

        <!-- Recipients Table -->
        <div class="overflow-x-auto border border-stone-200 rounded-lg max-h-64 overflow-y-auto">
          <table class="w-full text-left text-xs" data-testid="reminder-recipients-table">
            <thead class="bg-stone-50 sticky top-0 border-b border-stone-200 text-stone-600 font-semibold text-[11px]">
              <tr>
                <th class="py-2.5 px-3">Student ID</th>
                <th class="py-2.5 px-3">Name</th>
                <th class="py-2.5 px-3">Email</th>
                <th class="py-2.5 px-3">Department</th>
                <th class="py-2.5 px-3 text-center">Enrolled</th>
                <th class="py-2.5 px-3 text-center">Completed</th>
                <th class="py-2.5 px-3 text-center">Pending</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 font-mono">
              <tr v-for="r in reminderBatch.recipients" :key="r.student_id" class="hover:bg-stone-50/50">
                <td class="py-2 px-3 text-stone-800 font-semibold">{{ r.student_id }}</td>
                <td class="py-2 px-3 font-sans text-stone-900">{{ r.student_name }}</td>
                <td class="py-2 px-3 text-stone-500">{{ r.email }}</td>
                <td class="py-2 px-3 font-sans text-stone-600">{{ r.department }}</td>
                <td class="py-2 px-3 text-center text-stone-700">{{ r.enrolled_courses }}</td>
                <td class="py-2 px-3 text-center text-emerald-700">{{ r.completed_evaluations }}</td>
                <td class="py-2 px-3 text-center text-amber-700 font-bold">{{ r.pending_evaluations }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-else class="text-center py-6 text-stone-400 text-xs">
        Click "Compile Reminder Notice" to inspect non-coercive reminder batches for pending student evaluators.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  academicTermId: {
    type: Number,
    default: null,
  },
});

const loading = ref(false);
const loadingReminders = ref(false);

const audit = ref({
  overall_verdict: 'READY_FOR_LAUNCH',
  readiness_score_pct: 100,
  total_checks: 6,
  checks: {},
});

const summary = ref({});
const velocity = ref({});
const leaderboard = ref([]);
const reminderBatch = ref({});

const formatVerdict = (v) => {
  if (v === 'READY_FOR_LAUNCH') return 'Ready for Launch';
  if (v === 'ACTION_REQUIRED') return 'Action Required';
  if (v === 'BLOCKED') return 'Launch Blocked';
  return v || 'Audited';
};

const formatDetailKey = (k) => {
  return String(k || '').replace(/_/g, ' ');
};

const loadWarRoomData = async () => {
  loading.value = true;
  try {
    const termParam = props.academicTermId ? `?academic_term_id=${props.academicTermId}` : '';
    
    // 1. Fetch pre-flight audit
    const auditRes = await fetch(`/api/admin/war-room/preflight${termParam}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${sessionStorage.getItem('evaluatech_auth_token') || ''}`,
      },
    });
    if (auditRes.ok) {
      audit.value = await auditRes.json();
    }

    // 2. Fetch turnout leaderboard
    const leadRes = await fetch(`/api/admin/war-room/leaderboard${termParam}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${sessionStorage.getItem('evaluatech_auth_token') || ''}`,
      },
    });
    if (leadRes.ok) {
      const data = await leadRes.json();
      summary.value = data.institutional_summary || {};
      velocity.value = data.velocity_projection || {};
      leaderboard.value = data.department_leaderboard || [];
    }
  } catch (err) {
    console.error('Failed to load War Room telemetry:', err);
  } finally {
    loading.value = false;
  }
};

const compileReminders = async () => {
  loadingReminders.value = true;
  try {
    const termParam = props.academicTermId ? `?academic_term_id=${props.academicTermId}` : '';
    const res = await fetch(`/api/admin/war-room/remind-batch${termParam}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${sessionStorage.getItem('evaluatech_auth_token') || ''}`,
      },
    });
    if (res.ok) {
      reminderBatch.value = await res.json();
    }
  } catch (err) {
    console.error('Failed to compile reminder batch:', err);
  } finally {
    loadingReminders.value = false;
  }
};

onMounted(() => {
  loadWarRoomData();
});
</script>
