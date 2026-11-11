<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

class RosterImportService
{
    private PDO $pdo;
    private IntegrityValidatorService $validator;

    public function __construct(
        ?PDO $pdo = null,
        ?IntegrityValidatorService $validator = null
    ) {
        $this->validator = $validator ?? new IntegrityValidatorService();
        $decoupled = new DecoupledEvaluationService($pdo, $this->validator);
        $this->pdo = $decoupled->getPdo();
    }

    /**
     * Atomically ingest an array of academic roster rows. Rejects entire batch on any malformed row.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function importRows(array $rows, int $academicTermId = 1): array
    {
        if (count($rows) === 0) {
            return [
                'imported' => 0,
                'status' => 'REJECTED',
                'code' => 'EMPTY_ROSTER_PAYLOAD',
                'http_status' => 422,
                'errors' => ['Roster payload contains no rows.'],
            ];
        }

        $errors = [];
        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $studentInstId = trim((string) ($row['student_institutional_id'] ?? $row['student_number'] ?? ''));
            $courseCode = trim((string) ($row['course_code'] ?? ''));
            $sectionCode = trim((string) ($row['section_code'] ?? 'A'));
            $instructorName = trim((string) ($row['instructor_name'] ?? $row['faculty_name'] ?? ''));

            if ($studentInstId === '' || $courseCode === '' || $sectionCode === '' || $instructorName === '') {
                $errors[] = "Row {$rowNum}: Missing required fields (student_institutional_id, course_code, section_code, instructor_name).";
                continue;
            }

            if (!preg_match('/^[A-Z]{2,6}-\d{3}$/', $courseCode)) {
                $errors[] = "Row {$rowNum}: Invalid course_code format '{$courseCode}' (expected e.g. CS-401).";
            }
        }

        if (count($errors) > 0) {
            return [
                'imported' => 0,
                'status' => 'REJECTED',
                'code' => 'MALFORMED_ROSTER_ROW',
                'http_status' => 422,
                'errors' => $errors,
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $this->ensureDefaultTerm($academicTermId);
            $importedCount = 0;

            foreach ($rows as $row) {
                $deptCode = trim((string) ($row['department_code'] ?? 'CCS'));
                $deptName = trim((string) ($row['department_name'] ?? 'Department of Computer Science'));
                $deptId = $this->upsertDepartment($deptCode, $deptName);

                $courseCode = trim((string) $row['course_code']);
                $courseTitle = trim((string) ($row['course_title'] ?? "{$courseCode} Course"));
                $courseId = $this->upsertCourse($deptId, $courseCode, $courseTitle);

                $instructorName = trim((string) ($row['instructor_name'] ?? $row['faculty_name']));
                $facultyEmail = trim((string) ($row['faculty_email'] ?? $this->slugEmail($instructorName, 'faculty')));
                $facultyInstId = trim((string) ($row['faculty_institutional_id'] ?? 'FAC-' . strtoupper(substr(md5($facultyEmail), 0, 6))));
                $facultyId = $this->upsertUser($facultyInstId, $instructorName, $facultyEmail, 'faculty', $deptId);

                $studentInstId = trim((string) ($row['student_institutional_id'] ?? $row['student_number']));
                $studentName = trim((string) ($row['student_name'] ?? "Student {$studentInstId}"));
                $studentEmail = trim((string) ($row['student_email'] ?? strtolower($studentInstId) . '@student.evaluatech.edu'));
                $studentId = $this->upsertUser($studentInstId, $studentName, $studentEmail, 'student', $deptId);

                $sectionCode = trim((string) ($row['section_code'] ?? 'A'));
                $sectionId = $this->upsertCourseSection($courseId, $facultyId, $academicTermId, $sectionCode);

                $this->upsertEnrollment($studentId, $sectionId, $academicTermId);
                $importedCount++;
            }

            $this->pdo->commit();

            return [
                'imported' => $importedCount,
                'status' => 'IMPORTED',
                'code' => 'ROSTER_IMPORTED',
                'http_status' => 200,
                'errors' => [],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'imported' => 0,
                'status' => 'REJECTED',
                'code' => 'ROSTER_TRANSACTION_ROLLED_BACK',
                'http_status' => 422,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Atomically import a CSV string with a header row.
     *
     * @return array<string, mixed>
     */
    public function importCsv(string $csvContent, int $academicTermId = 1): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        if (count($lines) < 2) {
            return [
                'imported' => 0,
                'status' => 'REJECTED',
                'code' => 'INVALID_CSV_FORMAT',
                'http_status' => 422,
                'errors' => ['CSV must include a header row and at least one data row.'],
            ];
        }

