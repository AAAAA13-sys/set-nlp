<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\AcademicSetupService;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use RuntimeException;

class AdminSetupController extends Controller
{
    private AcademicSetupService $setupService;

    public function __construct(?AcademicSetupService $setupService = null)
    {
        $this->setupService = $setupService ?? new AcademicSetupService();
    }

    private function verifyAdminRole(Request $request): ?JsonResponse
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
                    'message' => 'Only System Administrators / Registrars may configure academic cycles and faculty assignments.',
                ], 403);
            }
        }
        return null;
    }

    public function overview(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
        $data = $this->setupService->getSetupOverview($termId);

        return new JsonResponse($data, 200);
    }

    public function createOrUpdateTerm(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        try {
            $result = $this->setupService->createOrUpdateTerm($request->all());
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'ACADEMIC_TERM_MUTATED',
                'INFO',
                ['term_code' => $result['code'] ?? null],
                'admin',
                '/api/admin/setup/terms'
            );
            return new JsonResponse($result, 200);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_TERM_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function setActiveTerm(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $termId = $id > 0 ? $id : (int) $request->input('academic_term_id', 0);
        if ($termId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_TERM_ID',
                'message' => 'Valid academic_term_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->setActiveTerm($termId);
            return new JsonResponse($result, 200);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'TERM_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function deleteTerm(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $termId = $id > 0 ? $id : (int) $request->input('academic_term_id', 0);
        if ($termId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_TERM_ID',
                'message' => 'Valid academic_term_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->deleteTerm($termId);
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'ACADEMIC_TERM_DELETED',
                'INFO',
                ['term_id' => $termId, 'term_code' => $result['term_code'] ?? null],
                'admin',
                '/api/admin/setup/terms/' . $termId . '/delete'
            );
            return new JsonResponse($result, 200);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'active academic term')
                ? 'CANNOT_DELETE_ACTIVE_TERM'
                : 'CANNOT_DELETE_TERM_WITH_DATA';
            return new JsonResponse([
                'success' => false,
                'code' => $code,
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'TERM_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function createCourse(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        try {
            $result = $this->setupService->createCourse($request->all());
            return new JsonResponse($result, 201);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COURSE_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateCourse(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $courseId = $id > 0 ? $id : (int) $request->input('course_id', 0);
        if ($courseId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COURSE_ID',
                'message' => 'Valid course_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->updateCourse($courseId, $request->all());
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'COURSE_UPDATED',
                'INFO',
                ['course_id' => $courseId, 'code' => $result['code'] ?? null],
                'admin',
                '/api/admin/setup/courses/' . $courseId
            );
            return new JsonResponse($result, 200);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COURSE_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function deleteCourse(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $courseId = $id > 0 ? $id : (int) $request->input('course_id', 0);
        if ($courseId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COURSE_ID',
                'message' => 'Valid course_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->deleteCourse($courseId);
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'COURSE_DELETED',
                'INFO',
                ['course_id' => $courseId, 'code' => $result['code'] ?? null],
                'admin',
                '/api/admin/setup/courses/' . $courseId . '/delete'
            );
            return new JsonResponse($result, 200);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CANNOT_DELETE_ACTIVE_COURSE',
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'COURSE_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function createSection(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        try {
            $result = $this->setupService->createSection($request->all());
            return new JsonResponse($result, 201);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_SECTION_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function assignFaculty(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $sectionId = (int) $request->input('course_section_id', 0);
        $rawFacultyId = $request->input('faculty_id');
        $facultyId = $rawFacultyId !== null && $rawFacultyId !== '' ? (int) $rawFacultyId : null;

        if ($sectionId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_SECTION_ID',
                'message' => 'Valid course_section_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->assignFaculty($sectionId, $facultyId);
            return new JsonResponse($result, 200);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ASSIGNMENT_REJECTED',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function deleteSection(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $sectionId = $id > 0 ? $id : (int) $request->input('course_section_id', 0);
        if ($sectionId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_SECTION_ID',
                'message' => 'Valid course_section_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->deleteSection($sectionId);
            return new JsonResponse($result, 200);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CANNOT_DELETE_EVALUATED_SECTION',
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'SECTION_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function questionnaire(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        return new JsonResponse($this->setupService->getQuestionnairePreview(), 200);
    }

    public function createUser(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        try {
            $result = $this->setupService->createUser($request->all());
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'USER_ACCOUNT_CREATED',
                'INFO',
                [
                    'institutional_id' => $result['institutional_id'] ?? null,
                    'role' => $result['role'] ?? null,
                ],
                'admin',
                '/api/admin/setup/users'
            );
            return new JsonResponse($result, 201);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_USER_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function studentEnrollment(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $termId = $request->query('academic_term_id') ? (int) $request->query('academic_term_id') : null;
        $details = $this->setupService->getStudentEnrollmentDetails($id, $termId);
        if (!$details) {
            return new JsonResponse([
                'success' => false,
                'code' => 'STUDENT_NOT_FOUND',
                'message' => 'Student record not found.',
            ], 404);
        }

        return new JsonResponse($details, 200);
    }

    public function sectionRoster(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $roster = $this->setupService->getSectionRoster($id);
        if (!$roster) {
            return new JsonResponse([
                'success' => false,
                'code' => 'SECTION_NOT_FOUND',
                'message' => 'Course section record not found.',
            ], 404);
        }

        return new JsonResponse($roster, 200);
    }

    public function facultyAssignments(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $facultyId = $id > 0 ? $id : (int) $request->input('faculty_id', 0);
        $termId = $request->query('academic_term_id') ? (int) $request->query('academic_term_id') : null;

        $assignments = $this->setupService->getFacultyAssignments($facultyId, $termId);
        if (!$assignments) {
            return new JsonResponse([
                'success' => false,
                'code' => 'FACULTY_NOT_FOUND',
                'message' => 'Faculty member record not found.',
            ], 404);
        }

        return new JsonResponse($assignments, 200);
    }

    public function updateUser(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $userId = $id > 0 ? $id : (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_USER_ID',
                'message' => 'Valid user_id is required.',
            ], 422);
        }

        try {
            $result = $this->setupService->updateUser($userId, $request->all());
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'USER_UPDATED',
                'INFO',
                [
                    'user_id' => $userId,
                    'institutional_id' => $result['institutional_id'] ?? null,
                    'role' => $result['role'] ?? null,
                    'password_updated' => $result['password_updated'] ?? false,
                ],
                'admin',
                '/api/admin/setup/users/' . $userId
            );
            return new JsonResponse($result, 200);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_USER_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function toggleUserStatus(Request $request, int $id = 0): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $userId = $id > 0 ? $id : (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_USER_ID',
                'message' => 'Valid user_id is required.',
            ], 422);
        }

        $user = $request->user();
        $operatorId = is_object($user) && isset($user->id) ? (int) $user->id : null;

        try {
            $result = $this->setupService->toggleUserStatus($userId, $operatorId);
            (new SecurityAuditService())->logEvent(
                $this->setupService->getPdo(),
                'USER_STATUS_TOGGLED',
                'INFO',
                [
                    'user_id' => $userId,
                    'is_active' => $result['is_active'] ?? false,
                ],
                'admin',
                '/api/admin/setup/users/' . $userId . '/toggle-status'
            );
            return new JsonResponse($result, 200);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CANNOT_DEACTIVATE_SELF',
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'USER_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function pendingStudentsTracker(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminRole($request)) {
            return $authError;
        }

        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
        $deptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;

        try {
            $data = $this->setupService->getPendingStudentsTracker($termId, $deptId);
            return new JsonResponse($data, 200);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ACADEMIC_TERM_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
