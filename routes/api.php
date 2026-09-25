<?php

use App\Http\Controllers\Api\PayrollSyncController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeBulkImportController;

// Phase 4: VirtuoHR Webhook Endpoint
Route::post('/v1/payroll/sync', PayrollSyncController::class);
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/employees/bulk-import', [EmployeeBulkImportController::class, 'commit'])
        ->name('api.v1.employees.bulk-import');

    Route::post('/employees/bulk-import/validate', [EmployeeBulkImportController::class, 'dryRun'])
        ->name('api.v1.employees.bulk-import.validate');

    Route::get('/employees/bulk-import/template', [EmployeeBulkImportController::class, 'template'])
        ->name('api.v1.employees.bulk-import.template');
});