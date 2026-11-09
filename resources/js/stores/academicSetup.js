import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { useAuthStore } from './auth.js';

export const useAcademicSetupStore = defineStore('academicSetup', () => {
  const authStore = useAuthStore();

  const overview = ref({
    terms: [],
    selected_term: null,
    sections: [],
    departments: [],
    courses: [],
    faculty: [],
    readiness_summary: {
      total_sections: 0,
      assigned_sections: 0,
      unassigned_sections: 0,
      total_enrolled: 0,
      sections_with_zero_enrollment: 0,
      window_status: 'CLOSED',
      is_cycle_ready: false,
      validation_warnings: [],
    },
    window_config: null,
    questionnaire_preview: null,
  });

  const loading = ref(false);
  const actionBusy = ref(false);
  const error = ref(null);
  const selectedTermId = ref(null);
  const filterSearch = ref('');
  const filterDepartmentId = ref('ALL');
  const filterAssignment = ref('ALL'); // 'ALL' | 'UNASSIGNED' | 'ASSIGNED'

  function getApiHeaders(jsonBody = false) {
    try {
      return authStore.authHeaders(jsonBody ? { 'Content-Type': 'application/json' } : {});
    } catch (_) {
      return jsonBody
        ? { 'Content-Type': 'application/json', Accept: 'application/json' }
        : { Accept: 'application/json' };
    }
  }

  const terms = computed(() => overview.value.terms || []);
  const selectedTerm = computed(() => overview.value.selected_term || null);
  const sections = computed(() => overview.value.sections || []);
  const departments = computed(() => overview.value.departments || []);
  const courses = computed(() => overview.value.courses || []);
  const facultyList = computed(() => overview.value.faculty || []);
  const readinessSummary = computed(() => overview.value.readiness_summary || {});
  const isCycleReady = computed(() => Boolean(readinessSummary.value.is_cycle_ready));
  const questionnairePreview = computed(() => overview.value.questionnaire_preview || null);

  const filteredSections = computed(() => {
    let list = sections.value;

    const search = String(filterSearch.value || '').trim().toLowerCase();
    if (search) {
      list = list.filter((sec) => {
        const text = `${sec.course_code} ${sec.course_title} ${sec.section_code} ${sec.instructor_name} ${sec.department_code || ''}`.toLowerCase();
        return text.includes(search);
      });
    }

    if (filterDepartmentId.value !== 'ALL') {
      const depId = Number(filterDepartmentId.value);
      list = list.filter((sec) => Number(sec.department_id) === depId);
    }

    if (filterAssignment.value === 'UNASSIGNED') {
      list = list.filter((sec) => !sec.is_assigned || !sec.faculty_id);
    } else if (filterAssignment.value === 'ASSIGNED') {
      list = list.filter((sec) => sec.is_assigned && sec.faculty_id);
    }

    return list;
  });

  async function fetchOverview(termId = null) {
    loading.value = true;
    error.value = null;
    try {
      let url = '/api/admin/setup/overview';
      if (termId !== null) {
        url += `?academic_term_id=${encodeURIComponent(termId)}`;
      } else if (selectedTermId.value !== null) {
        url += `?academic_term_id=${encodeURIComponent(selectedTermId.value)}`;
      }

      const res = await fetch(url, {
        headers: getApiHeaders(),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to load academic setup overview.');
      }

      overview.value = data;
      if (data.selected_term?.id) {
        selectedTermId.value = data.selected_term.id;
      }
      return data;
    } catch (e) {
      error.value = e.message || 'Unable to contact server.';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function createOrUpdateTerm(payload) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch('/api/admin/setup/terms', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify(payload),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to save academic term.');
      }

      await fetchOverview(data.term_id || selectedTermId.value);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function setActiveTerm(termId) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch('/api/admin/setup/terms/set-active', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({ academic_term_id: termId }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to activate academic term.');
      }

      selectedTermId.value = termId;
      await fetchOverview(termId);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function createCourse(payload) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch('/api/admin/setup/courses', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify(payload),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to create course offering.');
      }

      await fetchOverview(selectedTermId.value);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function createSection(payload) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch('/api/admin/setup/sections', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({
          ...payload,
          academic_term_id: payload.academic_term_id || selectedTermId.value,
        }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to create course section.');
      }

      await fetchOverview(selectedTermId.value);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function assignFaculty(courseSectionId, facultyId) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch('/api/admin/setup/sections/assign-faculty', {
        method: 'POST',
        headers: getApiHeaders(true),
        body: JSON.stringify({
          course_section_id: courseSectionId,
          faculty_id: facultyId ? Number(facultyId) : null,
        }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to assign faculty to section.');
      }

      await fetchOverview(selectedTermId.value);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function deleteSection(courseSectionId) {
    actionBusy.value = true;
    error.value = null;
    try {
      const res = await fetch(`/api/admin/setup/sections/${encodeURIComponent(courseSectionId)}`, {
        method: 'DELETE',
        headers: getApiHeaders(),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Failed to delete course section.');
      }

      await fetchOverview(selectedTermId.value);
      return data;
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      actionBusy.value = false;
    }
  }

  async function fetchQuestionnaire() {
    try {
      const res = await fetch('/api/admin/setup/questionnaire', {
        headers: getApiHeaders(),
      });
      if (res.ok) {
        const data = await res.json();
        overview.value.questionnaire_preview = data;
        return data;
      }
    } catch (_) {}
    return null;
  }

  return {
    overview,
    loading,
    actionBusy,
    error,
    selectedTermId,
    filterSearch,
    filterDepartmentId,
    filterAssignment,
    terms,
    selectedTerm,
    sections,
    filteredSections,
    departments,
    courses,
    facultyList,
    readinessSummary,
    isCycleReady,
    questionnairePreview,
    fetchOverview,
    createOrUpdateTerm,
    setActiveTerm,
    createCourse,
    createSection,
    assignFaculty,
    deleteSection,
    fetchQuestionnaire,
  };
});
