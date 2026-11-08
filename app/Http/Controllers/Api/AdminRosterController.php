<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\DecoupledEvaluationService;
use App\Services\RosterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDO;

class AdminRosterController extends Controller
{
    private RosterImportService $rosterService;
    private DecoupledEvaluationService $evaluationService;
    private PDO $pdo;

    public function __construct(
        ?RosterImportService $rosterService = null,
        ?DecoupledEvaluationService $evaluationService = null
    ) {
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService();
        $this->pdo = $this->evaluationService->getPdo();
        $this->rosterService = $rosterService ?? new RosterImportService($this->pdo);
    }

    public function import(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');
            if ($role !== UserRole::ADMIN->value) {
                return new JsonResponse([
                    'authorized' => false,
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'Only System Administrators / Registrars may import academic rosters.',
                ], 403);
            }
        }

        $format = strtolower((string) $request->input('format', 'rows'));
        $termId = (int) $request->input('academic_term_id', 1);

        if ($format === 'csv') {
            $result = $this->rosterService->importCsv((string) $request->input('content', ''), $termId);
        } elseif ($format === 'json') {
            $result = $this->rosterService->importJson((string) $request->input('content', ''), $termId);
        } else {
            $rows = is_array($request->input('rows')) ? $request->input('rows') : [];
            $result = $this->rosterService->importRows($rows, $termId);
        }

        return new JsonResponse($result, (int) ($result['http_status'] ?? 200));
    }

    public function toggleRegistrarLock(Request $request, int $sectionId = 0): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');
            if ($role !== UserRole::ADMIN->value) {
                return new JsonResponse([
                    'authorized' => false,
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'Only the Registrar / System Administrator may toggle registrar_lock.',
                ], 403);
            }
        }

        $resolvedSectionId = $sectionId > 0
            ? $sectionId
            : (int) ($request->route('sectionId') ?? $request->input('course_section_id', 0));

        $lockState = $request->boolean('registrar_lock', true);

        $stmt = $this->pdo->prepare('SELECT * FROM course_sections WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $resolvedSectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($section)) {
            return new JsonResponse([
                'code' => 'SECTION_NOT_FOUND',
                'message' => 'Course section not found.',
            ], 404);
        }

        $upd = $this->pdo->prepare('
            UPDATE course_sections
            SET registrar_lock = :lock_val, updated_at = :updated_at
            WHERE id = :id
        ');
        $upd->execute([
            ':lock_val' => $lockState ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $resolvedSectionId,
        ]);

        $gradesSubmitted = (bool) $section['term_grades_submitted'];

        return new JsonResponse([
            'course_section_id' => $resolvedSectionId,
            'term_grades_submitted' => $gradesSubmitted,
            'registrar_lock' => $lockState,
            'double_blind_unlocked' => $this->evaluationService->canFacultyViewSection($gradesSubmitted, $lockState),
        ], 200);
    }

    public function getEvaluationWindow(Request $request): JsonResponse
    {
        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
        $window = $this->evaluationService->getEvaluationWindow($termId);

        return new JsonResponse($window, 200);
    }

    public function updateEvaluationWindow(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');
            if ($role !== UserRole::ADMIN->value) {
                return new JsonResponse([
                    'authorized' => false,
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'Only the University Registrar / System Administrator may modify the evaluation window parameters.',
                ], 403);
            }
        }

        $userId = $user !== null ? (int) ($user->id ?? 1) : null;
        $payload = $request->all();
        $updatedWindow = $this->evaluationService->updateEvaluationWindow($payload, $userId);

        return new JsonResponse($updatedWindow, 200);
    }
}
