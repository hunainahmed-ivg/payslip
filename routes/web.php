<?php

use App\Enums\Permission;
use App\Http\Controllers\ApiGuidelinesController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeBulkImportController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryComponentController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PayrollImportController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipAccessController;
use App\Http\Controllers\Portal\PayslipPortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryIncrementController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\RegistrationDocumentTypeController;
use App\Http\Controllers\Settings\SalaryComponentController;
use App\Http\Controllers\Settings\UserManagementController;
use App\Http\Controllers\StampedCopyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::middleware(['auth', 'verified', 'permission:'.Permission::DashboardView->value])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('auth')->group(function () {
    // Secure payslip / stamped PDF access
    Route::get('/payslips/{payslip}/view', [PayslipAccessController::class, 'viewPayslip'])->name('payslips.view');
    Route::get('/payslips/{payslip}/download', [PayslipAccessController::class, 'downloadPayslip'])->name('payslips.download');
    Route::get('/payslips/{payslip}/preview', [PayslipAccessController::class, 'previewHtml'])->name('payslips.preview');

    Route::get('/stamped-requests/{stampedRequest}/view', [PayslipAccessController::class, 'viewStampedCopy'])
        ->name('stamped-requests.view');
    Route::get('/stamped-requests/{stampedRequest}/download', [PayslipAccessController::class, 'downloadStampedCopy'])
        ->name('stamped-requests.download');

    // Employee portal
    Route::middleware('permission:'.Permission::PortalPayslips->value)->group(function () {
        Route::get('/portal/payslips', [PayslipPortalController::class, 'index'])->name('portal.payslips');
        Route::get('/portal/payslips/{payslip}/view', [PayslipAccessController::class, 'viewPayslip'])
            ->name('portal.payslips.view');
        Route::get('/portal/payslips/{payslip}/download', [PayslipAccessController::class, 'downloadPayslip'])
            ->name('portal.payslips.download');
        Route::get('/portal/payslips/{payslip}/preview', [PayslipAccessController::class, 'previewHtml'])
            ->name('portal.payslips.preview');
        Route::get('/portal/stamped-requests/{stampedRequest}/view', [PayslipAccessController::class, 'viewStampedCopy'])
            ->name('portal.stamped-requests.view');
        Route::get('/portal/stamped-requests/{stampedRequest}/download', [PayslipAccessController::class, 'downloadStampedCopy'])
            ->name('portal.stamped-requests.download');
        Route::post('/portal/stamped-requests', [StampedCopyRequestController::class, 'store'])
            ->name('portal.stamped-requests.store');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('permission:'.Permission::CompaniesManage->value)->group(function () {
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    });

    Route::middleware('permission:'.Permission::SettingsSecurityAudit->value)->group(function () {
        Route::get('/settings/security-audit', [AuditLogController::class, 'index'])->name('settings.security-audit');
    });

    Route::middleware('permission:'.Permission::ReportsView->value)->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

    Route::middleware('permission:'.Permission::SettingsIntegrations->value)->group(function () {
        Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations');
        Route::get('/settings/integrations/sync-status', [IntegrationController::class, 'syncStatus'])
            ->name('settings.integrations.sync-status');
        Route::post('/settings/integrations/webhook-secret', [IntegrationController::class, 'regenerateWebhookSecret'])
            ->name('settings.integrations.webhook-secret');
    });

    // Users & Access: Company Admin / Super Admin only (employees are hard-blocked).
    Route::middleware(['admin', 'permission:'.Permission::UsersManage->value])
        ->prefix('settings/users')
        ->name('settings.users.')
        ->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:'.Permission::SettingsApiGuidelines->value)->group(function () {
        Route::get('/settings/api-guidelines', [ApiGuidelinesController::class, 'index'])
            ->name('settings.api-guidelines');
        Route::post('/settings/api-guidelines/tokens', [ApiGuidelinesController::class, 'createToken'])
            ->name('settings.api-guidelines.tokens.store');
        Route::delete('/settings/api-guidelines/tokens/{tokenId}', [ApiGuidelinesController::class, 'revokeToken'])
            ->name('settings.api-guidelines.tokens.destroy');
    });

    Route::middleware('permission:'.Permission::StampedRequestsManage->value)->group(function () {
        Route::get('/stamped-requests', [StampedCopyRequestController::class, 'index'])->name('stamped-requests.index');
        Route::post('/stamped-requests/{stampedCopyRequest}/approve', [StampedCopyRequestController::class, 'approve'])->name('stamped-requests.approve');
        Route::post('/stamped-requests/{stampedCopyRequest}/reject', [StampedCopyRequestController::class, 'reject'])->name('stamped-requests.reject');
    });

    Route::middleware('permission:'.Permission::SettingsCompanyProfile->value)->group(function () {
        Route::get('/settings/company-profile', [CompanyProfileController::class, 'show'])->name('settings.company-profile');
    });

    Route::middleware('permission:'.Permission::SettingsPayslipTemplates->value)->group(function () {
        Route::get('/settings/payslip-templates', [CompanyProfileController::class, 'templates'])->name('settings.payslip-templates');
        Route::post('/settings/payslip-templates/activate', [CompanyProfileController::class, 'activateTemplate'])
            ->name('settings.payslip-templates.activate');
    });

    Route::middleware('permission:'.Permission::SettingsVisualIdentity->value)->group(function () {
        Route::get('/settings/visual-identity', [CompanyProfileController::class, 'edit'])->name('settings.visual-identity');
        Route::post('/settings/visual-identity', [CompanyProfileController::class, 'update'])->name('settings.visual-identity.update');
    });

    Route::middleware('permission:'.Permission::SettingsSalaryComponents->value)->group(function () {
        Route::get('/settings/salary-components', [SalaryComponentController::class, 'index'])->name('settings.salary-components.index');
        Route::post('/settings/salary-components', [SalaryComponentController::class, 'store'])->name('settings.salary-components.store');
        Route::put('/settings/salary-components/{component}', [SalaryComponentController::class, 'update'])->name('settings.salary-components.update');
        Route::delete('/settings/salary-components/{component}', [SalaryComponentController::class, 'destroy'])->name('settings.salary-components.destroy');
    });

    Route::middleware('permission:'.Permission::SettingsRegistrationDocuments->value)->group(function () {
        Route::get('/settings/registration-documents', [RegistrationDocumentTypeController::class, 'index'])->name('settings.registration-documents.index');
        Route::post('/settings/registration-documents', [RegistrationDocumentTypeController::class, 'store'])->name('settings.registration-documents.store');
        Route::put('/settings/registration-documents/{documentType}', [RegistrationDocumentTypeController::class, 'update'])->name('settings.registration-documents.update');
        Route::delete('/settings/registration-documents/{documentType}', [RegistrationDocumentTypeController::class, 'destroy'])->name('settings.registration-documents.destroy');
    });

    Route::middleware('permission:'.Permission::SalaryIncrementsManage->value)->prefix('salary-increments')->name('salary-increments.')->group(function () {
        Route::get('/', [SalaryIncrementController::class, 'index'])->name('index');
        Route::post('/', [SalaryIncrementController::class, 'store'])->name('store');
        Route::post('/preview', [SalaryIncrementController::class, 'preview'])->name('preview');
        Route::get('/export/csv', [SalaryIncrementController::class, 'exportCsv'])->name('export.csv');
        Route::get('/export/pdf', [SalaryIncrementController::class, 'exportPdf'])->name('export.pdf');
    });

    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/documents/{document}', [EmployeeController::class, 'downloadDocument'])
        ->name('employees.documents.download');

    // Company employee directory & management: Company Admin / Super Admin only.
    // Individual self-service profile remains on employees.show above.
    Route::middleware(['admin', 'permission:'.Permission::EmployeesManage->value])->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/bulk-import/template', [EmployeeBulkImportController::class, 'template'])
            ->name('employees.bulk-import.template');
        Route::post('/employees/bulk-import/dry-run', [EmployeeBulkImportController::class, 'dryRun'])
            ->name('employees.bulk-import.dry-run');
        Route::post('/employees/bulk-import/commit', [EmployeeBulkImportController::class, 'commit'])
            ->name('employees.bulk-import.commit');
        Route::get('/employees/{employee}/salary-structure', [EmployeeController::class, 'salaryStructure'])
            ->name('employees.salary-structure');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        Route::post('/employees/{employee}/salary-components', [EmployeeSalaryComponentController::class, 'store'])->name('employees.salary-components.store');
        Route::put('/employees/{employee}/salary-components/{override}', [EmployeeSalaryComponentController::class, 'update'])->name('employees.salary-components.update');
        Route::delete('/employees/{employee}/salary-components/{override}', [EmployeeSalaryComponentController::class, 'destroy'])->name('employees.salary-components.destroy');
    });

    Route::middleware('permission:'.Permission::PayrollImport->value)->prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/import', [PayrollImportController::class, 'create'])->name('import');
        Route::get('/import/template', [PayrollImportController::class, 'template'])->name('import.template');
        Route::post('/import/dry-run', [PayrollImportController::class, 'dryRun'])->name('import.dry-run');
        Route::post('/import/commit', [PayrollImportController::class, 'commit'])->name('import.commit');
        Route::get('/sync-status', [PayrollImportController::class, 'syncStatus'])->name('sync-status');
    });

    Route::middleware('permission:'.Permission::PayrollRunsManage->value)->prefix('payroll-runs')->name('payroll-runs.')->group(function () {
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
