<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

interface ReportRow {
    line: number;
    status: 'valid' | 'error';
    errors: string[];
    employee_name: string | null;
    data: {
        employee_code: string;
        period: string;
        total_working_days: number;
        attended_days: number | null;
        unpaid_leave_days: number;
        paid_leave_days: number;
        overtime_hours: number;
        late_count: number;
    };
}

interface Report {
    total: number;
    valid: number;
    errors: number;
    rows: ReportRow[];
}

interface RecentInput {
    id: number;
    period: string;
    total_working_days: number;
    attended_days: number | null;
    unpaid_leave_days: string;
    paid_leave_days: string;
    overtime_hours: string;
    late_count: number;
    source: string;
    employee: { id: number; employee_code: string; full_name: string } | null;
}

const props = withDefaults(
    defineProps<{
        employeeCount: number;
        recent: RecentInput[];
        report?: Report | null;
        success?: string;
        error?: string;
    }>(),
    { report: null, success: undefined, error: undefined },
);

const fileName = ref<string | null>(null);

const form = useForm({
    file: null as File | null,
});

const onFileChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.file = file;
    fileName.value = file?.name ?? null;
};

// Commit unlocks ONLY when a clean report exists
const canCommit = computed(
    () => !!props.report && props.report.errors === 0 && props.report.total > 0,
);

const runDryRun = () => {
    form.post(route('payroll.import.dry-run'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const commit = () => {
    form.post(route('payroll.import.commit'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Payroll Data Import" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Payroll Data Import</h1>
                    <p>Standardized CSV import with dry-run validation — nothing is written to the database until you commit a clean report.</p>
                </div>
                <a
                    :href="route('payroll.import.template')"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    ↓ Download CSV Template
                </a>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>
            <div v-if="error" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>

            <!-- Upload + actions -->
            <div class="card mb-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Step 1 — Upload attendance & leave file</h2>
                    <span class="badge">{{ employeeCount }} active employees</span>
                </div>

                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50">
                    <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    <span class="text-sm font-medium text-gray-900">{{ fileName ?? 'Click to select a CSV file' }}</span>
                    <span class="text-xs text-gray-500">
                        Columns: employee_code, period, total_working_days, attended_days, unpaid_leave_days, paid_leave_days, overtime_hours, late_count
                    </span>
                    <input type="file" accept=".csv,text/csv" class="hidden" @change="onFileChange" />
                </label>
                <InputError :message="form.errors.file" class="mt-2" />

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <PrimaryButton :disabled="!form.file || form.processing" @click="runDryRun">
                        <span v-if="form.processing">Working...</span>
                        <span v-else>Step 2 — Run Dry-Run Validation</span>
                    </PrimaryButton>
                    <SecondaryButton :disabled="!canCommit || form.processing" @click="commit">
                        Step 3 — Commit to Database
                    </SecondaryButton>
                    <span v-if="report && report.errors > 0" class="text-xs text-red-600">
                        Commit unlocks only when the report has zero errors.
                    </span>
                    <span v-else-if="!report" class="text-xs text-gray-500">Commit unlocks after a clean dry-run.</span>
                </div>
            </div>

            <!-- Dry-run report -->
            <div v-if="report" class="card mb-6 overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Dry-Run Validation Report</h2>
                    <div class="flex gap-2">
                        <span class="badge">{{ report.total }} rows</span>
                        <span class="badge badge-success">{{ report.valid }} valid</span>
                        <span class="badge" :class="report.errors > 0 ? 'badge-error' : 'badge-success'">
                            {{ report.errors }} errors
                        </span>
                    </div>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Line</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Working Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unpaid Leave</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Overtime</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="row in report.rows" :key="row.line" :class="row.status === 'error' ? 'bg-red-50/40' : ''">
                            <td class="px-6 py-3 text-sm text-gray-500">{{ row.line }}</td>
                            <td class="px-6 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ row.data.employee_code }}</div>
                                <div class="text-xs text-gray-400">{{ row.employee_name ?? 'Unknown employee' }}</div>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ row.data.period }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ row.data.total_working_days }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ row.data.unpaid_leave_days }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ row.data.overtime_hours }}</td>
                            <td class="px-6 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="row.status === 'valid' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                                >
                                    {{ row.status === 'valid' ? 'Valid' : 'Error' }}
                                </span>
                                <ul v-if="row.errors.length" class="mt-1 list-inside list-disc text-xs text-red-600">
                                    <li v-for="(err, i) in row.errors" :key="i">{{ err }}</li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent payroll inputs -->
            <div class="card overflow-hidden p-0">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Recent Payroll Inputs</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Working Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Attended</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unpaid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Paid Leave</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Overtime</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Late</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="input in recent" :key="input.id">
                            <td class="px-6 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ input.employee?.full_name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ input.employee?.employee_code ?? '' }}</div>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.period }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.total_working_days }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.attended_days ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.unpaid_leave_days }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.paid_leave_days }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.overtime_hours }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ input.late_count }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium uppercase text-indigo-700">{{ input.source }}</span>
                            </td>
                        </tr>
                        <tr v-if="!recent.length">
                            <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">
                                No payroll inputs yet — import a file above.
                            </td>
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
.badge {
    font-size: 11px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #e0e7ff;
}
.badge-success {
    background: #ecfdf5;
    color: #047857;
    border-color: #d1fae5;
}
.badge-error {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}
</style>