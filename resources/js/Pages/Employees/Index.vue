<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import EmployeeBulkImportModal from '@/Components/EmployeeBulkImportModal.vue';

interface Branch {
    id: number;
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
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
    branch: Branch | null;
}

const props = withDefaults(
    defineProps<{
        employees: Employee[];
        branches: Branch[];
        success?: string;
    }>(),
    { success: undefined },
);

const showModal = ref(false);
const editing = ref<Employee | null>(null);
const deleting = ref<Employee | null>(null);
const search = ref('');

const form = useForm({
    employee_code: '',
    full_name: '',
    email: '',
    department: '',
    designation: '',
    branch_id: '' as string,
    currency_code: '',
    base_salary: '0',
    joined_on: '',
    is_active: true,
});

const deleteForm = useForm({});

const filtered = computed(() => {
    const term = search.value.toLowerCase();
    if (!term) return props.employees;
    return props.employees.filter(
        (e) =>
            e.full_name.toLowerCase().includes(term) ||
            e.employee_code.toLowerCase().includes(term) ||
            (e.department ?? '').toLowerCase().includes(term),
    );
});

const money = (employee: Employee) => {
    const symbol = employee.branch?.currency_symbol ?? employee.currency_code;
    return (
        symbol +
        ' ' +
        Number(employee.base_salary).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
};

// Branch-aware currency auto-fill (ISO 4217 from branch, manual override allowed)
const onBranchChange = () => {
    const branch = props.branches.find((b) => String(b.id) === String(form.branch_id));
    if (branch) {
        form.currency_code = branch.currency_code;
    }
};

const openCreate = () => {
    editing.value = null;
    form.employee_code = '';
    form.full_name = '';
    form.email = '';
    form.department = '';
    form.designation = '';
    form.branch_id = '';
    form.currency_code = '';
    form.base_salary = '0';
    form.joined_on = '';
    form.is_active = true;
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (employee: Employee) => {
    editing.value = employee;
    form.employee_code = employee.employee_code;
    form.full_name = employee.full_name;
    form.email = employee.email ?? '';
    form.department = employee.department ?? '';
    form.designation = employee.designation ?? '';
    form.branch_id = String(employee.branch_id);
    form.currency_code = employee.currency_code;
    form.base_salary = employee.base_salary;
    form.joined_on = employee.joined_on ? employee.joined_on.slice(0, 10) : '';
    form.is_active = employee.is_active;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('employees.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    } else {
        form.post(route('employees.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('employees.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
    });
};

const showBulkImport = ref(false);

</script>

<template>
    <Head title="Employees" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Employees</h1>
                    <p>Contract values, branch assignment & currency mapping per employee.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        @click="showBulkImport = true"
                    >
                        Bulk Import Employees
                    </button>

                    <PrimaryButton @click="openCreate">
                        + Add Employee
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <!-- Search -->
            <div class="mb-4">
                <TextInput v-model="search" class="w-full max-w-sm" placeholder="Search by name, code or department..." />
            </div>

            <!-- Employees table -->
            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Branch</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Base Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="employee in filtered" :key="employee.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ employee.full_name }}</div>
                                <div class="text-xs text-gray-400">{{ employee.employee_code }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ employee.department ?? '—' }}
                                <div class="text-xs text-gray-400">{{ employee.designation ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ employee.branch?.name ?? '—' }}
                                <div class="text-xs text-gray-400">{{ employee.currency_code }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ money(employee) }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="employee.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ employee.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="route('employees.show', employee.id)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    View
                                </Link>
                                <button class="ml-3 text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="openEdit(employee)">Edit</button>
                                <button class="ml-3 text-sm font-medium text-red-600 hover:text-red-800" @click="deleting = employee">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No employees found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Create / Edit Modal -->
            <Modal :show="showModal" @close="showModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ editing ? 'Edit Employee' : 'New Employee' }}
                    </h2>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="employee_code" value="Employee Code" />
                                <TextInput id="employee_code" v-model="form.employee_code" class="mt-1 block w-full" required placeholder="e.g. EMP-8042" />
                                <InputError :message="form.errors.employee_code" class="mt-2" />
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

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="department" value="Department" />
                                <TextInput id="department" v-model="form.department" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel for="designation" value="Designation" />
                                <TextInput id="designation" v-model="form.designation" class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="branch_id" value="Branch" />
                                <select
                                    id="branch_id"
                                    v-model="form.branch_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                    @change="onBranchChange"
                                >
                                    <option value="" disabled>Select branch</option>
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
                                <p class="mt-1 text-xs text-gray-500">Auto-filled from branch, editable override.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="base_salary" value="Base Salary" />
                                <TextInput id="base_salary" v-model="form.base_salary" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.base_salary" class="mt-2" />
                            </div>
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
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="form.is_active" />
                            Active
                        </label>

                        <div class="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                            <PrimaryButton :disabled="form.processing">
                                {{ editing ? 'Update' : 'Create' }}
                            </PrimaryButton>

                            
                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Delete Confirmation Modal -->
            <Modal :show="!!deleting" @close="deleting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Delete Employee</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Are you sure you want to delete
                        <span class="font-semibold">{{ deleting?.full_name }}</span>
                        ({{ deleting?.employee_code }})? Their salary mappings will also be removed.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="deleting = null">Cancel</SecondaryButton>
                        <DangerButton :disabled="deleteForm.processing" @click="confirmDelete">Delete</DangerButton>
                    </div>
                </div>
            </Modal>
        </div>
        <EmployeeBulkImportModal
            :show="showBulkImport"
            @close="showBulkImport = false"
            @imported="router.reload()"
        />
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