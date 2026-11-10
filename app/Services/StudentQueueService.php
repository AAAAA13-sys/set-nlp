<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * StudentQueueService
 *
 * Dedicated domain service responsible for retrieving a student's active
 * evaluation queue, calculating overall clearance completion metrics,
 * and generating tamper-evident start tokens for incomplete course sections.
 */
class StudentQueueService
{
    private PDO $pdo;
    private IntegrityValidatorService $validator;
    private string $appSecret;

    public function __construct(
        PDO $pdo,
        ?IntegrityValidatorService $validator = null,
        ?string $appSecret = null
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator ?? new IntegrityValidatorService();
        $this->appSecret = DecoupledEvaluationService::resolveSigningSecret($appSecret);
    }

    /**
     * Retrieve the active student's evaluation queue with progress metrics and start tokens.
     *
     * @return array<string, mixed>
     */
    public function getStudentQueue(int $studentId, ?int $academicTermId = null): array
    {
        $sql = "
            SELECT
                e.course_section_id,
                e.academic_term_id,
                cs.section_code,
                c.code AS course_code,
                c.title AS course_title,
                f.id AS faculty_id,
                f.name AS instructor_name,
                ec.status AS clearance_status,
                ec.clearance_token,
                ec.cleared_at
            FROM enrollments e
            INNER JOIN course_sections cs ON cs.id = e.course_section_id
            INNER JOIN courses c ON c.id = cs.course_id
            INNER JOIN users f ON f.id = cs.faculty_id
            LEFT JOIN evaluation_clearances ec
                ON ec.student_id = e.student_id AND ec.course_section_id = e.course_section_id
            WHERE e.student_id = :student_id
        ";
        $params = [':student_id' => $studentId];

        if ($academicTermId !== null) {
            $sql .= ' AND e.academic_term_id = :term_id';
            $params[':term_id'] = $academicTermId;
        }

        $sql .= ' ORDER BY e.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $queueItems = [];
        $completedCount = 0;

        foreach ($rows as $row) {
            $isCompleted = ($row['clearance_status'] ?? 'PENDING') === 'CLEARED';
            if ($isCompleted) {
                $completedCount++;
            }

            $sectionId = (int) $row['course_section_id'];
            $queueItems[] = [
                'course_section_id' => $sectionId,
                'academic_term_id' => (int) $row['academic_term_id'],
                'course_code' => (string) $row['course_code'],
                'course_title' => (string) $row['course_title'],
                'section_code' => (string) $row['section_code'],
                'faculty_id' => (int) $row['faculty_id'],
                'instructor_name' => (string) $row['instructor_name'],
                'status' => $isCompleted ? 'COMPLETED' : 'PENDING',
                'clearance_status' => $isCompleted ? 'CLEARED' : 'PENDING',
                'action_label' => $isCompleted ? 'Clearance Signed' : 'Start Evaluation',
                'clearance_token' => $row['clearance_token'] ?? null,
                'cleared_at' => $row['cleared_at'] ?? null,
                'start_token' => $isCompleted
                    ? null
                    : $this->validator->issueStartToken($studentId, $sectionId, $this->appSecret),
            ];
        }

        $totalCount = count($queueItems);
        $completionPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0.0;
        $allCleared = $totalCount > 0 && $completedCount === $totalCount;

        $examPermitToken = $allCleared
            ? 'PERMIT-' . strtoupper(substr(hash_hmac('sha256', "PERMIT:{$studentId}:{$totalCount}", $this->appSecret), 0, 20))
            : null;

        return [
            'student_id' => $studentId,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'progress_label' => "My Evaluation Queue ({$completedCount} of {$totalCount} Completed)",
            'completion_percentage' => $completionPct,
            'overall_clearance_status' => $allCleared ? 'CLEARED' : 'PENDING',
            'exam_permit_unlocked' => $allCleared,
            'exam_permit_token' => $examPermitToken,
            'queue' => $queueItems,
            'courses' => $queueItems,
        ];
    }
}
