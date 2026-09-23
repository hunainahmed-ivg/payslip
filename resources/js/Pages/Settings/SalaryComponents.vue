<script setup lang="ts">
import { computed, ref } from 'vue';
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

interface SalaryComponent {
    id: number;
    name: string;
    slug: string;
    type: 'earning' | 'deduction';
    calculation_type: 'fixed' | 'percentage' | 'statutory';
    default_value: string;
    is_taxable: boolean;
    is_active: boolean;
    description: string | null;
}

interface Branch {
    id: number;
    code: string;
    name: string;
    location: string | null;
    currency_code: string;
    currency_symbol: string;
}

const props = withDefaults(
    defineProps<{
        components: SalaryComponent[];
        branches?: Branch[];
        success?: string;
    }>(),
    {
        branches: () => [],
        success: undefined,
    },
);

const showModal = ref(false);
const editing = ref<SalaryComponent | null>(null);
const deleting = ref<SalaryComponent | null>(null);
const filter = ref<'all' | 'earning' | 'deduction'>('all');

const filters: { key: 'all' | 'earning' | 'deduction'; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'earning', label: 'Earnings' },
    { key: 'deduction', label: 'Deductions' },
];

const form = useForm({
    name: '',
    type: 'earning' as string,
    calculation_type: 'fixed' as string,
    default_value: '0',
    is_taxable: true,
    is_active: true,
    description: '',
});

const deleteForm = useForm({});

const filtered = computed(() =>
    filter.value === 'all'
        ? props.components
        : props.components.filter((c) => c.type === filter.value),
);

const valueLabel = (component: SalaryComponent) => {
    if (component.calculation_type === 'percentage') return component.default_value + ' %';
    if (component.calculation_type === 'statutory') return 'Statutory formula';
    return component.default_value;
};

const openCreate = () => {
    editing.value = null;
    form.name = '';
    form.type = 'earning';
    form.calculation_type = 'fixed';
    form.default_value = '0';
    form.is_taxable = true;
    form.is_active = true;
    form.description = '';
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (component: SalaryComponent) => {
    editing.value = component;
    form.name = component.name;
    form.type = component.type;
    form.calculation_type = component.calculation_type;
    form.default_value = component.default_value;
    form.is_taxable = component.is_taxable;
    form.is_active = component.is_active;
    form.description = component.description ?? '';
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('settings.salary-components.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    } else {
        form.post(route('settings.salary-components.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('settings.salary-components.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
    });
};
</script>

<template>
    <Head title="Salary Components" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Master Salary Components</h1>
                    <p>Modular earnings & deduction rules used by the calculation engine.</p>
                </div>
                <PrimaryButton @click="openCreate">+ Add Component</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <!-- Branch currencies (ISO 4217) -->
            <div v-if="branches.length" class="card mb-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Branch Currencies (ISO 4217)</h2>
                    <span class="badge">{{ branches.length }} branches</span>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
                    >
                        <span class="text-lg">{{ branch.currency_symbol }}</span>
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ branch.name }}</div>
                            <div class="text-xs text-gray-500">{{ branch.code }} · {{ branch.currency_code }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="mb-4 flex gap-2">
                <button
                    v-for="f in filters"
                    :key="f.key"
                    class="rounded-md px-3 py-1.5 text-sm font-medium"
                    :class="filter === f.key ? 'bg-indigo-600 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    @click="filter = f.key"
                >
                    {{ f.label }}
                </button>
            </div>

            <!-- Components table -->
            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Component</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Calculation</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="component in filtered" :key="component.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ component.name }}</div>
                                <div class="text-xs text-gray-400">{{ component.slug }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="component.type === 'earning' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
                                >
                                    {{ component.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm capitalize text-gray-600">{{ component.calculation_type }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ valueLabel(component) }}</td>
                            <td class="px-6 py-4 text-sm" :class="component.is_taxable ? 'text-gray-900' : 'text-gray-400'">
                                {{ component.is_taxable ? 'Taxable' : 'Non-taxable' }}
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="component.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ component.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="openEdit(component)">Edit</button>
                                <button class="ml-3 text-sm font-medium text-red-600 hover:text-red-800" @click="deleting = component">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No components found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Create / Edit Modal -->
            <Modal :show="showModal" @close="showModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ editing ? 'Edit Component' : 'New Salary Component' }}
                    </h2>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" value="Component Name" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required placeholder="e.g. Overtime Allowance" />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="type" value="Type" />
                                <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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

                        <div v-if="form.calculation_type !== 'statutory'">
                            <InputLabel for="default_value" :value="form.calculation_type === 'percentage' ? 'Percentage (%)' : 'Fixed Amount'" />
                            <TextInput id="default_value" v-model="form.default_value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <InputError :message="form.errors.default_value" class="mt-2" />
                            <p v-if="form.calculation_type === 'percentage'" class="mt-1 text-xs text-gray-500">
                                Engine formula: (Basic Salary × {{ form.default_value }}) / 100
                            </p>
                        </div>

                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.is_taxable" />
                                Taxable
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.is_active" />
                                Active
                            </label>
                        </div>

                        <div>
                            <InputLabel for="description" value="Description" />
                            <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>

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
                    <h2 class="text-lg font-medium text-gray-900">Delete Component</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Are you sure you want to delete
                        <span class="font-semibold">{{ deleting?.name }}</span>?
                        This action cannot be undone.
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
.badge {
    font-size: 11px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #e0e7ff;
}
</style>