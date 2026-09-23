<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StampedCopyRequestController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\SalaryComponentController;
use App\Http\Controllers\EmployeeSalaryComponentController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayrollImportController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Portal\PayslipPortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

// 👇 THIS IS THE MISSING PIECE 👇
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    // Inside Route::middleware('auth')->group(...):
    Route::get('/settings/security-audit', [AuditLogController::class, 'index'])->name('settings.security-audit');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations');

    // HR stamped-copy workflow
    Route::get('/stamped-requests', [StampedCopyRequestController::class, 'index'])->name('stamped-requests.index');
    Route::post('/stamped-requests/{stampedCopyRequest}/approve', [StampedCopyRequestController::class, 'approve'])->name('stamped-requests.approve');
    Route::post('/stamped-requests/{stampedCopyRequest}/reject', [StampedCopyRequestController::class, 'reject'])->name('stamped-requests.reject');

    Route::get('/settings/company-profile', [CompanyProfileController::class, 'show'])->name('settings.company-profile');
    Route::get('/settings/payslip-templates', [CompanyProfileController::class, 'templates'])->name('settings.payslip-templates');

    // Employee portal request
    Route::post('/portal/stamped-requests', [StampedCopyRequestController::class, 'store'])->name('portal.stamped-requests.store');
    // Default Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/visual-identity', [CompanyProfileController::class, 'edit'])
    ->name('settings.visual-identity');
    Route::post('/settings/visual-identity', [CompanyProfileController::class, 'update'])
    ->name('settings.visual-identity.update');

    Route::get('/settings/salary-components', [SalaryComponentController::class, 'index'])->name('settings.salary-components.index');
    Route::post('/settings/salary-components', [SalaryComponentController::class, 'store'])->name('settings.salary-components.store');
    Route::put('/settings/salary-components/{component}', [SalaryComponentController::class, 'update'])->name('settings.salary-components.update');
    Route::delete('/settings/salary-components/{component}', [SalaryComponentController::class, 'destroy'])->name('settings.salary-components.destroy');

    Route::get('/portal/payslips', [PayslipPortalController::class, 'index'])->name('portal.payslips');

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');

        Route::post('/{employee}/salary-components', [EmployeeSalaryComponentController::class, 'store'])->name('salary-components.store');
        Route::put('/{employee}/salary-components/{override}', [EmployeeSalaryComponentController::class, 'update'])->name('salary-components.update');
        Route::delete('/{employee}/salary-components/{override}', [EmployeeSalaryComponentController::class, 'destroy'])->name('salary-components.destroy');
    });

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/import', [PayrollImportController::class, 'create'])->name('import');
        Route::get('/import/template', [PayrollImportController::class, 'template'])->name('import.template');
        Route::post('/import/dry-run', [PayrollImportController::class, 'dryRun'])->name('import.dry-run');
        Route::post('/import/commit', [PayrollImportController::class, 'commit'])->name('import.commit');
    });

    Route::prefix('payroll-runs')->name('payroll-runs.')->group(function () {
        Route::get('/', [PayrollRunController::class, 'index'])->name('index');
        Route::post('/', [PayrollRunController::class, 'store'])->name('store');
        Route::get('/{payrollRun}', [PayrollRunController::class, 'show'])->name('show');
        Route::put('/{payrollRun}/items/{item}', [PayrollRunController::class, 'updateItem'])->name('items.update');
        Route::post('/{payrollRun}/approve', [PayrollRunController::class, 'approve'])->name('approve');
        Route::delete('/{payrollRun}', [PayrollRunController::class, 'destroy'])->name('destroy');
        Route::post('/{payrollRun}/publish', [PayrollRunController::class, 'publish'])->name('publish');
    });
    

});

require __DIR__.'/auth.php';