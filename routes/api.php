<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminRosterController;
use App\Http\Controllers\Api\AdminSetupController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignWarRoomController;
use App\Http\Controllers\Api\DeanAnalyticsController;
use App\Http\Controllers\Api\FacultyPortalController;
use App\Http\Controllers\Api\MentorshipLifecycleController;
use App\Http\Controllers\Api\StudentEvaluationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/password', [AuthController::class, 'changePassword']);

// Public / Institutional Evaluation Clearance Verification & Signed Registrar Webhook
Route::match(['GET', 'POST'], '/clearance/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/student/clearance/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/permit/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/student/permit/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/evaluation/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/student/evaluation/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/completion/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/student/completion/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/receipt/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::post('/webhooks/registrar-sync', [AdminRosterController::class, 'registrarSyncWebhook']);

// Student Evaluation & Clearance Queue (SOP 1 & SOP 2)
Route::middleware(['role:student'])->group(function (): void {
    Route::get('/student/queue', [StudentEvaluationController::class, 'queue']);
    Route::post('/student/evaluations/{courseSectionId}/start', [StudentEvaluationController::class, 'startSession']);
    Route::post('/evaluation/submit', [StudentEvaluationController::class, 'submit']);
    Route::post('/student/evaluations/submit', [StudentEvaluationController::class, 'submit']);
    Route::post('/student/evaluations', [StudentEvaluationController::class, 'submit']);
    Route::get('/student/permit', [StudentEvaluationController::class, 'permit']);
    Route::get('/student/receipt', [StudentEvaluationController::class, 'permit']);
    Route::get('/student/completion', [StudentEvaluationController::class, 'permit']);
});

// Faculty Double-Blind Portal
Route::middleware(['role:faculty'])->group(function (): void {
    Route::get('/faculty/sections', [FacultyPortalController::class, 'sections']);
    Route::post('/faculty/sections/{sectionId}/submit-grades', [FacultyPortalController::class, 'submitTermGrades']);
    Route::get('/faculty/sections/{sectionId}/report', [FacultyPortalController::class, 'sectionReport'])
        ->middleware('double_blind');
    Route::post('/faculty/action-plan/status', [FacultyPortalController::class, 'updateActionPlanStatus']);
    Route::get('/faculty/action-plan', [MentorshipLifecycleController::class, 'getFacultyActionPlan']);
    Route::post('/faculty/action-plan/reflection', [MentorshipLifecycleController::class, 'submitFacultyReflection']);
    Route::post('/faculty/action-plan/milestone', [MentorshipLifecycleController::class, 'updateMilestoneProgress']);
});

// Dean & Department Chair Diagnostic Analytics & Watchlist (SOP 2 & SOP 3)
Route::middleware(['role:dean,chair,admin'])->group(function (): void {
    Route::get('/dean/overview', [DeanAnalyticsController::class, 'overview']);
    Route::get('/dean/watchlist', [DeanAnalyticsController::class, 'watchlist']);
    Route::get('/dean/quality-metrics', [DeanAnalyticsController::class, 'qualityMetrics']);
    Route::get('/dean/accreditation', [DeanAnalyticsController::class, 'accreditationSummary']);
    Route::get('/dean/compliance-audit', [DeanAnalyticsController::class, 'complianceAudit']);
    Route::get('/dean/evidence-dossier', [DeanAnalyticsController::class, 'evidenceDossier']);
    Route::get('/dean/performance-quartiles', [DeanAnalyticsController::class, 'performanceQuartiles']);
    Route::get('/dean/completion-heatmap', [DeanAnalyticsController::class, 'completionHeatmap']);
    Route::get('/dean/longitudinal-trends', [DeanAnalyticsController::class, 'longitudinalTrends']);
    Route::post('/dean/export-dossier-bundle', [DeanAnalyticsController::class, 'exportAccreditationDossier']);
    Route::get('/dean/qualitative-synthesis', [DeanAnalyticsController::class, 'qualitativeSynthesis']);
    Route::post('/dean/export-qualitative-csv', [DeanAnalyticsController::class, 'exportQualitativeCsv']);
    Route::post('/dean/action-plan/status', [DeanAnalyticsController::class, 'updateActionPlanStatus']);
    Route::post('/dean/sus/evaluate', [DeanAnalyticsController::class, 'evaluateLiveSus']);
    Route::post('/dean/export-csv', [DeanAnalyticsController::class, 'exportCsv']);
    Route::get('/dean/insights-search', [DeanAnalyticsController::class, 'insightsSearch']);
    Route::get('/dean/participation', [DeanAnalyticsController::class, 'participationTracker']);
    Route::get('/chair/participation', [DeanAnalyticsController::class, 'participationTracker']);
    Route::post('/dean/accreditation/compile', [DeanAnalyticsController::class, 'compileAccreditationDossier']);

    // Dedicated Chair Workspace Endpoints (SOP 2)
    Route::get('/chair/overview', [DeanAnalyticsController::class, 'overview']);
    Route::get('/chair/watchlist', [DeanAnalyticsController::class, 'watchlist']);
    Route::get('/chair/evidence-dossier', [DeanAnalyticsController::class, 'evidenceDossier']);
    Route::get('/chair/performance-quartiles', [DeanAnalyticsController::class, 'performanceQuartiles']);
    Route::get('/chair/completion-heatmap', [DeanAnalyticsController::class, 'completionHeatmap']);
    Route::get('/chair/longitudinal-trends', [DeanAnalyticsController::class, 'longitudinalTrends']);
    Route::get('/chair/insights-search', [DeanAnalyticsController::class, 'insightsSearch']);
    Route::post('/chair/action-plan/status', [DeanAnalyticsController::class, 'updateActionPlanStatus']);
    Route::get('/chair/mentorship-overview', [MentorshipLifecycleController::class, 'getDepartmentMentorshipDashboard']);
    Route::post('/chair/action-plan/assign-mentor', [MentorshipLifecycleController::class, 'assignMentorAndApprove']);
    Route::post('/chair/action-plan/resolve', [MentorshipLifecycleController::class, 'signOffAndResolve']);
    Route::post('/chair/accreditation/compile', [DeanAnalyticsController::class, 'compileAccreditationDossier']);
});

// System Administrator / Registrar Roster & Grade-Lock Management
Route::middleware(['role:admin'])->group(function (): void {
    Route::get('/admin/sections', [AdminRosterController::class, 'sections']);
    Route::post('/admin/rosters/import', [AdminRosterController::class, 'import']);
    Route::post('/admin/sections/{sectionId}/registrar-lock', [AdminRosterController::class, 'toggleRegistrarLock']);
    Route::post('/admin/sections/bulk-lock', [AdminRosterController::class, 'bulkToggleLocks']);
    Route::get('/admin/security-posture', [AdminRosterController::class, 'securityPosture']);
    Route::get('/admin/evaluation-window', [AdminRosterController::class, 'getEvaluationWindow']);
    Route::post('/admin/evaluation-window', [AdminRosterController::class, 'updateEvaluationWindow']);

    // Academic Setup & Evaluation Cycle Configuration Workspace
    Route::get('/admin/setup/overview', [AdminSetupController::class, 'overview']);
    Route::post('/admin/setup/terms', [AdminSetupController::class, 'createOrUpdateTerm']);
    Route::post('/admin/setup/terms/{id}/set-active', [AdminSetupController::class, 'setActiveTerm']);
    Route::match(['DELETE', 'POST'], '/admin/setup/terms/{id}/delete', [AdminSetupController::class, 'deleteTerm']);
    Route::post('/admin/setup/courses', [AdminSetupController::class, 'createCourse']);
    Route::match(['PUT', 'POST'], '/admin/setup/courses/{id}', [AdminSetupController::class, 'updateCourse']);
    Route::match(['DELETE', 'POST'], '/admin/setup/courses/{id}/delete', [AdminSetupController::class, 'deleteCourse']);
    Route::post('/admin/setup/sections', [AdminSetupController::class, 'createSection']);
    Route::post('/admin/setup/assign-faculty', [AdminSetupController::class, 'assignFaculty']);
    Route::post('/admin/setup/users', [AdminSetupController::class, 'createUser']);
    Route::match(['PUT', 'POST'], '/admin/setup/users/{id}', [AdminSetupController::class, 'updateUser']);
    Route::post('/admin/setup/users/{id}/toggle-status', [AdminSetupController::class, 'toggleUserStatus']);
    Route::get('/admin/setup/faculty/{id}/assignments', [AdminSetupController::class, 'facultyAssignments']);
    Route::match(['DELETE', 'POST'], '/admin/setup/sections/{id}/delete', [AdminSetupController::class, 'deleteSection']);
    Route::get('/admin/setup/questionnaire', [AdminSetupController::class, 'questionnaire']);
    Route::get('/admin/setup/students/{id}/enrollment', [AdminSetupController::class, 'studentEnrollment']);
    Route::get('/admin/setup/students/pending', [AdminSetupController::class, 'pendingStudentsTracker']);
    Route::get('/admin/setup/sections/{id}/roster', [AdminSetupController::class, 'sectionRoster']);

    // Campaign War Room & Pre-Flight Operations
    Route::get('/admin/war-room/preflight', [CampaignWarRoomController::class, 'preFlightAudit']);
    Route::get('/admin/war-room/leaderboard', [CampaignWarRoomController::class, 'turnoutLeaderboard']);
    Route::get('/admin/war-room/remind-batch', [CampaignWarRoomController::class, 'pendingReminderBatch']);
    Route::post('/admin/governance/aaccup-dossier/compile', [DeanAnalyticsController::class, 'compileAccreditationDossier']);
});
