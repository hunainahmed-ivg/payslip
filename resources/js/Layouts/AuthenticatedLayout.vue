<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;
const showingNavigationDropdown = ref(false);
const navigationLinks = [
    { name: 'Dashboard', href: route('dashboard'), routeName: 'dashboard', icon: '📊' },
    { name: 'My Pay Slips', href: route('portal.payslips'), routeName: 'portal.payslips', icon: '📄' },
    { name: 'Employees', href: route('employees.index'), routeName: 'employees.*', icon: '👥' },
    { name: 'Payroll', href: route('payroll.import'), routeName: 'payroll.*', icon: '📥' },
    { name: 'Payroll Runs', href: route('payroll-runs.index'), routeName: 'payroll-runs.*', icon: '🗓️' },
    { name: 'Reports', href: route('reports.index'), routeName: 'reports.*', icon: '📈' },
];

const configurationLinks = [
    { name: 'Company Profile', href: route('settings.company-profile'), routeName: 'settings.company-profile', icon: '🏢' },
    { name: 'Visual Identity', href: route('settings.visual-identity'), routeName: 'settings.visual-identity', icon: '🎨' },
    { name: 'Salary Components', href: route('settings.salary-components.index'), routeName: 'settings.salary-components.*', icon: '🧮' },
    { name: 'Payslip Templates', href: route('settings.payslip-templates'), routeName: 'settings.payslip-templates', icon: '📄' },
    { name: 'Integrations', href: route('settings.integrations'), routeName: 'settings.integrations', icon: '🔌' },
    { name: 'Security & Audit', href: route('settings.security-audit'), routeName: 'settings.security-audit', icon: '🔒' },
];
</script>

<template>
    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">P</div>
                <div>
                    <div class="brand-name">PayrollOS</div>
                    <div class="brand-sub">Enterprise Suite</div>
                </div>
            </div>

            <div>
                <div class="nav-group-label">Workspace</div>
                <nav class="nav">
                    <Link
                        v-for="link in navigationLinks"
                        :key="link.name"
                        :href="link.href"
                        class="nav-link"
                        :class="{ active: link.routeName ? route().current(link.routeName) : false }"
                    >
                        <span class="dot"></span>
                        {{ link.name }}
                    </Link>
                    <Link
                        :href="route('stamped-requests.index')"
                        class="nav-link"
                        :class="{ active: route().current('stamped-requests.*') }"
                    >
                        <span class="dot"></span>
                        Stamped Requests
                        <span v-if="$page.props.pendingStampedCount" class="pending-pill">
                            {{ $page.props.pendingStampedCount }}
                        </span>
                    </Link>
                </nav>
            </div>

            <div>
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
                <div class="avatar">{{ $page.props.auth.user.name.charAt(0) }}</div>
                <div class="user-card">
                    <div class="user-info">
                        <div class="user-name">{{ $page.props.auth.user.name }}</div>
                        <div class="user-role">{{ $page.props.auth.user.email }}</div>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="logout-btn"
                        title="Log Out"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            class="logout-icon"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                            />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <div class="topbar">
                <div class="crumbs">
                    <span>PayrollOS &nbsp;/&nbsp; {{ $page.props.auth.user.name }}</span>
                </div>
                <div class="topbar-actions">
                    <Link :href="route('settings.security-audit')" class="btn btn-ghost">View audit log</Link>
                </div>
            </div>

            <!-- Page Heading -->
            <header class="page-header" v-if="$slots.header">
                <div class="page-title">
                    <div>
                        <slot name="header" />
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="page-content">
                <slot />
            </main>
        </main>
    </div>
</template>

<style>
/* Base */
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    font-family: "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f4f6fb;
    color: #0f172a;
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; border: none; background: none; }

/* Layout */
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

/* Sidebar */
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
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
.logout-icon {
    width: 18px;
    height: 18px;
}
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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: grid; place-items: center;
    color: #fff; font-weight: 700;
    box-shadow: 0 6px 18px rgba(99,102,241,.35);
}

.brand-name { color: #fff; font-weight: 600; font-size: 15px; }
.brand-sub  { color: #64748b; font-size: 11px; }

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
    background: linear-gradient(90deg, rgba(99,102,241,.18), rgba(99,102,241,.02));
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(99,102,241,.35);
}

.nav .dot { width: 6px; height: 6px; border-radius: 50%; background: #334155; }
.nav-link.active .dot { background: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.25); }

.sidebar-footer {
    margin-top: auto;
    padding: 12px;
    border-radius: 10px;
    background: #0f1a30;
    display: flex; align-items: center; gap: 10px;
}

.avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg,#f59e0b,#ef4444);
    color: #fff; font-weight: 600;
    display: grid; place-items: center;
}

.user-name { color: #fff; font-weight: 500; font-size: 13px; }
.user-role { color: #64748b; font-size: 11.5px; }

/* Main */
.main { padding: 26px 34px 60px; overflow-x: hidden; }

.topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px;
}

.crumbs { color: #64748b; font-size: 12.5px; }
.crumbs span { color: #0f172a; font-weight: 500; }

.topbar-actions { display: flex; gap: 10px; }

.page-header {
    margin-bottom: 22px;
}

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
    transition: transform .08s, box-shadow .15s, background .15s;
}

.btn:active { transform: translateY(1px); }

.btn-primary {
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 6px 16px rgba(79,70,229,.28);
}

.btn-primary:hover { background: #4338ca; }

.btn-ghost {
    background: #fff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
}

.btn-ghost:hover { background: #f8fafc; }

.page-content {
    /* Additional page content styles */
}

/* Responsive */
@media (max-width: 768px) {
    .app {
        grid-template-columns: 1fr;
    }
    .sidebar {
        display: none;
    }
}
</style>