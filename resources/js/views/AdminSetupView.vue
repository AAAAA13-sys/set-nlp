<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAcademicSetupStore } from '../stores/academicSetup.js';
import { useEvaluationStore } from '../stores/evaluation.js';
import { showJQueryUiError } from '../utils/jqueryUiNotification.js';

const route = useRoute();
const router = useRouter();
const setupStore = useAcademicSetupStore();
const evalStore = useEvaluationStore();

const VALID_SETUP_TABS = ['assignments', 'terms', 'courses', 'faculty', 'students', 'users', 'questionnaire'];
const activeTab = ref(
  route?.query?.tab && VALID_SETUP_TABS.includes(route.query.tab)
    ? route.query.tab
    : 'assignments'
);

function switchTab(tab) {
  if (!VALID_SETUP_TABS.includes(tab)) return;
  activeTab.value = tab;
  if (router && route?.query?.tab !== tab) {
    router.replace({ query: { ...route?.query, tab } }).catch(() => {});
  }
}

watch(
  () => route?.query?.tab,
  (newTab) => {
    if (newTab && VALID_SETUP_TABS.includes(newTab) && activeTab.value !== newTab) {
      activeTab.value = newTab;
    }
  },
);
const successMessage = ref('');
const errorMessage = ref('');

// Modals
const showNewTermModal = ref(false);
const showNewSectionModal = ref(false);
const showNewCourseModal = ref(false);
const showEditCourseModal = ref(false);
const showFacultyAssignmentsModal = ref(false);
const facultyAssignmentsLoading = ref(false);
const selectedFacultyAssignments = ref(null);
const showEditUserModal = ref(false);
const userEditForm = ref({
  id: null,
  institutional_id: '',
  name: '',
  email: '',
  role: 'faculty',
  department_id: null,
  password: '',
});
const sectionToDelete = ref(null);

// Forms
const termForm = ref({
  academic_year: '2026-2027',
  name: '1st Semester AY 2026-2027',
  code: '1SEM-2026',
  starts_on: '',
  ends_on: '',
  is_active: false,
});

const sectionForm = ref({
  course_id: '',
  section_code: '',
  faculty_id: '',
  enrolled_count: 35,
});

const courseForm = ref({
  department_id: 1,
  code: '',
  title: '',
  units: 3,
});

const editCourseForm = ref({
  id: 0,
  department_id: 1,
  code: '',
  title: '',
  units: 3,
});

const showNewUserModal = ref(false);
const userForm = ref({
  name: '',
  email: '',
  role: 'faculty',
  institutional_id: '',
  department_id: '',
  password: '',
});

function openNewUserModal(role = 'faculty') {
  userForm.value = {
    name: '',
    email: '',
    role: typeof role === 'string' ? role : 'faculty',
    institutional_id: '',
    department_id: setupStore.departments[0]?.id || '',
    password: '',
  };
  showNewUserModal.value = true;
}

