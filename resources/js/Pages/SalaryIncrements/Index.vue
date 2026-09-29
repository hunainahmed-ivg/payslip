<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

interface EmployeeOption {
    id: number;
    employee_code: string;
    full_name: string;
    base_salary: string | number;
    currency_code: string;
}

interface LedgerEntry {
    id: number;
    event_type: string;
    previous_basic_salary: string | null;
    basic_salary: string;
    effective_date: string;
    note: string | null;
}

interface IncrementRow {
    id: number;
    employee_code: string | null;
    full_name: string | null;
    previous_basic_salary: string | number;
    increment_type: string;
    value: string | number;
    new_basic_salary: string | number;
    effective_date: string;
    note: string | null;
}

const props = withDefaults(
    defineProps<{
        employees: EmployeeOption[];
        selected_employee: EmployeeOption | null;
        ledger: LedgerEntry[];
        increments: Array<Record<string, unknown>>;
        yearly_report: IncrementRow[];
        year: number;
        success?: string;
    }>(),
    {
        selected_employee: null,
        success: undefined,
    },
);

const tab = ref<'apply' | 'ledger' | 'report'>('apply');
const employeeSearch = ref('');
const selectedId = ref(props.selected_employee ? String(props.selected_employee.id) : '');

const form = useForm({
    employee_id: selectedId.value,
    current_basic_salary: props.selected_employee ? String(props.selected_employee.base_salary) : '',
    increment_type: 'percent' as 'percent' | 'fixed',
    value: '',
    effective_date: new Date().toISOString().slice(0, 10),
    note: '',
});

const filteredEmployees = computed(() => {
    const term = employeeSearch.value.toLowerCase().trim();
    if (!term) return props.employees;
    return props.employees.filter(
        (e) =>
            e.full_name.toLowerCase().includes(term) ||
            e.employee_code.toLowerCase().includes(term),
    );
});

const previewSalary = computed(() => {
    const current = Number(form.current_basic_salary || 0);
    const value = Number(form.value || 0);
    if (Number.isNaN(current) || Number.isNaN(value)) return null;
    if (form.increment_type === 'percent') {
        return Math.round(current * (1 + value / 100) * 100) / 100;
    }
    return Math.round((current + value) * 100) / 100;
});

const selectEmployee = (employee: EmployeeOption) => {
    selectedId.value = String(employee.id);
    form.employee_id = String(employee.id);
    form.current_basic_salary = String(employee.base_salary);
    router.get(
        route('salary-increments.index'),
        { employee_id: employee.id, year: props.year },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => props.selected_employee,
    (employee) => {
        if (!employee) return;
        selectedId.value = String(employee.id);
        form.employee_id = String(employee.id);
        form.current_basic_salary = String(employee.base_salary);
    },
);

const submit = () => {
    form.post(route('salary-increments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.value = '';
            form.note = '';
        },
    });
};

const changeYear = (event: Event) => {
    const year = Number((event.target as HTMLSelectElement).value);
    router.get(
        route('salary-increments.index'),
        {
            year,
            employee_id: selectedId.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2, current - 3];
});

