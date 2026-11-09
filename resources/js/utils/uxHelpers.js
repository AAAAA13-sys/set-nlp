/**
 * EvaluaTech Production SaaS UX & Security Helpers
 * Covers debouncing (#7), throttling (#8), open-redirect sanitization (Security #18),
 * ephemeral sessionStorage survey draft autosave (#6), and institutional tier indicators (#14).
 */

const DRAFT_PREFIX = 'evaluatech_draft_sec_';
const UI_PREFS_KEY = 'evaluatech_ui_prefs';

/**
 * Debounce function execution until `delayMs` has elapsed since the last invocation (#7).
 * @template {(...args: any[]) => any} T
 * @param {T} fn
 * @param {number} [delayMs=250]
 * @returns {(...args: Parameters<T>) => void}
 */
export function debounce(fn, delayMs = 250) {
  let timer = null;
  return function debounced(...args) {
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(() => {
      timer = null;
      fn(...args);
    }, delayMs);
  };
}

/**
 * Throttle function execution to at most once every `intervalMs` (#8).
 * @template {(...args: any[]) => any} T
 * @param {T} fn
 * @param {number} [intervalMs=600]
 * @returns {(...args: Parameters<T>) => ReturnType<T> | undefined}
 */
export function throttle(fn, intervalMs = 600) {
  let lastInvokedAt = 0;
  return function throttled(...args) {
    const now = Date.now();
    if (now - lastInvokedAt < intervalMs) {
      return undefined;
    }
    lastInvokedAt = now;
    return fn(...args);
  };
}

/**
 * Strictly sanitize post-login redirect paths to internal single-slash routes only (Security #18 Open Redirects).
 * Rejects protocol-relative URLs (//evil.com), external schemes (http:, javascript:, data:), backslashes, and /login loops.
 * @param {unknown} rawRedirect
 * @param {string} [fallback='/']
 * @returns {string}
 */
export function sanitizeRedirectPath(rawRedirect, fallback = '/') {
  if (typeof rawRedirect !== 'string') {
    return fallback;
  }
  const trimmed = rawRedirect.trim();
  if (
    !trimmed ||
    !trimmed.startsWith('/') ||
    trimmed.startsWith('//') ||
    trimmed.startsWith('/\\') ||
    trimmed.includes('\\') ||
    trimmed.includes('://') ||
    /^\s*(javascript|data|vbscript):/i.test(trimmed)
  ) {
    return fallback;
  }

  const cleanPath = trimmed.split('#')[0];
  if (cleanPath === '/login' || cleanPath.startsWith('/login?')) {
    return fallback;
  }

  return trimmed;
}

/**
 * Persist an anonymized in-progress survey draft in ephemeral sessionStorage (#6 Autosave).
 * Never persists student_id, institutional_id, or bearer tokens inside the survey payload.
 * Key is scoped to authenticated studentId to prevent cross-account draft leakage on shared lab terminals.
 * @param {number|string} courseSectionId
 * @param {Record<string, any>} draftPayload
 * @param {number|string|null} [studentId=null]
 */
export function saveSurveyDraft(courseSectionId, draftPayload, studentId = null) {
  if (typeof window === 'undefined' || !courseSectionId) {
    return false;
  }
  try {
    const safeKey = studentId
      ? `${DRAFT_PREFIX}u${studentId}_sec${Number(courseSectionId)}`
      : `${DRAFT_PREFIX}${Number(courseSectionId)}`;
    const record = {
      ratings: Array.isArray(draftPayload.ratings) ? draftPayload.ratings : [],
      commentText: String(draftPayload.commentText || '').slice(0, 2000),
      threeWords: Array.isArray(draftPayload.threeWords) ? draftPayload.threeWords.slice(0, 3) : ['', '', ''],
      improvementTopics: Array.isArray(draftPayload.improvementTopics)
        ? draftPayload.improvementTopics.slice(0, 6)
        : ['', ''],
      teachingStrengths: String(draftPayload.teachingStrengths || '').slice(0, 1000),
      courseRecommendations: String(draftPayload.courseRecommendations || '').slice(0, 1000),
      showFifthQuestion: Boolean(draftPayload.showFifthQuestion),
      additionalObservation: String(draftPayload.additionalObservation || '').slice(0, 1000),
      savedAt: new Date().toISOString(),
    };
    window.sessionStorage?.setItem(safeKey, JSON.stringify(record));
    return true;
  } catch (_) {
    return false;
  }
}

