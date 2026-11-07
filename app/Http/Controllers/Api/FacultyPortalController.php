<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\DecoupledEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDO;

class FacultyPortalController extends Controller
{
    private DecoupledEvaluationService $evaluationService;
    private PDO $pdo;

    public function __construct(?DecoupledEvaluationService $evaluationService = null)
    {
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService();
        $this->pdo = $this->evaluationService->getPdo();
    }

    public function sectionReport(Request $request, int $sectionId = 0): JsonResponse
    {
        $resolvedSectionId = $sectionId > 0
            ? $sectionId
            : (int) ($request->route('sectionId') ?? $request->input('course_section_id', 0));

        $user = $request->user();
        $facultyId = ($user !== null && ($user->role ?? '') === 'faculty')
            ? (int) ($user->id ?? 0)
            : ($request->input('faculty_id') !== null ? (int) $request->input('faculty_id') : null);

        $report = $this->evaluationService->getFacultySectionReport($resolvedSectionId, $facultyId);
        $status = (int) ($report['http_status'] ?? 200);

        return new JsonResponse($report, $status);
    }

    public function submitTermGrades(Request $request, int $sectionId = 0): JsonResponse
    {
        $resolvedSectionId = $sectionId > 0
            ? $sectionId
            : (int) ($request->route('sectionId') ?? $request->input('course_section_id', 0));

        $user = $request->user();
        $facultyId = (int) ($user->id ?? $request->input('faculty_id', 0));

        $stmt = $this->pdo->prepare('SELECT * FROM course_sections WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $resolvedSectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($section)) {
            return new JsonResponse([
                'code' => 'SECTION_NOT_FOUND',
                'message' => 'Course section not found.',
            ], 404);
        }

        if ($facultyId > 0 && (int) $section['faculty_id'] !== $facultyId) {
            return new JsonResponse([
                'code' => 'FORBIDDEN_FACULTY_SECTION',
                'message' => 'Instructors may only mark grades submitted for their own sections.',
            ], 403);
        }

        $upd = $this->pdo->prepare('
            UPDATE course_sections
            SET term_grades_submitted = 1, updated_at = :updated_at
            WHERE id = :id
        ');
        $upd->execute([
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $resolvedSectionId,
        ]);

        $registrarLock = (bool) $section['registrar_lock'];

        return new JsonResponse([
            'course_section_id' => $resolvedSectionId,
            'term_grades_submitted' => true,
            'registrar_lock' => $registrarLock,
            'double_blind_unlocked' => $this->evaluationService->canFacultyViewSection(true, $registrarLock),
        ], 200);
    }
}
