<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

class AcademicSetupService
{
    private PDO $pdo;
    private DecoupledEvaluationService $evaluationService;

    public function __construct(?PDO $pdo = null)
    {
        $this->evaluationService = new DecoupledEvaluationService($pdo);
        $this->pdo = $this->evaluationService->getPdo();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Retrieve complete academic setup state for a selected academic term.
     *
     * @return array<string, mixed>
     */
    public function getSetupOverview(?int $academicTermId = null): array
    {
        // 1. Fetch all academic terms with summary metrics
        $termStmt = $this->pdo->query('
            SELECT 
                at.id,
                at.code,
                at.name,
                at.academic_year,
                at.is_active,
                at.evaluation_open,
                at.starts_on,
                at.ends_on,
                at.created_at,
                at.updated_at
            FROM academic_terms at
            ORDER BY at.is_active DESC, at.id DESC
        ');
        $rawTerms = $termStmt ? $termStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $terms = [];
        $selectedTermId = $academicTermId;
        $activeTermId = null;

        foreach ($rawTerms as $t) {
            $tId = (int) $t['id'];
            $isActive = (bool) $t['is_active'];
            if ($isActive) {
                $activeTermId = $tId;
            }

            // Fetch section counts for this term
            $secCntStmt = $this->pdo->prepare('SELECT COUNT(*) FROM course_sections WHERE academic_term_id = :tid');
            $secCntStmt->execute([':tid' => $tId]);
            $totalSections = (int) $secCntStmt->fetchColumn();

            $asgnCntStmt = $this->pdo->prepare('SELECT COUNT(*) FROM course_sections WHERE academic_term_id = :tid AND faculty_id IS NOT NULL AND faculty_id > 0');
            $asgnCntStmt->execute([':tid' => $tId]);
            $assignedSections = (int) $asgnCntStmt->fetchColumn();

            $enrCntStmt = $this->pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE academic_term_id = :tid');
            $enrCntStmt->execute([':tid' => $tId]);
            $enrolledCount = (int) $enrCntStmt->fetchColumn();

            // Window status
            $winStmt = $this->pdo->prepare('SELECT status, starts_at, ends_at FROM evaluation_window_configs WHERE academic_term_id = :tid LIMIT 1');
            $winStmt->execute([':tid' => $tId]);
            $winRow = $winStmt->fetch(PDO::FETCH_ASSOC);

            $terms[] = [
                'id' => $tId,
                'code' => (string) $t['code'],
                'name' => (string) $t['name'],
                'academic_year' => (string) $t['academic_year'],
                'is_active' => $isActive,
                'evaluation_open' => (bool) $t['evaluation_open'],
                'starts_on' => $t['starts_on'],
                'ends_on' => $t['ends_on'],
                'total_sections' => $totalSections,
                'assigned_sections' => $assignedSections,
                'unassigned_sections' => max(0, $totalSections - $assignedSections),
                'enrolled_count' => $enrolledCount,
                'window_status' => $winRow ? (string) $winRow['status'] : 'CLOSED',
            ];
        }

        // Determine active term and selected term
        if ($selectedTermId === null || $selectedTermId <= 0) {
            $selectedTermId = $activeTermId ?? ($terms[0]['id'] ?? 1);
        }

        $selectedTerm = null;
        foreach ($terms as $t) {
            if ($t['id'] === $selectedTermId) {
                $selectedTerm = $t;
                break;
            }
        }
        if ($selectedTerm === null && count($terms) > 0) {
            $selectedTerm = $terms[0];
            $selectedTermId = (int) $selectedTerm['id'];
        }

        // 2. Fetch departments
        $deptStmt = $this->pdo->query('
            SELECT id, code, name, college_name 
            FROM departments 
            ORDER BY code ASC
        ');
        $departments = $deptStmt ? $deptStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 3. Fetch courses
        $courseStmt = $this->pdo->query('
            SELECT 
                c.id,
                c.department_id,
                c.code,
                c.title,
                c.units,
                d.code AS department_code,
                d.name AS department_name
            FROM courses c
            LEFT JOIN departments d ON d.id = c.department_id
            ORDER BY c.code ASC
        ');
        $courses = $courseStmt ? $courseStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 4. Fetch faculty users
        $facStmt = $this->pdo->query("
            SELECT 
                u.id,
                u.institutional_id,
                u.name,
                u.email,
                u.department_id,
                u.role,
                d.code AS department_code,
                d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.role IN ('faculty', 'chair', 'dean') AND u.is_active = 1
            ORDER BY u.name ASC
        ");
        $faculty = $facStmt ? $facStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 5. Fetch sections for the selected term
        $secStmt = $this->pdo->prepare('
            SELECT 
                cs.id AS course_section_id,
                cs.section_code,
                cs.course_id,
                cs.faculty_id,
                cs.academic_term_id,
                cs.term_grades_submitted,
                cs.registrar_lock,
                cs.enrolled_count AS stored_enrolled_count,
                c.code AS course_code,
                c.title AS course_title,
                c.units,
                c.department_id,
                d.code AS department_code,
                d.name AS department_name,
                u.name AS instructor_name,
                u.institutional_id AS instructor_institutional_id,
                u.email AS instructor_email
            FROM course_sections cs
            INNER JOIN courses c ON c.id = cs.course_id
            LEFT JOIN users u ON u.id = cs.faculty_id
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE cs.academic_term_id = :term_id
            ORDER BY c.code ASC, cs.section_code ASC
        ');
        $secStmt->execute([':term_id' => $selectedTermId]);
        $rawSections = $secStmt->fetchAll(PDO::FETCH_ASSOC);

        $sections = [];
        $unassignedCount = 0;
        $totalEnrolledInSections = 0;
        $sectionsWithZeroEnrollment = 0;

        foreach ($rawSections as $row) {
            $secId = (int) $row['course_section_id'];
            $facultyId = !empty($row['faculty_id']) ? (int) $row['faculty_id'] : null;
            $hasFaculty = $facultyId !== null && $facultyId > 0 && !empty($row['instructor_name']);
            if (!$hasFaculty) {
                $unassignedCount++;
            }

            // Real enrollment count
            $enrCountStmt = $this->pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_section_id = :sec_id');
            $enrCountStmt->execute([':sec_id' => $secId]);
            $realEnrolledCount = (int) $enrCountStmt->fetchColumn();
            if ($realEnrolledCount === 0 && !empty($row['stored_enrolled_count'])) {
                $realEnrolledCount = (int) $row['stored_enrolled_count'];
            }
            $totalEnrolledInSections += $realEnrolledCount;
            if ($realEnrolledCount === 0) {
                $sectionsWithZeroEnrollment++;
            }

            // Clearance and vault counts
            $clrStmt = $this->pdo->prepare("SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = :sec_id AND status = 'CLEARED'");
            $clrStmt->execute([':sec_id' => $secId]);
            $clearedCount = (int) $clrStmt->fetchColumn();

            $vltStmt = $this->pdo->prepare('SELECT COUNT(*) FROM evaluation_vault WHERE course_section_id = :sec_id');
            $vltStmt->execute([':sec_id' => $secId]);
            $vaultCount = (int) $vltStmt->fetchColumn();

            $readiness = 'READY';
            if (!$hasFaculty) {
                $readiness = 'NO_FACULTY';
            } elseif ($realEnrolledCount === 0) {
                $readiness = 'ZERO_ENROLLMENT';
            }

            $sections[] = [
                'course_section_id' => $secId,
                'section_code' => (string) $row['section_code'],
                'course_id' => (int) $row['course_id'],
                'course_code' => (string) $row['course_code'],
                'course_title' => (string) $row['course_title'],
                'units' => (int) ($row['units'] ?? 3),
                'department_id' => (int) ($row['department_id'] ?? 1),
                'department_code' => (string) ($row['department_code'] ?? 'CCS'),
                'department_name' => (string) ($row['department_name'] ?? 'College of Computer Studies'),
                'faculty_id' => $facultyId,
                'instructor_name' => $hasFaculty ? (string) $row['instructor_name'] : 'Unassigned',
                'instructor_institutional_id' => (string) ($row['instructor_institutional_id'] ?? ''),
                'instructor_email' => (string) ($row['instructor_email'] ?? ''),
                'is_assigned' => $hasFaculty,
                'enrolled_count' => $realEnrolledCount,
                'cleared_count' => $clearedCount,
                'vault_count' => $vaultCount,
                'term_grades_submitted' => (bool) $row['term_grades_submitted'],
                'registrar_lock' => (bool) $row['registrar_lock'],
                'double_blind_unlocked' => (bool) $row['term_grades_submitted'] && (bool) $row['registrar_lock'],
                'readiness' => $readiness,
            ];
        }

        // 6. Window config for the selected term
        $windowConfig = $this->evaluationService->getEvaluationWindow($selectedTermId);
        $windowStatus = (string) ($windowConfig['status'] ?? 'CLOSED');

        // 7. Fetch students and clearance status for the selected term (Inspired by student_list.php)
        $studStmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.institutional_id,
                u.name,
                u.email,
                u.department_id,
                d.code AS department_code,
                d.name AS department_name,
                COUNT(DISTINCT e.course_section_id) AS enrolled_count,
                COUNT(DISTINCT ec.course_section_id) AS cleared_count
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            LEFT JOIN enrollments e ON e.student_id = u.id AND e.academic_term_id = :term_id
            LEFT JOIN evaluation_clearances ec ON ec.student_id = u.id AND ec.course_section_id = e.course_section_id
            WHERE u.role = 'student' AND u.is_active = 1
            GROUP BY u.id, u.institutional_id, u.name, u.email, u.department_id, d.code, d.name
            ORDER BY u.name ASC
        ");
        $studStmt->execute([':term_id' => $selectedTermId]);
        $rawStudents = $studStmt->fetchAll(PDO::FETCH_ASSOC);

        $students = [];
        foreach ($rawStudents as $s) {
            $enrolled = (int) $s['enrolled_count'];
            $cleared = (int) $s['cleared_count'];
            $isComplete = $enrolled > 0 && $cleared >= $enrolled;
            $students[] = [
                'id' => (int) $s['id'],
                'institutional_id' => (string) $s['institutional_id'],
                'name' => (string) $s['name'],
                'email' => (string) $s['email'],
                'department_id' => $s['department_id'] !== null ? (int) $s['department_id'] : null,
                'department_code' => $s['department_code'] ?: 'CCS',
                'department_name' => $s['department_name'] ?: 'College of Computer Studies',
                'enrolled_count' => $enrolled,
                'cleared_count' => $cleared,
                'is_complete' => $isComplete,
                'clearance_status' => $enrolled === 0 ? 'NO ENROLLMENT' : ($isComplete ? 'CLEARED' : "PENDING ({$cleared}/{$enrolled})"),
            ];
        }

        // 8. Fetch all institutional system users (Inspired by admin/user_list.php)
        $userStmt = $this->pdo->query("
            SELECT
                u.id,
                u.institutional_id,
                u.name,
                u.email,
                u.role,
                u.department_id,
                u.is_active,
                d.code AS department_code,
                d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            ORDER BY
                CASE u.role
                    WHEN 'admin' THEN 1
                    WHEN 'dean' THEN 2
                    WHEN 'chair' THEN 3
                    WHEN 'faculty' THEN 4
                    WHEN 'student' THEN 5
                    ELSE 6
                END,
                u.name ASC
        ");
        $rawUsers = $userStmt ? $userStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $systemUsers = [];
        foreach ($rawUsers as $u) {
            $systemUsers[] = [
                'id' => (int) $u['id'],
                'institutional_id' => (string) ($u['institutional_id'] ?? ''),
                'name' => (string) $u['name'],
                'email' => (string) $u['email'],
                'role' => (string) $u['role'],
                'department_id' => $u['department_id'] !== null ? (int) $u['department_id'] : null,
                'department_code' => (string) ($u['department_code'] ?? 'CCS'),
                'department_name' => (string) ($u['department_name'] ?? 'College of Computer Studies'),
                'is_active' => (bool) $u['is_active'],
                'created_at' => (string) ($u['created_at'] ?? '2026-01-01'),
            ];
        }

        // 9. Readiness analysis & actionable validation warnings
        $validationWarnings = [];
        $totalSectionsCount = count($sections);

        if ($totalSectionsCount === 0) {
            $validationWarnings[] = 'No course sections configured for this term. Add sections or import an academic roster.';
        }
        if ($unassignedCount > 0) {
            $validationWarnings[] = "{$unassignedCount} section(s) have no faculty assigned. Assign instructors before opening evaluations.";
        }
        if ($sectionsWithZeroEnrollment > 0) {
            $validationWarnings[] = "{$sectionsWithZeroEnrollment} section(s) currently have 0 enrolled students.";
        }
        if ($windowStatus !== 'OPEN') {
            $validationWarnings[] = "Evaluation window is currently {$windowStatus}. Configure schedule to open student evaluations.";
        }

        $isCycleReady = $totalSectionsCount > 0 && $unassignedCount === 0 && $windowStatus === 'OPEN';

        $readinessSummary = [
            'total_sections' => $totalSectionsCount,
            'assigned_sections' => $totalSectionsCount - $unassignedCount,
            'unassigned_sections' => $unassignedCount,
            'total_enrolled' => $totalEnrolledInSections,
            'zero_enrollment_sections' => $sectionsWithZeroEnrollment,
            'sections_with_zero_enrollment' => $sectionsWithZeroEnrollment,
            'evaluation_window_status' => $windowStatus,
            'window_status' => $windowStatus,
            'is_cycle_ready' => $isCycleReady,
            'validation_warnings' => $validationWarnings,
        ];

        // 9. Questionnaire Preview (Canonical 25 Likert items across 5 clusters + 4 qualitative questions)
        $questionnairePreview = $this->getQuestionnairePreview();

        return [
            'terms' => $terms,
            'selected_term_id' => $selectedTermId,
            'selected_term' => $selectedTerm,
            'active_term_id' => $activeTermId,
            'departments' => $departments,
            'courses' => $courses,
            'faculty' => $faculty,
            'students' => $students,
            'system_users' => $systemUsers,
            'sections' => $sections,
            'readiness_summary' => $readinessSummary,
            'window_config' => $windowConfig,
            'questionnaire_preview' => $questionnairePreview,
        ];
    }

    /**
     * Create or update an academic term.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createOrUpdateTerm(array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $year = trim((string) ($data['academic_year'] ?? ''));
        $isActive = (bool) filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $evalOpen = (bool) filter_var($data['evaluation_open'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $startsOn = !empty($data['starts_on']) ? trim((string) $data['starts_on']) : null;
        $endsOn = !empty($data['ends_on']) ? trim((string) $data['ends_on']) : null;
        $id = !empty($data['id']) ? (int) $data['id'] : null;

        if ($code === '' || $name === '' || $year === '') {
            throw new InvalidArgumentException('Academic year, semester name, and term code are required.');
        }

        if (!preg_match('/^\d{4}-\d{4}$/', $year)) {
            throw new InvalidArgumentException("Academic year must follow 'YYYY-YYYY' format (e.g., '2026-2027').");
        }

        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            // Check duplicate code
            if ($id === null) {
                $chk = $this->pdo->prepare('SELECT id FROM academic_terms WHERE code = :code LIMIT 1');
                $chk->execute([':code' => $code]);
                if ($chk->fetch()) {
                    throw new InvalidArgumentException("An academic term with code '{$code}' already exists.");
                }
            } else {
                $chk = $this->pdo->prepare('SELECT id FROM academic_terms WHERE code = :code AND id != :id LIMIT 1');
                $chk->execute([':code' => $code, ':id' => $id]);
                if ($chk->fetch()) {
                    throw new InvalidArgumentException("Another academic term with code '{$code}' already exists.");
                }
            }

            // If setting active, deactivate others
            if ($isActive) {
                $this->pdo->exec('UPDATE academic_terms SET is_active = 0');
            }

            if ($id === null) {
                $stmt = $this->pdo->prepare('
                    INSERT INTO academic_terms (code, name, academic_year, is_active, evaluation_open, starts_on, ends_on, created_at, updated_at)
                    VALUES (:code, :name, :year, :active, :eval_open, :starts_on, :ends_on, :created_at, :updated_at)
                ');
                $stmt->execute([
                    ':code' => $code,
                    ':name' => $name,
                    ':year' => $year,
                    ':active' => $isActive ? 1 : 0,
                    ':eval_open' => $evalOpen ? 1 : 0,
                    ':starts_on' => $startsOn,
                    ':ends_on' => $endsOn,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();

                // Initialize evaluation window config
                $this->ensureEvaluationWindowConfig($id);
            } else {
                $stmt = $this->pdo->prepare('
                    UPDATE academic_terms 
                    SET code = :code,
                        name = :name,
                        academic_year = :year,
                        is_active = :active,
                        evaluation_open = :eval_open,
                        starts_on = :starts_on,
                        ends_on = :ends_on,
                        updated_at = :updated_at
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':code' => $code,
                    ':name' => $name,
                    ':year' => $year,
                    ':active' => $isActive ? 1 : 0,
                    ':eval_open' => $evalOpen ? 1 : 0,
                    ':starts_on' => $startsOn,
                    ':ends_on' => $endsOn,
                    ':updated_at' => $now,
                    ':id' => $id,
                ]);

                $this->ensureEvaluationWindowConfig($id);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'term_id' => $id,
                'code' => $code,
                'name' => $name,
                'academic_year' => $year,
                'is_active' => $isActive,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Set a specific academic term as active.
     */
    public function setActiveTerm(int $termId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM academic_terms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$term) {
            throw new InvalidArgumentException("Academic term ID {$termId} does not exist.");
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE academic_terms SET is_active = 0');
            $upd = $this->pdo->prepare('UPDATE academic_terms SET is_active = 1, updated_at = :now WHERE id = :id');
            $upd->execute([':now' => date('Y-m-d H:i:s'), ':id' => $termId]);
            $this->pdo->commit();

            return [
                'success' => true,
                'active_term_id' => $termId,
                'term_code' => $term['code'],
                'term_name' => $term['name'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete an un-evaluated, inactive academic term cycle.
     *
     * @param int $termId
     * @return array<string, mixed>
     */
    public function deleteTerm(int $termId): array
    {
        if ($termId <= 0) {
            throw new InvalidArgumentException('Valid academic_term_id is required.');
        }

        $stmt = $this->pdo->prepare('SELECT id, code, name, is_active FROM academic_terms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $termId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$term) {
            throw new InvalidArgumentException("Academic term ID {$termId} does not exist.");
        }

        if (!empty($term['is_active'])) {
            throw new RuntimeException("Cannot delete active academic term '{$term['name']}'. Set another term as active first.");
        }

        // Safety check: sections count
        $secStmt = $this->pdo->prepare('SELECT COUNT(*) FROM course_sections WHERE academic_term_id = :tid');
        $secStmt->execute([':tid' => $termId]);
        $secCount = (int) $secStmt->fetchColumn();

        // Safety check: clearances count
        $clrStmt = $this->pdo->prepare('SELECT COUNT(*) FROM evaluation_clearances WHERE academic_term_id = :tid');
        $clrStmt->execute([':tid' => $termId]);
        $clrCount = (int) $clrStmt->fetchColumn();

        if ($secCount > 0 || $clrCount > 0) {
            throw new RuntimeException("Cannot delete academic term '{$term['name']}' because it contains {$secCount} course section(s) or active evaluation records. Archive the term instead.");
        }

        $this->pdo->beginTransaction();
        try {
            $delWin = $this->pdo->prepare('DELETE FROM evaluation_window_configs WHERE academic_term_id = :tid');
            $delWin->execute([':tid' => $termId]);

            $delTerm = $this->pdo->prepare('DELETE FROM academic_terms WHERE id = :id');
            $delTerm->execute([':id' => $termId]);

            $this->pdo->commit();

            return [
                'success' => true,
                'term_id' => $termId,
                'term_code' => $term['code'],
                'term_name' => $term['name'],
                'message' => "Academic term '{$term['name']}' deleted successfully.",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a new course offering.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createCourse(array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $title = trim((string) ($data['title'] ?? ''));
        $units = (int) ($data['units'] ?? 3);
        $deptId = (int) ($data['department_id'] ?? 1);

        if ($code === '' || $title === '') {
            throw new InvalidArgumentException('Course code and title are required.');
        }
        if ($units < 1 || $units > 12) {
            throw new InvalidArgumentException('Units must be between 1 and 12.');
        }

        $chkDept = $this->pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
        $chkDept->execute([':id' => $deptId]);
        if (!$chkDept->fetch()) {
            throw new InvalidArgumentException('Selected department does not exist.');
        }

        $chkCourse = $this->pdo->prepare('SELECT id FROM courses WHERE code = :code LIMIT 1');
        $chkCourse->execute([':code' => $code]);
        if ($chkCourse->fetch()) {
            throw new InvalidArgumentException("Course with code '{$code}' already exists.");
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO courses (department_id, code, title, units, created_at, updated_at)
            VALUES (:dept_id, :code, :title, :units, :created_at, :updated_at)
        ');
        $stmt->execute([
            ':dept_id' => $deptId,
            ':code' => $code,
            ':title' => $title,
            ':units' => $units,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return [
            'success' => true,
            'course_id' => (int) $this->pdo->lastInsertId(),
            'code' => $code,
            'title' => $title,
            'units' => $units,
        ];
    }

    /**
     * Update an existing course offering in the catalog.
     *
     * @param int $courseId
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateCourse(int $courseId, array $data): array
    {
        if ($courseId <= 0) {
            throw new InvalidArgumentException('Valid course_id is required.');
        }

        $chkStmt = $this->pdo->prepare('SELECT id, code, title, units, department_id FROM courses WHERE id = :id LIMIT 1');
        $chkStmt->execute([':id' => $courseId]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new InvalidArgumentException("Course with ID {$courseId} not found.");
        }

        $code = strtoupper(trim((string) ($data['code'] ?? $existing['code'])));
        $title = trim((string) ($data['title'] ?? $existing['title']));
        $units = isset($data['units']) ? (int) $data['units'] : (int) $existing['units'];
        $deptId = isset($data['department_id']) ? (int) $data['department_id'] : (int) $existing['department_id'];

        if ($code === '' || $title === '') {
            throw new InvalidArgumentException('Course code and title are required.');
        }
        if ($units < 1 || $units > 12) {
            throw new InvalidArgumentException('Units must be between 1 and 12.');
        }

        $chkDept = $this->pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
        $chkDept->execute([':id' => $deptId]);
        if (!$chkDept->fetch()) {
            throw new InvalidArgumentException('Selected department does not exist.');
        }

        // Prevent collision with another course
        $chkCollision = $this->pdo->prepare('SELECT id FROM courses WHERE code = :code AND id != :id LIMIT 1');
        $chkCollision->execute([':code' => $code, ':id' => $courseId]);
        if ($chkCollision->fetch()) {
            throw new InvalidArgumentException("Another course with code '{$code}' already exists.");
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            UPDATE courses
            SET department_id = :dept_id, code = :code, title = :title, units = :units, updated_at = :updated_at
            WHERE id = :id
        ');
        $stmt->execute([
            ':dept_id' => $deptId,
            ':code' => $code,
            ':title' => $title,
            ':units' => $units,
            ':updated_at' => $now,
            ':id' => $courseId,
        ]);

        return [
            'success' => true,
            'course_id' => $courseId,
            'code' => $code,
            'title' => $title,
            'units' => $units,
            'department_id' => $deptId,
        ];
    }

    /**
     * Delete an unassigned course record, protecting relational integrity.
     *
     * @param int $courseId
     * @return array<string, mixed>
     */
    public function deleteCourse(int $courseId): array
    {
        if ($courseId <= 0) {
            throw new InvalidArgumentException('Valid course_id is required.');
        }

        $chkStmt = $this->pdo->prepare('SELECT id, code, title FROM courses WHERE id = :id LIMIT 1');
        $chkStmt->execute([':id' => $courseId]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new InvalidArgumentException("Course with ID {$courseId} not found.");
        }

        // Check if sections exist
        $secStmt = $this->pdo->prepare('SELECT COUNT(*) FROM course_sections WHERE course_id = :id');
        $secStmt->execute([':id' => $courseId]);
        $secCount = (int) $secStmt->fetchColumn();
        if ($secCount > 0) {
            throw new RuntimeException("Cannot delete course '{$existing['code']}' because it has {$secCount} active course section(s). Remove or reassign sections first.");
        }

        $delStmt = $this->pdo->prepare('DELETE FROM courses WHERE id = :id');
        $delStmt->execute([':id' => $courseId]);

        return [
            'success' => true,
            'course_id' => $courseId,
            'code' => $existing['code'],
            'message' => "Course '{$existing['code']}' deleted successfully.",
        ];
    }

    /**
     * Create a new course section under an academic term.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createSection(array $data): array
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        $termId = (int) ($data['academic_term_id'] ?? 0);
        $sectionCode = strtoupper(trim((string) ($data['section_code'] ?? '')));
        $facultyId = !empty($data['faculty_id']) ? (int) $data['faculty_id'] : 0;
        $enrolledCount = max(0, (int) ($data['enrolled_count'] ?? 0));

        if ($courseId <= 0 || $termId <= 0 || $sectionCode === '') {
            throw new InvalidArgumentException('Course, academic term, and section code are required.');
        }

        // Verify course exists
        $cStmt = $this->pdo->prepare('SELECT id, code, title FROM courses WHERE id = :id LIMIT 1');
        $cStmt->execute([':id' => $courseId]);
        $course = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            throw new InvalidArgumentException("Course ID {$courseId} does not exist.");
        }

        // Verify term exists
        $tStmt = $this->pdo->prepare('SELECT id, code FROM academic_terms WHERE id = :id LIMIT 1');
        $tStmt->execute([':id' => $termId]);
        if (!$tStmt->fetch()) {
            throw new InvalidArgumentException("Academic term ID {$termId} does not exist.");
        }

        // Verify faculty if provided
        if ($facultyId > 0) {
            $fStmt = $this->pdo->prepare("SELECT id, name, role FROM users WHERE id = :id AND role IN ('faculty', 'chair', 'dean') LIMIT 1");
            $fStmt->execute([':id' => $facultyId]);
            if (!$fStmt->fetch()) {
                throw new InvalidArgumentException("Assigned user ID {$facultyId} is not a valid instructor.");
            }
        }

        // Check duplicate
        $dupStmt = $this->pdo->prepare('
            SELECT id FROM course_sections 
            WHERE course_id = :cid AND academic_term_id = :tid AND section_code = :scode 
            LIMIT 1
        ');
        $dupStmt->execute([
            ':cid' => $courseId,
            ':tid' => $termId,
            ':scode' => $sectionCode,
        ]);
        if ($dupStmt->fetch()) {
            throw new InvalidArgumentException("Section '{$sectionCode}' already exists for this course and term.");
        }

        $now = date('Y-m-d H:i:s');
        $insStmt = $this->pdo->prepare('
            INSERT INTO course_sections (course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count, created_at, updated_at)
            VALUES (:cid, :fid, :tid, :scode, 0, 0, :enrolled, :created_at, :updated_at)
        ');
        $insStmt->execute([
            ':cid' => $courseId,
            ':fid' => $facultyId > 0 ? $facultyId : null,
            ':tid' => $termId,
            ':scode' => $sectionCode,
            ':enrolled' => $enrolledCount,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $sectionId = (int) $this->pdo->lastInsertId();

        return [
            'success' => true,
            'course_section_id' => $sectionId,
            'course_code' => $course['code'],
            'section_code' => $sectionCode,
            'faculty_id' => $facultyId > 0 ? $facultyId : null,
            'academic_term_id' => $termId,
            'enrolled_count' => $enrolledCount,
        ];
    }

    /**
     * Assign or unassign faculty to a course section.
     */
    public function assignFaculty(int $courseSectionId, ?int $facultyId): array
    {
        $secStmt = $this->pdo->prepare('SELECT id, section_code, course_id, academic_term_id FROM course_sections WHERE id = :id LIMIT 1');
        $secStmt->execute([':id' => $courseSectionId]);
        $section = $secStmt->fetch(PDO::FETCH_ASSOC);

        if (!$section) {
            throw new InvalidArgumentException("Course section ID {$courseSectionId} not found.");
        }

        $instructorName = 'Unassigned';
        $assignedId = 0;

        if ($facultyId !== null && $facultyId > 0) {
            $uStmt = $this->pdo->prepare("SELECT id, name, role FROM users WHERE id = :id AND role IN ('faculty', 'chair', 'dean') LIMIT 1");
            $uStmt->execute([':id' => $facultyId]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new InvalidArgumentException("Faculty user ID {$facultyId} not found or is not an academic instructor.");
            }
            $assignedId = (int) $user['id'];
            $instructorName = (string) $user['name'];
        }

        $upd = $this->pdo->prepare('
            UPDATE course_sections 
            SET faculty_id = :fid, updated_at = :updated_at 
            WHERE id = :id
        ');
        $upd->execute([
            ':fid' => $assignedId > 0 ? $assignedId : null,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $courseSectionId,
        ]);

        return [
            'success' => true,
            'course_section_id' => $courseSectionId,
            'faculty_id' => $assignedId > 0 ? $assignedId : null,
            'instructor_name' => $instructorName,
            'is_assigned' => $assignedId > 0,
        ];
    }

    /**
     * Safely delete an un-evaluated course section.
     * Prevents cascade-deletion of historical evaluations or clearance records.
     */
    public function deleteSection(int $courseSectionId): array
    {
        $secStmt = $this->pdo->prepare('SELECT id, section_code FROM course_sections WHERE id = :id LIMIT 1');
        $secStmt->execute([':id' => $courseSectionId]);
        $section = $secStmt->fetch(PDO::FETCH_ASSOC);

        if (!$section) {
            throw new InvalidArgumentException("Course section ID {$courseSectionId} not found.");
        }

        // Safety verification: Check evaluation_vault
        $vaultStmt = $this->pdo->prepare('SELECT COUNT(*) FROM evaluation_vault WHERE course_section_id = :id');
        $vaultStmt->execute([':id' => $courseSectionId]);
        $vaultCount = (int) $vaultStmt->fetchColumn();

        if ($vaultCount > 0) {
            throw new RuntimeException("Cannot delete course section with {$vaultCount} submitted evaluation(s). Preserving institutional audit records.");
        }

        // Safety verification: Check clearances
        $clrStmt = $this->pdo->prepare('SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = :id');
        $clrStmt->execute([':id' => $courseSectionId]);
        $clrCount = (int) $clrStmt->fetchColumn();

        if ($clrCount > 0) {
            throw new RuntimeException("Cannot delete course section with active clearance records. Preserving audit trail.");
        }

        $this->pdo->beginTransaction();
        try {
            // Remove empty enrollments
            $delEnr = $this->pdo->prepare('DELETE FROM enrollments WHERE course_section_id = :id');
            $delEnr->execute([':id' => $courseSectionId]);

            // Remove section
            $delSec = $this->pdo->prepare('DELETE FROM course_sections WHERE id = :id');
            $delSec->execute([':id' => $courseSectionId]);

            $this->pdo->commit();

            return [
                'success' => true,
                'deleted_course_section_id' => $courseSectionId,
                'section_code' => $section['section_code'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a new institutional user account (admin, dean, chair, faculty, student).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = strtolower(trim((string) ($data['role'] ?? 'faculty')));
        $institutionalId = strtoupper(trim((string) ($data['institutional_id'] ?? '')));
        $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;
        $password = (string) ($data['password'] ?? 'evaluatech123');

        if ($name === '' || $email === '' || $institutionalId === '') {
            throw new InvalidArgumentException('Full name, email address, and institutional ID are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid institutional email address is required.');
        }

        $validRoles = ['admin', 'dean', 'chair', 'faculty', 'student'];
        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException('Invalid role specified. Must be one of: admin, dean, chair, faculty, student.');
        }

        // Check duplicate email
        $chkEmail = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $chkEmail->execute([':email' => $email]);
        if ($chkEmail->fetch()) {
            throw new InvalidArgumentException("A user with email '{$email}' already exists.");
        }

        // Check duplicate institutional_id
        $chkId = $this->pdo->prepare('SELECT id FROM users WHERE institutional_id = :id LIMIT 1');
        $chkId->execute([':id' => $institutionalId]);
        if ($chkId->fetch()) {
            throw new InvalidArgumentException("A user with institutional ID '{$institutionalId}' already exists.");
        }

        // If department provided, verify it exists
        if ($departmentId !== null && $departmentId > 0) {
            $chkDept = $this->pdo->prepare('SELECT id, code, name FROM departments WHERE id = :id LIMIT 1');
            $chkDept->execute([':id' => $departmentId]);
            $deptRow = $chkDept->fetch(PDO::FETCH_ASSOC);
            if (!$deptRow) {
                throw new InvalidArgumentException('Selected academic department does not exist.');
            }
        } else {
            $departmentId = null;
        }

        $passwordHash = password_hash($password !== '' ? $password : 'evaluatech123', PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        // Detect schema columns in users table (SQLite test harness vs production migrations)
        $userCols = [];
        try {
            $cols = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if (!empty($col['name'])) {
                    $userCols[] = (string) $col['name'];
                }
            }
        } catch (\Throwable) {
            $userCols = ['institutional_id', 'name', 'email', 'password', 'role', 'department_id', 'is_active', 'created_at', 'updated_at'];
        }

        $pwdCol = in_array('password', $userCols, true) ? 'password' : 'password_hash';
        $hasTimestamps = in_array('created_at', $userCols, true);

        if ($hasTimestamps) {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (institutional_id, name, email, {$pwdCol}, role, department_id, is_active, created_at, updated_at)
                VALUES (:inst_id, :name, :email, :pwd, :role, :dept_id, 1, :created_at, :updated_at)
            ");
            $stmt->execute([
                ':inst_id' => $institutionalId,
                ':name' => $name,
                ':email' => $email,
                ':pwd' => $passwordHash,
                ':role' => $role,
                ':dept_id' => $departmentId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (institutional_id, name, email, {$pwdCol}, role, department_id, is_active)
                VALUES (:inst_id, :name, :email, :pwd, :role, :dept_id, 1)
            ");
            $stmt->execute([
                ':inst_id' => $institutionalId,
                ':name' => $name,
                ':email' => $email,
                ':pwd' => $passwordHash,
                ':role' => $role,
                ':dept_id' => $departmentId,
            ]);
        }

        $userId = (int) $this->pdo->lastInsertId();

        return [
            'success' => true,
            'user_id' => $userId,
            'institutional_id' => $institutionalId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'department_id' => $departmentId,
            'is_active' => true,
        ];
    }

    /**
     * Ensure evaluation window config exists for an academic term.
     */
    private function ensureEvaluationWindowConfig(int $termId): void
    {
        $chk = $this->pdo->prepare('SELECT id FROM evaluation_window_configs WHERE academic_term_id = :tid LIMIT 1');
        $chk->execute([':tid' => $termId]);
        if (!$chk->fetch()) {
            $now = date('Y-m-d H:i:s');
            $ins = $this->pdo->prepare("
                INSERT INTO evaluation_window_configs (
                    academic_term_id, status, min_dwell_seconds, straight_line_variance_threshold,
                    watchlist_mean_threshold, watchlist_negative_sentiment_pct, announcement_message,
                    starts_at, ends_at, timezone, last_window_action, created_at, updated_at
                ) VALUES (
                    :tid, 'OPEN', 0, 0.0, 3.50, 35.0,
                    'Evaluation window configured for academic term.',
                    '2026-01-01 08:00:00', '2026-12-31 23:59:59', 'Asia/Manila', 'OPEN',
                    :now, :now
                )
            ");
            $ins->execute([':tid' => $termId, ':now' => $now]);
        }
    }

    /**
     * Questionnaire preview with the canonical 25 questions across 5 clusters and 4 structured questions.
     *
     * @return array<string, mixed>
     */
    public function getQuestionnairePreview(): array
    {
        return [
            'instrument_title' => 'Pamantasan ng Lungsod ng Pasig — Official Student Evaluation of Teachers (SET)',
            'accreditation_standard' => 'AACCUP Area III: Faculty & Instructional Quality',
            'scale_points' => 5,
            'rating_scale_legend' => [
                ['score' => 1, 'label' => 'Strongly Disagree', 'description' => 'Unsatisfactory performance'],
                ['score' => 2, 'label' => 'Disagree', 'description' => 'Below standard expectations'],
                ['score' => 3, 'label' => 'Neutral', 'description' => 'Adequate / meets minimum requirement'],
                ['score' => 4, 'label' => 'Agree', 'description' => 'Above standard expectations'],
                ['score' => 5, 'label' => 'Strongly Agree', 'description' => 'Exceptional instructional excellence'],
            ],
            'clusters' => [
                [
                    'cluster_id' => 1,
                    'code' => 'CLUSTER-1',
                    'name' => 'Pedagogical Delivery',
                    'description' => 'Comprehensibility, subject matter mastery, teaching pacing, and practical code examples.',
                    'items' => [
                        ['id' => 1, 'code' => 'PD-1', 'text' => 'Explains complex concepts and algorithms clearly using structured examples.'],
                        ['id' => 2, 'code' => 'PD-2', 'text' => 'Demonstrates strong mastery of the subject matter and current industry practices.'],
                        ['id' => 3, 'code' => 'PD-3', 'text' => 'Maintains an appropriate instructional pace that supports student comprehension.'],
                        ['id' => 4, 'code' => 'PD-4', 'text' => 'Connects theoretical principles to practical laboratory or real-world code examples.'],
                        ['id' => 5, 'code' => 'PD-5', 'text' => 'Uses instructional materials and visual aids effectively to clarify topics.'],
                    ],
                ],
                [
                    'cluster_id' => 2,
                    'code' => 'CLUSTER-2',
                    'name' => 'Assessment & Grading',
                    'description' => 'Exam fairness, timeliness of returning quiz/test scores, rubric clarity, and constructive feedback.',
                    'items' => [
                        ['id' => 6, 'code' => 'AG-1', 'text' => 'Designs quizzes, projects, and examinations that fairly reflect covered course objectives.'],
                        ['id' => 7, 'code' => 'AG-2', 'text' => 'Provides clear grading rubrics and evaluation criteria prior to major assessments.'],
                        ['id' => 8, 'code' => 'AG-3', 'text' => 'Returns graded quizzes, assignments, and exam results within a reasonable timeframe.'],
                        ['id' => 9, 'code' => 'AG-4', 'text' => 'Delivers constructive, actionable feedback that helps students improve performance.'],
                        ['id' => 10, 'code' => 'AG-5', 'text' => 'Applies grading standards consistently and transparently across all enrolled students.'],
                    ],
                ],
                [
                    'cluster_id' => 3,
                    'code' => 'CLUSTER-3',
                    'name' => 'Classroom Management',
                    'description' => 'Punctuality, syllabus adherence, instructional time management, and student engagement.',
                    'items' => [
                        ['id' => 11, 'code' => 'CM-1', 'text' => 'Arrives on time and begins and concludes lectures according to the official schedule.'],
                        ['id' => 12, 'code' => 'CM-2', 'text' => 'Follows the approved course syllabus and communicates schedule adjustments promptly.'],
                        ['id' => 13, 'code' => 'CM-3', 'text' => 'Maintains an organized, focused, and productive classroom or laboratory environment.'],
                        ['id' => 14, 'code' => 'CM-4', 'text' => 'Encourages active student participation, technical questions, and collaborative inquiry.'],
                        ['id' => 15, 'code' => 'CM-5', 'text' => 'Maximizes allocated instructional hours without unnecessary off-topic digressions.'],
                    ],
                ],
                [
                    'cluster_id' => 4,
                    'code' => 'CLUSTER-4',
                    'name' => 'Professional Conduct',
                    'description' => 'Respectfulness, empathy, impartiality, academic professionalism, and learning atmosphere.',
                    'items' => [
                        ['id' => 16, 'code' => 'PC-1', 'text' => 'Treats all students with courtesy, fairness, and professional respect.'],
                        ['id' => 17, 'code' => 'PC-2', 'text' => 'Demonstrates openness and empathy when addressing legitimate academic concerns.'],
                        ['id' => 18, 'code' => 'PC-3', 'text' => 'Maintains availability during designated consultation and advising hours.'],
                        ['id' => 19, 'code' => 'PC-4', 'text' => 'Upholds institutional ethics, impartiality, and non-retaliatory academic standards.'],
                        ['id' => 20, 'code' => 'PC-5', 'text' => 'Fosters a psychologically safe and motivating academic learning environment.'],
                    ],
                ],
                [
                    'cluster_id' => 5,
                    'code' => 'CLUSTER-5',
                    'name' => 'Student Support & Mentorship',
                    'description' => 'Academic consultation, responsiveness to student inquiries, research/capstone guidance, and learner support.',
                    'items' => [
                        ['id' => 21, 'code' => 'SSM-1', 'text' => 'Provides accessible academic consultation and timely guidance outside lecture hours.'],
                        ['id' => 22, 'code' => 'SSM-2', 'text' => 'Responds constructively to student questions, emails, and academic clarifications.'],
                        ['id' => 23, 'code' => 'SSM-3', 'text' => 'Mentors students effectively on complex problem-solving, projects, or research tasks.'],
                        ['id' => 24, 'code' => 'SSM-4', 'text' => 'Adapts explanations or supplementary learning resources for struggling learners.'],
                        ['id' => 25, 'code' => 'SSM-5', 'text' => 'Encourages independent critical thinking, curiosity, and academic growth.'],
                    ],
                ],
            ],
            'professor_qualitative_questions' => [
                [
                    'id' => 'three_words',
                    'question' => 'Three words that best describe this professor’s instructional delivery:',
                    'format' => 'Three short descriptive tokens (e.g. engaging, approachable, rigorous)',
                ],
                [
                    'id' => 'room_for_improvements',
                    'question' => 'Specific areas or topics where the professor can improve (sub-topics a, b, c):',
                    'format' => 'Structured pedagogical suggestions for instructional refinement',
                ],
                [
                    'id' => 'teaching_strengths',
                    'question' => 'What did this professor do exceptionally well throughout the term?',
                    'format' => 'Qualitative narrative regarding commendable pedagogical practices',
                ],
                [
                    'id' => 'recommendations',
                    'question' => 'Actionable recommendations for future course offerings and laboratory exercises:',
                    'format' => 'Constructive suggestions for syllabus, assignments, or pacing enhancements',
                ],
            ],
        ];
    }

    /**
     * Retrieve detailed course-by-course enrollment and evaluation clearance status for a student.
     *
     * @return array<string, mixed>|null
     */
    public function getStudentEnrollmentDetails(int $studentId, ?int $academicTermId = null): ?array
    {
        $studStmt = $this->pdo->prepare('
            SELECT
                u.id,
                u.institutional_id,
                u.name,
                u.email,
                u.department_id,
                d.code AS department_code,
                d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id AND u.role = \'student\'
            LIMIT 1
        ');
        $studStmt->execute([':id' => $studentId]);
        $student = $studStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return null;
        }

        $selectedTermId = $academicTermId ?? $this->getActiveTermId();
        $queueData = $this->evaluationService->getStudentQueue($studentId, $selectedTermId);

        return [
            'student' => [
                'id' => (int) $student['id'],
                'institutional_id' => (string) $student['institutional_id'],
                'name' => (string) $student['name'],
                'email' => (string) $student['email'],
                'department_code' => (string) ($student['department_code'] ?: 'CCS'),
                'department_name' => (string) ($student['department_name'] ?: 'College of Computer Studies'),
            ],
            'term_id' => $selectedTermId,
            'completed_count' => (int) ($queueData['completed_count'] ?? 0),
            'total_count' => (int) ($queueData['total_count'] ?? 0),
            'completion_percentage' => (float) ($queueData['completion_percentage'] ?? 0.0),
            'overall_clearance_status' => (string) ($queueData['overall_clearance_status'] ?? 'PENDING'),
            'exam_permit_unlocked' => (bool) ($queueData['exam_permit_unlocked'] ?? false),
            'exam_permit_token' => $queueData['exam_permit_token'] ?? null,
            'courses' => $queueData['queue'] ?? [],
        ];
    }

    /**
     * Retrieve the enrolled student roster and evaluation clearance status for a course section.
     *
     * @return array<string, mixed>|null
     */
    public function getSectionRoster(int $sectionId): ?array
    {
        $secStmt = $this->pdo->prepare('
            SELECT
                cs.id,
                cs.section_code,
                cs.course_id,
                cs.academic_term_id,
                cs.faculty_id,
                c.code AS course_code,
                c.title AS course_title,
                c.department_id,
                d.code AS department_code,
                d.name AS department_name,
                at.code AS term_code,
                at.name AS term_name,
                f.name AS instructor_name,
                f.institutional_id AS instructor_institutional_id
            FROM course_sections cs
            JOIN courses c ON c.id = cs.course_id
            JOIN academic_terms at ON at.id = cs.academic_term_id
            LEFT JOIN departments d ON d.id = c.department_id
            LEFT JOIN users f ON f.id = cs.faculty_id
            WHERE cs.id = :id
            LIMIT 1
        ');
        $secStmt->execute([':id' => $sectionId]);
        $section = $secStmt->fetch(PDO::FETCH_ASSOC);
        if (!$section) {
            return null;
        }

        // Fetch all enrolled students with clearance status
        $rosterStmt = $this->pdo->prepare('
            SELECT
                u.id AS student_id,
                u.institutional_id,
                u.name,
                u.email,
                sd.code AS department_code,
                sd.name AS department_name,
                ec.clearance_token,
                ec.cleared_at
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            LEFT JOIN departments sd ON sd.id = u.department_id
            LEFT JOIN evaluation_clearances ec
                ON ec.student_id = u.id AND ec.course_section_id = e.course_section_id
            WHERE e.course_section_id = :sid
            ORDER BY u.name ASC
        ');
        $rosterStmt->execute([':sid' => $sectionId]);
        $rawStudents = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

        $students = [];
        $evaluatedCount = 0;
        $totalEnrolled = count($rawStudents);

        foreach ($rawStudents as $s) {
            $hasEvaluated = !empty($s['clearance_token']);
            if ($hasEvaluated) {
                $evaluatedCount++;
            }
            $students[] = [
                'student_id' => (int) $s['student_id'],
                'institutional_id' => (string) $s['institutional_id'],
                'name' => (string) $s['name'],
                'email' => (string) $s['email'],
                'department_code' => (string) ($s['department_code'] ?: 'CCS'),
                'department_name' => (string) ($s['department_name'] ?: 'College of Computer Studies'),
                'has_evaluated' => $hasEvaluated,
                'clearance_status' => $hasEvaluated ? 'CLEARED' : 'PENDING',
                'clearance_token' => $s['clearance_token'] ? (string) $s['clearance_token'] : null,
                'cleared_at' => $s['cleared_at'] ? (string) $s['cleared_at'] : null,
            ];
        }

        $turnoutPct = $totalEnrolled > 0 ? round(($evaluatedCount / $totalEnrolled) * 100, 1) : 0.0;

        return [
            'section' => [
                'id' => (int) $section['id'],
                'section_code' => (string) $section['section_code'],
                'course_code' => (string) $section['course_code'],
                'course_title' => (string) $section['course_title'],
                'department_code' => (string) ($section['department_code'] ?: 'CCS'),
                'department_name' => (string) ($section['department_name'] ?: 'College of Computer Studies'),
                'instructor_name' => (string) ($section['instructor_name'] ?: 'Unassigned Faculty'),
                'term_code' => (string) $section['term_code'],
                'term_name' => (string) $section['term_name'],
                'enrolled_count' => $totalEnrolled,
                'evaluated_count' => $evaluatedCount,
                'turnout_pct' => $turnoutPct,
            ],
            'students' => $students,
        ];
    }

    /**
     * Get the active academic term ID or default to the lowest term ID.
     */
    public function getActiveTermId(): int
    {
        $stmt = $this->pdo->query('SELECT id FROM academic_terms WHERE is_active = 1 LIMIT 1');
        $id = $stmt ? $stmt->fetchColumn() : false;
        if ($id) {
            return (int) $id;
        }

        $fallback = $this->pdo->query('SELECT id FROM academic_terms ORDER BY id ASC LIMIT 1');
        return $fallback ? (int) $fallback->fetchColumn() : 1;
    }

    /**
     * Retrieve assigned course sections and teaching portfolio for a faculty member.
     *
     * @param int $facultyId
     * @param int|null $academicTermId
     * @return array<string, mixed>|null
     */
    public function getFacultyAssignments(int $facultyId, ?int $academicTermId = null): ?array
    {
        $facStmt = $this->pdo->prepare("
            SELECT
                u.id,
                u.institutional_id,
                u.name,
                u.email,
                u.department_id,
                u.role,
                u.is_active,
                d.code AS department_code,
                d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id AND u.role IN ('faculty', 'chair', 'dean')
            LIMIT 1
        ");
        $facStmt->execute([':id' => $facultyId]);
        $faculty = $facStmt->fetch(PDO::FETCH_ASSOC);
        if (!$faculty) {
            return null;
        }

        $termId = $academicTermId ?? $this->getActiveTermId();
        $termStmt = $this->pdo->prepare('SELECT id, code, name, academic_year FROM academic_terms WHERE id = :id LIMIT 1');
        $termStmt->execute([':id' => $termId]);
        $term = $termStmt->fetch(PDO::FETCH_ASSOC);

        $secStmt = $this->pdo->prepare('
            SELECT
                cs.id AS course_section_id,
                cs.section_code,
                cs.course_id,
                cs.faculty_id,
                cs.academic_term_id,
                cs.term_grades_submitted,
                cs.registrar_lock,
                cs.enrolled_count AS stored_enrolled_count,
                c.code AS course_code,
                c.title AS course_title,
                c.units,
                c.department_id,
                d.code AS department_code,
                d.name AS department_name
            FROM course_sections cs
            INNER JOIN courses c ON c.id = cs.course_id
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE cs.faculty_id = :fid AND cs.academic_term_id = :tid
            ORDER BY c.code ASC, cs.section_code ASC
        ');
        $secStmt->execute([':fid' => $facultyId, ':tid' => $termId]);
        $rawSections = $secStmt->fetchAll(PDO::FETCH_ASSOC);

        $sections = [];
        $totalEnrolled = 0;
        $totalEvaluations = 0;

        foreach ($rawSections as $row) {
            $secId = (int) $row['course_section_id'];

            // Real enrollment count
            $enrCountStmt = $this->pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_section_id = :sec_id');
            $enrCountStmt->execute([':sec_id' => $secId]);
            $realEnrolledCount = (int) $enrCountStmt->fetchColumn();
            if ($realEnrolledCount === 0 && !empty($row['stored_enrolled_count'])) {
                $realEnrolledCount = (int) $row['stored_enrolled_count'];
            }
            $totalEnrolled += $realEnrolledCount;

            // Clearances (completed evaluations count)
            $clrStmt = $this->pdo->prepare("SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = :sec_id AND status = 'CLEARED'");
            $clrStmt->execute([':sec_id' => $secId]);
            $clearedCount = (int) $clrStmt->fetchColumn();
            $totalEvaluations += $clearedCount;

            $turnoutPct = $realEnrolledCount > 0 ? round(($clearedCount / $realEnrolledCount) * 100, 1) : 0.0;

            $gradesSubmitted = (bool) $row['term_grades_submitted'];
            $registrarLock = (bool) $row['registrar_lock'];

            $sections[] = [
                'course_section_id' => $secId,
                'section_code' => (string) $row['section_code'],
                'course_id' => (int) $row['course_id'],
                'course_code' => (string) $row['course_code'],
                'course_title' => (string) $row['course_title'],
                'units' => (int) ($row['units'] ?? 3),
                'department_id' => (int) ($row['department_id'] ?? 1),
                'department_code' => (string) ($row['department_code'] ?? 'CCS'),
                'department_name' => (string) ($row['department_name'] ?? 'College of Computer Studies'),
                'enrolled_count' => $realEnrolledCount,
                'cleared_count' => $clearedCount,
                'turnout_pct' => $turnoutPct,
                'term_grades_submitted' => $gradesSubmitted,
                'registrar_lock' => $registrarLock,
                'double_blind_unlocked' => $gradesSubmitted && $registrarLock,
            ];
        }

        $overallTurnout = $totalEnrolled > 0 ? round(($totalEvaluations / $totalEnrolled) * 100, 1) : 0.0;

        return [
            'faculty' => [
                'id' => (int) $faculty['id'],
                'institutional_id' => (string) $faculty['institutional_id'],
                'name' => (string) $faculty['name'],
                'email' => (string) $faculty['email'],
                'department_code' => (string) ($faculty['department_code'] ?: 'CCS'),
                'department_name' => (string) ($faculty['department_name'] ?: 'College of Computer Studies'),
                'role' => (string) $faculty['role'],
                'is_active' => (bool) $faculty['is_active'],
            ],
            'term' => [
                'id' => $termId,
                'code' => $term ? (string) $term['code'] : 'CURRENT',
                'name' => $term ? (string) $term['name'] : 'Active Academic Term',
                'academic_year' => $term ? (string) $term['academic_year'] : '2026-2027',
            ],
            'summary' => [
                'total_sections' => count($sections),
                'total_enrolled' => $totalEnrolled,
                'total_evaluations' => $totalEvaluations,
                'overall_turnout_pct' => $overallTurnout,
            ],
            'sections' => $sections,
        ];
    }

    /**
     * Update an institutional user account's profile, role, department, or password.
     *
     * @param int $userId
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateUser(int $userId, array $data): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Valid user ID required.');
        }

        $chkStmt = $this->pdo->prepare('SELECT id, institutional_id, name, email, role, department_id, is_active FROM users WHERE id = :id LIMIT 1');
        $chkStmt->execute([':id' => $userId]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new InvalidArgumentException("User with ID {$userId} not found.");
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : (string) $existing['name'];
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : (string) $existing['email'];
        $role = isset($data['role']) ? strtolower(trim((string) $data['role'])) : (string) $existing['role'];
        $deptId = array_key_exists('department_id', $data) ? (!empty($data['department_id']) ? (int) $data['department_id'] : null) : ($existing['department_id'] !== null ? (int) $existing['department_id'] : null);
        $newPassword = isset($data['password']) && trim((string) $data['password']) !== '' ? (string) $data['password'] : null;

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('Full name and email address are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid institutional email address is required.');
        }

        $validRoles = ['admin', 'dean', 'chair', 'faculty', 'student'];
        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException('Invalid role specified. Must be one of: admin, dean, chair, faculty, student.');
        }

        // Email duplicate check
        $dupEmail = $this->pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $dupEmail->execute([':email' => $email, ':id' => $userId]);
        if ($dupEmail->fetch()) {
            throw new InvalidArgumentException("Another user already exists with email '{$email}'.");
        }

        // Department check
        if ($deptId !== null && $deptId > 0) {
            $chkDept = $this->pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
            $chkDept->execute([':id' => $deptId]);
            if (!$chkDept->fetch()) {
                throw new InvalidArgumentException('Selected academic department does not exist.');
            }
        } else {
            $deptId = null;
        }

        // Detect schema columns in users table (password vs password_hash, updated_at)
        $userCols = [];
        try {
            $cols = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if (!empty($col['name'])) {
                    $userCols[] = (string) $col['name'];
                }
            }
        } catch (\Throwable) {
            $userCols = ['institutional_id', 'name', 'email', 'password', 'role', 'department_id', 'is_active', 'updated_at'];
        }

        $pwdCol = in_array('password', $userCols, true) ? 'password' : 'password_hash';
        $hasUpdatedAt = in_array('updated_at', $userCols, true);

        $params = [
            ':name' => $name,
            ':email' => $email,
            ':role' => $role,
            ':dept_id' => $deptId,
            ':id' => $userId,
        ];

        $updateFields = ['name = :name', 'email = :email', 'role = :role', 'department_id = :dept_id'];
        if ($newPassword !== null) {
            $params[':pwd'] = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateFields[] = "{$pwdCol} = :pwd";
        }
        if ($hasUpdatedAt) {
            $params[':updated_at'] = date('Y-m-d H:i:s');
            $updateFields[] = 'updated_at = :updated_at';
        }

        $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
        $updStmt = $this->pdo->prepare($sql);
        $updStmt->execute($params);

        return [
            'success' => true,
            'user_id' => $userId,
            'institutional_id' => (string) $existing['institutional_id'],
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'department_id' => $deptId,
            'password_updated' => $newPassword !== null,
            'message' => "User account '{$name}' updated successfully.",
        ];
    }

    /**
     * Toggle a user account's active/inactive status (protects against self-deactivation).
     *
     * @param int $userId
     * @param int|null $operatorId
     * @return array<string, mixed>
     */
    public function toggleUserStatus(int $userId, ?int $operatorId = null): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Valid user ID required.');
        }

        $chkStmt = $this->pdo->prepare('SELECT id, institutional_id, name, email, is_active FROM users WHERE id = :id LIMIT 1');
        $chkStmt->execute([':id' => $userId]);
        $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new InvalidArgumentException("User with ID {$userId} not found.");
        }

        $currentStatus = (bool) $existing['is_active'];

        // Self-deactivation protection
        if ($operatorId !== null && $operatorId === $userId && $currentStatus) {
            throw new RuntimeException('CANNOT_DEACTIVATE_SELF: Administrators cannot deactivate their own active session account.');
        }

        $newStatus = !$currentStatus;
        $now = date('Y-m-d H:i:s');

        // Check if updated_at exists
        $userCols = [];
        try {
            $cols = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if (!empty($col['name'])) {
                    $userCols[] = (string) $col['name'];
                }
            }
        } catch (\Throwable) {
            $userCols = ['is_active', 'updated_at'];
        }

        if (in_array('updated_at', $userCols, true)) {
            $upd = $this->pdo->prepare('UPDATE users SET is_active = :status, updated_at = :updated_at WHERE id = :id');
            $upd->execute([':status' => $newStatus ? 1 : 0, ':updated_at' => $now, ':id' => $userId]);
        } else {
            $upd = $this->pdo->prepare('UPDATE users SET is_active = :status WHERE id = :id');
            $upd->execute([':status' => $newStatus ? 1 : 0, ':id' => $userId]);
        }

        return [
            'success' => true,
            'user_id' => $userId,
            'institutional_id' => (string) $existing['institutional_id'],
            'name' => (string) $existing['name'],
            'is_active' => $newStatus,
            'status_label' => $newStatus ? 'ACTIVE' : 'INACTIVE',
            'message' => "User account '{$existing['name']}' is now " . ($newStatus ? 'ACTIVE' : 'INACTIVE') . '.',
        ];
    }
}
