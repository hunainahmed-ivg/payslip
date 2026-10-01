<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Permission } from '@/constants/permissions';
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { can, roleLabel } = usePermissions();
const companyName = computed(() => (page.props.companyName as string) || 'Payslip Engine');

const isAdmin = computed(() => Boolean(user.value?.is_admin));

const workspaceLinks = computed(() =>
    [
        {
            name: 'Dashboard',
            href: route('dashboard'),
            routeName: 'dashboard',
            permission: Permission.DashboardView,
        },
        {
            name: 'My Payslips',
            href: route('portal.payslips'),
            routeName: 'portal.payslips',
            permission: Permission.PortalPayslips,
        },
        {
            name: 'My Profile',
            href: user.value?.employee_id
                ? route('employees.show', user.value.employee_id)
                : null,
            routeName: 'employees.show',
            // Visible to portal users with a linked employee record; admins use Employees instead.
            showWhen: Boolean(user.value?.employee_id) && !isAdmin.value,
        },
        {
            name: 'Employees',
            href: route('employees.index'),
            routeName: 'employees.*',
            permission: Permission.EmployeesManage,
            requireAdmin: true,
        },
        {
            name: 'Payroll Import',
            href: route('payroll.import'),
            routeName: 'payroll.*',
            permission: Permission.PayrollImport,
        },
        {
            name: 'Payroll Runs',
            href: route('payroll-runs.index'),
            routeName: 'payroll-runs.*',
            permission: Permission.PayrollRunsManage,
        },
        {
            name: 'Salary Increments',
            href: route('salary-increments.index'),
            routeName: 'salary-increments.*',
            permission: Permission.SalaryIncrementsManage,
        },
        {
            name: 'Reports',
            href: route('reports.index'),
            routeName: 'reports.*',
            permission: Permission.ReportsView,
        },
        {
            name: 'Stamped Requests',
            href: route('stamped-requests.index'),
            routeName: 'stamped-requests.*',
            permission: Permission.StampedRequestsManage,
            showPending: true,
        },
    ].filter((link) => {
        if ('showWhen' in link) {
            return Boolean(link.showWhen) && Boolean(link.href);
        }

        if (link.requireAdmin && !isAdmin.value) {
            return false;
        }

        return can(link.permission!);
    }),
);

const configurationLinks = computed(() =>
    [
        { name: 'Companies', href: route('companies.index'), routeName: 'companies.*', permission: Permission.CompaniesManage },
        { name: 'Company Profile', href: route('settings.company-profile'), routeName: 'settings.company-profile', permission: Permission.SettingsCompanyProfile },
        { name: 'Visual Identity', href: route('settings.visual-identity'), routeName: 'settings.visual-identity', permission: Permission.SettingsVisualIdentity },
        { name: 'Salary Components', href: route('settings.salary-components.index'), routeName: 'settings.salary-components.*', permission: Permission.SettingsSalaryComponents },
        { name: 'Registration Documents', href: route('settings.registration-documents.index'), routeName: 'settings.registration-documents.*', permission: Permission.SettingsRegistrationDocuments },
        { name: 'Payslip Templates', href: route('settings.payslip-templates'), routeName: 'settings.payslip-templates', permission: Permission.SettingsPayslipTemplates },
        { name: 'Integrations', href: route('settings.integrations'), routeName: 'settings.integrations', permission: Permission.SettingsIntegrations },
        { name: 'API Guidelines', href: route('settings.api-guidelines'), routeName: 'settings.api-guidelines', permission: Permission.SettingsApiGuidelines },
        { name: 'Security & Audit', href: route('settings.security-audit'), routeName: 'settings.security-audit', permission: Permission.SettingsSecurityAudit },
        {
            name: 'Users & Access',
            href: route('settings.users.index'),
            routeName: 'settings.users.*',
            permission: Permission.UsersManage,
            requireAdmin: true,
        },
    ].filter((link) => {
        if (link.requireAdmin && !isAdmin.value) {
            return false;
        }

        return can(link.permission);
    }),
);

const companies = computed(() => (page.props.companies as Array<{ id: number; company_name: string }>) || []);
const currentCompany = computed(() => page.props.currentCompany as { id: number; company_name: string } | null);

const switchCompany = (event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);
    if (!id || id === currentCompany.value?.id) return;
    router.post(route('companies.switch', id), {}, { preserveScroll: true });
};
</script>

