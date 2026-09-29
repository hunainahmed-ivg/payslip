<?php

namespace App\Enums;

enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case DashboardAdminStats = 'dashboard.admin-stats';
    case PortalPayslips = 'portal.payslips';

    case EmployeesManage = 'employees.manage';
    case PayrollImport = 'payroll.import';
    case PayrollRunsManage = 'payroll-runs.manage';
    case ReportsView = 'reports.view';
    case StampedRequestsManage = 'stamped-requests.manage';
    case SalaryIncrementsManage = 'salary-increments.manage';

    case CompaniesManage = 'companies.manage';
    case SettingsCompanyProfile = 'settings.company-profile';
    case SettingsVisualIdentity = 'settings.visual-identity';
    case SettingsSalaryComponents = 'settings.salary-components';
    case SettingsRegistrationDocuments = 'settings.registration-documents';
    case SettingsPayslipTemplates = 'settings.payslip-templates';
    case SettingsIntegrations = 'settings.integrations';
    case SettingsApiGuidelines = 'settings.api-guidelines';
    case SettingsSecurityAudit = 'settings.security-audit';
    case UsersManage = 'users.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
