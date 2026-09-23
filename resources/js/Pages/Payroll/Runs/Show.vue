<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';

interface Line {
    title: string;
    slug: string | null;
    type: string;
    calculation_type: string;
    value: number;
    amount: number;
}

interface RunItem {
    id: number;
    employee_id: number;
    base_salary: string;
    currency_code: string;
    earnings: Line[];
    deductions: Line[];
    gross_pay: string;
    total_deductions: string;
    net_pay: string;
    overrides: { unpaid_leave_days?: number | string; overtime_hours?: number | string } | null;
    employee: {
        id: number;
        employee_code: string;
        full_name: string;
        department: string | null;
        branch: { name: string; currency_symbol: string } | null;
    };
}

interface Run {
    id: number;
    period: string;
    status: 'draft' | 'approved' | 'locked';
    total_earnings: string;
    total_deductions: string;
    total_net_pay: string;
    generated_at: string | null;
    approved_at: string | null;
    generator: { id: number; name: string } | null;
    items: RunItem[];
}

const props = withDefaults(
    defineProps<{ run: Run; success?: string }>(),
    { success: undefined },
);

const isDraft = computed(() => props.run.status === 'draft');
const expanded = ref<number[]>([]);
const overrideItem = ref<RunItem | null>(null);
const showApprove = ref(false);

const overrideForm = useForm({
    overrides: {
        unpaid_leave_days: '' as string | number,
    },
});

const approveForm = useForm({});
const publishForm = useForm({});

const submitPublish = () => {
    if (confirm('Queue PDF generation & auto-publishing for every employee in this run?')) {
        publishForm.post(route('payroll-runs.publish', props.run.id), {
            preserveScroll: true,
        });
    }
};

const toggleExpand = (id: number) => {
    expanded.value = expanded.value.includes(id)
        ? expanded.value.filter((x) => x !== id)
        : [...expanded.value, id];
};

