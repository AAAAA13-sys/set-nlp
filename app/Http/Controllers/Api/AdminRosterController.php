<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\DecoupledEvaluationService;
use App\Services\RosterImportService;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDO;

class AdminRosterController extends Controller
{
    public const MAX_IMPORT_BYTES = 2_097_152;

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

    public function sections(Request $request): JsonResponse
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
                    'message' => 'Only System Administrators / Registrars may view institutional section lock rosters.',
                ], 403);
            }
        }

        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
        $data = $this->evaluationService->getAdminSections($termId);

        return new JsonResponse($data, 200);
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
        $rawContent = (string) $request->input('content', '');

        if (strlen($rawContent) > self::MAX_IMPORT_BYTES) {
            return new JsonResponse([
                'imported' => false,
                'code' => 'PAYLOAD_TOO_LARGE',
                'message' => 'Roster import file exceeds the 2 MB institutional upload ceiling.',
            ], 413);
        }

        if ($format === 'csv') {
            $result = $this->rosterService->importCsv($rawContent, $termId);
        } elseif ($format === 'json') {
            $result = $this->rosterService->importJson($rawContent, $termId);
        } elseif ($format === 'rows') {
            $rows = is_array($request->input('rows')) ? $request->input('rows') : [];
            $result = $this->rosterService->importRows($rows, $termId);
        } else {
            return new JsonResponse([
                'imported' => false,
                'code' => 'UNSUPPORTED_IMPORT_FORMAT',
                'message' => 'Only validated CSV, JSON, or structured row imports are permitted.',
            ], 422);
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

        $stmt = $this->pdo->prepare('SELECT * FROM course_sections WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $resolvedSectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($section)) {
            return new JsonResponse([
                'code' => 'SECTION_NOT_FOUND',
                'message' => 'Course section not found.',
            ], 404);
        }

        $payload = $request->all();
        $hasGrades = array_key_exists('term_grades_submitted', $payload);
        $hasLock = array_key_exists('registrar_lock', $payload);

        $gradesSubmitted = $hasGrades
            ? (bool) filter_var($payload['term_grades_submitted'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $section['term_grades_submitted'];

        $lockState = $hasLock
            ? (bool) filter_var($payload['registrar_lock'], FILTER_VALIDATE_BOOLEAN)
            : (!$hasGrades ? true : (bool) $section['registrar_lock']);

        $upd = $this->pdo->prepare('
            UPDATE course_sections
            SET term_grades_submitted = :grades_val,
                registrar_lock = :lock_val,
                updated_at = :updated_at
            WHERE id = :id
        ');
        $upd->execute([
            ':grades_val' => $gradesSubmitted ? 1 : 0,
            ':lock_val' => $lockState ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $resolvedSectionId,
        ]);

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
        $targetStatus = strtoupper(trim((string) ($payload['status'] ?? 'OPEN')));

        if (
            in_array($targetStatus, ['SUSPENDED', 'CLOSED'], true)
            && array_key_exists('confirm_destructive', $payload)
            && !filter_var($payload['confirm_destructive'], FILTER_VALIDATE_BOOLEAN)
        ) {
            return new JsonResponse([
                'updated' => false,
                'code' => 'DESTRUCTIVE_CONFIRMATION_REQUIRED',
                'message' => 'Explicit destructive action confirmation is required before suspending or closing the evaluation window.',
            ], 422);
        }

        try {
            $updatedWindow = $this->evaluationService->updateEvaluationWindow($payload, $userId);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'authorized' => true,
                'code' => 'INVALID_EVALUATION_WINDOW_DEADLINE',
                'message' => $e->getMessage(),
            ], 422);
        }
        (new SecurityAuditService())->logEvent(
            $this->pdo,
            'EVALUATION_WINDOW_UPDATED',
            in_array($targetStatus, ['SUSPENDED', 'CLOSED'], true) ? 'WARN' : 'INFO',
            ['status' => $updatedWindow['status'] ?? $targetStatus],
            'admin',
            '/api/admin/evaluation-window'
        );

        return new JsonResponse($updatedWindow, 200);
    }

    public function bulkToggleLocks(Request $request): JsonResponse
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
                    'message' => 'Only the University Registrar / System Administrator may execute bulk lock updates.',
                ], 403);
            }
        }

        $rawIds = $request->input('section_ids', []);
        if (!is_array($rawIds) || count($rawIds) === 0) {
            return new JsonResponse([
                'updated_count' => 0,
                'code' => 'EMPTY_BULK_SELECTION',
                'message' => 'Select at least one course section for bulk lock update.',
            ], 422);
        }

        $payload = $request->all();
        $gradesSubmitted = array_key_exists('term_grades_submitted', $payload)
            ? (bool) filter_var($payload['term_grades_submitted'], FILTER_VALIDATE_BOOLEAN)
            : null;
        $registrarLock = array_key_exists('registrar_lock', $payload)
            ? (bool) filter_var($payload['registrar_lock'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $result = $this->evaluationService->bulkUpdateSectionLocks($rawIds, $gradesSubmitted, $registrarLock);
        (new SecurityAuditService())->logEvent(
            $this->pdo,
            'ADMIN_BULK_SECTION_LOCK',
            'INFO',
            ['updated_count' => $result['updated_count']],
            'admin',
            '/api/admin/sections/bulk-lock'
        );

        return new JsonResponse($result, 200);
    }

    public function securityPosture(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');
            if (!in_array($role, [UserRole::ADMIN->value, UserRole::DEAN->value], true)) {
                return new JsonResponse([
                    'authorized' => false,
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'Only System Administrators or College Deans may inspect the security posture matrix.',
                ], 403);
            }
        }

        $posture = (new SecurityAuditService())->getSecurityMaxxingPosture($this->pdo);
        return new JsonResponse($posture, 200);
    }

    public function registrarSyncWebhook(Request $request, ?string $rawBodyOverride = null): JsonResponse
    {
        $auditService = new SecurityAuditService();
        $rawBody = $rawBodyOverride ?? (string) json_encode($request->all(), JSON_UNESCAPED_SLASHES);
        $sig = (string) ($request->header('X-EvaluaTech-Signature') ?? $request->header('x-evaluatech-signature') ?? '');
        $ts = (int) ($request->header('X-EvaluaTech-Timestamp') ?? $request->header('x-evaluatech-timestamp') ?? 0);
        $nonce = (string) ($request->header('X-EvaluaTech-Nonce') ?? $request->header('x-evaluatech-nonce') ?? '');

        $verification = $auditService->verifySignedWebhook($this->pdo, $rawBody, $sig, $ts, $nonce);
        if (!$verification['valid']) {
            $auditService->logEvent(
                $this->pdo,
                $verification['code'],
                'WARN',
                ['status' => $verification['status']],
                null,
                '/api/webhooks/registrar-sync'
            );
            return new JsonResponse([
                'accepted' => false,
                'code' => $verification['code'],
                'message' => $verification['message'],
            ], $verification['status']);
        }

        $payload = $request->all();
        $sectionIds = is_array($request->input('section_ids')) ? $request->input('section_ids') : [];
        $syncResult = count($sectionIds) > 0
            ? $this->evaluationService->bulkUpdateSectionLocks(
                $sectionIds,
                array_key_exists('term_grades_submitted', $payload) ? (bool) filter_var($payload['term_grades_submitted'], FILTER_VALIDATE_BOOLEAN) : null,
                array_key_exists('registrar_lock', $payload) ? (bool) filter_var($payload['registrar_lock'], FILTER_VALIDATE_BOOLEAN) : true
            )
            : ['updated_count' => 0, 'updated_sections' => []];

        $auditService->logEvent(
            $this->pdo,
            'WEBHOOK_REGISTRAR_SYNC_VERIFIED',
            'INFO',
            ['updated_count' => $syncResult['updated_count']],
            'webhook',
            '/api/webhooks/registrar-sync'
        );

        return new JsonResponse([
            'accepted' => true,
            'code' => 'WEBHOOK_VERIFIED',
            'updated_count' => $syncResult['updated_count'],
        ], 200);
    }
}
