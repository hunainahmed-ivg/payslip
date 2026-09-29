<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import type { PermissionKey } from '@/constants/permissions';

interface CatalogItem {
    value: PermissionKey;
    label: string;
    group: string;
}

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    company_id: number | null;
    company_name: string | null;
    uses_custom_permissions: boolean;
    permissions: string[];
}

interface CompanyOption {
    id: number;
    company_name: string;
}

const props = defineProps<{
    users: ManagedUser[];
    companies: CompanyOption[];
    permissionCatalog: CatalogItem[];
    roles: Array<{ value: string; label: string }>;
    canAssignCompany: boolean;
    defaultCompanyId: number | null;
    success?: string;
    error?: string;
}>();

const showModal = ref(false);
const editing = ref<ManagedUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'admin',
    company_id: null as number | null,
    use_custom_permissions: false,
    permissions: [] as string[],
});

const deleteForm = useForm({});

const catalogByGroup = computed(() => {
    const map = new Map<string, CatalogItem[]>();
    for (const item of props.permissionCatalog) {
        if (!map.has(item.group)) map.set(item.group, []);
        map.get(item.group)!.push(item);
    }
    return [...map.entries()];
});

const applyRoleDefaults = () => {
    if (form.use_custom_permissions) return;
    form.permissions = props.permissionCatalog
        .map((p) => p.value)
        .filter((value) => {
            if (form.role !== 'admin') {
                return value === 'dashboard.view' || value === 'portal.payslips';
            }
            if (form.company_id) {
                return value !== 'companies.manage';
            }
            return true;
        });
};

watch(
    () => [form.role, form.company_id, form.use_custom_permissions],
    () => applyRoleDefaults(),
);

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.role = 'admin';
    form.company_id = props.canAssignCompany ? props.defaultCompanyId : props.companies[0]?.id ?? null;
    form.use_custom_permissions = false;
    applyRoleDefaults();
    showModal.value = true;
};

const openEdit = (user: ManagedUser) => {
    editing.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.password_confirmation = '';
    form.role = user.role;
    form.company_id = user.company_id;
    form.use_custom_permissions = user.uses_custom_permissions;
    form.permissions = [...user.permissions];
    form.clearErrors();
    showModal.value = true;
};

const togglePermission = (value: string, checked: boolean) => {
    if (checked) {
        if (!form.permissions.includes(value)) form.permissions.push(value);
    } else {
        form.permissions = form.permissions.filter((p) => p !== value);
    }
};

const submit = () => {
    if (editing.value) {
        form.put(route('settings.users.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post(route('settings.users.store'), {
            preserveScroll: true,
            onSuccess: () => (showModal.value = false),
        });
    }
};

const destroyUser = (user: ManagedUser) => {
    if (!confirm(`Remove ${user.name}?`)) return;
    deleteForm.delete(route('settings.users.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Users & Access" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1>Users & Access</h1>
                    <p>Create accounts, assign roles, and pick page permissions per user.</p>
                </div>
                <PrimaryButton type="button" @click="openCreate">Add user</PrimaryButton>
            </div>
        </template>

        <div v-if="success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ success }}
        </div>
        <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ error }}
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Company</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Access</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ user.name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ user.email }}</td>
                        <td class="px-4 py-3">{{ user.role_label }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ user.company_name ?? 'Platform (all companies)' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ user.uses_custom_permissions ? 'Custom' : 'Role default' }}
                            · {{ user.permissions.length }} pages
                        </td>
                        <td class="px-4 py-3 text-right">
                            <SecondaryButton type="button" class="mr-2" @click="openEdit(user)">Edit</SecondaryButton>
                            <DangerButton type="button" @click="destroyUser(user)">Delete</DangerButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ editing ? 'Edit user' : 'New user' }}
                </h2>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="password" :value="editing ? 'New password (optional)' : 'Password'" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" :required="!editing" />
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Confirm password" />
                            <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="role" value="Role" />
                            <select id="role" v-model="form.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                        </div>
                        <div v-if="canAssignCompany">
                            <InputLabel for="company_id" value="Company (tenant)" />
                            <select id="company_id" v-model="form.company_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">Platform operator (all companies)</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Leave blank only for a global platform admin.</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <Checkbox
                            :checked="form.use_custom_permissions"
                            @update:checked="(v: boolean) => (form.use_custom_permissions = v)"
                        />
                        <span class="text-sm text-gray-700">Custom page permissions (override role defaults)</span>
                    </label>

                    <div v-if="form.use_custom_permissions" class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 p-3">
                        <div v-for="[group, items] in catalogByGroup" :key="group" class="mb-4 last:mb-0">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ group }}</div>
                            <label
                                v-for="item in items"
                                :key="item.value"
                                class="mb-1 flex items-center gap-2 text-sm text-gray-700"
                            >
                                <Checkbox
                                    :checked="form.permissions.includes(item.value)"
                                    @update:checked="(v: boolean) => togglePermission(item.value, v)"
                                />
                                {{ item.label }}
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="form.processing">Save</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
