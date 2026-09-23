<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';

interface Branch {
    id: number;
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
}

interface MasterComponent {
    id: number;
    name: string;
    slug: string;
    type: 'earning' | 'deduction';
    calculation_type: 'fixed' | 'percentage' | 'statutory';
    default_value: string;
    is_taxable: boolean;
}

interface EmployeeComponent {
    id: number;
    salary_component_id: number | null;
    title: string;
    type: 'earning' | 'deduction';
    calculation_type: 'fixed' | 'percentage' | 'statutory';
    value: string;
}

interface Employee {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    department: string | null;
    designation: string | null;
    currency_code: string;
    base_salary: string;
    joined_on: string | null;
    is_active: boolean;
    branch: Branch | null;
    salary_components: EmployeeComponent[];
}

interface StructureRow {
    key: string;
    id: number | null;
    slug: string | null;
    title: string;
    type: string;
    calculation_type: string;
    value: string;
    source: 'default' | 'override' | 'custom';
    master: MasterComponent | null;
}

const props = withDefaults(
    defineProps<{
        employee: Employee;
        masterComponents: MasterComponent[];
        success?: string;
    }>(),
    { success: undefined },
);

const showModal = ref(false);
const editingId = ref<number | null>(null);
const deleting = ref<{ id: number; title: string; isOverride: boolean } | null>(null);

const form = useForm({
    salary_component_id: null as number | null,
    title: '',
    type: 'earning' as string,
    calculation_type: 'fixed' as string,
    value: '0',
});

const deleteForm = useForm({});

const baseSalary = computed(() => parseFloat(props.employee.base_salary) || 0);

/**
 * Merge engine: master defaults → employee overrides → custom items.
 * Basic Salary always comes from the employee contract (base_salary).
 */
const structureRows = computed<StructureRow[]>(() => {
    const overrides = new Map<number, EmployeeComponent>();
    const customs: EmployeeComponent[] = [];

    for (const ec of props.employee.salary_components) {
        if (ec.salary_component_id) {
            overrides.set(ec.salary_component_id, ec);
        } else {
            customs.push(ec);
        }
    }

    const masterRows: StructureRow[] = props.masterComponents.map((mc) => {
        const ov = overrides.get(mc.id);
        const isBasic = mc.slug === 'basic_salary';
        return {
            key: 'mc-' + mc.id,
            id: ov ? ov.id : null,
            slug: mc.slug,
            title: mc.name,
            type: mc.type,
            calculation_type: ov ? ov.calculation_type : mc.calculation_type,
            value: ov ? ov.value : isBasic ? props.employee.base_salary : mc.default_value,
            source: ov ? 'override' : 'default',
            master: mc,
        };
    });

    const customRows: StructureRow[] = customs.map((ec) => ({
        key: 'ec-' + ec.id,
        id: ec.id,
        slug: null,
        title: ec.title,
        type: ec.type,
        calculation_type: ec.calculation_type,
        value: ec.value,
        source: 'custom',
        master: null,
    }));

    return [...masterRows, ...customRows];
});

const computedAmount = (row: StructureRow): number => {
    const val = parseFloat(row.value) || 0;
    if (row.calculation_type === 'percentage') {
        return (baseSalary.value * val) / 100;
    }
    return val;
};