        $headers = array_map(
            static fn (?string $col): string => strtolower(trim((string) $col)),
            str_getcsv((string) array_shift($lines))
        );

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $rowAssoc = [];
            foreach ($headers as $idx => $header) {
                $rowAssoc[$header] = isset($values[$idx]) ? trim((string) $values[$idx]) : '';
            }
            $rows[] = $rowAssoc;
        }

        return $this->importRows($rows, $academicTermId);
    }

    /**
     * Atomically import a JSON string representing a list of roster objects.
     *
     * @return array<string, mixed>
     */
    public function importJson(string $jsonContent, int $academicTermId = 1): array
    {
        $decoded = json_decode($jsonContent, true);
        if (!is_array($decoded)) {
            return [
                'imported' => 0,
                'status' => 'REJECTED',
                'code' => 'INVALID_JSON_FORMAT',
                'http_status' => 422,
                'errors' => ['Malformed JSON payload.'],
            ];
        }

        $rows = isset($decoded['rows']) && is_array($decoded['rows']) ? $decoded['rows'] : $decoded;
        return $this->importRows(array_values($rows), $academicTermId);
    }

    /**
     * Export accreditation summary rows to CSV with formula injection sanitization.
     *
     * @param list<array<string, scalar|null>> $rows
     */
    public function exportAccreditationCsv(array $rows): string
    {
        if (count($rows) === 0) {
            return "faculty_name,course_code,mean_score,sentiment_valence,pedagogical_tag\n";
        }

        $headers = array_keys($rows[0]);
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $escapedCells = [];
            foreach ($headers as $header) {
                $raw = (string) ($row[$header] ?? '');
                $sanitized = $this->validator->sanitizeCsvCell($raw);
                if (str_contains($sanitized, ',') || str_contains($sanitized, '"') || str_contains($sanitized, "\n")) {
                    $sanitized = '"' . str_replace('"', '""', $sanitized) . '"';
                }
                $escapedCells[] = $sanitized;
            }
            $lines[] = implode(',', $escapedCells);
        }

        return implode("\n", $lines) . "\n";
    }

    private function ensureDefaultTerm(int $termId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM academic_terms WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $termId]);
        if ($stmt->fetch() !== false) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO academic_terms (id, code, name, academic_year, is_active, evaluation_open)
            VALUES (:id, :code, 'Fall Term Final SET', '2026-2027', 1, 1)
        ");
        $ins->execute([
            ':id' => $termId,
            ':code' => "FALL-{$termId}",
        ]);
    }

    private function upsertDepartment(string $code, string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM departments WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return (int) $row['id'];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO departments (code, name, college_name)
            VALUES (:code, :name, 'College of Computer Studies')
        ");
        $ins->execute([':code' => $code, ':name' => $name]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertCourse(int $departmentId, string $code, string $title): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM courses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return (int) $row['id'];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO courses (department_id, code, title, units)
            VALUES (:dept_id, :code, :title, 3)
        ");
        $ins->execute([
            ':dept_id' => $departmentId,
            ':code' => $code,
            ':title' => $title,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertUser(string $instId, string $name, string $email, string $role, int $deptId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE institutional_id = :inst_id OR email = :email LIMIT 1');
        $stmt->execute([':inst_id' => $instId, ':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return (int) $row['id'];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO users (institutional_id, name, email, password, role, department_id, is_active)
            VALUES (:inst_id, :name, :email, :password, :role, :dept_id, 1)
        ");
        $ins->execute([
            ':inst_id' => $instId,
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash('evaluatech123', PASSWORD_BCRYPT),
            ':role' => $role,
            ':dept_id' => $deptId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertCourseSection(int $courseId, int $facultyId, int $termId, string $sectionCode): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM course_sections
            WHERE course_id = :course_id AND academic_term_id = :term_id AND section_code = :sec
            LIMIT 1
        ");
        $stmt->execute([
            ':course_id' => $courseId,
            ':term_id' => $termId,
            ':sec' => $sectionCode,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return (int) $row['id'];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO course_sections (course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count)
            VALUES (:course_id, :faculty_id, :term_id, :sec, 0, 0, 1)
        ");
        $ins->execute([
            ':course_id' => $courseId,
            ':faculty_id' => $facultyId,
            ':term_id' => $termId,
            ':sec' => $sectionCode,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertEnrollment(int $studentId, int $sectionId, int $termId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM enrollments
            WHERE student_id = :student_id AND course_section_id = :section_id
            LIMIT 1
        ");
        $stmt->execute([':student_id' => $studentId, ':section_id' => $sectionId]);
        if ($stmt->fetch() !== false) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO enrollments (student_id, course_section_id, academic_term_id, enrollment_status)
            VALUES (:student_id, :section_id, :term_id, 'ENROLLED')
        ");
        $ins->execute([
            ':student_id' => $studentId,
            ':section_id' => $sectionId,
            ':term_id' => $termId,
        ]);
    }

    private function slugEmail(string $name, string $prefix): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '.', trim($name)) ?? $prefix);
        return trim($slug, '.') . "@{$prefix}.evaluatech.edu";
    }

    /**
     * Export calamanCy qualitative synthesis as a sanitized CSV string.
     * Neutralizes formula injection on all descriptors, topics, strengths, and recommendations.
     *
     * @param array<string, mixed> $synthesis
     */
    public function exportQualitativeSynthesisCsv(array $synthesis): string
    {
        $lines = [];
        $lines[] = 'sep=,';
        $lines[] = '# SCHOOL AAAAA13 • calamanCy + VADER Qualitative SET Synthesis Export';
        $lines[] = '# Reference: https://aaaaa13.edu/ • NLP Engine: tl_calamancy_md';
        $lines[] = '';

        // Section 1: Top Descriptors
        $lines[] = '--- SECTION 1: TOP 3 WORDS DESCRIPTORS ---';
        $lines[] = 'DescriptorWord,FrequencyCount,PolarityScore,Valence';
        $descriptors = is_array($synthesis['top_descriptors'] ?? null) ? $synthesis['top_descriptors'] : [];
        foreach ($descriptors as $d) {
            $word = $this->validator->sanitizeCsvCell((string) ($d['word'] ?? ''));
            $count = (int) ($d['count'] ?? 0);
            $polarity = number_format((float) ($d['polarity'] ?? 0.0), 2);
            $valence = $this->validator->sanitizeCsvCell((string) ($d['valence'] ?? 'Neutral'));
            $lines[] = "\"{$word}\",{$count},{$polarity},\"{$valence}\"";
        }
        $lines[] = '';

        // Section 2: Room for Improvement Topics
        $lines[] = '--- SECTION 2: ROOM FOR IMPROVEMENT PEDAGOGICAL TOPICS ---';
        $lines[] = 'SubItem,PedagogicalCluster,TopicObservation,PolarityScore,Valence';
        $topics = is_array($synthesis['improvement_topics'] ?? null) ? $synthesis['improvement_topics'] : [];
        foreach ($topics as $t) {
            $subItem = $this->validator->sanitizeCsvCell((string) ($t['sub_item'] ?? 'a.'));
            $cluster = $this->validator->sanitizeCsvCell((string) ($t['pedagogical_tag'] ?? 'Pedagogical Delivery'));
            $topic = $this->validator->sanitizeCsvCell((string) ($t['topic'] ?? ''));
            $polarity = number_format((float) ($t['polarity'] ?? 0.0), 2);
            $valence = $this->validator->sanitizeCsvCell((string) ($t['valence'] ?? 'Negative'));
            $lines[] = "\"{$subItem}\",\"{$cluster}\",\"{$topic}\",{$polarity},\"{$valence}\"";
        }
        $lines[] = '';

        // Section 3: Teaching Strengths & Actionable Recommendations
        $lines[] = '--- SECTION 3: TEACHING STRENGTHS & ACTIONABLE RECOMMENDATIONS ---';
        $lines[] = 'Category,Content';
        $strengths = is_array($synthesis['key_strengths'] ?? null) ? $synthesis['key_strengths'] : [];
        foreach ($strengths as $s) {
            $sText = $this->validator->sanitizeCsvCell((string) $s);
            $lines[] = "\"Teaching Strength\",\"{$sText}\"";
        }
        $recommendations = is_array($synthesis['key_recommendations'] ?? null) ? $synthesis['key_recommendations'] : [];
        foreach ($recommendations as $r) {
            $rText = $this->validator->sanitizeCsvCell((string) $r);
            $lines[] = "\"Actionable Recommendation\",\"{$rText}\"";
        }

        return implode("\n", $lines) . "\n";
    }
}
