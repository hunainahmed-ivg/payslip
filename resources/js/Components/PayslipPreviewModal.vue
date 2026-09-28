<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps<{
    show: boolean;
    previewUrl: string | null;
    title?: string;
}>();

const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="flex h-[80vh] flex-col">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-medium text-gray-900">{{ title || 'Payslip Preview' }}</h2>
                <SecondaryButton type="button" @click="emit('close')">Close</SecondaryButton>
            </div>
            <div class="min-h-0 flex-1 bg-gray-100 p-3">
                <iframe
                    v-if="previewUrl"
                    :src="previewUrl"
                    class="h-full w-full rounded border border-gray-200 bg-white"
                    title="Payslip HTML preview"
                />
                <div v-else class="flex h-full items-center justify-center text-sm text-gray-500">
                    Preview is not available for this payslip yet.
                </div>
            </div>
        </div>
    </Modal>
</template>
