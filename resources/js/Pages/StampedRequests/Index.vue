<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';

interface RequestItem {
    id: number;
    reason: string;
    reason_note: string | null;
    reason_label: string;
    status: 'pending' | 'approved' | 'rejected';
    reviewed_at: string | null;
    review_note: string | null;
    stamped_pdf_url: string | null;
    created_at: string;
    employee: { id: number; employee_code: string; full_name: string } | null;
    payslip: { id: number; period: string } | null;
    reviewer: { id: number; name: string } | null;
}

const props = withDefaults(
    defineProps<{
        requests: RequestItem[];
        pendingCount: number;
        success?: string;
    }>(),
    { success: undefined },
);

const rejecting = ref<RequestItem | null>(null);

const approveForm = useForm({});
const rejectForm = useForm({ review_note: '' });

const reference = (id: number) => 'REQ-' + String(id).padStart(5, '0');

const approve = (item: RequestItem) => {
    if (
        confirm(
            `Approve ${reference(item.id)} for ${item.employee?.full_name}? A watermarked stamped PDF will be queued for generation.`,
        )
    ) {
        approveForm.post(route('stamped-requests.approve', item.id), {
            preserveScroll: true,
        });
    }
};

const openReject = (item: RequestItem) => {
    rejecting.value = item;
    rejectForm.review_note = '';
    rejectForm.clearErrors();
};

const submitReject = () => {
    if (!rejecting.value) return;
    rejectForm.post(route('stamped-requests.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
        },
    });
};
</script>

<template>
    <Head title="Stamped Copy Requests" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Stamped Copy Requests</h1>
                    <p>Approve or reject official stamped payslip copies (Visa / Bank Loan purposes).</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700">
                    {{ pendingCount }} pending
                </span>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>

            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Request</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Payslip</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reviewed By</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="item in requests" :key="item.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ reference(item.id) }}</div>
                                <div class="text-xs text-gray-400">{{ new Date(item.created_at).toLocaleString() }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ item.employee?.full_name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ item.employee?.employee_code ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ item.payslip?.period ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ item.reason_label }}</div>
                                <div v-if="item.reason_note" class="text-xs text-gray-400">{{ item.reason_note }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                                    :class="{
                                        'bg-amber-50 text-amber-700': item.status === 'pending',
                                        'bg-emerald-50 text-emerald-700': item.status === 'approved',
                                        'bg-red-50 text-red-700': item.status === 'rejected',
                                    }"
                                >
                                    {{ item.status }}
                                </span>
                                <div v-if="item.status === 'rejected' && item.review_note" class="mt-1 text-xs text-red-600">
                                    {{ item.review_note }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-600">{{ item.reviewer?.name ?? '—' }}</div>
                                <div v-if="item.reviewed_at" class="text-xs text-gray-400">
                                    {{ new Date(item.reviewed_at).toLocaleString() }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <template v-if="item.status === 'pending'">
                                    <PrimaryButton :disabled="approveForm.processing" @click="approve(item)">Approve</PrimaryButton>
                                    <SecondaryButton class="ml-2" @click="openReject(item)">Reject</SecondaryButton>
                                </template>
                                <template v-else-if="item.status === 'approved'">
                                    <a
                                        v-if="item.stamped_pdf_url"
                                        :href="item.stamped_pdf_url"
                                        target="_blank"
                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        ↓ Stamped PDF
                                    </a>
                                    <span v-else class="text-xs text-gray-400">Generating…</span>
                                </template>
                                <span v-else class="text-xs text-gray-400">Closed</span>
                            </td>
                        </tr>
                        <tr v-if="!requests.length">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                No stamped copy requests yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Reject Modal -->
            <Modal :show="!!rejecting" @close="rejecting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Reject {{ rejecting ? reference(rejecting.id) : '' }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Provide a mandatory note explaining why this stamped copy request is being rejected.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submitReject">
                        <div>
                            <InputLabel for="review_note" value="Rejection Note" />
                            <textarea
                                id="review_note"
                                v-model="rejectForm.review_note"
                                rows="3"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <InputError :message="rejectForm.errors.review_note" class="mt-2" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="rejecting = null">Cancel</SecondaryButton>
                            <DangerButton :disabled="rejectForm.processing">Reject Request</DangerButton>
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
</style>