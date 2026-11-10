<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminRosterController;
use App\Http\Controllers\Api\AdminSetupController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeanAnalyticsController;
use App\Http\Controllers\Api\FacultyPortalController;
use App\Http\Controllers\Api\StudentEvaluationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Public / Proctor Examination Permit Verification & Signed Registrar Webhook
Route::match(['GET', 'POST'], '/permit/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::match(['GET', 'POST'], '/student/permit/verify', [StudentEvaluationController::class, 'verifyPermit']);
Route::post('/webhooks/registrar-sync', [AdminRosterController::class, 'registrarSyncWebhook']);

// Student Evaluation & Clearance Queue (SOP 1 & SOP 2)
Route::middleware(['role:student'])->group(function (): void {
    Route::get('/student/queue', [StudentEvaluationController::class, 'queue']);
    Route::post('/student/evaluations/{courseSectionId}/start', [StudentEvaluationController::class, 'startSession']);
    Route::post('/evaluation/submit', [StudentEvaluationController::class, 'submit']);
    Route::post('/student/evaluations/submit', [StudentEvaluationController::class, 'submit']);
    Route::post('/student/evaluations', [StudentEvaluationController::class, 'submit']);
    Route::get('/student/permit', [StudentEvaluationController::class, 'permit']);
});

// Faculty Double-Blind Portal
Route::middleware(['role:faculty'])->group(function (): void {
    Route::get('/faculty/sections', [FacultyPortalController::class, 'sections']);
    Route::post('/faculty/sections/{sectionId}/submit-grades', [FacultyPortalController::class, 'submitTermGrades']);
    Route::get('/faculty/sections/{sectionId}/report', [FacultyPortalController::class, 'sectionReport'])
        ->middleware('double_blind');
    Route::post('/faculty/action-plan/status', [FacultyPortalController::class, 'updateActionPlanStatus']);
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

    // Dedicated Chair Workspace Endpoints (SOP 2)
    Route::get('/chair/overview', [DeanAnalyticsController::class, 'overview']);
    Route::get('/chair/watchlist', [DeanAnalyticsController::class, 'watchlist']);
    Route::get('/chair/evidence-dossier', [DeanAnalyticsController::class, 'evidenceDossier']);
    Route::get('/chair/performance-quartiles', [DeanAnalyticsController::class, 'performanceQuartiles']);
    Route::get('/chair/completion-heatmap', [DeanAnalyticsController::class, 'completionHeatmap']);
    Route::get('/chair/longitudinal-trends', [DeanAnalyticsController::class, 'longitudinalTrends']);
    Route::get('/chair/insights-search', [DeanAnalyticsController::class, 'insightsSearch']);
    Route::post('/chair/action-plan/status', [DeanAnalyticsController::class, 'updateActionPlanStatus']);
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
    Route::post('/admin/setup/courses', [AdminSetupController::class, 'createCourse']);
    Route::post('/admin/setup/sections', [AdminSetupController::class, 'createSection']);
    Route::post('/admin/setup/assign-faculty', [AdminSetupController::class, 'assignFaculty']);
    Route::post('/admin/setup/users', [AdminSetupController::class, 'createUser']);
    Route::match(['DELETE', 'POST'], '/admin/setup/sections/{id}/delete', [AdminSetupController::class, 'deleteSection']);
    Route::get('/admin/setup/questionnaire', [AdminSetupController::class, 'questionnaire']);
    Route::get('/admin/setup/students/{id}/enrollment', [AdminSetupController::class, 'studentEnrollment']);
    Route::get('/admin/setup/sections/{id}/roster', [AdminSetupController::class, 'sectionRoster']);
});
