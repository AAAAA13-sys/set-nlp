import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { clearAllSurveyDrafts, readUiPreferences, saveUiPreferences } from '../utils/uxHelpers.js';

const AUTH_TOKEN_KEY = 'evaluatech_auth_token';
const AUTH_USER_KEY = 'evaluatech_auth_user';

function readStoredSession() {
  if (typeof window === 'undefined') {
    return { token: null, user: null };
  }
  try {
    const sessionToken = window.sessionStorage?.getItem(AUTH_TOKEN_KEY);
    const sessionUserRaw = window.sessionStorage?.getItem(AUTH_USER_KEY);
    if (sessionToken && sessionUserRaw) {
      return { token: sessionToken, user: JSON.parse(sessionUserRaw) };
    }

    const localToken = window.localStorage?.getItem(AUTH_TOKEN_KEY);
    const localUserRaw = window.localStorage?.getItem(AUTH_USER_KEY);
    if (localToken && localUserRaw) {
      return { token: localToken, user: JSON.parse(localUserRaw) };
    }
  } catch (_) {
    // Ignore corrupted storage entries
  }
  return { token: null, user: null };
}

function persistStoredSession(tokenValue, userValue, remember = false) {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    const targetStorage = remember ? window.localStorage : window.sessionStorage;
    const otherStorage = remember ? window.sessionStorage : window.localStorage;
    otherStorage?.removeItem(AUTH_TOKEN_KEY);
    otherStorage?.removeItem(AUTH_USER_KEY);
    if (tokenValue && userValue) {
      targetStorage?.setItem(AUTH_TOKEN_KEY, tokenValue);
      targetStorage?.setItem(AUTH_USER_KEY, JSON.stringify(userValue));
    } else {
      targetStorage?.removeItem(AUTH_TOKEN_KEY);
      targetStorage?.removeItem(AUTH_USER_KEY);
    }
  } catch (_) {
    // Ignore storage quota errors
  }
}

function clearStoredSession() {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    window.localStorage?.removeItem(AUTH_TOKEN_KEY);
    window.localStorage?.removeItem(AUTH_USER_KEY);
    window.sessionStorage?.removeItem(AUTH_TOKEN_KEY);
    window.sessionStorage?.removeItem(AUTH_USER_KEY);
  } catch (_) {
    // Ignore storage access errors
  }
}

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
  const stored = readStoredSession();
  const user = ref(stored.user);
  const token = ref(stored.token);
  const activeTerm = ref(stored.user?.active_term || '1st Semester AY 2026-2027');
  const loading = ref(false);
  const sessionChecked = ref(false);
  const error = ref(null);
  const uiPreferences = ref(readUiPreferences());

  function updateUiPreferences(patch = {}) {
    uiPreferences.value = saveUiPreferences(patch);
    return uiPreferences.value;
  }

  const isAuthenticated = computed(() => Boolean(user.value && user.value.role && token.value));
  const currentRole = computed(() => user.value?.role ?? null);
  const displayIdentifier = computed(() => {
    if (!user.value) return '';
    const roleTitle = user.value.role_label || user.value.role;
    const displayName = user.value.name ? ` • ${user.value.name}` : '';
    return `${user.value.institutional_id}${displayName} (${roleTitle})`;
  });

  function authHeaders(extraHeaders = {}) {
    const headers = {
      Accept: 'application/json',
      ...extraHeaders,
    };
    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`;
    }
    return headers;
  }

  function clearSession() {
    user.value = null;
    token.value = null;
    error.value = null;
    clearStoredSession();
    clearAllSurveyDrafts();
  }

  async function hydrateSession() {
    if (!token.value) {
      user.value = null;
      sessionChecked.value = true;
      return null;
    }

    try {
      const response = await fetch('/api/auth/me', {
        method: 'GET',
        headers: authHeaders(),
      });

      if (response.ok) {
        const payload = await response.json();
        if (payload?.authenticated && payload?.user) {
          user.value = payload.user;
          if (payload.user.active_term) {
            activeTerm.value = payload.user.active_term;
          }
          const inLocal =
            typeof window !== 'undefined' && Boolean(window.localStorage?.getItem(AUTH_TOKEN_KEY));
          persistStoredSession(token.value, user.value, inLocal);
          sessionChecked.value = true;
          return user.value;
        }
      }

      if (response.status === 401 || response.status === 403) {
        clearSession();
      }
    } catch (_) {
      // Preserve existing token if network is momentarily unreachable during hydration
    }

    sessionChecked.value = true;
    return user.value;
  }

  async function login(credentials = {}) {
    loading.value = true;
    error.value = null;

    const identifier = String(
      credentials.identifier || credentials.institutional_id || credentials.email || '',
    ).trim();
    const password = String(credentials.password || '');
    // Security #38: Default to ephemeral sessionStorage unless user explicitly opts into persistent storage
    const remember = credentials.remember === true;

    if (!identifier || !password) {
      loading.value = false;
      error.value = 'Please enter both your Institutional ID (or email) and passphrase.';
      throw new Error(error.value);
    }

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          identifier,
          institutional_id: identifier,
          password,
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload?.authenticated || !payload?.token || !payload?.user) {
        clearSession();
        const msg =
          payload?.message ||
          'Invalid institutional credentials. Verify your Institutional ID / university email and passphrase.';
        error.value = msg;
        throw new Error(msg);
      }

      user.value = payload.user;
      token.value = payload.token;
      sessionChecked.value = true;
      if (payload.user.active_term) {
        activeTerm.value = payload.user.active_term;
      }
      persistStoredSession(payload.token, payload.user, remember);
      return user.value;
    } catch (err) {
      if (!error.value) {
        error.value =
          err?.message ||
          'Unable to reach the institutional authentication service. Please check your connection.';
      }
      throw new Error(error.value);
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    const activeHeaders = authHeaders();
    clearSession();
    sessionChecked.value = true;

    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: activeHeaders,
      });
    } catch (_) {
      // Ignore network errors after local revocation
    }
  }

  return {
    user,
    token,
    activeTerm,
    loading,
    sessionChecked,
    error,
    uiPreferences,
    isAuthenticated,
    currentRole,
    displayIdentifier,
    authHeaders,
    updateUiPreferences,
    hydrateSession,
    clearSession,
    login,
    logout,
    getDefaultRouteForRole,
  };
});
