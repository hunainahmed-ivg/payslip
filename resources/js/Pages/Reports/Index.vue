<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface RunSummary {
    id: number;
    period: string;
    status: 'draft' | 'approved' | 'locked';
    total_earnings: string;
    total_deductions: string;
    total_net_pay: string;
    generated_at: string | null;
    approved_at: string | null;
    items_count: number;
}

interface CurrencyRow {
    currency_code: string;
    employees: number;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
}

const props = defineProps<{
    stats: {
        active_employees: number;
        branches: number;
        payroll_runs: number;
        published_payslips: number;
        pending_requests: number;
    };
    runs: RunSummary[];
    latestRun: RunSummary | null;
    currencyBreakdown: CurrencyRow[];
}>();

const fmt = (v: number | string) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const statCards = [
    { key: 'active_employees', label: 'Active Employees', icon: '👥', color: 'text-indigo-600' },
    { key: 'branches', label: 'Branches', icon: '🏢', color: 'text-sky-600' },
    { key: 'payroll_runs', label: 'Payroll Runs', icon: '🗓️', color: 'text-amber-600' },
    { key: 'published_payslips', label: 'Payslips Published', icon: '📄', color: 'text-emerald-600' },
    { key: 'pending_requests', label: 'Pending Requests', icon: '🖋️', color: 'text-red-600' },
] as const;
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Reports & Analytics</h1>
                <p>System statistics, payroll execution summaries and multi-currency breakdowns.</p>
            </div>
        </template>

        <div class="py-6">
            <!-- Stat cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div v-for="card in statCards" :key="card.key" class="card">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wider text-gray-500">{{ card.label }}</span>
                        <span>{{ card.icon }}</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold" :class="card.color">{{ props.stats[card.key] }}</div>
                </div>
            </div>

            <!-- Currency breakdown (latest non-draft run) -->
            <div v-if="latestRun" class="card mb-6 overflow-hidden p-0">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">
                        Currency Breakdown — {{ latestRun.period }}
                        <span class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium uppercase text-emerald-700">
                            {{ latestRun.status }}
                        </span>
                    </h2>
                    <Link :href="route('payroll-runs.show', latestRun.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        Open review grid →
                    </Link>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Currency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gross Pay</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deductions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Net Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="row in currencyBreakdown" :key="row.currency_code">
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ row.currency_code }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ row.employees }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ fmt(row.gross_pay) }}</td>
                            <td class="px-6 py-4 text-sm text-red-600">− {{ fmt(row.total_deductions) }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ fmt(row.net_pay) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="px-6 py-3 text-xs text-gray-400">
                    Totals are grouped per currency (multi-branch) — cross-currency sums are never mixed.
                </p>
            </div>
            <div v-else class="card mb-6 py-10 text-center text-sm text-gray-500">
                No approved payroll runs yet — currency breakdowns will appear after the first approval.
            </div>

            <!-- Payroll run history -->
            <div class="card overflow-hidden p-0">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Payroll Run History</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gross</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deductions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Net Pay</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="run in runs" :key="run.id">
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ run.period }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                                    :class="run.status === 'draft' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                                >
                                    {{ run.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ run.items_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ fmt(run.total_earnings) }}</td>
                            <td class="px-6 py-4 text-sm text-red-600">− {{ fmt(run.total_deductions) }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ fmt(run.total_net_pay) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('payroll-runs.show', run.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Review Grid
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!runs.length">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No payroll runs yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.card {
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
}
.card.p-0 {
    padding: 0;
}
</style>