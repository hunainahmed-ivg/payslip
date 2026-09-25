<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';

interface Payslip {
    id: number;
    period: string;
    currency_code: string;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    published_at: string;
    pdf_url: string | null;
    pdf_download_url?: string | null;
}

interface StampedRequest {
    id: number;
    payslip_id: number;
    reason: string;
    reason_note: string | null;
    reason_label: string;
    status: 'pending' | 'approved' | 'rejected';
    reviewed_at: string | null;
    review_note: string | null;
    stamped_pdf_url: string | null;
    stamped_pdf_download_url?: string | null;
    created_at: string;
    payslip: { id: number; period: string } | null;
}

const props = defineProps<{
    employee: { full_name: string; employee_code: string; department: string } | null;
    payslips: Payslip[];
    requests: StampedRequest[];
    success?: string;
    error?: string;
}>();

const requesting = ref<Payslip | null>(null);

const requestForm = useForm({
    payslip_id: null as number | null,
    reason: 'visa_application',
    reason_note: '',
});

// Payslips jinki pending request already hai — button disable
const pendingPayslipIds = computed(() =>
    props.requests.filter((r) => r.status === 'pending').map((r) => r.payslip_id),
);

const reference = (id: number) => 'REQ-' + String(id).padStart(5, '0');

const fmt = (val: number, currency: string) => {
    return (
        currency +
        ' ' +
        Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
};

const formatDate = (period: string) => {
    const [year, month] = period.split('-');
    const date = new Date(parseInt(year), parseInt(month) - 1);
    return date.toLocaleString('default', { month: 'long', year: 'numeric' });
};

const openRequest = (payslip: Payslip) => {
    requesting.value = payslip;
    requestForm.payslip_id = payslip.id;
    requestForm.reason = 'visa_application';
    requestForm.reason_note = '';
    requestForm.clearErrors();
};

const submitRequest = () => {
    requestForm.post(route('portal.stamped-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            requesting.value = null;
        },
    });
};
</script>

<template>
    <Head title="My Payslips" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>My Payslips</h1>
                <p v-if="employee">Welcome back, {{ employee.full_name }} ({{ employee.employee_code }})</p>
                <p v-else>Employee Portal</p>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>
            <div v-if="error" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>

            <div v-if="!employee" class="card bg-amber-50 text-amber-800" style="border-color: #fde68a;">
                <p class="font-semibold">Admin / HR View</p>
                <p class="mt-1 text-sm">
                    Your login is not linked to an employee profile. Please log in as an employee (e.g., bilal.ahmed@northwind.com) to view payslips.
                </p>
            </div>

            <template v-else>
                <!-- Payslip cards -->
                <div v-if="payslips.length === 0" class="card py-12 text-center">
                    <p class="text-gray-500">No payslips have been published for you yet.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="payslip in payslips" :key="payslip.id" class="card flex flex-col justify-between">
                        <div>
                            <div class="mb-4 flex items-center justify-between">
                                <span class="badge">Payslip</span>
                                <span class="text-xs text-gray-500">{{ formatDate(payslip.period) }}</span>
                            </div>

                            <div class="mb-6">
                                <div class="mb-1 text-sm text-gray-500">Net Pay</div>
                                <div class="text-2xl font-bold text-emerald-600">
                                    {{ fmt(payslip.net_pay, payslip.currency_code) }}
                                </div>
                            </div>

                            <div class="space-y-2 border-t border-gray-100 pt-4 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Gross Earnings</span>
                                    <span class="font-medium text-gray-900">{{ fmt(payslip.gross_pay, payslip.currency_code) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total Deductions</span>
                                    <span class="font-medium text-red-600">- {{ fmt(payslip.total_deductions, payslip.currency_code) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2">
                            <div class="mt-6 space-y-2">
                                <div class="flex gap-2">
                                    <a
                                        v-if="payslip.pdf_url"
                                        :href="payslip.pdf_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                                    >
                                        👁 View Payslip
                                    </a>

                                    <a
                                        v-if="payslip.pdf_download_url"
                                        :href="payslip.pdf_download_url"
                                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                                    >
                                        ⬇ Download PDF
                                    </a>
                                </div>

                                <button
                                    v-if="pendingPayslipIds.includes(payslip.id)"
                                    disabled
                                    class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-700"
                                >
                                    ⏳ Stamped Request Pending…
                                </button>
                                <button
                                    v-else
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50"
                                    @click="openRequest(payslip)"
                                >
                                    🖋 Request Official Stamped Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Request History -->
                <div class="card mt-8 overflow-hidden p-0">
                    <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Stamped Copy Request History</h2>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="req in requests" :key="req.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ reference(req.id) }}</div>
                                    <div class="text-xs text-gray-400">{{ new Date(req.created_at).toLocaleString() }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ req.payslip?.period ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ req.reason_label }}</div>
                                    <div v-if="req.reason_note" class="text-xs text-gray-400">{{ req.reason_note }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                                        :class="{
                                            'bg-amber-50 text-amber-700': req.status === 'pending',
                                            'bg-emerald-50 text-emerald-700': req.status === 'approved',
                                            'bg-red-50 text-red-700': req.status === 'rejected',
                                        }"
                                    >
                                        {{ req.status }}
                                    </span>
                                    <div v-if="req.status === 'rejected' && req.review_note" class="mt-1 max-w-[200px] text-xs text-red-600">
                                        {{ req.review_note }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div
                                        v-if="req.status === 'approved' && (req.stamped_pdf_url || req.stamped_pdf_download_url)"
                                        class="flex justify-end gap-2"
                                    >
                                        <a
                                            v-if="req.stamped_pdf_url"
                                            :href="req.stamped_pdf_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                        >
                                            View
                                        </a>

                                        <a
                                            v-if="req.stamped_pdf_download_url"
                                            :href="req.stamped_pdf_download_url"
                                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                                        >
                                            Download
                                        </a>
                                    </div>

                                    <span v-else-if="req.status === 'approved'" class="text-xs text-gray-400">
                                        Generating…
                                    </span>

                                    <span v-else class="text-xs text-gray-400">
                                        Awaiting HR review
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!requests.length">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No stamped copy requests yet. Use "Request Official Stamped Copy" on any payslip above.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Request Modal -->
            <Modal :show="!!requesting" @close="requesting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Request Official Stamped Copy</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Payslip <span class="font-semibold">{{ requesting ? formatDate(requesting.period) : '' }}</span> —
                        HR will review your request and apply an official digital watermark/stamp to the frozen snapshot.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submitRequest">
                        <div>
                            <InputLabel for="reason" value="Purpose" />
                            <select
                                id="reason"
                                v-model="requestForm.reason"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="visa_application">Visa Application</option>
                                <option value="bank_loan">Bank Loan</option>
                                <option value="embassy">Embassy / Consulate</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError :message="requestForm.errors.reason" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="reason_note" value="Additional Note (optional)" />
                            <TextInput
                                id="reason_note"
                                v-model="requestForm.reason_note"
                                class="mt-1 block w-full"
                                placeholder="e.g. Schengen visa application — German Embassy"
                            />
                            <InputError :message="requestForm.errors.reason_note" class="mt-2" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="requesting = null">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="requestForm.processing">Submit Request</PrimaryButton>
                        </div>
                    </form>
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
.badge {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
</style>