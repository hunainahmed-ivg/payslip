<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    profile: {
        company_name: string;
        template_type: 'modern' | 'classic' | 'compact' | 'custom';
        custom_html: string | null;
        primary_color: string;
        accent_color: string;
        font_family: string;
        page_margin: string;
        header_image_url: string | null;
        footer_image_url: string | null;
    };
    success?: string;
}>();

const templates = [
    { key: 'modern' as const, name: 'Modern', desc: 'Accent letterhead bar with generous whitespace. Recommended default.' },
    { key: 'classic' as const, name: 'Classic', desc: 'Traditional centered letterhead with formal presentation.' },
    { key: 'compact' as const, name: 'Compact', desc: 'Minimal header for dense, multi-page statements.' },
];

const activeTemplate = computed(
    () => templates.find((t) => t.key === props.profile.template_type) ?? null,
);

const activateForm = useForm({
    template_type: props.profile.template_type === 'custom' ? 'modern' : props.profile.template_type,
});

const activate = (key: 'modern' | 'classic' | 'compact') => {
    activateForm.template_type = key;
    activateForm.post(route('settings.payslip-templates.activate'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Payslip Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Payslip Templates</h1>
                    <p>Choose the layout used for PDF generation and HTML preview. Brand colors and letterheads apply automatically.</p>
                </div>
                <Link :href="route('settings.visual-identity')">
                    <PrimaryButton>Edit in Visual Identity</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>

            <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <button
                    v-for="tpl in templates"
                    :key="tpl.key"
                    type="button"
                    class="card relative text-left transition hover:border-indigo-300"
                    :class="{ 'ring-2 ring-indigo-500': profile.template_type === tpl.key }"
                    :disabled="activateForm.processing"
                    @click="activate(tpl.key)"
                >
                    <span
                        v-if="profile.template_type === tpl.key"
                        class="absolute right-4 top-4 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700"
                    >
                        ACTIVE
                    </span>

                    <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div
                            class="mb-2 h-4 rounded"
                            :style="{ background: tpl.key === 'compact' ? '#94a3b8' : profile.primary_color }"
                        ></div>
                        <div class="space-y-1.5">
                            <div class="h-2 w-4/5 rounded bg-gray-200"></div>
                            <div class="h-2 w-3/5 rounded bg-gray-200"></div>
                            <div class="h-2 w-full rounded bg-gray-200"></div>
                            <div class="h-2 w-2/3 rounded bg-gray-200"></div>
                        </div>
                        <div class="mt-2 h-3 rounded" :style="{ background: profile.accent_color }"></div>
                    </div>

                    <div class="text-sm font-semibold text-gray-900">{{ tpl.name }}</div>
                    <p class="mt-1 text-xs text-gray-500">{{ tpl.desc }}</p>
                    <p class="mt-3 text-xs font-medium text-indigo-600">
                        {{ profile.template_type === tpl.key ? 'Currently active' : 'Click to activate' }}
                    </p>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Active Template Details</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <span class="text-gray-500">Template</span>
                            <span class="font-semibold capitalize text-gray-900">
                                {{ activeTemplate?.name ?? 'Custom HTML' }}
                            </span>
                        </div>
                        <div class="flex items-start justify-between gap-6 border-b border-gray-100 pb-3">
                            <span class="shrink-0 text-gray-500">Description</span>
                            <span class="text-right text-gray-900">
                                {{ activeTemplate?.desc ?? 'Custom HTML with system variable tags.' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <span class="text-gray-500">Render Engine</span>
                            <span class="text-gray-900">DomPDF · A4 Portrait</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Company</span>
                            <span class="font-medium text-gray-900">{{ profile.company_name }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Brand Tokens & Letterhead</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="text-xs uppercase tracking-wider text-gray-500">Primary Color</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="inline-block h-5 w-5 rounded border border-gray-200" :style="{ background: profile.primary_color }"></span>
                                <span class="text-sm font-medium text-gray-900">{{ profile.primary_color }}</span>
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div class="text-xs uppercase tracking-wider text-gray-500">Accent Color</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="inline-block h-5 w-5 rounded border border-gray-200" :style="{ background: profile.accent_color }"></span>
                                <span class="text-sm font-medium text-gray-900">{{ profile.accent_color }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="mb-2 text-xs uppercase tracking-wider text-gray-500">Header Banner</div>
                            <div class="flex h-20 items-center justify-center rounded-lg border border-gray-200 bg-gray-50">
                                <img v-if="profile.header_image_url" :src="profile.header_image_url" class="max-h-full max-w-full object-contain" alt="Header" />
                                <span v-else class="text-xs text-gray-400">Not uploaded</span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 text-xs uppercase tracking-wider text-gray-500">Footer Banner</div>
                            <div class="flex h-20 items-center justify-center rounded-lg border border-gray-200 bg-gray-50">
                                <img v-if="profile.footer_image_url" :src="profile.footer_image_url" class="max-h-full max-w-full object-contain" alt="Footer" />
                                <span v-else class="text-xs text-gray-400">Not uploaded</span>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Upload letterheads and colors under Visual Identity. Already published PDFs keep their original layout until republished.
                    </p>
                </div>
            </div>
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
button.card:disabled {
    opacity: 0.7;
    cursor: wait;
}
</style>
