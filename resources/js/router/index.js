import { createMemoryHistory, createRouter, createWebHistory } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import AccessDeniedView from '../views/AccessDeniedView.vue';
import AdminRosterView from '../views/AdminRosterView.vue';
import ChairDashboardView from '../views/ChairDashboardView.vue';
import DeanDashboardView from '../views/DeanDashboardView.vue';
import EvaluationFormView from '../views/EvaluationFormView.vue';
import FacultyDashboardView from '../views/FacultyDashboardView.vue';
import LoginView from '../views/LoginView.vue';
import StudentQueueView from '../views/StudentQueueView.vue';

export const routes = [
  {
    path: '/',
    redirect: (to) => {
      try {
        const authStore = useAuthStore();
        return getDefaultRouteForRole(authStore.currentRole);
      } catch (_) {
        return '/queue';
      }
    },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { public: true, title: 'Institutional Sign In' },
  },
  {
    path: '/queue',
    name: 'student-queue',
    component: StudentQueueView,
    meta: {
      roles: ['student'],
      title: 'My Evaluation Queue (Student Workspace)',
    },
  },
  {
    path: '/evaluate/:courseSectionId?',
    name: 'evaluation-form',
    component: EvaluationFormView,
    meta: {
      roles: ['student'],
      title: 'Anonymized Faculty Evaluation Form (Student Workspace)',
    },
  },
  {
    path: '/faculty',
    name: 'faculty-dashboard',
    component: FacultyDashboardView,
    meta: {
      roles: ['faculty'],
      title: 'Faculty Double-Blind Feedback Portal (Faculty Workspace)',
    },
  },
  {
    path: '/chair',
    name: 'chair-dashboard',
    component: ChairDashboardView,
    meta: {
      roles: ['chair'],
      title: 'Department Chair Diagnostic Workspace (Chair Workspace)',
    },
  },
  {
    path: '/dean',
    name: 'dean-dashboard',
    component: DeanDashboardView,
    meta: {
      roles: ['dean'],
      title: "Dean's Analytical Overview (Dean Workspace)",
    },
  },
  {
    path: '/admin',
    name: 'admin-roster',
    component: AdminRosterView,
    meta: {
      roles: ['admin'],
      title: 'System Administrator Roster & Grade-Lock Console (Admin Workspace)',
    },
  },
  {
    path: '/access-denied',
    name: 'access-denied',
    component: AccessDeniedView,
    meta: {
      public: true,
      title: '403 Forbidden — Institutional Role Access Restricted',
    },
  },
];

/**
 * Verifies whether a user role is authorized for a route's allowed roles list.
 * @param {string} userRole
 * @param {string[]} [allowedRoles]
 * @returns {boolean}
 */
export function canAccessRoute(userRole, allowedRoles = []) {
  if (!Array.isArray(allowedRoles) || allowedRoles.length === 0) {
    return true;
  }
  const normalized = String(userRole || '').toLowerCase();
  return allowedRoles.map((r) => String(r).toLowerCase()).includes(normalized);
}

export function createEvaluaTechRouter(history) {
  const resolvedHistory =
    history || (typeof window !== 'undefined' ? createWebHistory() : createMemoryHistory());

  const routerInstance = createRouter({
    history: resolvedHistory,
    routes,
  });

  routerInstance.beforeEach((to) => {
    if (to.meta?.public) {
      return true;
    }
    try {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) {
        return { path: '/login' };
      }
      const allowedRoles = to.meta?.roles || [];
      if (allowedRoles.length > 0 && !canAccessRoute(authStore.currentRole, allowedRoles)) {
        return {
          path: '/access-denied',
          query: {
            attempted: to.path,
            required_roles: allowedRoles.join(', '),
            current_role: authStore.currentRole,
          },
        };
      }
    } catch (_) {
      // Allow navigation if Pinia is not yet initialized in isolated route inspection
    }
    return true;
  });

  return routerInstance;
}

const router = createEvaluaTechRouter();
export default router;
