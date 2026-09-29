<script setup lang="ts">
import { ref } from 'vue';
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

interface DocumentType {
    id: number;
    title: string;
    type: string;
    required: boolean;
    allow_front_back: boolean;
    profile_pic_required: boolean;
    is_active: boolean;
    sort_order: number;
}

const props = withDefaults(
    defineProps<{
        document_types: DocumentType[];
        success?: string;
    }>(),
    { success: undefined },
);

const showModal = ref(false);
const editing = ref<DocumentType | null>(null);
const deleting = ref<DocumentType | null>(null);

const form = useForm({
    title: '',
    type: '',
    required: false,
    allow_front_back: false,
    profile_pic_required: false,
    is_active: true,
    sort_order: '0',
});

const deleteForm = useForm({});

const openCreate = () => {
    editing.value = null;
    form.title = '';
    form.type = '';
    form.required = false;
    form.allow_front_back = false;
    form.profile_pic_required = false;
    form.is_active = true;
    form.sort_order = '0';
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (doc: DocumentType) => {
    editing.value = doc;
    form.title = doc.title;
    form.type = doc.type;
    form.required = doc.required;
    form.allow_front_back = doc.allow_front_back;
    form.profile_pic_required = doc.profile_pic_required;
    form.is_active = doc.is_active;
    form.sort_order = String(doc.sort_order);
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('settings.registration-documents.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    } else {
        form.post(route('settings.registration-documents.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const confirmDelete = () => {
    if (!deleting.value) return;
    deleteForm.delete(route('settings.registration-documents.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
    });
};
</script>

<template>
    <Head title="Registration Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Registration Documents</h1>
                    <p>Configure required identity documents collected during employee registration.</p>
                </div>
                <PrimaryButton @click="openCreate">+ Add Document Type</PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <div class="card overflow-hidden p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Document</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Flags</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="doc in document_types" :key="doc.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ doc.title }}</div>
                                <div class="text-xs text-gray-400">Order {{ doc.sort_order }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ doc.type }}</td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                <span v-if="doc.required" class="mr-2 rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Required</span>
                                <span v-if="doc.allow_front_back" class="mr-2 rounded-full bg-sky-50 px-2 py-0.5 text-sky-700">Front/Back</span>
                                <span v-if="doc.profile_pic_required" class="rounded-full bg-violet-50 px-2 py-0.5 text-violet-700">Profile pic</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="doc.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ doc.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="openEdit(doc)">Edit</button>
                                <button class="ml-3 text-sm font-medium text-red-600 hover:text-red-800" @click="deleting = doc">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!document_types.length">
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No document types configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal :show="showModal" @close="showModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ editing ? 'Edit Document Type' : 'New Document Type' }}
                    </h2>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="title" value="Title" />
                            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required placeholder="e.g. CNIC" />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="type" value="Type slug" />
                            <TextInput id="type" v-model="form.type" class="mt-1 block w-full" required placeholder="e.g. cnic" :disabled="!!editing" />
                            <InputError :message="form.errors.type" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="sort_order" value="Sort order" />
                            <TextInput id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-full" />
                            <InputError :message="form.errors.sort_order" class="mt-2" />
                        </div>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.required" />
                                Required
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.allow_front_back" />
                                Allow front/back
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.profile_pic_required" />
                                Profile pic required
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <Checkbox v-model:checked="form.is_active" />
                                Active
                            </label>
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

            <Modal :show="!!deleting" @close="deleting = null">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Delete Document Type</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Delete <span class="font-semibold">{{ deleting?.title }}</span>? Existing employee uploads for this type will also be removed.
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
