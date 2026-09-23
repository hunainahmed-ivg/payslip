<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';

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

const props = withDefaults(
    defineProps<{ runs: RunSummary[]; success?: string }>(),
    { success: undefined },
);

const showCreate = ref(false);
const deleting = ref<RunSummary | null>(null);

const form = useForm({ period: '' });
const deleteForm = useForm({});

const openCreate = () => {
    form.period = '';
    form.clearErrors();
    showCreate.value = true;
};

const submit = () => {
    form.post(route('payroll-runs.store'));
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('payroll-runs.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
    });
};

const fmt = (v: string) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Payroll Runs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Payroll Runs</h1>
                    <p>Monthly batch execution — draft, review, override, approve & freeze.</p>
                </div>
                <PrimaryButton @click="openCreate">▶ Run Monthly Payroll Draft</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>

            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Total Net Pay</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Generated</th>
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
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ fmt(run.total_net_pay) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ run.generated_at ? new Date(run.generated_at).toLocaleString() : '—' }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('payroll-runs.show', run.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Review Grid
                                </Link>
                                <button
                                    v-if="run.status === 'draft'"
                                    class="ml-3 text-sm font-medium text-red-600 hover:text-red-800"
                                    @click="deleting = run"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!runs.length">
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                No payroll runs yet — click "Run Monthly Payroll Draft" to start.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Create Draft Modal -->
            <Modal :show="showCreate" @close="showCreate = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Run Monthly Payroll Draft</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        The engine will compute earnings, deductions and net pay for every active employee using master rules, contract overrides and ingested attendance data.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="period" value="Payroll Period" />
                            <input
                                id="period"
                                v-model="form.period"
                                type="month"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="form.errors.period" class="mt-2" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="showCreate = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="form.processing">Generate Draft</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Delete Confirmation -->
            <Modal :show="!!deleting" @close="deleting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Delete Draft Run</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Delete draft payroll for <span class="font-semibold">{{ deleting?.period }}</span>? All computed line items will be removed.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="deleting = null">Cancel</SecondaryButton>
                        <DangerButton :disabled="deleteForm.processing" @click="confirmDelete">Delete</DangerButton>
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