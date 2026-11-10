import { createMemoryHistory, createRouter, createWebHistory } from 'vue-router';
import { getDefaultRouteForRole, useAuthStore } from '../stores/auth.js';
import { sanitizeRedirectPath } from '../utils/uxHelpers.js';
import AccessDeniedView from '../views/AccessDeniedView.vue';
import AdminRosterView from '../views/AdminRosterView.vue';
import AdminSetupView from '../views/AdminSetupView.vue';
import ChairDashboardView from '../views/ChairDashboardView.vue';
import DeanDashboardView from '../views/DeanDashboardView.vue';
import EvaluationAdvocacyView from '../views/EvaluationAdvocacyView.vue';
import EvaluationFormView from '../views/EvaluationFormView.vue';
import FacultyDashboardView from '../views/FacultyDashboardView.vue';
import LandingView from '../views/LandingView.vue';
import LoginView from '../views/LoginView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import PermitVerifyView from '../views/PermitVerifyView.vue';
import StudentQueueView from '../views/StudentQueueView.vue';

export const routes = [
  {
    path: '/',
    name: 'landing',
    component: LandingView,
    meta: { public: true, title: 'EvaluaTech — Automated Faculty Evaluation System | Pamantasan ng Lungsod ng Pasig' },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { public: true, guestOnly: true, title: 'Institutional Sign In — Pamantasan ng Lungsod ng Pasig' },
  },
  {
    path: '/queue',
    name: 'student-queue',
    component: StudentQueueView,
    meta: {
      roles: ['student'],
      title: 'My Evaluation Queue — PLP Student Portal',
    },
  },
  {
    path: '/evaluate/:courseSectionId?',
    name: 'evaluation-form',
    component: EvaluationFormView,
    meta: {
      roles: ['student'],
      title: 'Anonymized Faculty Evaluation — PLP Student Portal',
    },
  },
  {
    path: '/faculty',
    name: 'faculty-dashboard',
    component: FacultyDashboardView,
    meta: {
      roles: ['faculty'],
      title: 'Faculty Double-Blind Feedback Portal — PLP',
    },
  },
  {
    path: '/chair',
    name: 'chair-dashboard',
    component: ChairDashboardView,
    meta: {
      roles: ['chair'],
      title: 'Department Chair Diagnostic Workspace — PLP',
    },
  },
  {
    path: '/dean',
    name: 'dean-dashboard',
    component: DeanDashboardView,
    meta: {
      roles: ['dean'],
      title: "Dean's Analytical Overview — PLP",
    },
  },
  {
    path: '/admin',
    name: 'admin-roster',
    component: AdminRosterView,
    meta: {
      roles: ['admin'],
      title: 'University Registrar & System Administration Console — PLP',
    },
  },
  {
    path: '/admin/setup',
    name: 'admin-setup',
    component: AdminSetupView,
    meta: {
      roles: ['admin'],
      title: 'Academic Setup & Evaluation Questionnaire — PLP',
    },
  },
  {
    path: '/advocacy',
    name: 'campus-advocacy',
    component: EvaluationAdvocacyView,
    meta: {
      roles: ['faculty', 'chair', 'dean', 'admin'],
      title: 'Campus SET Advocacy & Standards Hub — PLP',
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
  {
    path: '/verify',
    alias: '/permit/verify',
    name: 'permit-verify',
    component: PermitVerifyView,
    meta: {
      public: true,
      title: 'Evaluation Clearance Verification — Pamantasan ng Lungsod ng Pasig',
    },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
    meta: {
      public: true,
      title: '404 Page Not Found — Pamantasan ng Lungsod ng Pasig',
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
    scrollBehavior(to, from, savedPosition) {
      if (savedPosition) {
        return savedPosition;
      }
      return { top: 0, left: 0 };
    },
  });

  routerInstance.beforeEach(async (to) => {
    try {
      const authStore = useAuthStore();
      if (authStore.token && !authStore.sessionChecked) {
        await authStore.hydrateSession();
      }

      if (to.meta?.guestOnly && authStore.isAuthenticated) {
        return { path: getDefaultRouteForRole(authStore.currentRole) };
      }

      if (to.meta?.public) {
        return true;
      }

      if (!authStore.isAuthenticated) {
        const safeRedirect = sanitizeRedirectPath(to.fullPath, '');
        return {
          path: '/login',
          query: safeRedirect && safeRedirect !== '/' ? { redirect: safeRedirect } : {},
        };
      }

      const allowedRoles = to.meta?.roles || [];
      if (allowedRoles.length > 0 && !canAccessRoute(authStore.currentRole, allowedRoles)) {
        if (authStore.currentRole === 'student') {
          return { path: '/queue' };
        }
        return {
          path: '/access-denied',
          query: {
            attempted: sanitizeRedirectPath(to.path, '/'),
            required_roles: allowedRoles.join(', '),
            current_role: authStore.currentRole,
          },
        };
      }
    } catch (_) {
      // Allow isolated route table inspection when Pinia is not mounted
    }
    return true;
  });

  routerInstance.afterEach((to) => {
    if (typeof document !== 'undefined' && to.meta?.title) {
      document.title = `${to.meta.title} | EvaluaTech`;
    }
  });

  return routerInstance;
}

const router = createEvaluaTechRouter();
export default router;
