import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const DEMO_ROLE_PROFILES = {
  student: {
    id: 1,
    institutional_id: '2024-10892',
    name: 'Student 2024-10892',
    email: '2024-10892@evaluatech.edu.ph',
    role: 'student',
    role_label: 'Student',
    department_id: 1,
    department_name: 'College of Computer Studies',
    active_term: 'Fall / 1st Semester',
  },
  faculty: {
    id: 2,
    institutional_id: 'FAC-2019-042',
    name: 'Prof. A. Dela Cruz',
    email: 'adelacruz@evaluatech.edu.ph',
    role: 'faculty',
    role_label: 'Faculty',
    department_id: 1,
    department_name: 'College of Computer Studies',
    active_term: 'Fall / 1st Semester',
  },
  chair: {
    id: 3,
    institutional_id: 'CHR-2017-011',
    name: 'Dr. M. Santos',
    email: 'msantos.chair@evaluatech.edu.ph',
    role: 'chair',
    role_label: 'Department Chair',
    department_id: 1,
    department_name: 'College of Computer Studies',
    active_term: 'Fall / 1st Semester',
  },
  dean: {
    id: 4,
    institutional_id: 'DEAN-2015-001',
    name: 'Dr. V. Mendoza',
    email: 'dean.ccs@evaluatech.edu.ph',
    role: 'dean',
    role_label: 'Dean',
    department_id: 1,
    department_name: 'College of Computer Studies',
    active_term: 'Fall / 1st Semester',
  },
  admin: {
    id: 5,
    institutional_id: 'REG-2012-001',
    name: 'University Registrar',
    email: 'registrar@evaluatech.edu.ph',
    role: 'admin',
    role_label: 'System Administrator',
    department_id: null,
    department_name: 'Office of the University Registrar',
    active_term: 'Fall / 1st Semester',
  },
};

export function getDefaultRouteForRole(role) {
  switch (String(role || '').toLowerCase()) {
    case 'student':
      return '/queue';
    case 'faculty':
      return '/faculty';
    case 'chair':
      return '/chair';
    case 'dean':
      return '/dean';
    case 'admin':
      return '/admin';
    default:
      return '/login';
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref({ ...DEMO_ROLE_PROFILES.student });
  const token = ref('demo-session-token');
  const activeTerm = ref('Fall / 1st Semester');
  const loading = ref(false);
  const error = ref(null);

  const isAuthenticated = computed(() => Boolean(user.value && user.value.role));
  const currentRole = computed(() => user.value?.role ?? 'student');
  const displayIdentifier = computed(() => {
    if (!user.value) return 'Guest';
    const roleTitle = user.value.role_label || user.value.role;
    return `${user.value.institutional_id} (${roleTitle})`;
  });

  async function login(credentials = {}) {
    loading.value = true;
    error.value = null;
    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(credentials),
      });

      if (response.ok) {
        const payload = await response.json();
        const resolvedUser = payload.user || payload.data?.user;
        if (resolvedUser) {
          user.value = {
            ...DEMO_ROLE_PROFILES[resolvedUser.role || 'student'],
            ...resolvedUser,
          };
          token.value = payload.token || 'session-authenticated';
          return user.value;
        }
      }
    } catch (_) {
      // Fallback to deterministic demo role session when offline or in local UI test mode
    } finally {
      loading.value = false;
    }

    const requestedRole = String(credentials.role || 'student').toLowerCase();
    const profile = DEMO_ROLE_PROFILES[requestedRole] || DEMO_ROLE_PROFILES.student;
    user.value = {
      ...profile,
      institutional_id: credentials.institutional_id || credentials.email || profile.institutional_id,
    };
    token.value = `session-${user.value.role}`;
    return user.value;
  }

  function switchDemoRole(role) {
    const normalized = String(role || 'student').toLowerCase();
    if (DEMO_ROLE_PROFILES[normalized]) {
      user.value = { ...DEMO_ROLE_PROFILES[normalized] };
      token.value = `session-${normalized}`;
      error.value = null;
    }
    return user.value;
  }

  async function logout() {
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });
    } catch (_) {
      // Ignore network errors on logout
    }
    user.value = null;
    token.value = null;
  }

  return {
    user,
    token,
    activeTerm,
    loading,
    error,
    isAuthenticated,
    currentRole,
    displayIdentifier,
    login,
    switchDemoRole,
    logout,
    getDefaultRouteForRole,
  };
});
