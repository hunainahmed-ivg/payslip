<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmployeeModuleNav from '@/Components/EmployeeModuleNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Permission } from '@/constants/permissions';
import { usePermissions } from '@/composables/usePermissions';

interface Branch {
    id: number;
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
}

interface DocumentType {
    id: number;
    title: string;
    type: string;
    required: boolean;
    allow_front_back: boolean;
    profile_pic_required: boolean;
}

interface EmployeeDocument {
    id: number;
    side: string | null;
    original_filename: string | null;
    document_type?: DocumentType | null;
}

interface Employee {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    department: string | null;
    designation: string | null;
    branch_id: number;
    currency_code: string;
    base_salary: string;
    joined_on: string | null;
    is_active: boolean;
    profile_picture_path: string | null;
    branch: Branch | null;
    documents: EmployeeDocument[];
}

const props = withDefaults(
    defineProps<{
        employee: Employee;
        branches: Branch[];
        document_types?: DocumentType[];
        profile_pic_required?: boolean;
        canManage?: boolean;
        canViewSalaryStructure?: boolean;
        success?: string;
    }>(),
    {
        document_types: () => [],
        profile_pic_required: false,
        canManage: false,
        canViewSalaryStructure: false,
        success: undefined,
    },
);

const { can } = usePermissions();
const canBrowseEmployees = computed(() => can(Permission.EmployeesManage));

const profilePicture = ref<File | null>(null);
const documentFiles = ref<Record<string, { single?: File | null; front?: File | null; back?: File | null }>>({});

const form = useForm({
    employee_code: props.employee.employee_code,
    full_name: props.employee.full_name,
    email: props.employee.email ?? '',
    department: props.employee.department ?? '',
    designation: props.employee.designation ?? '',
    branch_id: String(props.employee.branch_id),
    currency_code: props.employee.currency_code,
    joined_on: props.employee.joined_on ? props.employee.joined_on.slice(0, 10) : '',
    is_active: props.employee.is_active,
    profile_picture: null as File | null,
    documents: {} as Record<string, { single?: File | null; front?: File | null; back?: File | null }>,
});

const money = computed(() => {
    const symbol = props.employee.branch?.currency_symbol ?? props.employee.currency_code;
    return (
        symbol +
        ' ' +
        Number(props.employee.base_salary).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
});

const detailRows = computed(() => [
    { label: 'Employee Code', value: props.employee.employee_code },
    { label: 'Full Name', value: props.employee.full_name },
    { label: 'Email', value: props.employee.email || '—' },
    { label: 'Department', value: props.employee.department || '—' },
    { label: 'Designation', value: props.employee.designation || '—' },
    { label: 'Branch', value: props.employee.branch?.name || '—' },
    { label: 'Currency', value: props.employee.currency_code },
    {
        label: 'Joined On',
        value: props.employee.joined_on ? props.employee.joined_on.slice(0, 10) : '—',
    },
    { label: 'Status', value: props.employee.is_active ? 'Active' : 'Inactive' },
]);

const onBranchChange = () => {
    const branch = props.branches.find((b) => String(b.id) === String(form.branch_id));
    if (branch) {
        form.currency_code = branch.currency_code;
    }
};

const onProfilePictureChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    profilePicture.value = input.files?.[0] ?? null;
};

const onDocumentFileChange = (typeId: number, side: 'single' | 'front' | 'back', event: Event) => {
    const input = event.target as HTMLInputElement;
    const key = String(typeId);
    if (!documentFiles.value[key]) {
        documentFiles.value[key] = {};
    }
    documentFiles.value[key][side] = input.files?.[0] ?? null;
};

