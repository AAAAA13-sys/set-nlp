<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminRosterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeanAnalyticsController;
use App\Http\Controllers\Api\FacultyPortalController;
use App\Http\Controllers\Api\StudentEvaluationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Student Evaluation & Clearance Queue (SOP 1 & SOP 2)
Route::middleware(['role:student'])->group(function (): void {
    Route::get('/student/queue', [StudentEvaluationController::class, 'queue']);
    Route::post('/student/evaluations/{courseSectionId}/start', [StudentEvaluationController::class, 'startSession']);
    Route::post('/evaluation/submit', [StudentEvaluationController::class, 'submit']);
    Route::post('/student/evaluations/submit', [StudentEvaluationController::class, 'submit']);
    Route::get('/student/permit', [StudentEvaluationController::class, 'permit']);
    Route::match(['GET', 'POST'], '/student/permit/verify', [StudentEvaluationController::class, 'verifyPermit']);
});

// Faculty Double-Blind Portal
Route::middleware(['role:faculty'])->group(function (): void {
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
    Route::post('/dean/action-plan/status', [DeanAnalyticsController::class, 'updateActionPlanStatus']);
    Route::post('/dean/sus/evaluate', [DeanAnalyticsController::class, 'evaluateLiveSus']);
    Route::post('/dean/export-csv', [DeanAnalyticsController::class, 'exportCsv']);
});

// System Administrator / Registrar Roster & Grade-Lock Management
Route::middleware(['role:admin'])->group(function (): void {
    Route::post('/admin/rosters/import', [AdminRosterController::class, 'import']);
    Route::post('/admin/sections/{sectionId}/registrar-lock', [AdminRosterController::class, 'toggleRegistrarLock']);
    Route::get('/admin/evaluation-window', [AdminRosterController::class, 'getEvaluationWindow']);
    Route::post('/admin/evaluation-window', [AdminRosterController::class, 'updateEvaluationWindow']);
});
