export const Permission = {
    DashboardView: 'dashboard.view',
    DashboardAdminStats: 'dashboard.admin-stats',
    PortalPayslips: 'portal.payslips',
    EmployeesManage: 'employees.manage',
    PayrollImport: 'payroll.import',
    PayrollRunsManage: 'payroll-runs.manage',
    ReportsView: 'reports.view',
    StampedRequestsManage: 'stamped-requests.manage',
    CompaniesManage: 'companies.manage',
    SettingsCompanyProfile: 'settings.company-profile',
    SettingsVisualIdentity: 'settings.visual-identity',
    SettingsSalaryComponents: 'settings.salary-components',
    SettingsPayslipTemplates: 'settings.payslip-templates',
    SettingsIntegrations: 'settings.integrations',
    SettingsApiGuidelines: 'settings.api-guidelines',
    SettingsSecurityAudit: 'settings.security-audit',
    UsersManage: 'users.manage',
} as const;

export type PermissionKey = (typeof Permission)[keyof typeof Permission];
