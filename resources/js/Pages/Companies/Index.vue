<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import Checkbox from '@/Components/Checkbox.vue';

interface CompanyRow {
    id: number;
    company_name: string;
    tax_id: string | null;
    registration_number: string | null;
    address: string | null;
    template_type: string;
    is_active: boolean;
    branches_count: number;
    employees_count: number;
    is_current: boolean;
}

const props = defineProps<{
    companies: CompanyRow[];
    currentCompanyId: number | null;
    success?: string;
    error?: string;
}>();

const showCreate = ref(false);
const editing = ref<CompanyRow | null>(null);
const deleting = ref<CompanyRow | null>(null);

const createForm = useForm({
    company_name: '',
    tax_id: '',
    registration_number: '',
    address: '',
    create_default_branch: true,
    copy_salary_components: true,
});

const editForm = useForm({
    company_name: '',
    tax_id: '',
    registration_number: '',
    address: '',
    is_active: true as boolean,
});

const deleteForm = useForm({});

const openCreate = () => {
    createForm.reset();
    createForm.create_default_branch = true;
    createForm.copy_salary_components = true;
    createForm.clearErrors();
    showCreate.value = true;
};

const submitCreate = () => {
    createForm.post(route('companies.store'), {
        onSuccess: () => {
            showCreate.value = false;
        },
    });
};

const openEdit = (company: CompanyRow) => {
    editing.value = company;
    editForm.company_name = company.company_name;
    editForm.tax_id = company.tax_id || '';
    editForm.registration_number = company.registration_number || '';
    editForm.address = company.address || '';
    editForm.is_active = company.is_active;
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.put(route('companies.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const switchTo = (company: CompanyRow) => {
    router.post(route('companies.switch', company.id), {}, { preserveScroll: true });
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('companies.destroy', deleting.value.id), {
        onSuccess: () => {
            deleting.value = null;
        },
    });
};
</script>

<template>
    <Head title="Companies" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Companies</h1>
                    <p>Create and switch between companies. Payroll, employees, and branding are scoped to the active company.</p>
                </div>
                <PrimaryButton @click="openCreate">Create Company</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>
            <div v-if="error" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>

            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Branches</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="company in companies" :key="company.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ company.company_name }}
                                    <span
                                        v-if="company.is_current"
                                        class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-indigo-700"
                                    >
                                        Active
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400">{{ company.tax_id || 'No tax ID' }} · {{ company.template_type }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ company.branches_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ company.employees_count }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                                    :class="company.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ company.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button
                                    v-if="!company.is_current && company.is_active"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    @click="switchTo(company)"
                                >
                                    Switch
                                </button>
                                <button class="ml-3 text-sm font-medium text-gray-600 hover:text-gray-900" @click="openEdit(company)">
                                    Edit
                                </button>
                                <button
                                    class="ml-3 text-sm font-medium text-red-600 hover:text-red-800"
                                    @click="deleting = company"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!companies.length">
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                No companies yet. Create your first company to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal :show="showCreate" @close="showCreate = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Create Company</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        A new company gets its own branding, branches, employees, and payroll runs.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submitCreate">
                        <div>
                            <InputLabel for="company_name" value="Company Name" />
                            <TextInput id="company_name" v-model="createForm.company_name" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.company_name" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel for="tax_id" value="Tax ID" />
                                <TextInput id="tax_id" v-model="createForm.tax_id" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="registration_number" value="Registration Number" />
                                <TextInput id="registration_number" v-model="createForm.registration_number" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="address" value="Address" />
                            <TextInput id="address" v-model="createForm.address" class="mt-1 block w-full" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="createForm.create_default_branch" />
                            Create default Head Office branch
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="createForm.copy_salary_components" />
                            Copy salary components from the current company
                        </label>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="showCreate = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="createForm.processing">Create Company</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <Modal :show="!!editing" @close="editing = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Edit Company</h2>
                    <form class="mt-6 space-y-4" @submit.prevent="submitEdit">
                        <div>
                            <InputLabel value="Company Name" />
                            <TextInput v-model="editForm.company_name" class="mt-1 block w-full" required />
                            <InputError :message="editForm.errors.company_name" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel value="Tax ID" />
                                <TextInput v-model="editForm.tax_id" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Registration Number" />
                                <TextInput v-model="editForm.registration_number" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Address" />
                            <TextInput v-model="editForm.address" class="mt-1 block w-full" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="editForm.is_active" />
                            Company is active
                        </label>
                        <div class="flex justify-end gap-3">
                            <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="editForm.processing">Save Changes</PrimaryButton>
                        </div>
                    </form>
                </div>
            </Modal>

            <Modal :show="!!deleting" @close="deleting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Delete Company</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Delete <strong>{{ deleting?.company_name }}</strong>? Branches, salary components, and payroll runs for this company will also be removed.
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
.card.p-0 { padding: 0; }
</style>