/**
 * Load a previously autosaved survey draft for `courseSectionId`.
 * Checks for user-scoped draft key first, falling back to legacy unscoped key.
 * @param {number|string} courseSectionId
 * @param {number|string|null} [studentId=null]
 */
export function loadSurveyDraft(courseSectionId, studentId = null) {
  if (typeof window === 'undefined' || !courseSectionId) {
    return null;
  }
  try {
    let raw = null;
    if (studentId) {
      raw = window.sessionStorage?.getItem(`${DRAFT_PREFIX}u${studentId}_sec${Number(courseSectionId)}`);
    }
    if (!raw) {
      raw = window.sessionStorage?.getItem(`${DRAFT_PREFIX}${Number(courseSectionId)}`);
    }
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch (_) {
    return null;
  }
}

/**
 * Clear an autosaved draft for a specific course section upon submission or explicit discard.
 * @param {number|string} courseSectionId
 * @param {number|string|null} [studentId=null]
 */
export function clearSurveyDraft(courseSectionId, studentId = null) {
  if (typeof window === 'undefined' || !courseSectionId) {
    return;
  }
  try {
    if (studentId) {
      window.sessionStorage?.removeItem(`${DRAFT_PREFIX}u${studentId}_sec${Number(courseSectionId)}`);
    }
    window.sessionStorage?.removeItem(`${DRAFT_PREFIX}${Number(courseSectionId)}`);
  } catch (_) {}
}

/**
 * Purge all ephemeral survey drafts on logout.
 */
export function clearAllSurveyDrafts() {
  if (typeof window === 'undefined' || !window.sessionStorage) {
    return;
  }
  try {
    const keysToRemove = [];
    for (let i = 0; i < window.sessionStorage.length; i += 1) {
      const key = window.sessionStorage.key(i);
      if (key && key.startsWith(DRAFT_PREFIX)) {
        keysToRemove.push(key);
      }
    }
    for (const key of keysToRemove) {
      window.sessionStorage.removeItem(key);
    }
  } catch (_) {}
}

/**
 * Read non-sensitive UI layout preferences (density, sidebar collapsed, onboarding complete).
 */
export function readUiPreferences() {
  const defaults = {
    density: 'comfortable',
    sidebarCollapsed: false,
    onboardingCompleted: false,
    focusArea: 'analytics',
  };
  if (typeof window === 'undefined') {
    return defaults;
  }
  try {
    const raw = window.sessionStorage?.getItem(UI_PREFS_KEY) || window.localStorage?.getItem(UI_PREFS_KEY);
    if (!raw) return defaults;
    return { ...defaults, ...JSON.parse(raw) };
  } catch (_) {
    return defaults;
  }
}

/**
 * Save non-sensitive UI layout preferences (#15 Onboarding Data Capture).
 * @param {Record<string, any>} patch
 */
export function saveUiPreferences(patch = {}) {
  const merged = { ...readUiPreferences(), ...patch };
  if (typeof window !== 'undefined') {
    try {
      window.sessionStorage?.setItem(UI_PREFS_KEY, JSON.stringify(merged));
    } catch (_) {}
  }
  return merged;
}

/**
 * Map institutional user role to its governance tier indicator (#14 Tier-Based UI Indicators).
 * @param {string|null|undefined} role
 */
export function getRoleTierBadge(role) {
  switch (String(role || '').toLowerCase()) {
    case 'admin':
      return {
        tierCode: 'TIER 1',
        label: 'Registrar Office',
        tone: 'gold',
      };
    case 'dean':
      return {
        tierCode: 'TIER 1',
        label: 'College Dean',
        tone: 'gold',
      };
    case 'chair':
      return {
        tierCode: 'TIER 2',
        label: 'Department Chair',
        tone: 'emerald',
      };
    case 'faculty':
      return {
        tierCode: 'TIER 3',
        label: 'Faculty Member',
        tone: 'emerald',
      };
    case 'student':
    default:
      return {
        tierCode: 'SET PASS',
        label: 'Student Portal',
        tone: 'sage',
      };
  }
}

