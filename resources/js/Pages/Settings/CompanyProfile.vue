<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps<{
    profile: {
        company_name: string;
        tax_id: string | null;
        registration_number: string | null;
        address: string | null;
        header_image_url: string | null;
        footer_image_url: string | null;
        template_type: string;
        primary_color: string;
        accent_color: string;
    };
}>();
</script>

<template>
    <Head title="Company Profile" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1>Company Profile</h1>
                    <p>Legal entity details printed on every payslip & stamped copy.</p>
                </div>
                <Link :href="route('settings.visual-identity')">
                    <PrimaryButton>✏️ Edit in Visual Identity</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="card mb-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Company Name</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ profile.company_name }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Tax ID</div>
                        <div class="mt-1 text-sm text-gray-900">{{ profile.tax_id ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Registration Number</div>
                        <div class="mt-1 text-sm text-gray-900">{{ profile.registration_number ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Address</div>
                        <div class="mt-1 text-sm text-gray-900">{{ profile.address ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Letterhead Assets</h2>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="mb-2 text-xs uppercase tracking-wider text-gray-500">Header Banner</div>
                        <div class="flex h-28 items-center justify-center rounded-lg border border-gray-200 bg-gray-50">
                            <img v-if="profile.header_image_url" :src="profile.header_image_url" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-sm text-gray-400">Not uploaded</span>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2 text-xs uppercase tracking-wider text-gray-500">Footer Banner</div>
                        <div class="flex h-28 items-center justify-center rounded-lg border border-gray-200 bg-gray-50">
                            <img v-if="profile.footer_image_url" :src="profile.footer_image_url" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-sm text-gray-400">Not uploaded</span>
                        </div>
                    </div>
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
}
</style>