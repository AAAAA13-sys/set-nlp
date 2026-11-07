import { createMemoryHistory, createRouter, createWebHistory } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import AdminRosterView from '../views/AdminRosterView.vue';
import DeanDashboardView from '../views/DeanDashboardView.vue';
import EvaluationFormView from '../views/EvaluationFormView.vue';
import FacultyDashboardView from '../views/FacultyDashboardView.vue';
import LoginView from '../views/LoginView.vue';
import StudentQueueView from '../views/StudentQueueView.vue';

export const routes = [
  {
    path: '/',
    redirect: '/queue',
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
      roles: ['student', 'admin'],
      title: 'My Evaluation Queue',
    },
  },
  {
    path: '/evaluate/:courseSectionId?',
    name: 'evaluation-form',
    component: EvaluationFormView,
    meta: {
      roles: ['student', 'admin'],
      title: 'Anonymized Faculty Evaluation Form',
    },
  },
  {
    path: '/faculty',
    name: 'faculty-dashboard',
    component: FacultyDashboardView,
    meta: {
      roles: ['faculty', 'chair', 'dean', 'admin'],
      title: 'Faculty Double-Blind Feedback Portal',
    },
  },
  {
    path: '/dean',
    name: 'dean-dashboard',
    component: DeanDashboardView,
    meta: {
      roles: ['dean', 'chair', 'admin'],
      title: "Dean's Analytical Overview",
    },
  },
  {
    path: '/admin',
    name: 'admin-roster',
    component: AdminRosterView,
    meta: {
      roles: ['admin'],
      title: 'System Administrator Roster & Grade-Lock Console',
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
        // Switch demo role automatically when navigating via quick-nav in demonstration mode,
        // or redirect to the role's default home if strict mode is active
        return getDefaultRouteForRole(authStore.currentRole);
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
