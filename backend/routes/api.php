<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditIssueController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoAuthController;
use App\Http\Controllers\RemediationController;
use App\Http\Controllers\RiskDashboardController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\WorkQueueController;
use Illuminate\Support\Facades\Route;

// Per-account throttling lives in AuthController; this route-level limit caps raw request volume.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');

// Portfolio demo sign-in; both routes answer 404 unless KRI_DEMO_MODE=true.
Route::get('demo/accounts', [DemoAuthController::class, 'accounts']);
Route::post('demo/login', [DemoAuthController::class, 'login'])->middleware('throttle:30,1');

// Everything else requires a signed-in user: KRI data is internal bank information.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::prefix('departments')->controller(RiskDashboardController::class)->group(function () {
        Route::get('risk-summary', 'summary');
        Route::get('{department}/historical-risk', 'history')->whereNumber('department');
    });

    Route::get('audit-issues', [AuditIssueController::class, 'index']);
    Route::get('audit-issues/{auditIssue}', [AuditIssueController::class, 'show'])->whereNumber('auditIssue');
    Route::get('servers', [ServerController::class, 'index']);

    Route::post('audit-issues/{auditIssue}/plans', [RemediationController::class, 'submitPlan'])->whereNumber('auditIssue');
    Route::post('audit-issues/{auditIssue}/evidence', [RemediationController::class, 'submitEvidence'])->whereNumber('auditIssue');
    Route::post('remediation-submissions/{submission}/review', [RemediationController::class, 'review'])->whereNumber('submission');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment');
    Route::get('work-queue', [WorkQueueController::class, 'index']);
});