const submit = () => {
    if (!props.canManage) return;

    form.profile_picture = profilePicture.value;
    form.documents = documentFiles.value;

    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('employees.update', props.employee.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.transform((data) => data);
                profilePicture.value = null;
                documentFiles.value = {};
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
                    <Link
                        v-if="canBrowseEmployees"
                        :href="route('employees.index')"
                        class="text-sm text-indigo-600 hover:underline"
                    >
                        ← Back to Employees
                    </Link>
                    <p v-else class="text-sm text-gray-500">My profile</p>
                    <h1 class="mt-1">
                        {{ employee.full_name }}
                        <span class="font-normal text-gray-400">· {{ employee.employee_code }}</span>
                    </h1>
                    <p>
                        {{ employee.designation ?? '—' }} · {{ employee.department ?? '—' }} · {{ employee.branch?.name }}
                        ({{ employee.currency_code }})
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="employee.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                >
                    {{ employee.is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </template>

        <div class="py-6">
            <EmployeeModuleNav
                :employee-id="employee.id"
                active="details"
                :can-view-salary-structure="canViewSalaryStructure"
            />

            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div v-if="canManage" class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Base Salary</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ money }}</div>
                    <p class="mt-1 text-xs text-amber-600">Changes go through Salary Increments.</p>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Branch</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ employee.branch?.name ?? '—' }}</div>
                    <p class="mt-1 text-xs text-gray-500">{{ employee.currency_code }}</p>
                </div>
                <div class="card">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Joined</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        {{ employee.joined_on ? employee.joined_on.slice(0, 10) : '—' }}
                    </div>
                </div>
            </div>

            <!-- Employee self-view: read-only profile -->
            <div v-if="!canManage" class="card space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Profile details</h2>
                    <p class="mt-1 text-sm text-gray-500">Your employment profile. Contact HR to request changes.</p>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="row in detailRows" :key="row.label" class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">{{ row.label }}</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ row.value }}</dd>
                    </div>
                </dl>

                <div v-if="employee.documents?.length" class="rounded-lg border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Your documents</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li
                            v-for="doc in employee.documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-2 text-sm"
                        >
                            <div>
                                <div class="font-medium text-gray-800">
                                    {{ doc.document_type?.title ?? 'Document' }}
                                    <span v-if="doc.side" class="font-normal text-gray-400">({{ doc.side }})</span>
                                </div>
                                <div class="text-xs text-gray-400">{{ doc.original_filename }}</div>
                            </div>
                            <a
                                :href="route('employees.documents.download', [employee.id, doc.id])"
                                class="font-medium text-indigo-600 hover:text-indigo-800"
                            >
                                Download
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- HR/admin: editable form -->
            <form v-else class="card space-y-5" @submit.prevent="submit">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Employee details</h2>
                    <p class="mt-1 text-sm text-gray-500">Update profile fields for this employee.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="employee_code" value="Employee Code" />
                        <TextInput
                            id="employee_code"
                            v-model="form.employee_code"
                            class="mt-1 block w-full"
                            readonly
                        />
                        <p class="mt-1 text-xs text-gray-500">Assigned by the system and cannot be changed.</p>
                    </div>
                    <div>
                        <InputLabel for="full_name" value="Full Name" />
                        <TextInput id="full_name" v-model="form.full_name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.full_name" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="email" value="Email (optional)" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="department" value="Department" />
                        <TextInput id="department" v-model="form.department" class="mt-1 block w-full" />
                        <InputError :message="form.errors.department" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="designation" value="Designation" />
                        <TextInput id="designation" v-model="form.designation" class="mt-1 block w-full" />
                        <InputError :message="form.errors.designation" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="branch_id" value="Branch" />
                        <select
                            id="branch_id"
                            v-model="form.branch_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                            @change="onBranchChange"
                        >
                            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                                {{ branch.name }} ({{ branch.currency_code }})
                            </option>
                        </select>
                        <InputError :message="form.errors.branch_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="currency_code" value="Currency (ISO 4217)" />
                        <TextInput id="currency_code" v-model="form.currency_code" class="mt-1 block w-full" required :maxlength="3" />
                        <InputError :message="form.errors.currency_code" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="joined_on" value="Joined On" />
                        <input
                            id="joined_on"
                            v-model="form.joined_on"
                            type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError :message="form.errors.joined_on" class="mt-2" />
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="form.is_active" />
                            Active
                        </label>
                    </div>
                </div>

                <div v-if="profile_pic_required || document_types.length" class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Documents</h3>

                    <div>
                        <InputLabel for="profile_picture" :value="profile_pic_required ? 'Profile picture' : 'Profile picture (optional)'" />
                        <input
                            id="profile_picture"
                            type="file"
                            accept="image/*"
                            class="mt-1 block w-full text-sm"
                            @change="onProfilePictureChange"
                        />
                        <InputError :message="form.errors.profile_picture" class="mt-2" />
                    </div>

                    <div v-for="doc in document_types" :key="doc.id" class="rounded-md border border-gray-200 bg-white p-3">
                        <div class="mb-2 text-sm font-medium text-gray-800">
                            {{ doc.title }}
                            <span v-if="doc.required" class="text-xs text-amber-600">(required)</span>
                        </div>
                        <div v-if="doc.allow_front_back" class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Front" />
                                <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm" @change="onDocumentFileChange(doc.id, 'front', $event)" />
                                <InputError :message="form.errors[`documents.${doc.id}.front`]" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel value="Back" />
                                <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm" @change="onDocumentFileChange(doc.id, 'back', $event)" />
                                <InputError :message="form.errors[`documents.${doc.id}.back`]" class="mt-2" />
                            </div>
                        </div>
                        <div v-else>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm" @change="onDocumentFileChange(doc.id, 'single', $event)" />
                            <InputError :message="form.errors[`documents.${doc.id}.single`]" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div v-if="employee.documents?.length" class="rounded-lg border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Uploaded documents</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li
                            v-for="doc in employee.documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-3 py-2 text-sm"
                        >
                            <div>
                                <div class="font-medium text-gray-800">
                                    {{ doc.document_type?.title ?? 'Document' }}
                                    <span v-if="doc.side" class="font-normal text-gray-400">({{ doc.side }})</span>
                                </div>
                                <div class="text-xs text-gray-400">{{ doc.original_filename }}</div>
                            </div>
                            <a
                                :href="route('employees.documents.download', [employee.id, doc.id])"
                                class="font-medium text-indigo-600 hover:text-indigo-800"
                            >
                                Download
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <SecondaryButton type="button" @click="form.reset()">Reset</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save details</PrimaryButton>
                </div>
            </form>
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
</style>