<template>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">{{ companyName.charAt(0) }}</div>
                <div class="brand-text">
                    <div class="brand-name">{{ companyName }}</div>
                    <div class="brand-sub">Payslip Engine</div>
                    <select
                        v-if="user?.is_super_admin && companies.length > 1"
                        class="company-switcher"
                        :value="currentCompany?.id"
                        @change="switchCompany"
                    >
                        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                </div>
            </div>

            <div>
                <div class="nav-group-label">Workspace</div>
                <nav class="nav">
                    <Link
                        v-for="link in workspaceLinks"
                        :key="link.name"
                        :href="link.href as string"
                        class="nav-link"
                        :class="{ active: link.routeName ? route().current(link.routeName) : false }"
                    >
                        <span class="dot"></span>
                        {{ link.name }}
                        <span
                            v-if="link.showPending && $page.props.pendingStampedCount"
                            class="pending-pill"
                        >
                            {{ $page.props.pendingStampedCount }}
                        </span>
                    </Link>
                </nav>
            </div>

            <div v-if="configurationLinks.length">
                <div class="nav-group-label">Configuration</div>
                <nav class="nav">
                    <Link
                        v-for="link in configurationLinks"
                        :key="link.name"
                        :href="link.href"
                        class="nav-link"
                        :class="{ active: link.routeName ? route().current(link.routeName) : false }"
                    >
                        <span class="dot"></span>
                        {{ link.name }}
                    </Link>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="avatar">{{ user?.name?.charAt(0) }}</div>
                <div class="user-card">
                    <div class="user-info">
                        <div class="user-name">{{ user?.name }}</div>
                        <div class="user-role">{{ roleLabel }}</div>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="logout-btn"
                        title="Log Out"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="logout-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div class="crumbs">
                    <span>{{ companyName }} / {{ user?.name }}</span>
                </div>
                <div class="topbar-actions">
                    <Link
                        v-if="can(Permission.SettingsSecurityAudit)"
                        :href="route('settings.security-audit')"
                        class="btn btn-ghost"
                    >
                        View audit log
                    </Link>
                    <Link :href="route('profile.edit')" class="btn btn-ghost">Profile</Link>
                </div>
            </div>

            <header class="page-header" v-if="$slots.header">
                <div class="page-title">
                    <div>
                        <slot name="header" />
                    </div>
                </div>
            </header>

            <main class="page-content">
                <slot />
            </main>
        </main>
    </div>
</template>

<style>
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    background: #f4f6fb;
    color: #0f172a;
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; border: none; background: none; }

.pending-pill {
    margin-left: auto;
    background: #f59e0b;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 999px;
}
.app {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
}

.sidebar {
    background: #0b1220;
    color: #cbd5e1;
    padding: 22px 18px;
    border-right: 1px solid #111a2e;
    display: flex;
    flex-direction: column;
    gap: 22px;
}
.logout-btn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: #94a3b8;
    transition: all 0.15s;
}
.logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
.logout-icon { width: 18px; height: 18px; }
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 6px 14px;
    border-bottom: 1px solid #1f2a44;
}
.brand-mark {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
    display: grid; place-items: center;
    color: #fff; font-weight: 700;
}
.brand-name { color: #fff; font-weight: 600; font-size: 15px; }
.brand-sub  { color: #64748b; font-size: 11px; }
.brand-text { min-width: 0; flex: 1; }
.company-switcher {
    margin-top: 8px;
    width: 100%;
    background: #111a2e;
    color: #e2e8f0;
    border: 1px solid #1f2a44;
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 11.5px;
}
.nav-group-label {
    font-size: 10.5px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #475569;
    padding: 0 8px;
    margin-bottom: 6px;
}
.nav { display: flex; flex-direction: column; gap: 2px; }
.nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px;
    border-radius: 8px;
    color: #cbd5e1;
    font-size: 13.5px;
    transition: background .15s, color .15s;
}
.nav-link:hover { background: #111a2e; color: #fff; }
.nav-link.active {
    background: linear-gradient(90deg, rgba(29,78,216,.18), rgba(29,78,216,.02));
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(59,130,246,.35);
}
.nav .dot { width: 6px; height: 6px; border-radius: 50%; background: #334155; }
.nav-link.active .dot { background: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.25); }
.sidebar-footer {
    margin-top: auto;
    padding: 12px;
    border-radius: 10px;
    background: #0f1a30;
    display: flex; align-items: center; gap: 10px;
}
.user-card { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg,#0ea5e9,#1d4ed8);
    color: #fff; font-weight: 600;
    display: grid; place-items: center;
}
.user-name { color: #fff; font-weight: 500; font-size: 13px; }
.user-role { color: #64748b; font-size: 11.5px; }
.main { padding: 26px 34px 60px; overflow-x: hidden; }
.topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px;
}
.crumbs { color: #64748b; font-size: 12.5px; }
.crumbs span { color: #0f172a; font-weight: 500; }
.topbar-actions { display: flex; gap: 10px; }
.page-header { margin-bottom: 22px; }
.page-title {
    display: flex; align-items: flex-end; justify-content: space-between;
}
.page-title h1, .page-title h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 650;
    letter-spacing: -0.01em;
    color: #0f172a;
}
.page-title p { margin: 6px 0 0; color: #64748b; font-size: 13.5px; max-width: 720px; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 14px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
}
.btn-ghost {
    background: #fff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
}
.btn-ghost:hover { background: #f8fafc; }
@media (max-width: 768px) {
    .app { grid-template-columns: 1fr; }
    .sidebar { display: none; }
}
</style>
