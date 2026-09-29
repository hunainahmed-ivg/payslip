<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\EmployeeDocumentController as ApiEmployeeDocumentController;
use App\Http\Controllers\Api\V1\RegistrationDocumentTypeController as ApiRegistrationDocumentTypeController;
use App\Http\Controllers\Api\V1\SalaryIncrementController as ApiSalaryIncrementController;
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

Route::middleware(['auth:sanctum', 'abilities:employees:import', 'api.company'])->prefix('v1')->group(function () {
    Route::post('/employees/bulk-import', [EmployeeBulkImportController::class, 'commit'])
        ->name('api.v1.employees.bulk-import');

    Route::post('/employees/bulk-import/validate', [EmployeeBulkImportController::class, 'dryRun'])
        ->name('api.v1.employees.bulk-import.validate');

    Route::get('/employees/bulk-import/template', [EmployeeBulkImportController::class, 'template'])
        ->name('api.v1.employees.bulk-import.template');
});

Route::middleware(['auth:sanctum', 'api.company'])->prefix('v1')->group(function () {
    Route::middleware('permission:'.Permission::SettingsRegistrationDocuments->value)->group(function () {
        Route::get('/registration-document-types', [ApiRegistrationDocumentTypeController::class, 'index'])
            ->name('api.v1.registration-document-types.index');
        Route::post('/registration-document-types', [ApiRegistrationDocumentTypeController::class, 'store'])
            ->name('api.v1.registration-document-types.store');
        Route::put('/registration-document-types/{documentType}', [ApiRegistrationDocumentTypeController::class, 'update'])
            ->name('api.v1.registration-document-types.update');
        Route::delete('/registration-document-types/{documentType}', [ApiRegistrationDocumentTypeController::class, 'destroy'])
            ->name('api.v1.registration-document-types.destroy');
    });

    Route::middleware('permission:'.Permission::EmployeesManage->value)->group(function () {
        Route::get('/employees/{employee}/documents', [ApiEmployeeDocumentController::class, 'index'])
            ->name('api.v1.employees.documents.index');
        Route::post('/employees/{employee}/documents', [ApiEmployeeDocumentController::class, 'store'])
            ->name('api.v1.employees.documents.store');
    });

    Route::middleware('permission:'.Permission::SalaryIncrementsManage->value)->group(function () {
        Route::get('/employees/search', [ApiSalaryIncrementController::class, 'searchEmployees'])
            ->name('api.v1.employees.search');
        Route::post('/salary-increments/preview', [ApiSalaryIncrementController::class, 'preview'])
            ->name('api.v1.salary-increments.preview');
        Route::post('/salary-increments', [ApiSalaryIncrementController::class, 'store'])
            ->name('api.v1.salary-increments.store');
        Route::get('/employees/{employee}/salary-ledger', [ApiSalaryIncrementController::class, 'ledger'])
            ->name('api.v1.employees.salary-ledger');
        Route::get('/salary-increments/report', [ApiSalaryIncrementController::class, 'yearlyReport'])
            ->name('api.v1.salary-increments.report');
    });
});

Route::post('/v1/payroll/sync', [VirtuoHRWebhookController::class, 'sync'])
    ->name('api.v1.payroll.sync');