const money = (amount: number | string, item: RunItem) => {
    const symbol = item.employee?.branch?.currency_symbol ?? item.currency_code;
    return (
        symbol +
        ' ' +
        Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
};

// Effective unpaid leave days used in the calculation (from the deduction line)
const unpaidDaysOf = (item: RunItem): number => {
    const line = item.deductions.find((d) => d.slug === 'unpaid_leave');
    return line ? Number(line.value) : 0;
};

const hasOverride = (item: RunItem) => !!item.overrides && Object.keys(item.overrides).length > 0;

const openOverride = (item: RunItem) => {
    overrideItem.value = item;
    overrideForm.overrides.unpaid_leave_days =
        item.overrides?.unpaid_leave_days ?? unpaidDaysOf(item);
    overrideForm.clearErrors();
};

const submitOverride = () => {
    if (!overrideItem.value) return;
    overrideForm.put(route('payroll-runs.items.update', [props.run.id, overrideItem.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            overrideItem.value = null;
        },
    });
};

const submitApprove = () => {
    approveForm.post(route('payroll-runs.approve', props.run.id), {
        preserveScroll: true,
        onSuccess: () => {
            showApprove.value = false;
        },
    });
};

// Net pay grouped per currency (multi-branch reality)
const netsByCurrency = computed(() => {
    const map = new Map<string, { symbol: string; total: number }>();
    for (const item of props.run.items) {
        const symbol = item.employee?.branch?.currency_symbol ?? item.currency_code;
        const entry = map.get(item.currency_code) ?? { symbol, total: 0 };
        entry.total += Number(item.net_pay);
        map.set(item.currency_code, entry);
    }
    return [...map.entries()].map(([code, v]) => ({
        code,
        label: `${v.symbol} ${v.total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
    }));
});

const fmt = (v: string) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="`Payroll ${run.period}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <Link :href="route('payroll-runs.index')" class="text-sm text-indigo-600 hover:underline">← All Payroll Runs</Link>
                    <h1 class="mt-1">
                        Payroll Review — {{ run.period }}
                        <span
                            class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium uppercase align-middle"
                            :class="isDraft ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                        >
                            {{ run.status }}
                        </span>
                    </h1>
                    <p>
                        Generated by {{ run.generator?.name ?? 'System' }}
                        <span v-if="run.approved_at"> · Approved & frozen on {{ new Date(run.approved_at).toLocaleString() }}</span>
                    </p>
                </div>
                <PrimaryButton v-if="isDraft" @click="showApprove = true">Approve & Freeze</PrimaryButton>
                <PrimaryButton v-else :disabled="publishForm.processing" @click="submitPublish">Generate & Publish Payslips</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>
            <div v-if="!isDraft" class="mb-4 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">
                This payroll is approved & frozen. Overrides are locked — the snapshot is immutable (Phase 6 distribution ready).
            </div>

            <!-- Totals -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Employees</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ run.items.length }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Gross Earnings</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-600">{{ fmt(run.total_earnings) }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Total Deductions</div>
                    <div class="mt-1 text-lg font-semibold text-red-600">{{ fmt(run.total_deductions) }}</div>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Net Pay (by currency)</div>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <span v-for="c in netsByCurrency" :key="c.code" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                            {{ c.code }} · {{ c.label }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Review Grid -->
            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Base Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gross</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deductions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Net Pay</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unpaid Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <template v-for="item in run.items" :key="item.id">
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ item.employee.full_name }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ item.employee.employee_code }} · {{ item.employee.branch?.name ?? '—' }}
                                        <span v-if="hasOverride(item)" class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">OVERRIDDEN</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ money(item.base_salary, item) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ money(item.gross_pay, item) }}</td>
                                <td class="px-6 py-4 text-sm text-red-600">− {{ money(item.total_deductions, item) }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ money(item.net_pay, item) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ unpaidDaysOf(item) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-sm font-medium text-gray-500 hover:text-gray-800" @click="toggleExpand(item.id)">
                                        {{ expanded.includes(item.id) ? 'Hide' : 'Details' }}
                                    </button>
                                    <button
                                        v-if="isDraft"
                                        class="ml-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        @click="openOverride(item)"
                                    >
                                        Override
                                    </button>
                                </td>
                            </tr>
                            <!-- Expandable line-item breakdown -->
                            <tr v-if="expanded.includes(item.id)">
                                <td colspan="7" class="bg-gray-50 px-6 py-4">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <div>
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-700">Earnings</div>
                                            <div v-for="line in item.earnings" :key="line.title" class="flex justify-between py-1 text-sm">
                                                <span class="text-gray-600">
                                                    {{ line.title }}
                                                    <span class="text-xs text-gray-400">
                                                        ({{ line.calculation_type === 'percentage' ? line.value + '%' : line.calculation_type }})
                                                    </span>
                                                </span>
                                                <span class="font-medium text-gray-900">{{ money(line.amount, item) }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-red-700">Deductions</div>
                                            <div v-for="line in item.deductions" :key="line.title" class="flex justify-between py-1 text-sm">
                                                <span class="text-gray-600">
                                                    {{ line.title }}
                                                    <span class="text-xs text-gray-400">
                                                        ({{ line.calculation_type === 'percentage' ? line.value + '%' : line.calculation_type }})
                                                    </span>
                                                </span>
                                                <span class="font-medium text-red-600">− {{ money(line.amount, item) }}</span>
                                            </div>
                                            <div v-if="!item.deductions.length" class="text-sm text-gray-400">No deductions this period.</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Override Modal -->
            <Modal :show="!!overrideItem" @close="overrideItem = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Line-Item Override — {{ overrideItem?.employee.full_name }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Manually override the unpaid leave days used in the deduction formula
                        <code class="rounded bg-gray-100 px-1 py-0.5 text-xs text-indigo-600">(Basic / Working Days) × Unpaid Days</code>.
                        Net pay recalculates instantly. Leave empty to use the ingested attendance value.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submitOverride">
                        <div>
                            <InputLabel for="unpaid_leave_days" value="Unpaid Leave Days" />
                            <input
                                id="unpaid_leave_days"
                                v-model="overrideForm.overrides.unpaid_leave_days"
                                type="number"
                                step="0.5"
                                min="0"
                                max="31"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="overrideForm.errors['overrides.unpaid_leave_days']" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">
                                Currently used: {{ overrideItem ? unpaidDaysOf(overrideItem) : 0 }} day(s)
                            </p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="overrideItem = null">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="overrideForm.processing">Recalculate & Save</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Approve Confirmation -->
            <Modal :show="showApprove" @close="showApprove = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Approve & Freeze Payroll {{ run.period }}</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        This locks every line item into an immutable JSON snapshot. Overrides will be disabled forever for this period, and the snapshot becomes ready for PDF distribution (Phase 6). Continue?
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showApprove = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="approveForm.processing" @click="submitApprove">Approve & Freeze</PrimaryButton>
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