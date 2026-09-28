<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import PayslipPreviewModal from '@/Components/PayslipPreviewModal.vue';

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
    overrides: {
        unpaid_leave_days?: number | string;
        overtime_hours?: number | string;
        bonus_amount?: number | string;
    } | null;
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

interface PayslipRow {
    id: number;
    employee_id: number;
    status: string;
    pdf_path: string | null;
}

const props = withDefaults(
    defineProps<{
        run: Run;
        payslips?: Record<number, PayslipRow>;
        success?: string;
    }>(),
    { payslips: () => ({}), success: undefined },
);

const isDraft = computed(() => props.run.status === 'draft');
const expanded = ref<number[]>([]);
const showApprove = ref(false);
const savingId = ref<number | null>(null);
const previewUrl = ref<string | null>(null);
const previewTitle = ref('Payslip Preview');

const draftValues = reactive<Record<number, {
    unpaid_leave_days: string | number;
    overtime_hours: string | number;
    bonus_amount: string | number;
}>>({});

const unpaidDaysOf = (item: RunItem): number => {
    const line = item.deductions.find((d) => d.slug === 'unpaid_leave');
    return line ? Number(line.value) : 0;
};

const overtimeHoursOf = (item: RunItem): number => {
    const ot = item.earnings.find((e) => e.slug === 'overtime' || e.slug === 'overtime_pay');
    if (ot?.slug === 'overtime') return Number(ot.value);
    return Number(item.overrides?.overtime_hours ?? 0);
};

const bonusOf = (item: RunItem): number => {
    const line = item.earnings.find((e) => e.slug === 'bonus');
    return line ? Number(line.amount) : Number(item.overrides?.bonus_amount ?? 0);
};

const initDrafts = () => {
    for (const item of props.run.items) {
        draftValues[item.id] = {
            unpaid_leave_days: item.overrides?.unpaid_leave_days ?? unpaidDaysOf(item),
            overtime_hours: item.overrides?.overtime_hours ?? overtimeHoursOf(item),
            bonus_amount: item.overrides?.bonus_amount ?? bonusOf(item),
        };
    }
};
initDrafts();

const approveForm = useForm({});
const publishForm = useForm({});

const submitPublish = () => {
    if (confirm('Queue PDF generation and publishing for every employee in this run?')) {
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

const hasOverride = (item: RunItem) => !!item.overrides && Object.keys(item.overrides).length > 0;

const saveOverride = (item: RunItem) => {
    const values = draftValues[item.id];
    if (!values) return;

    savingId.value = item.id;
    router.put(
        route('payroll-runs.items.update', [props.run.id, item.id]),
        {
            overrides: {
                unpaid_leave_days: values.unpaid_leave_days,
                overtime_hours: values.overtime_hours,
                bonus_amount: values.bonus_amount,
            },
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
};

const submitApprove = () => {
    approveForm.post(route('payroll-runs.approve', props.run.id), {
        preserveScroll: true,
        onSuccess: () => {
            showApprove.value = false;
        },
    });
};

const payslipFor = (employeeId: number): PayslipRow | null => {
    return props.payslips?.[employeeId] ?? null;
};

const openPreview = (employeeId: number, name: string) => {
    const payslip = payslipFor(employeeId);
    if (!payslip) return;
    previewTitle.value = `Preview — ${name}`;
    previewUrl.value = route('payslips.preview', payslip.id);
};

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
                        <span v-if="run.approved_at"> · Approved &amp; frozen on {{ new Date(run.approved_at).toLocaleString() }}</span>
                    </p>
                </div>
                <PrimaryButton v-if="isDraft" @click="showApprove = true">Approve &amp; Freeze</PrimaryButton>
                <PrimaryButton v-else :disabled="publishForm.processing" @click="submitPublish">Publish Payslips</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>
            <div v-if="!isDraft" class="mb-4 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">
                This payroll is approved and frozen. Line items are locked. Publish when you are ready to generate PDFs and notify employees.
            </div>

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

            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gross</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deductions</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Net Pay</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unpaid Days</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">OT Hours</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Bonus</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <template v-for="item in run.items" :key="item.id">
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ item.employee.full_name }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ item.employee.employee_code }} · {{ item.employee.branch?.name ?? '—' }}
                                        <span v-if="hasOverride(item)" class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">Adjusted</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ money(item.gross_pay, item) }}</td>
                                <td class="px-4 py-3 text-sm text-red-600">− {{ money(item.total_deductions, item) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ money(item.net_pay, item) }}</td>
                                <td class="px-4 py-3">
                                    <input
                                        v-if="isDraft"
                                        v-model="draftValues[item.id].unpaid_leave_days"
                                        type="number"
                                        min="0"
                                        max="31"
                                        step="0.5"
                                        class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="saveOverride(item)"
                                    />
                                    <span v-else class="text-sm text-gray-600">{{ unpaidDaysOf(item) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        v-if="isDraft"
                                        v-model="draftValues[item.id].overtime_hours"
                                        type="number"
                                        min="0"
                                        step="0.5"
                                        class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="saveOverride(item)"
                                    />
                                    <span v-else class="text-sm text-gray-600">{{ overtimeHoursOf(item) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        v-if="isDraft"
                                        v-model="draftValues[item.id].bonus_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="saveOverride(item)"
                                    />
                                    <span v-else class="text-sm text-gray-600">{{ money(bonusOf(item), item) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span v-if="savingId === item.id" class="mr-2 text-xs text-gray-400">Saving…</span>
                                    <button class="text-sm font-medium text-gray-500 hover:text-gray-800" @click="toggleExpand(item.id)">
                                        {{ expanded.includes(item.id) ? 'Hide' : 'Details' }}
                                    </button>
                                    <button
                                        v-if="payslipFor(item.employee_id)"
                                        class="ml-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        @click="openPreview(item.employee_id, item.employee.full_name)"
                                    >
                                        Preview
                                    </button>
                                    <a
                                        v-if="payslipFor(item.employee_id)?.status === 'published'"
                                        :href="route('payslips.download', payslipFor(item.employee_id)!.id)"
                                        class="ml-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="expanded.includes(item.id)">
                                <td colspan="8" class="bg-gray-50 px-6 py-4">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <div>
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-700">Earnings</div>
                                            <div v-for="line in item.earnings" :key="line.title" class="flex justify-between py-1 text-sm">
                                                <span class="text-gray-600">{{ line.title }}</span>
                                                <span class="font-medium text-gray-900">{{ money(line.amount, item) }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-red-700">Deductions</div>
                                            <div v-for="line in item.deductions" :key="line.title" class="flex justify-between py-1 text-sm">
                                                <span class="text-gray-600">{{ line.title }}</span>
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

            <Modal :show="showApprove" @close="showApprove = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Approve &amp; Freeze Payroll {{ run.period }}</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        This locks every line item into an immutable snapshot. Overrides will be disabled for this period. You can publish PDFs afterward.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showApprove = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="approveForm.processing" @click="submitApprove">Approve &amp; Freeze</PrimaryButton>
                    </div>
                </div>
            </Modal>

            <PayslipPreviewModal
                :show="!!previewUrl"
                :preview-url="previewUrl"
                :title="previewTitle"
                @close="previewUrl = null"
            />
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
.card.p-0 { padding: 0; }
</style>