const money = (amount: number) => {
    const symbol = props.employee.branch?.currency_symbol ?? props.employee.currency_code;
    return (
        symbol +
        ' ' +
        amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
};

const earningsTotal = computed(() =>
    structureRows.value.filter((r) => r.type === 'earning').reduce((sum, r) => sum + computedAmount(r), 0),
);
const deductionsTotal = computed(() =>
    structureRows.value.filter((r) => r.type === 'deduction').reduce((sum, r) => sum + computedAmount(r), 0),
);
const netPay = computed(() => earningsTotal.value - deductionsTotal.value);

const masterName = (id: number | null) =>
    props.masterComponents.find((m) => m.id === id)?.name ?? '';

const openOverride = (master: MasterComponent) => {
    editingId.value = null;
    form.salary_component_id = master.id;
    form.title = '';
    form.type = master.type;
    form.calculation_type = master.calculation_type;
    form.value = master.default_value;
    form.clearErrors();
    showModal.value = true;
};

const openCustom = () => {
    editingId.value = null;
    form.salary_component_id = null;
    form.title = '';
    form.type = 'earning';
    form.calculation_type = 'fixed';
    form.value = '0';
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (row: StructureRow) => {
    editingId.value = row.id;
    form.salary_component_id = row.master ? row.master.id : null;
    form.title = row.master ? '' : row.title;
    form.type = row.type;
    form.calculation_type = row.calculation_type;
    form.value = row.value;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editingId.value) {
        form.put(route('employees.salary-components.update', [props.employee.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    } else {
        form.post(route('employees.salary-components.store', props.employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('employees.salary-components.destroy', [props.employee.id, deleting.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
    });
};
</script>

<template>
    <Head :title="employee.full_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <Link :href="route('employees.index')" class="text-sm text-indigo-600 hover:underline">← Back to Employees</Link>
                    <h1 class="mt-1">
                        {{ employee.full_name }}
                        <span class="font-normal text-gray-400">· {{ employee.employee_code }}</span>
                    </h1>
                    <p>
                        {{ employee.designation ?? '—' }} · {{ employee.department ?? '—' }} · {{ employee.branch?.name }}
                        ({{ employee.currency_code }})
                    </p>
                </div>
                <PrimaryButton @click="openCustom">+ Add Custom Item</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <!-- Live totals -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Base Salary</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ money(baseSalary) }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Gross Earnings</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-600">{{ money(earningsTotal) }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Total Deductions</div>
                    <div class="mt-1 text-lg font-semibold text-red-600">{{ money(deductionsTotal) }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Net Pay (preview)</div>
                    <div class="mt-1 text-lg font-semibold text-indigo-600">{{ money(netPay) }}</div>
                </div>
            </div>

            <!-- Effective salary structure -->
            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Component</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Calculation</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="row in structureRows" :key="row.key">
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ row.title }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="row.type === 'earning' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
                                >
                                    {{ row.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-gray-100 text-gray-600': row.source === 'default',
                                        'bg-indigo-50 text-indigo-700': row.source === 'override',
                                        'bg-amber-50 text-amber-700': row.source === 'custom',
                                    }"
                                >
                                    {{ row.source === 'default' ? 'Global default' : row.source === 'override' ? 'Override' : 'Custom' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm capitalize text-gray-600">{{ row.calculation_type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ row.calculation_type === 'percentage' ? row.value + ' %' : row.value }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ money(computedAmount(row)) }}</td>
                            <td class="px-6 py-4 text-right">
                                <span v-if="row.slug === 'basic_salary'" class="text-xs text-gray-400">Via employee profile</span>
                                <template v-else-if="row.source === 'default'">
                                    <button
                                        v-if="row.master"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        @click="openOverride(row.master)"
                                    >
                                        Add Override
                                    </button>
                                </template>
                                <template v-else>
                                    <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="openEdit(row)">Edit</button>
                                    <button
                                        class="ml-3 text-sm font-medium text-red-600 hover:text-red-800"
                                        @click="deleting = { id: row.id!, title: row.title, isOverride: row.source === 'override' }"
                                    >
                                        {{ row.source === 'override' ? 'Revert' : 'Delete' }}
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Override / Custom Modal -->
            <Modal :show="showModal" @close="showModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ editingId ? 'Edit Salary Mapping' : form.salary_component_id ? 'Override Component' : 'New Custom Item' }}
                    </h2>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div v-if="form.salary_component_id">
                            <InputLabel value="Master Component" />
                            <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ masterName(form.salary_component_id) }}
                            </div>
                        </div>
                        <div v-else>
                            <InputLabel for="title" value="Title" />
                            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required placeholder="e.g. Fuel Conveyance" />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="type" value="Type" />
                                <select
                                    id="type"
                                    v-model="form.type"
                                    :disabled="!!form.salary_component_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50"
                                >
                                    <option value="earning">Earning</option>
                                    <option value="deduction">Deduction</option>
                                </select>
                                <InputError :message="form.errors.type" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="calculation_type" value="Calculation" />
                                <select id="calculation_type" v-model="form.calculation_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage of Basic</option>
                                    <option value="statutory">Statutory Formula</option>
                                </select>
                                <InputError :message="form.errors.calculation_type" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="value" :value="form.calculation_type === 'percentage' ? 'Percentage (%)' : 'Value'" />
                            <TextInput id="value" v-model="form.value" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.value" class="mt-2" />
                            <p v-if="form.calculation_type === 'percentage'" class="mt-1 text-xs text-gray-500">
                                ({{ money(baseSalary) }} × {{ form.value }}) / 100 =
                                {{ money((baseSalary * (parseFloat(form.value) || 0)) / 100) }}
                            </p>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="form.processing">
                                {{ editingId ? 'Update' : 'Save' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Delete / Revert Confirmation -->
            <Modal :show="!!deleting" @close="deleting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ deleting?.isOverride ? 'Revert to Global Default' : 'Delete Custom Item' }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ deleting?.isOverride
                            ? `The override for "${deleting?.title}" will be removed and the global default rule will apply again.`
                            : `The custom item "${deleting?.title}" will be permanently removed.` }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="deleting = null">Cancel</SecondaryButton>
                        <DangerButton :disabled="deleteForm.processing" @click="confirmDelete">
                            {{ deleting?.isOverride ? 'Revert' : 'Delete' }}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
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