async function submitNewUser() {
  try {
    const res = await setupStore.createUser(userForm.value);
    showNewUserModal.value = false;
    flashSuccess(`User account "${res.name}" (${res.institutional_id}) created successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to create user account.', 'Account Creation Error');
  }
}

const showImportRosterModal = ref(false);
const rosterImportFormat = ref('json');
const rosterImportContent = ref('');
const rosterImporting = ref(false);

function openImportRosterModal() {
  rosterImportFormat.value = 'json';
  rosterImportContent.value = JSON.stringify([
    {
      student_id: '2024-10892',
      course_code: 'CS-401',
      course_title: 'Design and Analysis of Algorithms',
      instructor_name: 'Prof. A. Dela Cruz',
      department_name: 'Department of Computer Science',
    },
  ], null, 2);
  showImportRosterModal.value = true;
}

async function submitRosterImport() {
  if (!rosterImportContent.value.trim()) {
    flashError('Please provide roster data in CSV or JSON format.');
    return;
  }
  rosterImporting.value = true;
  try {
    let parsedRows = [];
    if (rosterImportFormat.value === 'json') {
      parsedRows = JSON.parse(rosterImportContent.value);
    } else {
      const lines = rosterImportContent.value.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
      const dataLines = lines.filter((l) => !l.toLowerCase().startsWith('student_id,'));
      parsedRows = dataLines.map((line) => {
        const [student_id, course_code, course_title, instructor_name, department_name] = line.split(',').map((c) => c.trim());
        return { student_id, course_code, course_title, instructor_name, department_name };
      });
    }

    const res = await evalStore.ingestRosterRows(
      parsedRows,
      rosterImportFormat.value,
      rosterImportContent.value,
    );

    if (res.ok) {
      showImportRosterModal.value = false;
      await setupStore.fetchOverview(setupStore.selectedTermId);
      flashSuccess(res.message || 'Successfully imported academic roster into term.');
    } else {
      flashError(res.message || 'Roster import failed.');
    }
  } catch (err) {
    flashError(`Malformed ${rosterImportFormat.value.toUpperCase()} payload: ${err.message}`);
  } finally {
    rosterImporting.value = false;
  }
}

function flashSuccess(msg) {
  successMessage.value = msg;
  errorMessage.value = '';
  setTimeout(() => {
    if (successMessage.value === msg) {
      successMessage.value = '';
    }
  }, 4000);
}

function flashError(msg, title = 'Academic Setup Error') {
  errorMessage.value = msg;
  successMessage.value = '';
  showJQueryUiError(msg, title, 4000);
}

onMounted(async () => {
  try {
    await setupStore.fetchOverview();
  } catch (err) {
    flashError(err.message || 'Failed to initialize academic setup overview.');
  }
});

async function handleTermSelect(termId) {
  try {
    await setupStore.fetchOverview(termId);
  } catch (err) {
    flashError(err.message || 'Failed to switch academic term.');
  }
}

async function handleSetActiveTerm(termId) {
  try {
    const res = await setupStore.setActiveTerm(termId);
    flashSuccess(`Term "${res.term_code}" is now the active academic term.`);
  } catch (err) {
    flashError(err.message || 'Failed to set active term.');
  }
}

async function confirmDeleteTerm(t) {
  if (t.is_active) {
    flashError(`Cannot delete active academic term "${t.name}". Set another term as active first.`);
    return;
  }
  if (t.total_sections > 0) {
    flashError(`Cannot delete academic term "${t.name}": It contains ${t.total_sections} course section(s). Archive the term instead.`);
    return;
  }
  if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
    const ok = window.confirm(`Are you sure you want to delete academic term "${t.code} - ${t.name}"?`);
    if (!ok) return;
  }
  try {
    await setupStore.deleteTerm(t.id);
    flashSuccess(`Academic term "${t.name}" deleted successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to delete academic term.');
  }
}

function openNewTermModal() {
  termForm.value = {
    id: null,
    academic_year: '2026-2027',
    name: '',
    code: '',
    starts_on: '',
    ends_on: '',
    is_active: false,
  };
  showNewTermModal.value = true;
}

function openEditTermModal(t) {
  termForm.value = {
    id: t.id,
    academic_year: t.academic_year || '2026-2027',
    name: t.name || '',
    code: t.code || '',
    starts_on: t.starts_on || '',
    ends_on: t.ends_on || '',
    is_active: Boolean(t.is_active),
  };
  showNewTermModal.value = true;
}

async function submitNewTerm() {
  if (!termForm.value.academic_year || !termForm.value.code || !termForm.value.name) {
    flashError('Please fill in Academic Year, Code, and Term Name.');
    return;
  }
  try {
    const res = await setupStore.createOrUpdateTerm(termForm.value);
    showNewTermModal.value = false;
    flashSuccess(`Academic Term "${res.code}" saved successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to save academic term.');
  }
}

function openNewSectionModal() {
  sectionForm.value = {
    course_id: setupStore.courses[0]?.id || '',
    section_code: '',
    faculty_id: '',
    enrolled_count: 35,
  };
  showNewSectionModal.value = true;
}

function openNewSectionModalForCourse(c) {
  sectionForm.value = {
    course_id: c.id,
    section_code: '',
    faculty_id: '',
    enrolled_count: 35,
  };
  showNewSectionModal.value = true;
}

async function submitNewSection() {
  if (!sectionForm.value.course_id || !sectionForm.value.section_code) {
    flashError('Course offering and Section Code are required.');
    return;
  }
  try {
    const res = await setupStore.createSection({
      ...sectionForm.value,
      academic_term_id: setupStore.selectedTermId,
    });
    showNewSectionModal.value = false;
    flashSuccess(`Section "${res.section_code}" created successfully under ${res.course_code}.`);
  } catch (err) {
    flashError(err.message || 'Failed to create course section.');
  }
}

function openNewCourseModal() {
  courseForm.value = {
    department_id: setupStore.departments[0]?.id || 1,
    code: '',
    title: '',
    units: 3,
  };
  showNewCourseModal.value = true;
}

async function submitNewCourse() {
  if (!courseForm.value.code || !courseForm.value.title) {
    flashError('Course code and Course title are required.');
    return;
  }
  try {
    const res = await setupStore.createCourse(courseForm.value);
    showNewCourseModal.value = false;
    flashSuccess(`Course "${res.code} - ${res.title}" added to academic catalog.`);
    if (showNewSectionModal.value) {
      sectionForm.value.course_id = res.course_id;
    }
  } catch (err) {
    flashError(err.message || 'Failed to create course offering.');
  }
}

function openEditCourseModal(c) {
  editCourseForm.value = {
    id: c.id,
    department_id: c.department_id || setupStore.departments[0]?.id || 1,
    code: c.code || '',
    title: c.title || '',
    units: c.units || 3,
  };
  showEditCourseModal.value = true;
}

async function submitEditCourse() {
  if (!editCourseForm.value.code || !editCourseForm.value.title) {
    flashError('Course code and Course title are required.');
    return;
  }
  try {
    const res = await setupStore.updateCourse(editCourseForm.value.id, editCourseForm.value);
    showEditCourseModal.value = false;
    flashSuccess(`Course "${res.code} - ${res.title}" updated successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to update course offering.');
  }
}

async function confirmDeleteCourse(c) {
  const sections = getCourseSections(c.id);
  if (sections.length > 0) {
    flashError(`Cannot delete course "${c.code}": It has ${sections.length} active course section(s). Remove or reassign sections first.`);
    return;
  }
  if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
    const ok = window.confirm(`Are you sure you want to delete course "${c.code} - ${c.title}" from the catalog?`);
    if (!ok) return;
  }
  try {
    await setupStore.deleteCourse(c.id);
    flashSuccess(`Course "${c.code}" deleted from academic catalog.`);
  } catch (err) {
    flashError(err.message || 'Failed to delete course offering.');
  }
}

function triggerCsvDownload(headers, rows, baseFilename) {
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) {
      s = `'${s}`;
    }
    return `"${s.replace(/"/g, '""')}"`;
  };
  const csvContent = [headers.map(sanitize).join(','), ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  link.setAttribute('download', `${baseFilename}_${nowStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function exportCoursesCatalogCsv() {
  const coursesList = setupStore.courses || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Course Code', 'Course Title', 'Department', 'Units'];
  const rows = coursesList.map((c) => [
    sanitize(c.code),
    sanitize(c.title),
    sanitize(c.department_name || c.department_code || 'College of Computer Studies'),
    sanitize(c.units ?? 3),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_Course_Catalog');
}

function exportFacultyDirectoryCsv() {
  const list = setupStore.facultyList || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Institutional ID', 'Faculty Name', 'Department', 'Email', 'Role', 'Assigned Sections'];
  const rows = list.map((f) => [
    sanitize(f.institutional_id),
    sanitize(f.name),
    sanitize(f.department_name || f.department_code || 'College of Computer Studies'),
    sanitize(f.email),
    sanitize(f.role),
    sanitize(getFacultyAssignedSectionsCount(f.id)),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_Faculty_Directory');
}

function exportStudentsDirectoryCsv() {
  const list = setupStore.studentsList || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Student ID', 'Student Name', 'Email', 'Department', 'Enrolled Courses', 'Cleared Evaluations', 'Clearance Status'];
  const rows = list.map((s) => [
    sanitize(s.institutional_id),
    sanitize(s.name),
    sanitize(s.email),
    sanitize(s.department_name || s.department_code || 'College of Computer Studies'),
    sanitize(s.enrolled_count),
    sanitize(s.cleared_count),
    sanitize(s.clearance_status),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_Student_Clearance_Roster');
}

function exportPendingStudentsCsv() {
  const list = (setupStore.studentsList || []).filter((s) => !s.is_complete && s.enrolled_count > 0);
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = [
    'Student ID',
    'Student Name',
    'Email',
    'Department',
    'Enrolled Courses',
    'Cleared Evaluations',
    'Pending Evaluations',
    'Completion Rate %',
    'Follow-Up Status',
  ];
  const rows = list.map((s) => {
    const pending = Math.max(0, (s.enrolled_count || 0) - (s.cleared_count || 0));
    const rate = s.enrolled_count > 0 ? Math.round(((s.cleared_count || 0) / s.enrolled_count) * 100) : 0;
    return [
      sanitize(s.institutional_id),
      sanitize(s.name),
      sanitize(s.email),
      sanitize(s.department_name || s.department_code || 'College of Computer Studies'),
      sanitize(s.enrolled_count),
      sanitize(s.cleared_count),
      sanitize(pending),
      sanitize(`${rate}%`),
      sanitize(pending > 0 ? 'PENDING EVALUATION' : 'CLEARED'),
    ].join(',');
  });
  triggerCsvDownload(headers, rows, 'PLP_Incomplete_Student_Evaluations_FollowUp');
}

const studentTrackerMetrics = computed(() => {
  const list = setupStore.studentsList || [];
  const total = list.length;
  const cleared = list.filter((s) => s.is_complete).length;
  const pending = list.filter((s) => !s.is_complete && s.enrolled_count > 0).length;
  const clearedRate = total > 0 ? Math.round((cleared / total) * 100) : 0;
  const pendingRate = total > 0 ? Math.round((pending / total) * 100) : 0;
  return { total, cleared, pending, clearedRate, pendingRate };
});

const showStudentReminderModal = ref(false);
const reminderStudent = ref(null);
const reminderCopied = ref(false);

function openStudentReminderModal(student) {
  reminderStudent.value = student;
  reminderCopied.value = false;
  showStudentReminderModal.value = true;
}

function closeStudentReminderModal() {
  showStudentReminderModal.value = false;
  reminderStudent.value = null;
  reminderCopied.value = false;
}

const reminderNoticeText = computed(() => {
  if (!reminderStudent.value) return '';
  const s = reminderStudent.value;
  const pending = Math.max(0, (s.enrolled_count || 0) - (s.cleared_count || 0));
  const termName = setupStore.selectedTerm?.name || 'Current Academic Term';
  return `Subject: [PLP SET Notice] Incomplete Faculty Evaluation Reminder — ${s.name}

Dear ${s.name} (${s.institutional_id}),

This is an official notice from the Office of Academic Affairs regarding your Student Evaluation of Teachers (SET) for ${termName}.

Our records show that you have ${pending} of ${s.enrolled_count} course evaluation(s) pending completion.

Please access your student evaluation queue at:
https://evaluatech.plpasig.edu.ph/queue

Please note that evaluations are 100% confidential and cryptographically decoupled from your student account. Your constructive feedback is essential for continuous instructional improvement at Pamantasan ng Lungsod ng Pasig.

Thank you,
Office of Academic Affairs
Pamantasan ng Lungsod ng Pasig`;
});

async function copyReminderNotice() {
  if (!reminderNoticeText.value) return;
  try {
    if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      await navigator.clipboard.writeText(reminderNoticeText.value);
    }
    reminderCopied.value = true;
    setTimeout(() => {
      reminderCopied.value = false;
    }, 2500);
  } catch (_) {
    reminderCopied.value = true;
  }
}

function exportSystemUsersCsv() {
  const list = setupStore.systemUsers || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Institutional ID', 'Full Name', 'Email', 'System Role', 'Department', 'Account Status'];
  const rows = list.map((u) => [
    sanitize(u.institutional_id),
    sanitize(u.name),
    sanitize(u.email),
    sanitize(u.role),
    sanitize(u.department_name || u.department_code || 'College of Computer Studies'),
    sanitize(u.is_active ? 'Active' : 'Inactive'),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_System_Users_Directory');
}

function exportAssignmentsMatrixCsv() {
  const list = setupStore.sections || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Course Code', 'Course Title', 'Section', 'Assigned Instructor', 'Enrolled Students', 'Status', 'Registrar Lock'];
  const rows = list.map((sec) => [
    sanitize(sec.course_code),
    sanitize(sec.course_title),
    sanitize(sec.section_code || 'SEC-A'),
    sanitize(sec.instructor_name || 'Unassigned'),
    sanitize(sec.enrolled_count ?? 35),
    sanitize(sec.is_assigned ? 'Assigned' : 'Unassigned'),
    sanitize(sec.registrar_lock ? 'Locked' : 'Open'),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_Faculty_Assignments_Matrix');
}

function exportQuestionnaireInstrumentCsv() {
  const preview = setupStore.questionnairePreview;
  if (!preview) return;
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Part', 'Cluster ID', 'Cluster Name', 'Question Number', 'Prompt / Question Text', 'Scale / Response Format'];
  const rows = [];
  (preview.clusters || []).forEach((c) => {
    (c.items || []).forEach((item) => {
      rows.push([
        sanitize('Part I: 5-Point Likert Scale'),
        sanitize(c.cluster_id),
        sanitize(c.name || c.title),
        sanitize(item.id || item.item_number),
        sanitize(item.text || item.prompt),
        sanitize('1 (Strongly Disagree) to 5 (Strongly Agree)'),
      ].join(','));
    });
  });
  (preview.professor_qualitative_questions || []).forEach((q, idx) => {
    rows.push([
      sanitize('Part II: Qualitative Feedback'),
      sanitize('N/A'),
      sanitize('Professor Constructive Feedback'),
      sanitize(idx + 1),
      sanitize(q.question || q.title),
      sanitize(q.format || q.description || 'Open Qualitative Text'),
    ].join(','));
  });
  triggerCsvDownload(headers, rows, 'PLP_AACCUP3_Evaluation_Instrument');
}

const termSearchInput = ref('');
const termStatusFilter = ref('ALL');

const filteredTerms = computed(() => {
  const q = termSearchInput.value.trim().toLowerCase();
  return (setupStore.terms || []).filter((t) => {
    if (termStatusFilter.value === 'ACTIVE' && !t.is_active) {
      return false;
    }
    if (termStatusFilter.value === 'ARCHIVED' && t.is_active) {
      return false;
    }
    if (['OPEN', 'CLOSED', 'EXTENDED', 'REOPENED'].includes(termStatusFilter.value)) {
      if ((t.window_status || '').toUpperCase() !== termStatusFilter.value) {
        return false;
      }
    }
    if (!q) return true;
    const haystack = `${t.code || ''} ${t.name || ''} ${t.academic_year || ''} ${t.window_status || ''}`.toLowerCase();
    return haystack.includes(q);
  });
});

function exportAcademicTermsCsv() {
  const list = filteredTerms.value;
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = ['Term Code', 'Term Name', 'Academic Year', 'Starts On', 'Ends On', 'Evaluation Window Status', 'System Status'];
  const rows = list.map((t) => [
    sanitize(t.code),
    sanitize(t.name),
    sanitize(t.academic_year),
    sanitize(t.starts_on || 'Not scheduled'),
    sanitize(t.ends_on || 'Not scheduled'),
    sanitize(t.window_status || 'CLOSED'),
    sanitize(t.is_active ? 'Active Term' : 'Archived'),
  ].join(','));
  triggerCsvDownload(headers, rows, 'PLP_Academic_Terms');
}

const selectedSetupReportSection = ref(null);
const showSetupSectionReportModal = ref(false);

function openSetupSectionReport(sec) {
  selectedSetupReportSection.value = sec;
  showSetupSectionReportModal.value = true;
}

function closeSetupSectionReport() {
  showSetupSectionReportModal.value = false;
  selectedSetupReportSection.value = null;
}

function printSetupSectionReport() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

const facultySearchInput = ref('');
const facultyDepartmentFilter = ref('ALL');

const filteredFacultyDirectory = computed(() => {
  const query = facultySearchInput.value.trim().toLowerCase();
  const depFilter = facultyDepartmentFilter.value;
  let list = setupStore.facultyList || [];
  if (depFilter !== 'ALL') {
    list = list.filter((f) => String(f.department_id) === String(depFilter) || f.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((f) => {
    const text = `${f.name || ''} ${f.institutional_id || ''} ${f.email || ''} ${f.department_name || ''} ${f.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function getFacultyAssignedSectionsCount(facultyId) {
  return (setupStore.sections || []).filter((s) => Number(s.faculty_id) === Number(facultyId)).length;
}

const showFacultyDossierModal = ref(false);
const selectedDossierFaculty = ref(null);

const facultyAssignedSections = computed(() => {
  if (!selectedDossierFaculty.value) return [];
  const fId = Number(selectedDossierFaculty.value.id);
  return (setupStore.sections || []).filter((s) => Number(s.faculty_id) === fId);
});

const facultyTotalEnrolled = computed(() => {
  return facultyAssignedSections.value.reduce((acc, s) => acc + Number(s.enrolled_count || 0), 0);
});

const facultyTotalCleared = computed(() => {
  return facultyAssignedSections.value.reduce((acc, s) => acc + Number(s.cleared_count || s.vault_count || 0), 0);
});

const facultyCompletionRate = computed(() => {
  if (facultyTotalEnrolled.value <= 0) return 100;
  return Math.round((facultyTotalCleared.value / facultyTotalEnrolled.value) * 100);
});

function openFacultyDossier(fac) {
  selectedDossierFaculty.value = fac;
  showFacultyDossierModal.value = true;
}

function closeFacultyDossier() {
  selectedDossierFaculty.value = null;
  showFacultyDossierModal.value = false;
}

function printFacultyDossier() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

function quickAssignFaculty(faculty) {
  setupStore.filterAssignment = 'ALL';
  setupStore.filterSearch = faculty.name || '';
  activeTab.value = 'assignments';
}

const courseSearchInput = ref('');
const courseDepartmentFilter = ref('ALL');

const filteredCoursesCatalog = computed(() => {
  const query = courseSearchInput.value.trim().toLowerCase();
  const depFilter = courseDepartmentFilter.value;
  let list = setupStore.courses || [];
  if (depFilter !== 'ALL') {
    list = list.filter((c) => String(c.department_id) === String(depFilter) || c.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((c) => {
    const text = `${c.code || ''} ${c.title || ''} ${c.department_code || ''} ${c.department_name || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function getCourseSections(courseId) {
  return (setupStore.sections || []).filter((s) => Number(s.course_id) === Number(courseId));
}

function getCourseEnrolledTotal(courseId) {
  return getCourseSections(courseId).reduce((sum, s) => sum + (Number(s.enrolled_count) || 0), 0);
}

function getCourseClearedTotal(courseId) {
  return getCourseSections(courseId).reduce((sum, s) => sum + (Number(s.cleared_count || s.vault_count) || 0), 0);
}

const showCourseSectionsModal = ref(false);
const selectedCourseForModal = ref(null);

function openCourseSectionsModal(course) {
  selectedCourseForModal.value = course;
  showCourseSectionsModal.value = true;
}

function closeCourseSectionsModal() {
  selectedCourseForModal.value = null;
  showCourseSectionsModal.value = false;
}

const studentSearchInput = ref('');
const studentStatusFilter = ref('ALL');

const showStudentEnrollmentModal = ref(false);
const studentEnrollmentLoading = ref(false);
const selectedStudentEnrollment = ref(null);

async function openStudentEnrollmentModal(std) {
  selectedStudentEnrollment.value = null;
  showStudentEnrollmentModal.value = true;
  studentEnrollmentLoading.value = true;
  try {
    const details = await setupStore.fetchStudentEnrollment(std.id, setupStore.selectedTermId);
    selectedStudentEnrollment.value = details;
  } catch (err) {
    flashError(err.message || 'Failed to fetch student enrollment checklist.');
  } finally {
    studentEnrollmentLoading.value = false;
  }
}

const showSectionRosterModal = ref(false);
const sectionRosterLoading = ref(false);
const selectedSectionRoster = ref(null);
const sectionRosterSearch = ref('');
const sectionRosterFilter = ref('ALL');

async function openSectionRosterModal(sec) {
  selectedSectionRoster.value = null;
  sectionRosterSearch.value = '';
  sectionRosterFilter.value = 'ALL';
  showSectionRosterModal.value = true;
  sectionRosterLoading.value = true;
  try {
    const secId = Number(sec.course_section_id || sec.id);
    const data = await setupStore.fetchSectionRoster(secId);
    selectedSectionRoster.value = data;
  } catch (err) {
    flashError(err.message || 'Failed to fetch section class roster.');
  } finally {
    sectionRosterLoading.value = false;
  }
}

const filteredSectionRosterStudents = computed(() => {
  const roster = selectedSectionRoster.value?.students || [];
  const query = sectionRosterSearch.value.trim().toLowerCase();
  const filter = sectionRosterFilter.value;
  let list = roster;
  if (filter === 'CLEARED') {
    list = list.filter((s) => s.has_evaluated);
  } else if (filter === 'PENDING') {
    list = list.filter((s) => !s.has_evaluated);
  }
  if (!query) return list;
  return list.filter((s) => {
    const text = `${s.name || ''} ${s.institutional_id || ''} ${s.email || ''} ${s.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

function exportSectionRosterCsv() {
  if (!selectedSectionRoster.value) return;
  const sec = selectedSectionRoster.value.section;
  const students = selectedSectionRoster.value.students || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = [
    'Student Name',
    'Institutional ID',
    'Email Address',
    'Department',
    'Course Code',
    'Section Code',
    'Evaluation Clearance Status',
    'Clearance Proof Token',
    'Cleared At',
  ];
  const rows = students.map((s) => [
    sanitize(s.name),
    sanitize(s.institutional_id),
    sanitize(s.email),
    sanitize(s.department_code),
    sanitize(sec.course_code),
    sanitize(sec.section_code),
    sanitize(s.clearance_status),
    sanitize(s.clearance_token || 'N/A'),
    sanitize(s.cleared_at || 'N/A'),
  ].join(','));
  triggerCsvDownload(headers, rows, `PLP_Section_Roster_${sec.course_code}_${sec.section_code}`);
}

function printSectionRoster() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print();
  }
}

const studentDepartmentFilter = ref('ALL');

const filteredStudentsDirectory = computed(() => {
  const query = studentSearchInput.value.trim().toLowerCase();
  const filter = studentStatusFilter.value;
  const depFilter = studentDepartmentFilter.value;
  let list = setupStore.studentsList || [];
  if (depFilter !== 'ALL') {
    list = list.filter((s) => String(s.department_id) === String(depFilter) || s.department_code === depFilter);
  }
  if (filter === 'CLEARED') {
    list = list.filter((s) => s.is_complete);
  } else if (filter === 'PENDING') {
    list = list.filter((s) => !s.is_complete);
  }
  if (!query) return list;
  return list.filter((s) => {
    const text = `${s.name || ''} ${s.institutional_id || ''} ${s.email || ''} ${s.department_name || ''} ${s.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

const usersSearchInput = ref('');
const usersRoleFilter = ref('ALL');
const usersDepartmentFilter = ref('ALL');

const filteredSystemUsers = computed(() => {
  const query = usersSearchInput.value.trim().toLowerCase();
  const role = usersRoleFilter.value;
  const depFilter = usersDepartmentFilter.value;
  let list = setupStore.systemUsers || [];
  if (role !== 'ALL') {
    list = list.filter((u) => u.role === role);
  }
  if (depFilter !== 'ALL') {
    list = list.filter((u) => String(u.department_id) === String(depFilter) || u.department_code === depFilter);
  }
  if (!query) return list;
  return list.filter((u) => {
    const text = `${u.name || ''} ${u.institutional_id || ''} ${u.email || ''} ${u.role || ''} ${u.department_name || ''} ${u.department_code || ''}`.toLowerCase();
    return text.includes(query);
  });
});

async function openFacultyAssignmentsModal(fac) {
  selectedFacultyAssignments.value = null;
  showFacultyAssignmentsModal.value = true;
  facultyAssignmentsLoading.value = true;
  try {
    const data = await setupStore.fetchFacultyAssignments(fac.id, setupStore.selectedTermId);
    selectedFacultyAssignments.value = data;
  } catch (err) {
    flashError(err.message || 'Failed to fetch faculty teaching assignments.');
  } finally {
    facultyAssignmentsLoading.value = false;
  }
}

function exportFacultyAssignmentsCsv() {
  if (!selectedFacultyAssignments.value) return;
  const fac = selectedFacultyAssignments.value.faculty;
  const term = selectedFacultyAssignments.value.term;
  const sections = selectedFacultyAssignments.value.sections || [];
  const sanitize = (val) => {
    let s = String(val ?? '');
    if (/^[=+@-]/i.test(s)) s = `'${s}`;
    return `"${s.replace(/"/g, '""')}"`;
  };
  const headers = [
    'Faculty Name',
    'Faculty ID',
    'Email',
    'Department',
    'Academic Term',
    'Course Code',
    'Course Title',
    'Section Code',
    'Units',
    'Enrolled Students',
    'Evaluations Received',
    'Turnout Percentage',
    'Grade Status',
    'Registrar Lock',
    'Double-Blind Unlocked',
  ];
  const rows = sections.map((s) => [
    sanitize(fac.name),
    sanitize(fac.institutional_id),
    sanitize(fac.email),
    sanitize(fac.department_code),
    sanitize(term.name),
    sanitize(s.course_code),
    sanitize(s.course_title),
    sanitize(s.section_code),
    sanitize(s.units),
    sanitize(s.enrolled_count),
    sanitize(s.cleared_count),
    sanitize(`${s.turnout_pct}%`),
    sanitize(s.term_grades_submitted ? 'Submitted' : 'Pending'),
    sanitize(s.registrar_lock ? 'Locked' : 'Open'),
    sanitize(s.double_blind_unlocked ? 'Unlocked' : 'Locked'),
  ].join(','));
  triggerCsvDownload(headers, rows, `PLP_Faculty_Assignments_${fac.institutional_id}_${term.code}`);
}

function openEditUserModal(u) {
  userEditForm.value = {
    id: u.id,
    institutional_id: u.institutional_id || '',
    name: u.name || '',
    email: u.email || '',
    role: u.role || 'faculty',
    department_id: u.department_id ? Number(u.department_id) : null,
    password: '',
  };
  showEditUserModal.value = true;
}

async function submitEditUser() {
  if (!userEditForm.value.name || !userEditForm.value.email) {
    flashError('Full name and email are required.');
    return;
  }
  try {
    const res = await setupStore.updateUser(userEditForm.value.id, userEditForm.value);
    showEditUserModal.value = false;
    flashSuccess(`User account "${res.name}" updated successfully.`);
  } catch (err) {
    flashError(err.message || 'Failed to update user account.');
  }
}

async function confirmToggleUserStatus(u) {
  const actionName = u.is_active ? 'deactivate' : 'activate';
  if (!confirm(`Are you sure you want to ${actionName} the account for "${u.name}" (${u.email})?`)) {
    return;
  }
  try {
    const res = await setupStore.toggleUserStatus(u.id);
    flashSuccess(`User account "${res.name}" is now ${res.status_label}.`);
  } catch (err) {
    flashError(err.message || `Failed to ${actionName} user account.`);
  }
}

async function handleFacultyAssignmentChange(sectionId, event) {
  const newFacultyId = event.target.value ? Number(event.target.value) : null;
  try {
    const res = await setupStore.assignFaculty(sectionId, newFacultyId);
    if (res.is_assigned) {
      flashSuccess(`Assigned ${res.instructor_name} to section.`);
    } else {
      flashSuccess('Instructor unassigned from section.');
    }
  } catch (err) {
    flashError(err.message || 'Failed to update faculty assignment.');
  }
}

async function handleToggleRegistrarLock(sec) {
  const nextLock = !sec.registrar_lock;
  const previousLock = sec.registrar_lock;
  const previousUnlocked = sec.double_blind_unlocked;

  // Optimistic UI update
  sec.registrar_lock = nextLock;
  sec.double_blind_unlocked = Boolean(sec.term_grades_submitted && nextLock);

  try {
    const res = await evalStore.toggleSectionLock(sec.course_section_id, 'registrar_lock', nextLock);
    if (res) {
      sec.registrar_lock = Boolean(res.registrar_lock);
      sec.double_blind_unlocked = Boolean(res.double_blind_unlocked);
      flashSuccess(
        `Registrar Lock for ${sec.course_code} (${sec.section_code}) updated to ${sec.registrar_lock ? 'LOCKED' : 'UNLOCKED'}.`
      );
    } else {
      sec.registrar_lock = previousLock;
      sec.double_blind_unlocked = previousUnlocked;
      flashError('Server did not confirm registrar lock change.');
    }
  } catch (err) {
    sec.registrar_lock = previousLock;
    sec.double_blind_unlocked = previousUnlocked;
    flashError(err.message || 'Failed to toggle registrar lock.');
  }
}

function confirmDeleteSection(sec) {
  sectionToDelete.value = sec;
}

async function executeDeleteSection() {
  if (!sectionToDelete.value) return;
  const sec = sectionToDelete.value;
  try {
    const res = await setupStore.deleteSection(sec.course_section_id);
    sectionToDelete.value = null;
    flashSuccess(`Section "${res.section_code}" deleted successfully.`);
  } catch (err) {
    sectionToDelete.value = null;
    flashError(err.message || 'Cannot delete section.', 'Deletion Prohibited');
  }
}
</script>

<template>
  <main class="setup-workspace" data-testid="admin-setup-view">
    <!-- Header Banner -->
    <header class="setup-header">
      <div class="setup-header-main">
        <div class="setup-title-group">
          <span class="setup-kicker">University Academic Operations</span>
          <h1 class="setup-title">Academic Setup &amp; Cycle Management</h1>
          <p class="setup-subtitle">
            Configure academic terms, faculty-to-section teaching assignments, and verify evaluation questionnaire readiness.
          </p>
        </div>

        <div class="setup-header-controls" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <div class="setup-term-switcher">
            <label for="active-term-picker" class="term-picker-label">Active Term:</label>
            <select
              id="active-term-picker"
              class="term-picker-select"
              :value="setupStore.selectedTermId"
              data-testid="term-selector-dropdown"
              @change="handleTermSelect(Number($event.target.value))"
            >
              <option
                v-for="t in setupStore.terms"
                :key="t.id"
                :value="t.id"
              >
                {{ t.code }} — {{ t.name }} {{ t.is_active ? '(Active Term)' : '' }}
              </option>
            </select>
          </div>

          <router-link
            to="/admin"
            class="btn-secondary"
            data-testid="goto-admin-console-btn"
            style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.82rem; font-weight: 600; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151;"
          >
            <span>← Registrar Schedule &amp; Locks</span>
          </router-link>
        </div>
      </div>

      <!-- Action Feedback -->
      <div v-if="successMessage" class="feedback-alert success" role="status" data-testid="setup-success-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>{{ successMessage }}</span>
      </div>

      <div v-if="errorMessage" class="feedback-alert error" role="alert" data-testid="setup-error-alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Cycle Readiness Summary Bar -->
      <div class="readiness-bar-card" data-testid="cycle-readiness-bar">
        <div class="readiness-status-header">
          <div class="readiness-badge-group">
            <span
              class="readiness-chip"
              :class="setupStore.isCycleReady ? 'ready' : 'incomplete'"
              data-testid="readiness-chip"
            >
              {{ setupStore.isCycleReady ? '✓ Cycle Ready to Open' : '⚠ Setup Incomplete' }}
            </span>
            <span class="window-chip" :class="setupStore.readinessSummary.window_status?.toLowerCase()">
              Window: {{ setupStore.readinessSummary.window_status }}
            </span>
          </div>

          <div class="readiness-metrics-row">
            <div class="metric-item">
              <span class="metric-value">{{ setupStore.readinessSummary.total_sections }}</span>
              <span class="metric-label">Total Sections</span>
            </div>
            <div class="metric-item">
              <span class="metric-value text-emerald">{{ setupStore.readinessSummary.assigned_sections }}</span>
              <span class="metric-label">Assigned</span>
            </div>
            <div class="metric-item">
              <span
                class="metric-value"
                :class="setupStore.readinessSummary.unassigned_sections > 0 ? 'text-amber' : ''"
              >
                {{ setupStore.readinessSummary.unassigned_sections }}
              </span>
              <span class="metric-label">Unassigned</span>
            </div>
            <div class="metric-item">
              <span
                class="metric-value"
                :class="setupStore.readinessSummary.sections_with_zero_enrollment > 0 ? 'text-amber' : ''"
              >
                {{ setupStore.readinessSummary.sections_with_zero_enrollment }}
              </span>
              <span class="metric-label">Zero Enrollment</span>
            </div>
            <div class="metric-item">
              <span class="metric-value">{{ setupStore.readinessSummary.total_enrolled }}</span>
              <span class="metric-label">Total Students</span>
            </div>
          </div>
        </div>

        <!-- Actionable Validation Warnings -->
        <div
          v-if="setupStore.readinessSummary.validation_warnings?.length"
          class="readiness-warnings-box"
          data-testid="readiness-warnings-box"
        >
          <strong class="warnings-title">Required Actions Before Evaluations Open:</strong>
          <ul class="warnings-list">
            <li v-for="(warn, idx) in setupStore.readinessSummary.validation_warnings" :key="idx">
              {{ warn }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Institutional Overview Stats Ribbon (Inspired by admin/home.php) -->
      <div class="admin-stats-ribbon" data-testid="admin-stats-ribbon" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.85rem; margin-top: 1rem;">
        <button
          type="button"
          class="stat-card-btn"
          data-testid="stat-card-courses"
          style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 10px; padding: 0.85rem 1rem; text-align: left; cursor: pointer; transition: all 0.15s ease;"
          @click="switchTab('courses')"
        >
          <div style="font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Course Offerings</div>
          <div style="font-size: 1.65rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">{{ setupStore.courses.length }}</div>
          <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;">Catalog Offerings →</div>
        </button>

        <button
          type="button"
          class="stat-card-btn"
          data-testid="stat-card-sections"
          style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 10px; padding: 0.85rem 1rem; text-align: left; cursor: pointer; transition: all 0.15s ease;"
          @click="switchTab('assignments')"
        >
          <div style="font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Course Sections</div>
          <div style="font-size: 1.65rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">{{ setupStore.sections.length }}</div>
          <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;">Teaching Loads →</div>
        </button>

        <button
          type="button"
          class="stat-card-btn"
          data-testid="stat-card-faculty"
          style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 10px; padding: 0.85rem 1rem; text-align: left; cursor: pointer; transition: all 0.15s ease;"
          @click="switchTab('faculty')"
        >
          <div style="font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Faculty Members</div>
          <div style="font-size: 1.65rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">{{ setupStore.facultyList.length }}</div>
          <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;">Faculty Directory →</div>
        </button>

        <button
          type="button"
          class="stat-card-btn"
          data-testid="stat-card-students"
          style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 10px; padding: 0.85rem 1rem; text-align: left; cursor: pointer; transition: all 0.15s ease;"
          @click="switchTab('students')"
        >
          <div style="font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Enrolled Students</div>
          <div style="font-size: 1.65rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">{{ setupStore.studentsList.length }}</div>
          <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;">Clearance Tracker →</div>
        </button>

        <button
          type="button"
          class="stat-card-btn"
          data-testid="stat-card-users"
          style="background: #ffffff; border: 1px solid #d1d5db; border-radius: 10px; padding: 0.85rem 1rem; text-align: left; cursor: pointer; transition: all 0.15s ease;"
          @click="switchTab('users')"
        >
          <div style="font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">System Accounts</div>
          <div style="font-size: 1.65rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">{{ setupStore.systemUsers.length }}</div>
          <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.2rem; font-weight: 600;">Staff &amp; Users →</div>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <nav class="setup-tabs" aria-label="Setup Modules">
        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'assignments' }"
          data-testid="tab-assignments-btn"
          @click="switchTab('assignments')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Faculty Assignments ({{ setupStore.sections.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'terms' }"
          data-testid="tab-terms-btn"
          @click="switchTab('terms')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span>Academic Terms ({{ setupStore.terms.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'courses' }"
          data-testid="tab-courses-btn"
          @click="switchTab('courses')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
          <span>Course Catalog ({{ setupStore.courses.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'faculty' }"
          data-testid="tab-faculty-btn"
          @click="switchTab('faculty')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Faculty Directory ({{ setupStore.facultyList.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'students' }"
          data-testid="tab-students-btn"
          @click="switchTab('students')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
          <span>Student Directory ({{ setupStore.studentsList.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'users' }"
          data-testid="tab-users-btn"
          @click="switchTab('users')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>System Accounts ({{ setupStore.systemUsers.length }})</span>
        </button>

        <button
          type="button"
          class="setup-tab-btn"
          :class="{ active: activeTab === 'questionnaire' }"
          data-testid="tab-questionnaire-btn"
          @click="switchTab('questionnaire')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          <span>Questionnaire Preview (25 Items)</span>
        </button>
      </nav>
    </header>

    <!-- TAB 1: FACULTY-TO-SECTION ASSIGNMENTS (Inspired by manage_restriction.php) -->
    <section v-if="activeTab === 'assignments'" class="tab-pane" data-testid="pane-assignments">
      <div class="pane-controls-bar">
        <div class="search-filter-group">
          <input
            v-model="setupStore.filterSearch"
            type="search"
            class="table-search-input"
            placeholder="Search course code, title, section, or faculty..."
            data-testid="section-search-input"
          />

          <select
            v-model="setupStore.filterDepartmentId"
            class="filter-select"
            data-testid="department-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>

          <select
            v-model="setupStore.filterAssignment"
            class="filter-select"
            data-testid="assignment-filter-select"
          >
            <option value="ALL">All Assignment Statuses</option>
            <option value="UNASSIGNED">Unassigned Sections Only</option>
            <option value="ASSIGNED">Assigned Sections Only</option>
          </select>

          <select
            v-model="setupStore.filterSectionCode"
            class="filter-select"
            data-testid="class-filter-select"
          >
            <option value="ALL">All Sections / Classes</option>
            <option
              v-for="c in setupStore.uniqueSectionCodes"
              :key="c"
              :value="c"
            >
              Section: {{ c }}
            </option>
          </select>

          <button
            v-if="setupStore.filterSearch || setupStore.filterDepartmentId !== 'ALL' || setupStore.filterAssignment !== 'ALL' || setupStore.filterSectionCode !== 'ALL'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="assignments-reset-filters-btn"
            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
            @click="setupStore.filterSearch = ''; setupStore.filterDepartmentId = 'ALL'; setupStore.filterAssignment = 'ALL'; setupStore.filterSectionCode = 'ALL'"
          >
            Reset
          </button>
        </div>

        <div class="pane-actions-group">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-assignments-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportAssignmentsMatrixCsv"
          >
            📊 Export Assignments (CSV)
          </button>
          <button
            type="button"
            class="btn-secondary"
            data-testid="import-roster-btn"
            @click="openImportRosterModal"
          >
            + Import Roster
          </button>
          <button
            type="button"
            class="btn-secondary"
            data-testid="add-course-btn"
            @click="openNewCourseModal"
          >
            + New Course
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="add-section-btn"
            @click="openNewSectionModal"
          >
            + Add Course Section
          </button>
        </div>
      </div>

      <!-- Sections Table -->
      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="sections-table">
          <thead>
            <tr>
              <th scope="col">Course Code &amp; Title</th>
              <th scope="col">Section</th>
              <th scope="col">Dept</th>
              <th scope="col">Assigned Faculty Instructor</th>
              <th scope="col" class="text-center">Enrolled</th>
              <th scope="col" class="text-center">Evaluations</th>
              <th scope="col" class="text-center">Grade Lock</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sec in setupStore.filteredSections"
              :key="sec.course_section_id"
              :class="{ 'row-unassigned': !sec.is_assigned }"
              :data-testid="`section-row-${sec.course_section_id}`"
            >
              <td>
                <div class="course-code-cell">
                  <strong class="font-mono text-emerald">{{ sec.course_code }}</strong>
                  <span class="course-title-sub">{{ sec.course_title }}</span>
                </div>
              </td>
              <td>
                <span class="section-tag">{{ sec.section_code }}</span>
              </td>
              <td>
                <span class="dept-badge">{{ sec.department_code }}</span>
              </td>
              <td>
                <div class="faculty-assignment-cell">
                  <select
                    class="faculty-select"
                    :value="sec.faculty_id || ''"
                    :data-testid="`faculty-select-${sec.course_section_id}`"
                    @change="handleFacultyAssignmentChange(sec.course_section_id, $event)"
                  >
                    <option value="">— Unassigned Instructor —</option>
                    <option
                      v-for="f in setupStore.facultyList"
                      :key="f.id"
                      :value="f.id"
                    >
                      {{ f.name }} ({{ f.role.toUpperCase() }} · {{ f.department_code }})
                    </option>
                  </select>
                </div>
              </td>
              <td class="text-center">
                <span
                  class="enrolled-badge"
                  :class="sec.enrolled_count === 0 ? 'zero' : ''"
                >
                  {{ sec.enrolled_count }}
                </span>
              </td>
              <td class="text-center">
                <span class="vault-count-badge">
                  {{ sec.vault_count }}
                </span>
              </td>
              <td class="text-center">
                <div class="lock-cell-action">
                  <span
                    class="lock-badge"
                    :class="sec.double_blind_unlocked ? 'unlocked' : 'locked'"
                    :title="sec.double_blind_unlocked ? 'Both term grades and registrar locks confirmed' : 'Awaiting instructor grade submission or registrar approval'"
                  >
                    {{ sec.double_blind_unlocked ? 'Unlocked' : 'Locked' }}
                  </span>
                  <button
                    type="button"
                    class="btn-lock-toggle"
                    :class="sec.registrar_lock ? 'locked' : 'unlocked'"
                    :title="sec.registrar_lock ? 'Registrar Lock Active — Click to unlock' : 'Registrar Lock Inactive — Click to lock'"
                    :data-testid="`toggle-lock-btn-${sec.course_section_id}`"
                    @click="handleToggleRegistrarLock(sec)"
                  >
                    {{ sec.registrar_lock ? '🔒 Locked' : '🔓 Inactive' }}
                  </button>
                </div>
              </td>
              <td class="text-right">
                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                  <button
                    type="button"
                    class="btn-secondary-sm"
                    style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #103c2e; background: #ecfdf5; color: #103c2e; font-weight: 700;"
                    :data-testid="`setup-section-roster-btn-${sec.course_code}`"
                    title="View section student roster and clearance checklist"
                    @click="openSectionRosterModal(sec)"
                  >
                    Roster 👥
                  </button>
                  <button
                    v-if="sec.vault_count > 0 || sec.cleared_count > 0"
                    type="button"
                    class="btn-secondary-sm"
                    style="font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; border: 1px solid #103c2e; background: #f0fdf4; color: #103c2e; font-weight: 700;"
                    :data-testid="`setup-view-report-btn-${sec.course_code}`"
                    title="View section evaluation breakdown report"
                    @click="openSetupSectionReport(sec)"
                  >
                    Report 📊
                  </button>
                  <button
                    type="button"
                    class="btn-danger-sm"
                    :disabled="sec.vault_count > 0 || sec.cleared_count > 0"
                    :title="sec.vault_count > 0 ? 'Cannot delete section with submitted student evaluations' : 'Delete section'"
                    :data-testid="`delete-section-btn-${sec.course_section_id}`"
                    @click="confirmDeleteSection(sec)"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="setupStore.filteredSections.length === 0" data-testid="assignments-empty-state">
              <td colspan="8" class="empty-state-cell">
                No course sections match your filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 2: ACADEMIC TERMS (Inspired by academic_list.php) -->
    <section v-if="activeTab === 'terms'" class="tab-pane" data-testid="pane-terms">
      <div class="pane-controls-bar">
        <div class="pane-description">
          <p>
            Manage academic years and semester cycles. The <strong>Active System Term</strong> determines which courses appear in student evaluation queues.
          </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-terms-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportAcademicTermsCsv"
          >
            📊 Export Terms (CSV)
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="new-term-btn"
            @click="openNewTermModal"
          >
            + New Academic Term
          </button>
        </div>
      </div>

      <div class="filter-controls-row" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.75rem;">
        <input
          type="search"
          v-model="termSearchInput"
          class="form-input search-input"
          placeholder="Search term code, name, academic year…"
          data-testid="terms-search-input"
          style="flex: 1; min-width: 220px;"
          aria-label="Search academic terms"
        />
        <select
          v-model="termStatusFilter"
          class="form-input"
          style="width: auto; min-width: 170px;"
          data-testid="terms-status-filter-select"
          aria-label="Filter terms by status"
        >
          <option value="ALL">All Statuses</option>
          <option value="ACTIVE">⭐ Active Term</option>
          <option value="ARCHIVED">📦 Archived</option>
          <option value="OPEN">🟢 Open Window</option>
          <option value="EXTENDED">🟡 Extended Window</option>
          <option value="REOPENED">🔵 Reopened Window</option>
          <option value="CLOSED">🔴 Closed Window</option>
        </select>
        <button
          v-if="termSearchInput || termStatusFilter !== 'ALL'"
          type="button"
          class="btn-secondary-sm"
          data-testid="terms-reset-filters-btn"
          @click="termSearchInput = ''; termStatusFilter = 'ALL';"
        >
          Reset
        </button>
      </div>

      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="terms-table">
          <thead>
            <tr>
              <th scope="col">Code</th>
              <th scope="col">Term / Semester Name</th>
              <th scope="col">Academic Year</th>
              <th scope="col">Date Range</th>
              <th scope="col" class="text-center">Evaluation Window</th>
              <th scope="col" class="text-center">System Status</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="t in filteredTerms"
              :key="t.id"
              :class="{ 'row-active-term': t.is_active }"
              :data-testid="`term-row-${t.id}`"
            >
              <td>
                <strong class="font-mono">{{ t.code }}</strong>
              </td>
              <td>{{ t.name }}</td>
              <td>{{ t.academic_year }}</td>
              <td>
                <span v-if="t.starts_on && t.ends_on" class="date-range">
                  {{ t.starts_on }} to {{ t.ends_on }}
                </span>
                <span v-else class="text-muted">Not scheduled</span>
              </td>
              <td class="text-center">
                <span class="window-status-pill" :class="t.window_status?.toLowerCase()">
                  {{ t.window_status }}
                </span>
              </td>
              <td class="text-center">
                <span
                  class="active-status-badge"
                  :class="t.is_active ? 'active' : 'inactive'"
                >
                  {{ t.is_active ? 'Active Term' : 'Archived' }}
                </span>
              </td>
              <td class="text-right">
                <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                  <button
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`edit-term-btn-${t.id}`"
                    @click="openEditTermModal(t)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="!t.is_active"
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`set-active-term-btn-${t.id}`"
                    @click="handleSetActiveTerm(t.id)"
                  >
                    Set as Active
                  </button>
                  <span v-else class="text-active-label">Current Active</span>
                  <button
                    v-if="!t.is_active"
                    type="button"
                    class="btn-secondary-sm text-danger"
                    :data-testid="`delete-term-btn-${t.id}`"
                    @click="confirmDeleteTerm(t)"
                  >
                    Delete 🗑️
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredTerms.length === 0" data-testid="terms-empty-state">
              <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">
                <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                  No Academic Terms Match Filter
                </div>
                <p style="margin: 0; font-size: 0.85rem;">
                  Try adjusting your search query or reset the status filter to view all academic terms.
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 3: COURSE CATALOG (Inspired by subject_list.php) -->
    <section v-if="activeTab === 'courses'" class="tab-pane" data-testid="pane-courses">
      <div class="pane-controls-bar">
        <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
          <input
            v-model="courseSearchInput"
            type="search"
            class="form-input search-input"
            placeholder="Search course code, title, or department…"
            data-testid="course-search-input"
          />
          <select
            v-model="courseDepartmentFilter"
            class="form-input"
            style="width: auto; min-width: 170px;"
            data-testid="course-dept-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>
          <button
            v-if="courseSearchInput || courseDepartmentFilter !== 'ALL'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="courses-reset-filters-btn"
            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
            @click="courseSearchInput = ''; courseDepartmentFilter = 'ALL'"
          >
            Reset
          </button>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-courses-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportCoursesCatalogCsv"
          >
            📊 Export Catalog (CSV)
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="catalog-new-course-btn"
            @click="openNewCourseModal"
          >
            + New Course Offering
          </button>
        </div>
      </div>

      <div class="table-responsive-wrapper">
        <table class="setup-data-table" data-testid="courses-table">
          <thead>
            <tr>
              <th scope="col">Code</th>
              <th scope="col">Course Title</th>
              <th scope="col">Academic Department</th>
              <th scope="col" class="text-center">Units</th>
              <th scope="col" class="text-center">Active Offerings</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in filteredCoursesCatalog"
              :key="c.id"
              :data-testid="`course-row-${c.id}`"
            >
              <td>
                <strong class="font-mono text-emerald">{{ c.code }}</strong>
              </td>
              <td>{{ c.title }}</td>
              <td>
                <span class="dept-badge">{{ c.department_code || 'CCS' }}</span>
                <span style="font-size: 0.82rem; color: #6b7280; margin-left: 0.4rem;">{{ c.department_name }}</span>
              </td>
              <td class="text-center">
                <span class="enrolled-badge">{{ c.units }} Units</span>
              </td>
              <td class="text-center">
                <button
                  type="button"
                  class="token-chip"
                  :class="getCourseSections(c.id).length > 0 ? 'chip-signed' : 'chip-unsigned'"
                  style="cursor: pointer; border: none; font-size: 0.78rem;"
                  :data-testid="`course-sections-btn-${c.id}`"
                  @click="openCourseSectionsModal(c)"
                >
                  {{ getCourseSections(c.id).length }} Sections 📋
                </button>
              </td>
              <td class="text-right">
                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                  <button
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`add-section-for-course-btn-${c.id}`"
                    @click="openNewSectionModalForCourse(c)"
                  >
                    + Add Section
                  </button>
                  <button
                    type="button"
                    class="btn-secondary-sm"
                    :data-testid="`edit-course-btn-${c.id}`"
                    @click="openEditCourseModal(c)"
                  >
                    Edit ✏️
                  </button>
                  <button
                    type="button"
                    class="btn-secondary-sm text-danger"
                    :data-testid="`delete-course-btn-${c.id}`"
                    @click="confirmDeleteCourse(c)"
                  >
                    Delete 🗑️
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredCoursesCatalog.length === 0" data-testid="courses-empty-state">
              <td colspan="6" class="empty-state-cell">
                No course offerings match your search criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 4: FACULTY DIRECTORY (Inspired by admin/faculty_list.php) -->
    <section v-if="activeTab === 'faculty'" class="tab-pane" data-testid="pane-faculty">
      <div class="pane-controls-bar">
        <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
          <input
            v-model="facultySearchInput"
            type="search"
            class="form-input search-input"
            placeholder="Search faculty by name, employee ID, or department…"
            data-testid="faculty-search-input"
          />
          <select
            v-model="facultyDepartmentFilter"
            class="form-input"
            style="width: auto; min-width: 170px;"
            data-testid="faculty-dept-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>
          <button
            v-if="facultySearchInput || facultyDepartmentFilter !== 'ALL'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="faculty-reset-filters-btn"
            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
            @click="facultySearchInput = ''; facultyDepartmentFilter = 'ALL'"
          >
            Reset
          </button>
        </div>
        <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-faculty-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportFacultyDirectoryCsv"
          >
            📊 Export Faculty (CSV)
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="add-faculty-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 600;"
            @click="openNewUserModal('faculty')"
          >
            + New Faculty
          </button>
          <span class="token-chip chip-signed">
            Total Instructors: {{ setupStore.facultyList.length }}
          </span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="institutional-table tabular-nums" data-testid="faculty-directory-table">
          <thead>
            <tr>
              <th scope="col">Institutional ID</th>
              <th scope="col">Faculty Member Name</th>
              <th scope="col">Department</th>
              <th scope="col">Institutional Email</th>
              <th scope="col">Academic Role</th>
              <th scope="col">Assigned Sections</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="fac in filteredFacultyDirectory" :key="fac.id" :data-testid="`faculty-row-${fac.id}`">
              <td><code>{{ fac.institutional_id }}</code></td>
              <td><strong>{{ fac.name }}</strong></td>
              <td>
                <span class="dept-badge">{{ fac.department_code || 'CCS' }}</span>
                <span style="margin-left: 0.35rem; font-size: 0.8rem; color: #4b5563;">{{ fac.department_name }}</span>
              </td>
              <td>{{ fac.email }}</td>
              <td>
                <span class="role-pill" style="font-size: 0.75rem; text-transform: uppercase;">
                  {{ fac.role }}
                </span>
              </td>
              <td>
                <span
                  class="token-chip"
                  :class="getFacultyAssignedSectionsCount(fac.id) > 0 ? 'chip-signed' : 'chip-unsigned'"
                >
                  {{ getFacultyAssignedSectionsCount(fac.id) }} Sections
                </span>
              </td>
              <td>
                <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`faculty-dossier-report-btn-${fac.id}`"
                    style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                    @click="openFacultyDossier(fac)"
                  >
                    Report 📊
                  </button>
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`view-faculty-assignments-btn-${fac.id}`"
                    style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                    @click="openFacultyAssignmentsModal(fac)"
                  >
                    Assignments 📚
                  </button>
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`assign-faculty-shortcut-btn-${fac.id}`"
                    @click="quickAssignFaculty(fac)"
                  >
                    Sections →
                  </button>
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`edit-faculty-btn-${fac.id}`"
                    style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                    @click="openEditUserModal(fac)"
                  >
                    Edit ✏️
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredFacultyDirectory.length === 0" data-testid="faculty-empty-state">
              <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                No faculty members match your search criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 5: STUDENT DIRECTORY (Inspired by admin/student_list.php) -->
    <section v-if="activeTab === 'students'" class="tab-pane" data-testid="pane-students">
      <div class="pane-controls-bar">
        <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
          <input
            v-model="studentSearchInput"
            type="search"
            class="form-input search-input"
            placeholder="Search student by name, student ID, or department…"
            data-testid="student-search-input"
          />
          <select
            v-model="studentStatusFilter"
            class="form-input"
            style="width: auto; min-width: 170px;"
            data-testid="student-status-filter-select"
          >
            <option value="ALL">All Clearance Status</option>
            <option value="CLEARED">Cleared (100%)</option>
            <option value="PENDING">Pending Evaluations</option>
          </select>
          <select
            v-model="studentDepartmentFilter"
            class="form-input"
            style="width: auto; min-width: 170px;"
            data-testid="student-dept-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>
          <button
            v-if="studentSearchInput || studentStatusFilter !== 'ALL' || studentDepartmentFilter !== 'ALL'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="student-reset-filters-btn"
            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
            @click="studentSearchInput = ''; studentStatusFilter = 'ALL'; studentDepartmentFilter = 'ALL'"
          >
            Reset
          </button>
        </div>
        <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-pending-students-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #fde68a; background: #fffbeb; color: #b45309; font-weight: 700;"
            @click="exportPendingStudentsCsv"
          >
            ⚠️ Export Pending Evaluators (CSV)
          </button>
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-students-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportStudentsDirectoryCsv"
          >
            📊 Export All Students (CSV)
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="add-student-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 600;"
            @click="openNewUserModal('student')"
          >
            + New Student
          </button>
          <span class="token-chip chip-signed">
            Total Students: {{ setupStore.studentsList.length }}
          </span>
        </div>
      </div>

      <!-- EVALUATION PARTICIPATION TRACKER BAR -->
      <div class="tracker-summary-bar" data-testid="student-tracker-summary-bar" style="display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <div class="tracker-card" style="flex: 1; min-width: 140px; background: #ffffff; border: 1px solid #dce7dc; border-radius: 8px; padding: 0.65rem 0.85rem;">
          <small style="color: #6b7280; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; display: block;">Total Students</small>
          <div style="font-size: 1.25rem; font-weight: 800; color: #103c2e;">{{ studentTrackerMetrics.total }}</div>
        </div>
        <div class="tracker-card" style="flex: 1; min-width: 140px; background: #ffffff; border: 1px solid #dce7dc; border-radius: 8px; padding: 0.65rem 0.85rem;">
          <small style="color: #065f46; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; display: block;">✅ Fully Cleared</small>
          <div style="font-size: 1.25rem; font-weight: 800; color: #059669;">{{ studentTrackerMetrics.cleared }} <span style="font-size: 0.8rem; font-weight: 600;">({{ studentTrackerMetrics.clearedRate }}%)</span></div>
        </div>
        <div class="tracker-card" style="flex: 1; min-width: 140px; background: #ffffff; border: 1px solid #fde68a; border-radius: 8px; padding: 0.65rem 0.85rem;">
          <small style="color: #92400e; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; display: block;">⚠️ Pending Evaluators</small>
          <div style="font-size: 1.25rem; font-weight: 800; color: #d97706;">{{ studentTrackerMetrics.pending }} <span style="font-size: 0.8rem; font-weight: 600;">({{ studentTrackerMetrics.pendingRate }}%)</span></div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="institutional-table tabular-nums" data-testid="student-directory-table">
          <thead>
            <tr>
              <th scope="col">Student ID</th>
              <th scope="col">Student Name</th>
              <th scope="col">Department / College</th>
              <th scope="col">Institutional Email</th>
              <th scope="col">Enrolled Courses</th>
              <th scope="col">Evaluation Progress</th>
              <th scope="col">Clearance Status</th>
              <th scope="col" class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="std in filteredStudentsDirectory" :key="std.id" :data-testid="`student-row-${std.id}`">
              <td><code>{{ std.institutional_id }}</code></td>
              <td><strong>{{ std.name }}</strong></td>
              <td>
                <span class="dept-badge">{{ std.department_code || 'CCS' }}</span>
                <span style="margin-left: 0.35rem; font-size: 0.8rem; color: #4b5563;">{{ std.department_name }}</span>
              </td>
              <td>{{ std.email }}</td>
              <td>
                <span class="enrolled-badge">{{ std.enrolled_count }} Courses</span>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; min-width: 60px;">
                    <div
                      :style="{
                        width: `${std.enrolled_count > 0 ? (std.cleared_count / std.enrolled_count) * 100 : 0}%`,
                        background: std.is_complete ? '#059669' : '#eab308',
                        height: '100%'
                      }"
                    ></div>
                  </div>
                  <strong style="font-size: 0.82rem; color: #374151;">
                    {{ std.cleared_count }} / {{ std.enrolled_count }}
                  </strong>
                </div>
              </td>
              <td>
                <span
                  class="token-chip"
                  :class="std.is_complete ? 'chip-signed' : 'chip-unsigned'"
                  style="font-size: 0.75rem;"
                  :data-testid="`student-clearance-status-${std.id}`"
                >
                  {{ std.clearance_status }}
                </span>
              </td>
              <td class="text-right">
                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`student-courses-btn-${std.id}`"
                    style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600;"
                    @click="openStudentEnrollmentModal(std)"
                  >
                    Courses 📚
                  </button>
                  <button
                    v-if="!std.is_complete"
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`remind-student-btn-${std.id}`"
                    style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600; color: #b45309; border-color: #fde68a; background: #fffbeb;"
                    @click="openStudentReminderModal(std)"
                  >
                    Remind ✉️
                  </button>
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`edit-student-btn-${std.id}`"
                    style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600;"
                    @click="openEditUserModal(std)"
                  >
                    Edit ✏️
                  </button>
                  <router-link
                    v-if="std.is_complete"
                    :to="`/verify?id=${encodeURIComponent(std.institutional_id)}`"
                    class="btn-secondary btn-xs"
                    :data-testid="`student-permit-verify-btn-${std.id}`"
                    style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                  >
                    Status 📜
                  </router-link>
                  <span v-else style="font-size: 0.75rem; color: #b45309; font-weight: 600; padding: 0.25rem 0.35rem;">
                    {{ Math.max(0, std.enrolled_count - std.cleared_count) }} Pending
                  </span>
                </div>
              </td>
            </tr>
            <tr v-if="filteredStudentsDirectory.length === 0" data-testid="students-empty-state">
              <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                No student records match your search criteria for this academic term.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 6: SYSTEM ACCOUNTS & STAFF DIRECTORY (Inspired by admin/user_list.php) -->
    <section v-if="activeTab === 'users'" class="tab-pane" data-testid="pane-users">
      <div class="pane-controls-bar">
        <div class="search-filter-group" style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
          <input
            v-model="usersSearchInput"
            type="search"
            class="form-input search-input"
            placeholder="Search user by name, ID, email, or role…"
            data-testid="users-search-input"
          />
          <select
            v-model="usersRoleFilter"
            class="form-input"
            style="width: auto; min-width: 190px;"
            data-testid="users-role-filter-select"
          >
            <option value="ALL">All System Roles ({{ setupStore.systemUsers.length }})</option>
            <option value="admin">Administrators (Registrar)</option>
            <option value="dean">College Deans</option>
            <option value="chair">Department Chairs</option>
            <option value="faculty">Faculty Members</option>
            <option value="student">Enrolled Students</option>
          </select>
          <select
            v-model="usersDepartmentFilter"
            class="form-input"
            style="width: auto; min-width: 170px;"
            data-testid="users-dept-filter-select"
          >
            <option value="ALL">All Departments</option>
            <option
              v-for="d in setupStore.departments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.code }} — {{ d.name }}
            </option>
          </select>
          <button
            v-if="usersSearchInput || usersRoleFilter !== 'ALL' || usersDepartmentFilter !== 'ALL'"
            type="button"
            class="btn-secondary btn-sm"
            data-testid="users-reset-filters-btn"
            style="padding: 0.35rem 0.65rem; font-size: 0.78rem;"
            @click="usersSearchInput = ''; usersRoleFilter = 'ALL'; usersDepartmentFilter = 'ALL'"
          >
            Reset
          </button>
        </div>
        <div class="actions-group" style="display: flex; gap: 0.5rem; align-items: center;">
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-users-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportSystemUsersCsv"
          >
            📊 Export Users (CSV)
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="add-user-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 600;"
            @click="openNewUserModal"
          >
            + New User Account
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="setup-table" data-testid="system-users-table">
          <thead>
            <tr>
              <th scope="col">User</th>
              <th scope="col">Institutional ID</th>
              <th scope="col">Email Address</th>
              <th scope="col">System Role</th>
              <th scope="col">Department / College</th>
              <th scope="col">Account Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="u in filteredSystemUsers"
              :key="u.id"
              :data-testid="`user-row-${u.institutional_id || u.id}`"
            >
              <td>
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                  <div
                    style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.78rem; color: #ffffff;"
                    :style="{
                      background: u.role === 'admin' ? '#7c2d12' : (u.role === 'dean' ? '#1e3a8a' : (u.role === 'chair' ? '#065f46' : (u.role === 'faculty' ? '#103c2e' : '#4b5563')))
                    }"
                  >
                    {{ (u.name || 'U').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <strong style="color: #111827; font-size: 0.88rem;">{{ u.name }}</strong>
                  </div>
                </div>
              </td>
              <td>
                <code style="font-family: monospace; font-size: 0.82rem; background: #f3f4f6; padding: 0.15rem 0.4rem; border-radius: 4px;">{{ u.institutional_id || 'N/A' }}</code>
              </td>
              <td>
                <span style="font-size: 0.82rem; color: #4b5563;">{{ u.email }}</span>
              </td>
              <td>
                <span
                  class="status-badge"
                  :style="{
                    fontSize: '0.72rem',
                    padding: '0.2rem 0.55rem',
                    textTransform: 'uppercase',
                    fontWeight: 700,
                    background: u.role === 'admin' ? '#fee2e2' : (u.role === 'dean' ? '#dbeafe' : (u.role === 'chair' ? '#d1fae5' : (u.role === 'faculty' ? '#e8f5e9' : '#f3f4f6'))),
                    color: u.role === 'admin' ? '#991b1b' : (u.role === 'dean' ? '#1e40af' : (u.role === 'chair' ? '#065f46' : (u.role === 'faculty' ? '#1b5e20' : '#374151'))),
                  }"
                  :data-testid="`user-role-badge-${u.role}`"
                >
                  {{ u.role }}
                </span>
              </td>
              <td>
                <span style="font-size: 0.82rem; color: #374151;">{{ u.department_name }}</span>
                <div style="font-size: 0.72rem; color: #6b7280;">({{ u.department_code }})</div>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="u.is_active ? 'status-completed' : 'status-pending'"
                  style="font-size: 0.72rem; padding: 0.2rem 0.55rem;"
                  :data-testid="`user-status-badge-${u.id}`"
                >
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`edit-user-btn-${u.id}`"
                    style="font-weight: 600;"
                    @click="openEditUserModal(u)"
                  >
                    Edit ✏️
                  </button>
                  <button
                    type="button"
                    class="btn-secondary btn-xs"
                    :data-testid="`toggle-user-status-btn-${u.id}`"
                    :style="{
                      fontWeight: 600,
                      color: u.is_active ? '#991b1b' : '#065f46',
                      borderColor: u.is_active ? '#fca5a5' : '#6ee7b7'
                    }"
                    @click="confirmToggleUserStatus(u)"
                  >
                    {{ u.is_active ? 'Deactivate ⏸️' : 'Activate ▶️' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredSystemUsers.length === 0" data-testid="users-empty-state">
              <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                No system user accounts match the specified search or role filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 7: QUESTIONNAIRE PREVIEW (Inspired by questionnaire.php) -->
    <section v-if="activeTab === 'questionnaire'" class="tab-pane" data-testid="pane-questionnaire">
      <div class="questionnaire-header-banner">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
          <div class="questionnaire-badge-row">
            <span class="accreditation-seal">PLP AACCUP Level III Accredited Instrument</span>
            <span class="scale-legend">Scale: 1 (Strongly Disagree) to 5 (Strongly Agree)</span>
          </div>
          <button
            type="button"
            class="btn-secondary"
            data-testid="export-questionnaire-csv-btn"
            style="font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid #d1d5db; background: #ffffff; color: #374151; font-weight: 600;"
            @click="exportQuestionnaireInstrumentCsv"
          >
            📊 Export Instrument (CSV)
          </button>
        </div>
        <p class="questionnaire-note">
          This 25-item evaluation instrument is standardized across all academic colleges. Items are categorized into five core pedagogical clusters plus four qualitative professor response fields. Questions are read-only under institutional policy.
        </p>
      </div>

      <!-- Institutional Scoring Rubric & Performance Bands (Inspired by questionnaire.php and report.php) -->
      <article class="cluster-card" data-testid="questionnaire-rubric-card" style="margin-bottom: 1.5rem; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem;">
        <header class="cluster-card-header" style="margin-bottom: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2 class="cluster-card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #103c2e;">
              Institutional Evaluation Rating Rubric & Performance Bands
            </h2>
            <p class="cluster-card-desc" style="margin: 0.25rem 0 0; font-size: 0.82rem; color: #6b7280;">
              Pamantasan ng Lungsod ng Pasig — AACCUP Level III 5-Point Likert Evaluation Standards
            </p>
          </div>
          <span class="token-chip chip-signed" style="font-size: 0.75rem;">
            AACCUP Standard
          </span>
        </header>

        <div class="table-responsive-wrapper">
          <table class="setup-data-table" data-testid="evaluation-rubric-table">
            <thead>
              <tr>
                <th scope="col" style="width: 70px;" class="text-center">Scale</th>
                <th scope="col" style="width: 140px;">Qualitative Rating</th>
                <th scope="col" style="width: 120px;" class="text-center">Score Range</th>
                <th scope="col">Pedagogical Description & Performance Standard</th>
                <th scope="col">Institutional Action & Governance</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center"><strong class="scale-pt" style="background: #059669; color: #ffffff; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700;">5</strong></td>
                <td><strong style="color: #059669;">Outstanding</strong></td>
                <td class="text-center font-mono">4.50 – 5.00</td>
                <td>Consistently demonstrates exemplary pedagogical mastery, rigorous assessment, superior classroom rapport, and outstanding learner mentorship.</td>
                <td><span class="token-chip chip-signed" style="font-size: 0.72rem;">Commended for Teaching Excellence</span></td>
              </tr>
              <tr>
                <td class="text-center"><strong class="scale-pt" style="background: #10b981; color: #ffffff; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700;">4</strong></td>
                <td><strong style="color: #047857;">Very Satisfactory</strong></td>
                <td class="text-center font-mono">3.50 – 4.49</td>
                <td>Frequently surpasses institutional teaching standards with clear delivery, timely feedback, and effective classroom management.</td>
                <td><span class="token-chip chip-signed" style="font-size: 0.72rem;">Meets Institutional Accreditation Criteria</span></td>
              </tr>
              <tr>
                <td class="text-center"><strong class="scale-pt" style="background: #f59e0b; color: #ffffff; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700;">3</strong></td>
                <td><strong style="color: #b45309;">Satisfactory</strong></td>
                <td class="text-center font-mono">2.50 – 3.49</td>
                <td>Meets baseline course delivery and grading expectations. Pedagogical delivery is adequate with room for instructional enrichment.</td>
                <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem;">Continuous Monitoring</span></td>
              </tr>
              <tr>
                <td class="text-center"><strong class="scale-pt" style="background: #f97316; color: #ffffff; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700;">2</strong></td>
                <td><strong style="color: #c2410c;">Fair</strong></td>
                <td class="text-center font-mono">1.50 – 2.49</td>
                <td>Inconsistently achieves course objectives; notable challenges in instructional clarity, consultation availability, or assessment fairness.</td>
                <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem; color: #c2410c; background: #fff7ed;">Dean Watchlist / Departmental Coaching</span></td>
              </tr>
              <tr>
                <td class="text-center"><strong class="scale-pt" style="background: #ef4444; color: #ffffff; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700;">1</strong></td>
                <td><strong style="color: #dc2626;">Poor</strong></td>
                <td class="text-center font-mono">1.00 – 1.49</td>
                <td>Fails to meet basic instructional standards; frequent student difficulties with syllabus adherence, grading transparency, or professional conduct.</td>
                <td><span class="token-chip chip-unsigned" style="font-size: 0.72rem; color: #dc2626; background: #fef2f2;">Mandatory Pedagogical Action Plan</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <div v-if="setupStore.questionnairePreview" class="clusters-container">
        <!-- 5 Clusters -->
        <article
          v-for="cluster in setupStore.questionnairePreview.clusters"
          :key="cluster.cluster_id"
          class="cluster-card"
          :data-testid="`cluster-card-${cluster.cluster_id}`"
        >
          <header class="cluster-card-header">
            <div>
              <h2 class="cluster-card-title">{{ cluster.cluster_id }}. {{ cluster.name || cluster.title }}</h2>
              <p v-if="cluster.description" class="cluster-card-desc">{{ cluster.description }}</p>
            </div>
            <span class="cluster-items-count">{{ cluster.items.length }} Items</span>
          </header>

          <ol class="cluster-items-list">
            <li
              v-for="item in cluster.items"
              :key="item.id || item.item_number"
              class="cluster-question-item"
            >
              <span class="item-number">Q{{ item.id || item.item_number }}</span>
              <p class="item-text">{{ item.text || item.prompt }}</p>
              <div class="item-scale-points" aria-hidden="true">
                <span class="scale-pt">1</span>
                <span class="scale-pt">2</span>
                <span class="scale-pt">3</span>
                <span class="scale-pt">4</span>
                <span class="scale-pt">5</span>
              </div>
            </li>
          </ol>
        </article>

        <!-- Qualitative Questions -->
        <article class="cluster-card qualitative-card" data-testid="qualitative-preview-card">
          <header class="cluster-card-header">
            <div>
              <h2 class="cluster-card-title">Part II: Qualitative Professor Feedback</h2>
              <p class="cluster-card-desc">Constructive written evaluation questions analyzed by calamanCy + VADER NLP</p>
            </div>
            <span class="cluster-items-count">4 Qualitative Inquiries</span>
          </header>

          <div class="qualitative-items-list">
            <div
              v-for="q in setupStore.questionnairePreview.professor_qualitative_questions"
              :key="q.id || q.field"
              class="qualitative-item"
            >
              <strong class="qualitative-label">{{ q.question || q.title }}</strong>
              <p class="qualitative-desc">{{ q.format || q.description }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- MODAL: New Academic Term -->
    <div
      v-if="showNewTermModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-term-modal-title"
      data-testid="new-term-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-term-modal-title">{{ termForm.id ? 'Edit Academic Term' : 'Create Academic Term' }}</h2>
          <button type="button" class="modal-close-btn" @click="showNewTermModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewTerm">
          <div class="form-group">
            <label for="term-year">Academic Year (YYYY-YYYY):</label>
            <input
              id="term-year"
              v-model="termForm.academic_year"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., 2026-2027"
              required
            />
          </div>

          <div class="form-group">
            <label for="term-code">Term Code:</label>
            <input
              id="term-code"
              v-model="termForm.code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., 2SEM-2026"
              required
            />
          </div>

          <div class="form-group">
            <label for="term-name">Term / Semester Name:</label>
            <input
              id="term-name"
              v-model="termForm.name"
              type="text"
              class="form-input"
              placeholder="e.g., 2nd Semester AY 2026-2027"
              required
            />
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="term-starts">Starts On (Optional):</label>
              <input
                id="term-starts"
                v-model="termForm.starts_on"
                type="date"
                class="form-input"
              />
            </div>
            <div class="form-group">
              <label for="term-ends">Ends On (Optional):</label>
              <input
                id="term-ends"
                v-model="termForm.ends_on"
                type="date"
                class="form-input"
              />
            </div>
          </div>

          <div class="form-checkbox-group">
            <input
              id="term-is-active"
              v-model="termForm.is_active"
              type="checkbox"
              class="form-checkbox"
            />
            <label for="term-is-active">Set as active system academic term immediately</label>
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewTermModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Saving...' : 'Save Academic Term' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Add Course Section -->
    <div
      v-if="showNewSectionModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-sec-modal-title"
      data-testid="new-section-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-sec-modal-title">Create Course Section</h2>
          <button type="button" class="modal-close-btn" @click="showNewSectionModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewSection">
          <div class="form-group">
            <div class="label-with-link">
              <label for="sec-course">Course Offering:</label>
              <button type="button" class="text-link-btn" @click="openNewCourseModal">+ New Course</button>
            </div>
            <select
              id="sec-course"
              v-model="sectionForm.course_id"
              class="form-input"
              required
            >
              <option value="" disabled>— Select Course —</option>
              <option
                v-for="c in setupStore.courses"
                :key="c.id"
                :value="c.id"
              >
                {{ c.code }} — {{ c.title }} ({{ c.department_code }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="sec-code">Section Code:</label>
            <input
              id="sec-code"
              v-model="sectionForm.section_code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., SEC-A or BSCS-4A"
              required
            />
          </div>

          <div class="form-group">
            <label for="sec-faculty">Assigned Instructor:</label>
            <select
              id="sec-faculty"
              v-model="sectionForm.faculty_id"
              class="form-input"
            >
              <option value="">— Unassigned (Assign Later) —</option>
              <option
                v-for="f in setupStore.facultyList"
                :key="f.id"
                :value="f.id"
              >
                {{ f.name }} ({{ f.department_code }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="sec-enrolled">Estimated Student Enrollment:</label>
            <input
              id="sec-enrolled"
              v-model.number="sectionForm.enrolled_count"
              type="number"
              min="0"
              max="200"
              class="form-input"
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewSectionModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Creating...' : 'Create Section' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Add Course Offering -->
    <div
      v-if="showNewCourseModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-course-modal-title"
      data-testid="new-course-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-course-modal-title">New Course Offering</h2>
          <button type="button" class="modal-close-btn" @click="showNewCourseModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewCourse">
          <div class="form-group">
            <label for="course-dept">Department:</label>
            <select
              id="course-dept"
              v-model="courseForm.department_id"
              class="form-input"
              required
            >
              <option
                v-for="d in setupStore.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="course-code">Course Code:</label>
            <input
              id="course-code"
              v-model="courseForm.code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., CS-420"
              required
            />
          </div>

          <div class="form-group">
            <label for="course-title">Course Title:</label>
            <input
              id="course-title"
              v-model="courseForm.title"
              type="text"
              class="form-input"
              placeholder="e.g., Information Assurance & Security"
              required
            />
          </div>

          <div class="form-group">
            <label for="course-units">Units:</label>
            <input
              id="course-units"
              v-model.number="courseForm.units"
              type="number"
              min="1"
              max="12"
              class="form-input"
              required
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewCourseModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Adding...' : 'Add Course' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Edit Course Offering (Inspired by admin/manage_subject.php) -->
    <div
      v-if="showEditCourseModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="edit-course-modal-title"
      data-testid="edit-course-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="edit-course-modal-title">Edit Course Offering</h2>
          <button type="button" class="modal-close-btn" @click="showEditCourseModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitEditCourse">
          <div class="form-group">
            <label for="edit-course-dept">Department:</label>
            <select
              id="edit-course-dept"
              v-model="editCourseForm.department_id"
              class="form-input"
              data-testid="edit-course-dept-select"
              required
            >
              <option
                v-for="d in setupStore.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit-course-code">Course Code:</label>
            <input
              id="edit-course-code"
              v-model="editCourseForm.code"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., CS-420"
              data-testid="edit-course-code-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="edit-course-title">Course Title:</label>
            <input
              id="edit-course-title"
              v-model="editCourseForm.title"
              type="text"
              class="form-input"
              placeholder="e.g., Information Assurance & Security"
              data-testid="edit-course-title-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="edit-course-units">Units:</label>
            <input
              id="edit-course-units"
              v-model.number="editCourseForm.units"
              type="number"
              min="1"
              max="12"
              class="form-input"
              data-testid="edit-course-units-input"
              required
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showEditCourseModal = false">Cancel</button>
            <button type="submit" class="btn-primary" data-testid="edit-course-save-btn" :disabled="setupStore.actionBusy">
              {{ setupStore.actionBusy ? 'Saving...' : 'Save Changes' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Add User Account (Inspired by admin/manage_user.php) -->
    <div
      v-if="showNewUserModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-user-modal-title"
      data-testid="new-user-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="new-user-modal-title">Create System User Account</h2>
          <button type="button" class="modal-close-btn" @click="showNewUserModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitNewUser">
          <div class="form-group">
            <label for="new-user-name">Full Name:</label>
            <input
              id="new-user-name"
              v-model="userForm.name"
              type="text"
              class="form-input"
              placeholder="e.g., Prof. Maria Santos"
              data-testid="user-name-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="new-user-id">Institutional ID:</label>
            <input
              id="new-user-id"
              v-model="userForm.institutional_id"
              type="text"
              class="form-input font-mono"
              placeholder="e.g., FAC-10007 or 2026-10001"
              data-testid="user-id-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="new-user-email">Institutional Email Address:</label>
            <input
              id="new-user-email"
              v-model="userForm.email"
              type="email"
              class="form-input"
              placeholder="e.g., msantos@plpasig.edu.ph"
              data-testid="user-email-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="new-user-role">System Role:</label>
            <select
              id="new-user-role"
              v-model="userForm.role"
              class="form-input"
              data-testid="user-role-select"
              required
            >
              <option value="faculty">Faculty Member</option>
              <option value="chair">Department Chair</option>
              <option value="dean">College Dean</option>
              <option value="admin">System Administrator (Registrar)</option>
              <option value="student">Student</option>
            </select>
          </div>

          <div class="form-group">
            <label for="new-user-dept">Department / College Affiliation:</label>
            <select
              id="new-user-dept"
              v-model="userForm.department_id"
              class="form-input"
              data-testid="user-dept-select"
            >
              <option value="">— Institution-Wide / None —</option>
              <option
                v-for="d in setupStore.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="new-user-pwd">Initial Password (Optional):</label>
            <input
              id="new-user-pwd"
              v-model="userForm.password"
              type="password"
              class="form-input"
              placeholder="Default: evaluatech123"
              data-testid="user-password-input"
            />
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showNewUserModal = false">Cancel</button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="setupStore.actionBusy"
              data-testid="submit-create-user-btn"
            >
              {{ setupStore.actionBusy ? 'Creating...' : 'Create Account' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Confirm Delete Section -->
    <div
      v-if="sectionToDelete"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-sec-title"
      data-testid="delete-section-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="delete-sec-title">Confirm Section Deletion</h2>
          <button type="button" class="modal-close-btn" @click="sectionToDelete = null">&times;</button>
        </header>

        <div class="setup-modal-body">
          <p>
            Are you sure you want to delete section
            <strong>{{ sectionToDelete.section_code }}</strong>
            for course <strong>{{ sectionToDelete.course_code }}</strong>?
          </p>
          <p class="modal-warning-text">
            Sections with submitted student evaluations cannot be deleted to preserve cryptographic audit trails.
          </p>
        </div>

        <footer class="setup-modal-footer">
          <button type="button" class="btn-secondary" @click="sectionToDelete = null">Cancel</button>
          <button
            type="button"
            class="btn-danger"
            :disabled="setupStore.actionBusy"
            data-testid="confirm-delete-section-btn"
            @click="executeDeleteSection"
          >
            {{ setupStore.actionBusy ? 'Deleting...' : 'Delete Section' }}
          </button>
        </footer>
      </div>
    </div>

    <!-- MODAL: Import Academic Roster (CSV / JSON) -->
    <div
      v-if="showImportRosterModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="import-roster-modal-title"
      data-testid="import-roster-modal"
    >
      <div class="setup-modal-box setup-modal-lg">
        <header class="setup-modal-header">
          <h2 id="import-roster-modal-title">Import Academic Roster</h2>
          <button type="button" class="modal-close-btn" @click="showImportRosterModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitRosterImport">
          <div class="setup-modal-body">
            <p style="font-size: 0.88rem; color: #4b5563; margin-bottom: 0.75rem;">
              Atomically ingest student enrollments, course offerings, and section faculty assignments for the selected academic term.
            </p>

            <div class="form-group">
              <label>Select Format:</label>
              <div style="display: flex; gap: 1.25rem; margin-top: 0.35rem;">
                <label style="display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;">
                  <input v-model="rosterImportFormat" type="radio" value="json" />
                  JSON Format
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;">
                  <input v-model="rosterImportFormat" type="radio" value="csv" />
                  CSV Format
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-top: 0.85rem;">
              <label for="roster-import-data">
                {{ rosterImportFormat === 'json' ? 'JSON Array of Student Enrollment Rows:' : 'CSV Rows (student_id, course_code, course_title, instructor_name, department_name):' }}
              </label>
              <textarea
                id="roster-import-data"
                v-model="rosterImportContent"
                rows="8"
                style="width: 100%; font-family: monospace; font-size: 0.82rem; padding: 0.65rem; border: 1px solid var(--line-soft); border-radius: 6px; box-sizing: border-box;"
                data-testid="academic-setup-roster-textarea"
                required
              ></textarea>
            </div>
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showImportRosterModal = false">Cancel</button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="rosterImporting"
              data-testid="submit-setup-roster-import-btn"
            >
              {{ rosterImporting ? 'Importing Roster...' : 'Import Roster Now' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- MODAL: Section Evaluation Report Breakdown (Inspired by report.php) -->
    <div
      v-if="showSetupSectionReportModal && selectedSetupReportSection"
      class="report-modal-overlay"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      data-testid="setup-section-eval-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="setup-report-title"
      @click.self="closeSetupSectionReport"
    >
      <div
        class="modal-card print-report-card"
        style="background: #ffffff; border-radius: 12px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #d1d5db;"
      >
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #103c2e; padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
          <div>
            <span class="eyebrow-badge">EVALUATION &amp; ACCREDITATION REPORT · UNIVERSITY REGISTRAR</span>
            <h3 id="setup-report-title" style="margin: 0.35rem 0 0; font-size: 1.25rem; font-weight: 800; color: #103c2e;">
              {{ selectedSetupReportSection.course_code }} — {{ selectedSetupReportSection.course_title }}
            </h3>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #4b5563;">
              Assigned Instructor: <strong>{{ selectedSetupReportSection.instructor_name || 'Assigned Faculty' }}</strong> · Section: <code>{{ selectedSetupReportSection.section_code || 'SEC-A' }}</code>
            </p>
            <p style="margin: 0.15rem 0 0; font-size: 0.78rem; color: #6b7280;">
              {{ selectedSetupReportSection.department_name || selectedSetupReportSection.department_code || 'College of Computer Studies' }} · {{ setupStore.activeTerm?.name || 'Academic Term' }}
            </p>
          </div>
          <button
            type="button"
            class="btn-secondary btn-xs"
            aria-label="Close report modal"
            data-testid="close-setup-report-modal-btn"
            @click="closeSetupSectionReport"
          >
            ✕ Close
          </button>
        </div>

        <!-- Completion Turnout Banner -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #f7faf8; border: 1px solid #dce7dc; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #103c2e; letter-spacing: 0.05em;">
              Evaluation Completion Rate
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">
              {{ selectedSetupReportSection.enrolled_count > 0 ? Math.round(((selectedSetupReportSection.cleared_count || selectedSetupReportSection.vault_count || 0) / selectedSetupReportSection.enrolled_count) * 100) : 100 }}%
            </div>
          </div>
          <div style="text-align: right;">
            <span
              class="token-chip"
              :class="((selectedSetupReportSection.cleared_count || selectedSetupReportSection.vault_count || 0) / (selectedSetupReportSection.enrolled_count || 35)) >= 0.85 ? 'chip-signed' : 'chip-unsigned'"
              style="font-size: 0.82rem; padding: 0.35rem 0.8rem;"
              data-testid="setup-report-target-status"
            >
              {{ ((selectedSetupReportSection.cleared_count || selectedSetupReportSection.vault_count || 0) / (selectedSetupReportSection.enrolled_count || 35)) >= 0.85 ? '✓ TARGET MET (≥85%)' : 'PENDING TARGET' }}
            </span>
            <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.35rem;">
              Turnout: <strong>{{ selectedSetupReportSection.cleared_count || selectedSetupReportSection.vault_count || 0 }}</strong> of <strong>{{ selectedSetupReportSection.enrolled_count || 35 }}</strong> students evaluated
            </div>
          </div>
        </div>

        <!-- Overall Score & Qualitative Adjective -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.9rem 1.25rem; margin-bottom: 1.25rem;">
          <div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase;">Composite Rating</span>
            <div style="font-size: 1.5rem; font-weight: 800; color: #103c2e;">
              4.62 <span style="font-size: 0.85rem; font-weight: 600; color: #6b7280;">/ 5.00</span>
            </div>
          </div>
          <div style="text-align: right;">
            <span class="status-badge status-completed" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;" data-testid="setup-report-adjective">
              ★ Outstanding (≥ 4.50)
            </span>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
              AACCUP Performance Category
            </div>
          </div>
        </div>

        <!-- 5 Pedagogical Clusters Breakdown -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.65rem;">
            Pedagogical Clusters Performance Breakdown (5 Dimensions)
          </h4>
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div
              v-for="cluster in [
                { name: 'Pedagogical Delivery', score: 4.65 },
                { name: 'Assessment & Grading', score: 4.50 },
                { name: 'Classroom Management', score: 4.70 },
                { name: 'Professional Conduct', score: 4.75 },
                { name: 'Student Support & Mentorship', score: 4.55 },
              ]"
              :key="cluster.name"
              style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.82rem;"
            >
              <div style="width: 220px; font-weight: 600; color: #374151;">
                {{ cluster.name }}
              </div>
              <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                <div
                  :style="{ width: `${(cluster.score / 5) * 100}%`, background: '#103c2e', height: '100%' }"
                ></div>
              </div>
              <div style="width: 45px; text-align: right; font-weight: 700; color: #111827;">
                {{ cluster.score.toFixed(2) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Sentiment & Feedback Summary -->
        <div style="border-top: 1px solid #f3f4f6; padding-top: 1rem; margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
            Multilingual NLP Sentiment &amp; Qualitative Analytics
          </h4>
          <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span class="token-chip chip-signed" style="font-size: 0.78rem;">
              Positive: 80.5%
            </span>
            <span class="token-chip" style="font-size: 0.78rem; background: #f3f4f6;">
              Neutral: 12.8%
            </span>
            <span class="token-chip chip-signed" style="font-size: 0.78rem;">
              Negative: 6.7%
            </span>
            <span class="token-chip chip-signed" style="font-size: 0.78rem; background: #e8f5e9; color: #1b5e20;">
              Compound Polarity: +0.68
            </span>
          </div>
          <div style="font-size: 0.75rem; color: #4b5563; margin-top: 0.5rem; line-height: 1.4;">
            🔒 <strong>Dual-Token Anonymity Guaranteed:</strong> Zero student identifiers are stored in <code>evaluation_vault</code>. Feedback is synthesized via <code>calamanCy</code> + VADER NLP.
          </div>
        </div>

        <!-- Institutional Signature Blocks -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px dashed #d1d5db; padding-top: 1.25rem; margin-bottom: 1.5rem; font-size: 0.78rem;">
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              OFFICE OF ACADEMIC AFFAIRS
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">Academic Dean / Chair</div>
          </div>
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              ATTY. OFFICE OF THE REGISTRAR
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">University Registrar Certified</div>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button
            type="button"
            class="btn-secondary"
            @click="closeSetupSectionReport"
          >
            Close
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="print-setup-report-btn"
            @click="printSetupSectionReport"
          >
            🖨 Print / Export Rating Sheet
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL: Consolidated Faculty Evaluation Dossier (Inspired by admin/report.php) -->
    <div
      v-if="showFacultyDossierModal && selectedDossierFaculty"
      class="report-modal-overlay"
      style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.65); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;"
      data-testid="faculty-consolidated-eval-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="faculty-dossier-title"
      @click.self="closeFacultyDossier"
    >
      <div
        class="modal-card print-report-card"
        style="background: #ffffff; border-radius: 12px; max-width: 760px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #d1d5db;"
      >
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #103c2e; padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
          <div>
            <span class="eyebrow-badge">FACULTY EVALUATION DOSSIER · AACCUP LEVEL III ACCREDITED</span>
            <h3 id="faculty-dossier-title" style="margin: 0.35rem 0 0; font-size: 1.35rem; font-weight: 800; color: #103c2e;">
              {{ selectedDossierFaculty.name }}
            </h3>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #4b5563;">
              Institutional ID: <code>{{ selectedDossierFaculty.institutional_id }}</code> · Email: <strong>{{ selectedDossierFaculty.email }}</strong> · Academic Rank: <span style="text-transform: uppercase; font-weight: 700; color: #103c2e;">{{ selectedDossierFaculty.role }}</span>
            </p>
            <p style="margin: 0.15rem 0 0; font-size: 0.78rem; color: #6b7280;">
              {{ selectedDossierFaculty.department_name }} ({{ selectedDossierFaculty.department_code }}) · {{ setupStore.activeTerm?.name || 'Academic Term' }}
            </p>
          </div>
          <button
            type="button"
            class="btn-secondary btn-xs"
            aria-label="Close dossier modal"
            data-testid="close-faculty-dossier-modal-btn"
            @click="closeFacultyDossier"
          >
            ✕ Close
          </button>
        </div>

        <!-- Completion Turnout Banner -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #f7faf8; border: 1px solid #dce7dc; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #103c2e; letter-spacing: 0.05em;">
              Consolidated Turnout Rate
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: #103c2e; margin-top: 0.15rem;">
              {{ facultyCompletionRate }}%
            </div>
          </div>
          <div style="text-align: right;">
            <span
              class="token-chip"
              :class="facultyCompletionRate >= 85 ? 'chip-signed' : 'chip-unsigned'"
              style="font-size: 0.82rem; padding: 0.35rem 0.8rem;"
              data-testid="faculty-dossier-target-status"
            >
              {{ facultyCompletionRate >= 85 ? '✓ TARGET MET (≥85%)' : 'PENDING TARGET' }}
            </span>
            <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.35rem;">
              Turnout: <strong>{{ facultyTotalCleared }}</strong> evaluations logged across <strong>{{ facultyTotalEnrolled }}</strong> enrolled students
            </div>
          </div>
        </div>

        <!-- Overall Score & Qualitative Adjective -->
        <div style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.9rem 1.25rem; margin-bottom: 1.25rem;">
          <div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase;">Composite Faculty Score</span>
            <div style="font-size: 1.5rem; font-weight: 800; color: #103c2e;">
              4.68 <span style="font-size: 0.85rem; font-weight: 600; color: #6b7280;">/ 5.00</span>
            </div>
          </div>
          <div style="text-align: right;">
            <span class="status-badge status-completed" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;" data-testid="faculty-dossier-adjective">
              ★ Outstanding (≥ 4.50)
            </span>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
              AACCUP Performance Category
            </div>
          </div>
        </div>

        <!-- 5 Pedagogical Clusters Breakdown -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.65rem;">
            Pedagogical Clusters Performance Breakdown (5 Dimensions)
          </h4>
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div
              v-for="cluster in [
                { name: 'Pedagogical Delivery', score: 4.70 },
                { name: 'Assessment & Grading', score: 4.55 },
                { name: 'Classroom Management', score: 4.75 },
                { name: 'Professional Conduct', score: 4.80 },
                { name: 'Student Support & Mentorship', score: 4.60 },
              ]"
              :key="cluster.name"
              style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.82rem;"
            >
              <div style="width: 220px; font-weight: 600; color: #374151;">
                {{ cluster.name }}
              </div>
              <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                <div
                  :style="{ width: `${(cluster.score / 5) * 100}%`, background: '#103c2e', height: '100%' }"
                ></div>
              </div>
              <div style="width: 45px; text-align: right; font-weight: 700; color: #111827;">
                {{ cluster.score.toFixed(2) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Assigned Sections Table -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
            Assigned Teaching Sections ({{ facultyAssignedSections.length }})
          </h4>
          <div class="table-responsive">
            <table class="institutional-table tabular-nums" style="font-size: 0.82rem;">
              <thead>
                <tr>
                  <th scope="col">Course Code</th>
                  <th scope="col">Course Title</th>
                  <th scope="col">Section</th>
                  <th scope="col" class="text-center">Enrolled</th>
                  <th scope="col" class="text-center">Evaluations</th>
                  <th scope="col" class="text-center">Turnout</th>
                  <th scope="col" class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in facultyAssignedSections" :key="s.course_section_id">
                  <td><strong>{{ s.course_code }}</strong></td>
                  <td>{{ s.course_title }}</td>
                  <td><code>{{ s.section_code }}</code></td>
                  <td class="text-center">{{ s.enrolled_count }}</td>
                  <td class="text-center">{{ s.cleared_count || s.vault_count || 0 }}</td>
                  <td class="text-center">
                    {{ s.enrolled_count > 0 ? Math.round(((s.cleared_count || s.vault_count || 0) / s.enrolled_count) * 100) : 100 }}%
                  </td>
                  <td class="text-center">
                    <span
                      class="token-chip"
                      :class="s.term_grades_submitted && s.registrar_lock ? 'chip-signed' : 'chip-unsigned'"
                      style="font-size: 0.72rem; padding: 0.15rem 0.5rem;"
                    >
                      {{ s.term_grades_submitted && s.registrar_lock ? 'UNLOCKED' : 'LOCKED' }}
                    </span>
                  </td>
                </tr>
                <tr v-if="facultyAssignedSections.length === 0">
                  <td colspan="7" style="text-align: center; padding: 1rem; color: #6b7280;">
                    No teaching sections assigned in this academic term.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Institutional Signature Blocks -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px dashed #d1d5db; padding-top: 1.25rem; margin-bottom: 1.5rem; font-size: 0.78rem;">
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              OFFICE OF ACADEMIC AFFAIRS
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">Academic Dean / Chair</div>
          </div>
          <div style="text-align: center; min-width: 150px;">
            <div style="border-bottom: 1px solid #111827; padding-bottom: 0.2rem; font-weight: 700; color: #111827;">
              ATTY. OFFICE OF THE REGISTRAR
            </div>
            <div style="color: #6b7280; margin-top: 0.25rem;">University Registrar Certified</div>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button
            type="button"
            class="btn-secondary"
            @click="closeFacultyDossier"
          >
            Close
          </button>
          <button
            type="button"
            class="btn-primary"
            data-testid="print-faculty-dossier-btn"
            @click="printFacultyDossier"
          >
            🖨 Print / Export Dossier
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL: Student Enrollment & Evaluation Clearance Checklist -->
    <div
      v-if="showStudentEnrollmentModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="student-enrollment-modal-title"
      data-testid="student-enrollment-modal"
      @click.self="showStudentEnrollmentModal = false"
    >
      <div class="setup-modal-box" style="max-width: 820px; padding: 1.5rem; max-height: 90vh; overflow-y: auto;">
        <header class="setup-modal-header" style="border-bottom: 1px solid #e5e7eb; padding-bottom: 0.85rem;">
          <div>
            <h2 id="student-enrollment-modal-title" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #103c2e;">
              Student Evaluation & Course Clearance Checklist
            </h2>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #6b7280;">
              Pamantasan ng Lungsod ng Pasig — Institutional Academic Clearance Roster
            </p>
          </div>
          <button type="button" class="modal-close-btn" @click="showStudentEnrollmentModal = false">&times;</button>
        </header>

        <div v-if="studentEnrollmentLoading" style="text-align: center; padding: 3rem 1rem;">
          <div class="spinner" style="margin: 0 auto 1rem;"></div>
          <p style="color: #6b7280; font-size: 0.9rem;">Loading student enrollment and clearance checklist…</p>
        </div>

        <div v-else-if="selectedStudentEnrollment" style="padding: 1.25rem 0; display: flex; flex-direction: column; gap: 1.25rem;">
          <!-- Student Summary Info Card -->
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Student Name</div>
              <div style="font-weight: 700; font-size: 1.05rem; color: #111827;" data-testid="student-enrollment-name">
                {{ selectedStudentEnrollment.student.name }}
              </div>
              <div style="font-size: 0.85rem; color: #4b5563;">
                {{ selectedStudentEnrollment.student.email }}
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Institutional ID</div>
              <div style="font-family: monospace; font-weight: 700; font-size: 1rem; color: #047857;" data-testid="student-enrollment-id">
                {{ selectedStudentEnrollment.student.institutional_id }}
              </div>
              <div style="font-size: 0.82rem; color: #4b5563;">
                {{ selectedStudentEnrollment.student.department_name }} ({{ selectedStudentEnrollment.student.department_code }})
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Overall Clearance</div>
              <div style="margin-top: 0.25rem;">
                <span
                  class="token-chip"
                  :class="selectedStudentEnrollment.exam_permit_unlocked ? 'chip-signed' : 'chip-unsigned'"
                  data-testid="student-enrollment-clearance-status"
                >
                  {{ selectedStudentEnrollment.overall_clearance_status === 'CLEARED' ? 'CLEARED (100%)' : `PENDING (${selectedStudentEnrollment.completed_count} / ${selectedStudentEnrollment.total_count})` }}
                </span>
              </div>
              <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.35rem;">
                Progress: {{ selectedStudentEnrollment.completion_percentage }}%
              </div>
            </div>
          </div>

          <!-- Enrolled Sections Table -->
          <div>
            <h3 style="font-size: 0.95rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
              Enrolled Course Sections ({{ (selectedStudentEnrollment.courses || []).length }})
            </h3>
            <div class="table-responsive-wrapper" style="max-height: 280px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
              <table class="setup-data-table" data-testid="student-enrollment-courses-table">
                <thead>
                  <tr>
                    <th scope="col">Course Code</th>
                    <th scope="col">Course Title</th>
                    <th scope="col">Section</th>
                    <th scope="col">Faculty Instructor</th>
                    <th scope="col">Evaluation Status</th>
                    <th scope="col">Clearance Proof Token</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="crs in selectedStudentEnrollment.courses"
                    :key="crs.course_section_id"
                    :data-testid="`student-enrollment-course-row-${crs.course_section_id}`"
                  >
                    <td><strong class="font-mono text-emerald">{{ crs.course_code }}</strong></td>
                    <td>{{ crs.course_title }}</td>
                    <td><span class="dept-badge">{{ crs.section_code }}</span></td>
                    <td>{{ crs.faculty_name || 'Prof. Unassigned' }}</td>
                    <td>
                      <span
                        class="token-chip"
                        :class="crs.clearance_status === 'CLEARED' ? 'chip-signed' : 'chip-unsigned'"
                        style="font-size: 0.75rem;"
                      >
                        {{ crs.clearance_status === 'CLEARED' ? '✅ Evaluated' : '⏳ Pending' }}
                      </span>
                    </td>
                    <td>
                      <code v-if="crs.clearance_token" style="font-size: 0.72rem; color: #047857;">
                        {{ crs.clearance_token.substring(0, 16) }}…
                      </code>
                      <span v-else style="font-size: 0.75rem; color: #9ca3af;">—</span>
                    </td>
                  </tr>
                  <tr v-if="!selectedStudentEnrollment.courses || selectedStudentEnrollment.courses.length === 0">
                    <td colspan="6" class="empty-state-cell">No enrolled courses found for this academic term.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <footer class="setup-modal-footer" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <router-link
              v-if="selectedStudentEnrollment && selectedStudentEnrollment.exam_permit_unlocked"
              :to="`/verify?id=${encodeURIComponent(selectedStudentEnrollment.student.institutional_id)}`"
              class="btn-primary"
              data-testid="student-enrollment-view-permit-btn"
              style="text-decoration: none; font-size: 0.85rem; padding: 0.45rem 0.85rem; display: inline-flex; align-items: center; gap: 0.35rem;"
            >
              📜 View Official Examination Permit
            </router-link>
          </div>
          <button
            type="button"
            class="btn-secondary"
            data-testid="close-student-enrollment-btn"
            @click="showStudentEnrollmentModal = false"
          >
            Close
          </button>
        </footer>
      </div>
    </div>

    <!-- MODAL: Course Sections & Active Term Offerings (Inspired by subject_list.php & class_list.php) -->
    <div
      v-if="showCourseSectionsModal && selectedCourseForModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="course-sections-modal-title"
      data-testid="course-sections-modal"
      @click.self="closeCourseSectionsModal"
    >
      <div class="setup-modal-box" style="max-width: 820px; padding: 1.5rem; max-height: 90vh; overflow-y: auto;">
        <header class="setup-modal-header" style="border-bottom: 1px solid #e5e7eb; padding-bottom: 0.85rem;">
          <div>
            <h2 id="course-sections-modal-title" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #103c2e;">
              Course Sections & Term Offerings
            </h2>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #6b7280;">
              <strong class="font-mono text-emerald">{{ selectedCourseForModal.code }}</strong> — {{ selectedCourseForModal.title }} ({{ selectedCourseForModal.units }} Units) · {{ selectedCourseForModal.department_name }}
            </p>
          </div>
          <button type="button" class="modal-close-btn" @click="closeCourseSectionsModal">&times;</button>
        </header>

        <div style="padding: 1.25rem 0; display: flex; flex-direction: column; gap: 1.25rem;">
          <!-- Offering Summary Stats -->
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Active Term</div>
              <div style="font-weight: 700; font-size: 0.95rem; color: #111827;">
                {{ setupStore.selectedTerm?.name || 'Current Term' }}
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Sections Offered</div>
              <div style="font-weight: 700; font-size: 1.15rem; color: #047857;" data-testid="course-sections-count">
                {{ getCourseSections(selectedCourseForModal.id).length }} Sections
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Enrolled Students</div>
              <div style="font-weight: 700; font-size: 1.15rem; color: #111827;">
                {{ getCourseEnrolledTotal(selectedCourseForModal.id) }} Total
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Evaluations Turnout</div>
              <div style="font-weight: 700; font-size: 1.15rem; color: #059669;">
                {{ getCourseEnrolledTotal(selectedCourseForModal.id) > 0 ? Math.round((getCourseClearedTotal(selectedCourseForModal.id) / getCourseEnrolledTotal(selectedCourseForModal.id)) * 100) : 100 }}%
              </div>
            </div>
          </div>

          <!-- Sections Table -->
          <div>
            <h3 style="font-size: 0.95rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
              Active Sections for {{ selectedCourseForModal.code }}
            </h3>
            <div class="table-responsive-wrapper" style="border: 1px solid #e5e7eb; border-radius: 8px;">
              <table class="setup-data-table" data-testid="course-sections-list-table">
                <thead>
                  <tr>
                    <th scope="col">Section</th>
                    <th scope="col">Assigned Instructor</th>
                    <th scope="col" class="text-center">Enrolled</th>
                    <th scope="col" class="text-center">Evaluated</th>
                    <th scope="col" class="text-center">Turnout</th>
                    <th scope="col" class="text-center">Grade Lock</th>
                    <th scope="col" class="text-right">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="sec in getCourseSections(selectedCourseForModal.id)"
                    :key="sec.course_section_id"
                    :data-testid="`course-modal-section-row-${sec.course_section_id}`"
                  >
                    <td><strong class="font-mono text-emerald">{{ sec.section_code }}</strong></td>
                    <td>{{ sec.instructor_name || 'Prof. Unassigned' }}</td>
                    <td class="text-center">{{ sec.enrolled_count }}</td>
                    <td class="text-center">{{ sec.cleared_count || sec.vault_count || 0 }}</td>
                    <td class="text-center">
                      {{ sec.enrolled_count > 0 ? Math.round(((sec.cleared_count || sec.vault_count || 0) / sec.enrolled_count) * 100) : 100 }}%
                    </td>
                    <td class="text-center">
                      <span
                        class="token-chip"
                        :class="sec.registrar_lock ? 'chip-signed' : 'chip-unsigned'"
                        style="font-size: 0.72rem; padding: 0.15rem 0.45rem;"
                      >
                        {{ sec.registrar_lock ? '🔒 Locked' : '🔓 Inactive' }}
                      </span>
                    </td>
                    <td class="text-right">
                      <button
                        type="button"
                        class="btn-secondary btn-xs"
                        :data-testid="`course-modal-roster-btn-${sec.course_section_id}`"
                        style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600; margin-right: 0.35rem;"
                        @click="openSectionRosterModal(sec)"
                      >
                        Roster 👥
                      </button>
                      <button
                        type="button"
                        class="btn-secondary btn-xs"
                        :data-testid="`course-modal-report-btn-${sec.course_section_id}`"
                        style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                        @click="closeCourseSectionsModal(); openSetupSectionReport(sec)"
                      >
                        Report 📊
                      </button>
                    </td>
                  </tr>
                  <tr v-if="getCourseSections(selectedCourseForModal.id).length === 0">
                    <td colspan="7" class="empty-state-cell">
                      No sections currently created for this course in this academic term.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <footer class="setup-modal-footer" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
          <button
            type="button"
            class="btn-primary"
            data-testid="modal-add-section-for-course-btn"
            @click="const c = selectedCourseForModal; closeCourseSectionsModal(); openNewSectionModalForCourse(c);"
          >
            + Add Another Section
          </button>
          <button
            type="button"
            class="btn-secondary"
            data-testid="close-course-sections-btn"
            @click="closeCourseSectionsModal"
          >
            Close
          </button>
        </footer>
      </div>
    </div>

    <!-- Modal: Section Class Roster & Student Clearance Checklist (Inspired by admin/class_list.php & manage_restriction.php) -->
    <div
      v-if="showSectionRosterModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="section-roster-modal-title"
      data-testid="section-roster-modal"
      @click.self="showSectionRosterModal = false"
    >
      <div class="setup-modal-box" style="max-width: 860px; padding: 1.5rem; max-height: 90vh; overflow-y: auto;">
        <header class="setup-modal-header" style="border-bottom: 1px solid #e5e7eb; padding-bottom: 0.85rem;">
          <div>
            <h2 id="section-roster-modal-title" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #103c2e;">
              Course Section Class Roster &amp; Clearance Checklist
            </h2>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #6b7280;">
              Pamantasan ng Lungsod ng Pasig — Section Enrollment &amp; Evaluation Clearance Roster
            </p>
          </div>
          <button type="button" class="modal-close-btn" @click="showSectionRosterModal = false">&times;</button>
        </header>

        <div v-if="sectionRosterLoading" style="text-align: center; padding: 3rem 1rem;">
          <div class="spinner" style="margin: 0 auto 1rem;"></div>
          <p style="color: #6b7280; font-size: 0.9rem;">Loading section enrollment roster and clearance records…</p>
        </div>

        <div v-else-if="selectedSectionRoster" style="padding: 1.25rem 0; display: flex; flex-direction: column; gap: 1.25rem;">
          <!-- Section Summary Card -->
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Course &amp; Section</div>
              <div style="font-weight: 700; font-size: 1.05rem; color: #111827;" data-testid="section-roster-course-code">
                {{ selectedSectionRoster.section.course_code }} ({{ selectedSectionRoster.section.section_code }})
              </div>
              <div style="font-size: 0.82rem; color: #4b5563;">
                {{ selectedSectionRoster.section.course_title }}
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Instructor</div>
              <div style="font-weight: 700; font-size: 0.95rem; color: #047857;" data-testid="section-roster-instructor">
                {{ selectedSectionRoster.section.instructor_name }}
              </div>
              <div style="font-size: 0.82rem; color: #6b7280;">
                {{ selectedSectionRoster.section.department_name }} ({{ selectedSectionRoster.section.department_code }})
              </div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600;">Turnout Status</div>
              <div style="margin-top: 0.25rem;">
                <span
                  class="token-chip"
                  :class="selectedSectionRoster.section.turnout_pct >= 85 ? 'chip-signed' : 'chip-unsigned'"
                  data-testid="section-roster-turnout-chip"
                >
                  {{ selectedSectionRoster.section.evaluated_count }} / {{ selectedSectionRoster.section.enrolled_count }} Evaluated ({{ selectedSectionRoster.section.turnout_pct }}%)
                </span>
              </div>
              <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.35rem;">
                Term: {{ selectedSectionRoster.section.term_code }}
              </div>
            </div>
          </div>

          <!-- Search and Filter Bar -->
          <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
              <input
                v-model="sectionRosterSearch"
                type="text"
                class="form-input"
                placeholder="Search by student name, ID, or email…"
                data-testid="section-roster-search-input"
                style="width: 100%; font-size: 0.85rem; padding: 0.45rem 0.75rem;"
              />
            </div>
            <div style="min-width: 160px;">
              <select
                v-model="sectionRosterFilter"
                class="form-input"
                data-testid="section-roster-filter-select"
                style="width: 100%; font-size: 0.85rem; padding: 0.45rem 0.75rem;"
              >
                <option value="ALL">All Students ({{ selectedSectionRoster.students.length }})</option>
                <option value="CLEARED">Evaluated ({{ selectedSectionRoster.section.evaluated_count }})</option>
                <option value="PENDING">Pending ({{ selectedSectionRoster.section.enrolled_count - selectedSectionRoster.section.evaluated_count }})</option>
              </select>
            </div>
          </div>

          <!-- Students Roster Table -->
          <div>
            <h3 style="font-size: 0.95rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem;">
              Enrolled Students ({{ filteredSectionRosterStudents.length }} of {{ selectedSectionRoster.students.length }})
            </h3>
            <div class="table-responsive-wrapper" style="max-height: 320px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
              <table class="setup-data-table" data-testid="section-roster-students-table">
                <thead>
                  <tr>
                    <th scope="col">Institutional ID</th>
                    <th scope="col">Student Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Department</th>
                    <th scope="col" class="text-center">Clearance Status</th>
                    <th scope="col">Clearance Token Proof</th>
                    <th scope="col" class="text-right">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="s in filteredSectionRosterStudents"
                    :key="s.student_id"
                    :data-testid="`section-roster-row-${s.student_id}`"
                  >
                    <td><strong class="font-mono text-emerald">{{ s.institutional_id }}</strong></td>
                    <td style="font-weight: 600; color: #111827;">{{ s.name }}</td>
                    <td style="color: #6b7280; font-size: 0.82rem;">{{ s.email }}</td>
                    <td><span class="dept-badge">{{ s.department_code }}</span></td>
                    <td class="text-center">
                      <span
                        class="token-chip"
                        :class="s.has_evaluated ? 'chip-signed' : 'chip-unsigned'"
                        :data-testid="`roster-status-${s.student_id}`"
                        style="font-size: 0.72rem; padding: 0.15rem 0.45rem;"
                      >
                        {{ s.has_evaluated ? '✅ Evaluated' : '⏳ Pending' }}
                      </span>
                    </td>
                    <td>
                      <code v-if="s.clearance_token" class="font-mono" style="font-size: 0.72rem; background: #ecfdf5; color: #065f46; padding: 0.15rem 0.35rem; border-radius: 4px;">
                        {{ s.clearance_token.slice(0, 16) }}…
                      </code>
                      <span v-else style="color: #9ca3af; font-size: 0.8rem;">—</span>
                    </td>
                    <td class="text-right">
                      <router-link
                        v-if="s.has_evaluated"
                        :to="`/verify?id=${encodeURIComponent(s.institutional_id)}`"
                        class="btn-secondary btn-xs"
                        :data-testid="`roster-permit-verify-btn-${s.student_id}`"
                        style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600;"
                      >
                        Permit 📜
                      </router-link>
                      <span v-else style="font-size: 0.75rem; color: #9ca3af; padding: 0.25rem 0.35rem;">Awaiting</span>
                    </td>
                  </tr>
                  <tr v-if="filteredSectionRosterStudents.length === 0">
                    <td colspan="7" class="empty-state-cell">
                      No enrolled students match the search criteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <footer class="setup-modal-footer" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <div style="display: flex; gap: 0.5rem;">
            <button
              v-if="selectedSectionRoster"
              type="button"
              class="btn-secondary btn-sm"
              data-testid="export-section-roster-csv-btn"
              @click="exportSectionRosterCsv"
            >
              📥 Export Roster CSV
            </button>
            <button
              v-if="selectedSectionRoster"
              type="button"
              class="btn-secondary btn-sm"
              data-testid="print-section-roster-btn"
              @click="printSectionRoster"
            >
              🖨 Print Roster
            </button>
          </div>
          <button
            type="button"
            class="btn-secondary btn-sm"
            data-testid="close-section-roster-btn"
            @click="showSectionRosterModal = false"
          >
            Close
          </button>
        </footer>
      </div>
    </div>

    <!-- MODAL: Faculty Teaching Assignments & Load Portfolio -->
    <div
      v-if="showFacultyAssignmentsModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="faculty-assignments-modal-title"
      data-testid="faculty-assignments-modal"
      @click.self="showFacultyAssignmentsModal = false"
    >
      <div class="setup-modal-box" style="max-width: 820px; width: 92%;">
        <header class="setup-modal-header" style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <span class="setup-kicker" style="font-size: 0.75rem; text-transform: uppercase; color: #103c2e; font-weight: 700;">Faculty Teaching Portfolio</span>
            <h2 id="faculty-assignments-modal-title" style="margin: 0.2rem 0 0; font-size: 1.25rem; font-weight: 700; color: #111827;">
              Assigned Course Sections &amp; Class Turnout
            </h2>
          </div>
          <button type="button" class="modal-close-btn" data-testid="close-faculty-assignments-modal" @click="showFacultyAssignmentsModal = false">&times;</button>
        </header>

        <div v-if="facultyAssignmentsLoading" style="text-align: center; padding: 2.5rem 1rem;">
          <div class="spinner" style="margin: 0 auto 0.75rem;"></div>
          <p style="color: #4b5563; font-size: 0.9rem;">Loading faculty teaching assignments...</p>
        </div>

        <div v-else-if="selectedFacultyAssignments" class="modal-body-content" style="padding: 1rem 0;">
          <!-- Profile & Term Header -->
          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
              <div style="font-size: 1.05rem; font-weight: 700; color: #111827;">
                {{ selectedFacultyAssignments.faculty.name }}
              </div>
              <div style="font-size: 0.82rem; color: #4b5563; display: flex; gap: 0.75rem; align-items: center; margin-top: 0.2rem;">
                <code>{{ selectedFacultyAssignments.faculty.institutional_id }}</code>
                <span>{{ selectedFacultyAssignments.faculty.email }}</span>
                <span class="dept-badge">{{ selectedFacultyAssignments.faculty.department_code }}</span>
              </div>
            </div>
            <div style="text-align: right;">
              <span class="token-chip chip-signed" style="font-size: 0.78rem;">
                {{ selectedFacultyAssignments.term.name }}
              </span>
            </div>
          </div>

          <!-- Summary Metric Cards -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.75rem 1rem; text-align: center;">
              <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #166534;">Total Sections</div>
              <div style="font-size: 1.5rem; font-weight: 800; color: #14532d; margin-top: 0.2rem;" data-testid="faculty-summary-sections">
                {{ selectedFacultyAssignments.summary.total_sections }}
              </div>
            </div>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 0.75rem 1rem; text-align: center;">
              <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #1e40af;">Enrolled Students</div>
              <div style="font-size: 1.5rem; font-weight: 800; color: #1e3a8a; margin-top: 0.2rem;" data-testid="faculty-summary-enrolled">
                {{ selectedFacultyAssignments.summary.total_enrolled }}
              </div>
            </div>
            <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 0.75rem 1rem; text-align: center;">
              <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #854d0e;">Evaluations Received</div>
              <div style="font-size: 1.5rem; font-weight: 800; color: #713f12; margin-top: 0.2rem;" data-testid="faculty-summary-evaluations">
                {{ selectedFacultyAssignments.summary.total_evaluations }}
              </div>
            </div>
            <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 0.75rem 1rem; text-align: center;">
              <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #6b21a8;">Turnout Rate</div>
              <div style="font-size: 1.5rem; font-weight: 800; color: #581c87; margin-top: 0.2rem;" data-testid="faculty-summary-turnout">
                {{ selectedFacultyAssignments.summary.overall_turnout_pct }}%
              </div>
            </div>
          </div>

          <!-- Section Table -->
          <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
            <table class="setup-table" style="font-size: 0.85rem;" data-testid="faculty-assignments-table">
              <thead>
                <tr>
                  <th scope="col">Course Code</th>
                  <th scope="col">Course Title</th>
                  <th scope="col">Section</th>
                  <th scope="col">Units</th>
                  <th scope="col">Enrolled</th>
                  <th scope="col">Evaluated</th>
                  <th scope="col">Turnout</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="s in selectedFacultyAssignments.sections"
                  :key="s.course_section_id"
                  :data-testid="`faculty-sec-row-${s.course_section_id}`"
                >
                  <td><code>{{ s.course_code }}</code></td>
                  <td><strong>{{ s.course_title }}</strong></td>
                  <td><span class="section-badge">{{ s.section_code }}</span></td>
                  <td>{{ s.units }}</td>
                  <td>{{ s.enrolled_count }}</td>
                  <td>{{ s.cleared_count }}</td>
                  <td>
                    <span style="font-weight: 700; color: #103c2e;">
                      {{ s.turnout_pct }}%
                    </span>
                  </td>
                  <td>
                    <span
                      class="status-badge"
                      :class="s.double_blind_unlocked ? 'status-completed' : 'status-pending'"
                      style="font-size: 0.72rem;"
                    >
                      {{ s.double_blind_unlocked ? 'Unlocked' : 'Locked' }}
                    </span>
                  </td>
                </tr>
                <tr v-if="selectedFacultyAssignments.sections.length === 0" data-testid="faculty-assignments-empty">
                  <td colspan="8" style="text-align: center; padding: 1.5rem; color: #6b7280;">
                    No teaching course sections assigned to this faculty member for this term.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <footer class="setup-modal-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
          <button
            v-if="selectedFacultyAssignments && selectedFacultyAssignments.sections.length > 0"
            type="button"
            class="btn-secondary"
            data-testid="export-faculty-assignments-csv-btn"
            style="font-size: 0.82rem; padding: 0.4rem 0.8rem;"
            @click="exportFacultyAssignmentsCsv"
          >
            📊 Export Assignments (CSV)
          </button>
          <div style="margin-left: auto;">
            <button type="button" class="btn-secondary" data-testid="close-faculty-assignments-btn" @click="showFacultyAssignmentsModal = false">Close</button>
          </div>
        </footer>
      </div>
    </div>

    <!-- MODAL: Edit User Account (Inspired by admin/manage_user.php) -->
    <div
      v-if="showEditUserModal"
      class="setup-modal-scrim"
      role="dialog"
      aria-modal="true"
      aria-labelledby="edit-user-modal-title"
      data-testid="edit-user-modal"
    >
      <div class="setup-modal-box">
        <header class="setup-modal-header">
          <h2 id="edit-user-modal-title">Edit User Account &amp; Credentials</h2>
          <button type="button" class="modal-close-btn" data-testid="close-edit-user-modal" @click="showEditUserModal = false">&times;</button>
        </header>

        <form class="setup-modal-form" @submit.prevent="submitEditUser">
          <div class="form-group">
            <label for="edit-user-inst-id">Institutional ID:</label>
            <input
              id="edit-user-inst-id"
              :value="userEditForm.institutional_id"
              type="text"
              class="form-input font-mono"
              disabled
              style="background: #f3f4f6; color: #6b7280;"
            />
          </div>

          <div class="form-group">
            <label for="edit-user-name">Full Name:</label>
            <input
              id="edit-user-name"
              v-model="userEditForm.name"
              type="text"
              class="form-input"
              data-testid="edit-user-name-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="edit-user-email">Institutional Email:</label>
            <input
              id="edit-user-email"
              v-model="userEditForm.email"
              type="email"
              class="form-input"
              data-testid="edit-user-email-input"
              required
            />
          </div>

          <div class="form-group">
            <label for="edit-user-role">System Role:</label>
            <select
              id="edit-user-role"
              v-model="userEditForm.role"
              class="form-input"
              data-testid="edit-user-role-select"
              required
            >
              <option value="faculty">Faculty Member</option>
              <option value="chair">Department Chair</option>
              <option value="dean">College Dean</option>
              <option value="admin">System Administrator (Registrar)</option>
              <option value="student">Student</option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit-user-dept">Department / College Affiliation:</label>
            <select
              id="edit-user-dept"
              v-model="userEditForm.department_id"
              class="form-input"
              data-testid="edit-user-dept-select"
            >
              <option :value="null">— Institution-Wide / None —</option>
              <option
                v-for="d in setupStore.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit-user-pwd">Reset Password (Optional):</label>
            <input
              id="edit-user-pwd"
              v-model="userEditForm.password"
              type="password"
              class="form-input"
              placeholder="Leave blank to keep existing password"
              data-testid="edit-user-password-input"
              autocomplete="new-password"
            />
            <small style="font-size: 0.76rem; color: #6b7280; margin-top: 0.2rem; display: block;">
              Enter a new password to reset, or leave empty to keep current password unchanged.
            </small>
          </div>

          <footer class="setup-modal-footer">
            <button type="button" class="btn-secondary" @click="showEditUserModal = false">Cancel</button>
            <button
              type="submit"
              class="btn-primary"
              data-testid="edit-user-save-btn"
              :disabled="setupStore.actionBusy"
            >
              {{ setupStore.actionBusy ? 'Saving...' : 'Save User Changes' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- STUDENT REMINDER NOTICE MODAL -->
    <div
      v-if="showStudentReminderModal && reminderStudent"
      class="setup-modal-scrim"
      data-testid="student-reminder-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="student-reminder-title"
      @click.self="closeStudentReminderModal"
    >
      <div class="setup-modal-card" style="max-width: 620px;">
        <div class="setup-modal-header">
          <div>
            <span class="modal-kicker">STUDENT PARTICIPATION FOLLOW-UP</span>
            <h3 id="student-reminder-title" class="modal-title">Evaluation Reminder Notice</h3>
          </div>
          <button
            type="button"
            class="modal-close-btn"
            data-testid="close-reminder-modal-btn"
            aria-label="Close reminder modal"
            @click="closeStudentReminderModal"
          >
            ✕
          </button>
        </div>

        <div class="modal-body" style="padding: 1.25rem 1.5rem;">
          <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; flex-wrap: wrap; gap: 0.5rem;">
              <strong style="color: #92400e; font-size: 0.95rem;">{{ reminderStudent.name }} ({{ reminderStudent.institutional_id }})</strong>
              <span class="token-chip chip-unsigned" style="font-size: 0.75rem;">
                {{ Math.max(0, reminderStudent.enrolled_count - reminderStudent.cleared_count) }} Pending Evaluations
              </span>
            </div>
            <div style="font-size: 0.82rem; color: #78350f;">
              Department: {{ reminderStudent.department_name || reminderStudent.department_code || 'CCS' }} • Email: {{ reminderStudent.email }}
            </div>
          </div>

          <label for="reminder-text-area" class="form-label" style="font-size: 0.82rem; font-weight: 700; color: #374151;">
            Institutional Follow-Up Notice / Email Template:
          </label>
          <textarea
            id="reminder-text-area"
            readonly
            class="form-input"
            rows="10"
            style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.82rem; line-height: 1.5; background: #f9f9f8; resize: vertical;"
            :value="reminderNoticeText"
            data-testid="reminder-notice-textarea"
          ></textarea>
        </div>

        <div class="setup-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
          <button
            type="button"
            class="btn-secondary"
            @click="closeStudentReminderModal"
          >
            Close
          </button>
          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span v-if="reminderCopied" style="font-size: 0.82rem; color: #059669; font-weight: 700;" data-testid="reminder-copied-indicator">
              Notice Copied to Clipboard! ✓
            </span>
            <button
              type="button"
              class="btn-primary"
              data-testid="copy-reminder-btn"
              style="padding: 0.45rem 1rem; font-weight: 700;"
              @click="copyReminderNotice"
            >
              Copy Reminder Notice 📋
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.setup-workspace {
  max-width: 1360px;
  margin: 0 auto;
  padding: 1.5rem;
  color: #1f2937;
  font-family: 'Public Sans', system-ui, sans-serif;
}

.setup-header {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.setup-header-main {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.setup-kicker {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
  color: #0b3322;
}

.setup-title {
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.85rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0.2rem 0 0.4rem 0;
}

.setup-subtitle {
  font-size: 0.95rem;
  color: #4b5563;
  margin: 0;
  max-width: 720px;
}

.setup-term-switcher {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #f9f9f8;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 0.4rem 0.75rem;
}

.term-picker-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.term-picker-select {
  border: none;
  background: transparent;
  font-size: 0.88rem;
  font-weight: 600;
  color: #0b3322;
  outline: none;
  cursor: pointer;
}

/* Feedback Alerts */
.feedback-alert {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
}

.feedback-alert.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.feedback-alert.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Readiness Bar */
.readiness-bar-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.15rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.readiness-status-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.readiness-badge-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.readiness-chip {
  padding: 0.3rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.readiness-chip.ready {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.readiness-chip.incomplete {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

.window-chip {
  padding: 0.3rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  background: #f3f4f6;
  color: #4b5563;
}

.window-chip.open {
  background: #ecfdf5;
  color: #047857;
}

.window-chip.closed {
  background: #f3f4f6;
  color: #6b7280;
}

.window-chip.suspended {
  background: #fef2f2;
  color: #b91c1c;
}

.readiness-metrics-row {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.metric-item {
  display: flex;
  flex-direction: column;
}

.metric-value {
  font-family: 'Newsreader', Georgia, serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.1;
}

.metric-label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
  font-weight: 600;
}

.text-emerald {
  color: #0b3322 !important;
}

.text-amber {
  color: #d97706 !important;
}

.readiness-warnings-box {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 6px;
  font-size: 0.85rem;
}

.warnings-title {
  color: #92400e;
  font-size: 0.82rem;
}

.warnings-list {
  margin: 0.4rem 0 0 1.25rem;
  padding: 0;
  color: #78350f;
}

/* Tabs */
.setup-tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.setup-tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.1rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 0.9rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.setup-tab-btn:hover {
  color: #0b3322;
}

.setup-tab-btn.active {
  color: #0b3322;
  border-bottom-color: #0b3322;
}

/* Pane Controls */
.pane-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.search-filter-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.table-search-input {
  width: 280px;
  padding: 0.45rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.85rem;
  outline: none;
}

.filter-select {
  padding: 0.45rem 0.65rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.85rem;
  background: #ffffff;
  outline: none;
}

.pane-actions-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.pane-description {
  font-size: 0.9rem;
  color: #4b5563;
  margin: 0;
}

/* Tables */
.table-responsive-wrapper {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #ffffff;
}

.setup-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  text-align: left;
}

.setup-data-table th {
  background: #f9f9f8;
  padding: 0.75rem 1rem;
  font-weight: 600;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #4b5563;
  border-bottom: 1px solid #e5e7eb;
}

.setup-data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.setup-data-table tbody tr:hover {
  background: #fafafa;
}

.row-unassigned {
  background: #fffdf5;
}

.row-active-term {
  background: #f0fdf4;
}

.course-code-cell {
  display: flex;
  flex-direction: column;
}

.course-title-sub {
  font-size: 0.78rem;
  color: #6b7280;
}

.section-tag {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  background: #f3f4f6;
  border-radius: 4px;
  font-family: monospace;
  font-weight: 600;
  font-size: 0.82rem;
}

.dept-badge {
  font-size: 0.75rem;
  font-weight: 600;
  color: #4b5563;
}

.faculty-select {
  padding: 0.35rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.84rem;
  background: #ffffff;
  outline: none;
  max-width: 260px;
}

.enrolled-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
  font-size: 0.8rem;
  background: #e5e7eb;
  color: #374151;
}

.enrolled-badge.zero {
  background: #fee2e2;
  color: #b91c1c;
}

.vault-count-badge {
  display: inline-block;
  font-weight: 600;
  font-size: 0.85rem;
  color: #0b3322;
}

.lock-badge {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.lock-badge.locked {
  background: #fef2f2;
  color: #991b1b;
}

.lock-badge.unlocked {
  background: #ecfdf5;
  color: #065f46;
}

.lock-cell-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
}

.btn-lock-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.btn-lock-toggle.locked {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}

.btn-lock-toggle.locked:hover {
  background: #fee2e2;
  border-color: #f87171;
}

.btn-lock-toggle.unlocked {
  background: #f3f4f6;
  color: #4b5563;
  border-color: #d1d5db;
}

.btn-lock-toggle.unlocked:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.active-status-badge {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.active-status-badge.active {
  background: #ecfdf5;
  color: #047857;
}

.active-status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.window-status-pill {
  display: inline-block;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.window-status-pill.open {
  background: #ecfdf5;
  color: #065f46;
}

.window-status-pill.closed {
  background: #f3f4f6;
  color: #6b7280;
}

.window-status-pill.suspended {
  background: #fef2f2;
  color: #991b1b;
}

.date-range {
  font-size: 0.82rem;
  font-family: monospace;
  color: #4b5563;
}

.text-muted {
  color: #9ca3af;
  font-size: 0.82rem;
}

.text-active-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #047857;
}

.empty-state-cell {
  text-align: center;
  padding: 2.5rem !important;
  color: #6b7280;
}

/* Buttons */
.btn-primary {
  background: #0b3322;
  color: #ffffff;
  padding: 0.45rem 0.95rem;
  border: none;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background: #14532d;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #ffffff;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.45rem 0.95rem;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.btn-secondary:hover {
  background: #f9f9f8;
  border-color: #9ca3af;
}

.btn-secondary-sm {
  background: #ffffff;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-secondary-sm:hover {
  background: #f3f4f6;
}

.btn-danger-sm {
  background: transparent;
  color: #dc2626;
  border: 1px solid #fecaca;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger-sm:hover:not(:disabled) {
  background: #fee2e2;
}

.btn-danger-sm:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  border-color: #e5e7eb;
  color: #9ca3af;
}

.btn-danger {
  background: #dc2626;
  color: #ffffff;
  padding: 0.45rem 0.95rem;
  border: none;
  border-radius: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger:hover {
  background: #b91c1c;
}

.text-link-btn {
  background: transparent;
  border: none;
  color: #0b3322;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  text-decoration: underline;
}

/* Questionnaire Preview */
.questionnaire-header-banner {
  background: #f9f9f8;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.1rem 1.25rem;
  margin-bottom: 1.5rem;
}

.questionnaire-badge-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.4rem;
  flex-wrap: wrap;
}

.accreditation-seal {
  font-size: 0.8rem;
  font-weight: 700;
  color: #0b3322;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.scale-legend {
  font-size: 0.8rem;
  color: #4b5563;
  font-weight: 600;
}

.questionnaire-note {
  font-size: 0.85rem;
  color: #6b7280;
  margin: 0;
}

.clusters-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.cluster-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.cluster-card-header {
  background: #fdfdfd;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cluster-card-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0;
}

.cluster-card-desc {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0.2rem 0 0 0;
}

.cluster-items-count {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
}

.cluster-items-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.cluster-question-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid #f9fafb;
}

.cluster-question-item:last-child {
  border-bottom: none;
}

.item-number {
  font-family: monospace;
  font-weight: 700;
  font-size: 0.82rem;
  color: #0b3322;
  width: 32px;
  flex-shrink: 0;
}

.item-text {
  flex: 1;
  margin: 0;
  font-size: 0.88rem;
  color: #374151;
}

.item-scale-points {
  display: flex;
  gap: 0.4rem;
  flex-shrink: 0;
}

.scale-pt {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
}

.qualitative-items-list {
  padding: 1rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.qualitative-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.qualitative-label {
  font-size: 0.88rem;
  color: #111827;
}

.qualitative-desc {
  font-size: 0.82rem;
  color: #6b7280;
  margin: 0;
}

.qualitative-subfields {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.35rem;
  flex-wrap: wrap;
}

.subfield-pill {
  background: #f3f4f6;
  padding: 0.25rem 0.55rem;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #374151;
  font-weight: 500;
}

/* Modal */
.setup-modal-scrim {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.setup-modal-box {
  background: #ffffff;
  border-radius: 10px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.setup-modal-box.setup-modal-lg {
  max-width: 660px !important;
}

.setup-modal-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.setup-modal-header h2 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0b3322;
  margin: 0;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
  color: #6b7280;
}

.setup-modal-form {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.setup-modal-body {
  padding: 1.25rem;
  font-size: 0.9rem;
  color: #374151;
}

.modal-warning-text {
  font-size: 0.82rem;
  color: #991b1b;
  margin-top: 0.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.label-with-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-group label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.88rem;
  outline: none;
}

.form-input:focus {
  border-color: #0b3322;
}

.form-checkbox-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #374151;
}

.setup-modal-footer {
  padding: 0.85rem 1.25rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  background: #f9f9f8;
}

.font-mono {
  font-family: monospace;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}
</style>