const money = (amount: string | number | null | undefined) => {
    if (amount === null || amount === undefined || amount === '') return '—';
    return Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
</script>

<template>
    <Head title="Salary Increments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Salary Increments</h1>
                    <p>Append-only salary changes with full ledger history. Direct salary edits are disabled.</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <div class="mb-4 flex gap-2">
                <button
                    v-for="item in [
                        { key: 'apply', label: 'Apply Increment' },
                        { key: 'ledger', label: 'Salary Ledger' },
                        { key: 'report', label: 'Yearly Report' },
                    ]"
                    :key="item.key"
                    class="rounded-md px-3 py-1.5 text-sm font-medium"
                    :class="tab === item.key ? 'bg-indigo-600 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    @click="tab = item.key as 'apply' | 'ledger' | 'report'"
                >
                    {{ item.label }}
                </button>
            </div>

            <div v-if="tab === 'apply'" class="grid gap-6 lg:grid-cols-2">
                <div class="card">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900">Search employee</h2>
                    <TextInput v-model="employeeSearch" class="mb-3 block w-full" placeholder="Name or employee ID..." />
                    <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 rounded-md border border-gray-200">
                        <button
                            v-for="employee in filteredEmployees"
                            :key="employee.id"
                            type="button"
                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50"
                            :class="String(employee.id) === selectedId ? 'bg-indigo-50' : ''"
                            @click="selectEmployee(employee)"
                        >
                            <span>
                                <span class="font-medium text-gray-900">{{ employee.full_name }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ employee.employee_code }}</span>
                            </span>
                            <span class="text-xs text-gray-600">{{ money(employee.base_salary) }}</span>
                        </button>
                        <div v-if="!filteredEmployees.length" class="px-3 py-6 text-center text-sm text-gray-500">
                            No employees found.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900">Increment details</h2>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel value="Current basic salary" />
                            <TextInput v-model="form.current_basic_salary" class="mt-1 block w-full" readonly disabled />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="increment_type" value="Increment type" />
                                <select
                                    id="increment_type"
                                    v-model="form.increment_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="percent">Percent</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                                <InputError :message="form.errors.increment_type" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="value" :value="form.increment_type === 'percent' ? 'Percent value' : 'Fixed amount'" />
                                <TextInput id="value" v-model="form.value" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.value" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="effective_date" value="Effective date" />
                            <input
                                id="effective_date"
                                v-model="form.effective_date"
                                type="date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <InputError :message="form.errors.effective_date" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea
                                id="note"
                                v-model="form.note"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="form.errors.note" class="mt-2" />
                        </div>

                        <div class="rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                            Preview new salary:
                            <span class="font-semibold">{{ previewSalary === null ? '—' : money(previewSalary) }}</span>
                        </div>

                        <InputError :message="form.errors.employee_id" class="mt-2" />

                        <div class="flex justify-end gap-3">
                            <SecondaryButton
                                type="button"
                                @click="
                                    form.reset(
                                        'value',
                                        'note',
                                    )
                                "
                            >
                                Reset
                            </SecondaryButton>
                            <PrimaryButton :disabled="form.processing || !form.employee_id">
                                Save Increment
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-else-if="tab === 'ledger'" class="card overflow-hidden p-0">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">
                        Ledger
                        <span v-if="selected_employee" class="font-normal text-gray-500">
                            — {{ selected_employee.full_name }} ({{ selected_employee.employee_code }})
                        </span>
                    </h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Previous</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">New</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Effective</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="entry in ledger" :key="entry.id">
                            <td class="px-6 py-4 text-sm capitalize text-gray-700">{{ entry.event_type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ money(entry.previous_basic_salary) }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ money(entry.basic_salary) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ entry.effective_date?.slice?.(0, 10) ?? entry.effective_date }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ entry.note ?? '—' }}</td>
                        </tr>
                        <tr v-if="!selected_employee">
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Select an employee to view ledger history.</td>
                        </tr>
                        <tr v-else-if="!ledger.length">
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No ledger entries yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <InputLabel value="Year" />
                        <select
                            :value="year"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @change="changeYear"
                        >
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <a
                            :href="route('salary-increments.export.csv', { year })"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Download CSV
                        </a>
                        <a
                            :href="route('salary-increments.export.pdf', { year })"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Download PDF
                        </a>
                    </div>
                </div>

                <div class="card overflow-hidden p-0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Previous</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">New</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Effective</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="row in yearly_report" :key="row.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ row.full_name }}</div>
                                    <div class="text-xs text-gray-400">{{ row.employee_code }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ money(row.previous_basic_salary) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ row.increment_type === 'percent' ? `${row.value}%` : money(row.value) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ money(row.new_basic_salary) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ row.effective_date }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ row.note ?? '—' }}</td>
                            </tr>
                            <tr v-if="!yearly_report.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No increments for {{ year }}.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
