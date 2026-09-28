<?php

use App\Http\Controllers\EmployeeBulkImportController;
use App\Http\Controllers\VirtuoHRWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Routes here are assigned the "api" middleware group (throttle + binding
| substitution) by bootstrap/app.php. They are NOT session-authenticated.
*/

// ── Step 1: employee bulk-import (token-protected; human / integrator callers) ──
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/employees/bulk-import', [EmployeeBulkImportController::class, 'commit'])
        ->name('api.v1.employees.bulk-import');

    Route::post('/employees/bulk-import/validate', [EmployeeBulkImportController::class, 'dryRun'])
        ->name('api.v1.employees.bulk-import.validate');

    Route::get('/employees/bulk-import/template', [EmployeeBulkImportController::class, 'template'])
        ->name('api.v1.employees.bulk-import.template');
});

// ── Step 8: inbound VirtuoHR webhook ──
// MUST stay OUTSIDE the auth:sanctum group above. It authenticates itself by
// HMAC signature inside VirtuoHRWebhookVerifier, not by a logged-in user.
// A top-level Route::post here gets only the default "api" group (no auth).
Route::post('/v1/payroll/sync', [VirtuoHRWebhookController::class, 'sync'])
    ->name('api.v1.payroll.sync');