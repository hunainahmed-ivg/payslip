<?php

namespace App\Support;

use App\Enums\Permission;

class PermissionCatalog
{
    /** @return array<string, string> permission value => human label */
    public static function labels(): array
    {
        return [
            Permission::DashboardView->value => 'Dashboard',
            Permission::DashboardAdminStats->value => 'Dashboard admin metrics',
            Permission::PortalPayslips->value => 'My payslips (employee portal)',
            Permission::EmployeesManage->value => 'Employees',
            Permission::PayrollImport->value => 'Payroll import',
            Permission::PayrollRunsManage->value => 'Payroll runs',
            Permission::ReportsView->value => 'Reports',
            Permission::StampedRequestsManage->value => 'Stamped copy requests',
            Permission::SalaryIncrementsManage->value => 'Salary increments',
            Permission::CompaniesManage->value => 'Companies (platform)',
            Permission::SettingsCompanyProfile->value => 'Company profile settings',
            Permission::SettingsVisualIdentity->value => 'Visual identity',
            Permission::SettingsSalaryComponents->value => 'Salary components',
            Permission::SettingsRegistrationDocuments->value => 'Registration documents',
            Permission::SettingsPayslipTemplates->value => 'Payslip templates',
            Permission::SettingsIntegrations->value => 'Integrations',
            Permission::SettingsApiGuidelines->value => 'API guidelines & tokens',
            Permission::SettingsSecurityAudit->value => 'Security & audit log',
            Permission::UsersManage->value => 'User accounts & permissions',
        ];
    }

    /** @return list<array{value: string, label: string, group: string}> */
    public static function grouped(): array
    {
        $groups = [
            'Workspace' => [
                Permission::DashboardView,
                Permission::DashboardAdminStats,
                Permission::PortalPayslips,
            ],
            'Payroll' => [
                Permission::EmployeesManage,
                Permission::PayrollImport,
                Permission::PayrollRunsManage,
                Permission::ReportsView,
                Permission::StampedRequestsManage,
                Permission::SalaryIncrementsManage,
            ],
            'Configuration' => [
                Permission::CompaniesManage,
                Permission::SettingsCompanyProfile,
                Permission::SettingsVisualIdentity,
                Permission::SettingsSalaryComponents,
                Permission::SettingsRegistrationDocuments,
                Permission::SettingsPayslipTemplates,
                Permission::SettingsIntegrations,
                Permission::SettingsApiGuidelines,
                Permission::SettingsSecurityAudit,
                Permission::UsersManage,
            ],
        ];

        $labels = self::labels();
        $items = [];

        foreach ($groups as $group => $permissions) {
            foreach ($permissions as $permission) {
                $items[] = [
                    'value' => $permission->value,
                    'label' => $labels[$permission->value] ?? $permission->value,
                    'group' => $group,
                ];
            }
        }

        return $items;
    }
}
