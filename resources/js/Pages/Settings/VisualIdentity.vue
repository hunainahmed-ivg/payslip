<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    profile: {
        id: number;
        company_name: string;
        tax_id: string | null;
        registration_number: string | null;
        address: string | null;
        header_image_path: string | null;
        footer_image_path: string | null;
        header_image_url: string | null;
        footer_image_url: string | null;
        template_type: 'modern' | 'classic' | 'compact' | 'custom';
        custom_html: string | null;
        primary_color: string;
        accent_color: string;
        font_family: string;
        page_margin: string;
    };
    success?: string;
}>();

const form = useForm({
    company_name: props.profile.company_name,
    tax_id: props.profile.tax_id || '',
    registration_number: props.profile.registration_number || '',
    address: props.profile.address || '',
    header_image: null as File | null,
    footer_image: null as File | null,
    remove_header: false,
    remove_footer: false,
    template_type: props.profile.template_type,
    custom_html: props.profile.custom_html || '',
    primary_color: props.profile.primary_color,
    accent_color: props.profile.accent_color,
    font_family: props.profile.font_family,
    page_margin: props.profile.page_margin,
});

const headerPreview = ref(props.profile.header_image_url);
const footerPreview = ref(props.profile.footer_image_url);
const templateOptions: Array<'modern' | 'classic' | 'compact'> = ['modern', 'classic', 'compact'];

const handleHeaderUpload = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.header_image = file;
        form.remove_header = false;
        headerPreview.value = URL.createObjectURL(file);
    }
};

const handleFooterUpload = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.footer_image = file;
        form.remove_footer = false;
        footerPreview.value = URL.createObjectURL(file);
    }
};

const removeHeader = () => {
    form.header_image = null;
    form.remove_header = true;
    headerPreview.value = null;
};

const removeFooter = () => {
    form.footer_image = null;
    form.remove_footer = true;
    footerPreview.value = null;
};

const submit = () => {
    form.post(route('settings.visual-identity.update'), {
        forceFormData: true, // Required for Inertia file uploads
        onSuccess: () => {
            form.clearErrors('header_image', 'footer_image');
        },
    });
};
</script>

<template>
    <Head title="Visual Identity Configuration" />

    <AuthenticatedLayout>
        <template #header>
            <h1>Visual Identity Configuration</h1>
            <p>Configure your company's letterhead, branding, and payslip templates.</p>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ success }}
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Company Info -->
                <div class="card">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Company Details</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="company_name" value="Company Name" />
                            <TextInput id="company_name" v-model="form.company_name" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <InputLabel for="tax_id" value="Tax ID" />
                            <TextInput id="tax_id" v-model="form.tax_id" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="registration_number" value="Registration Number" />
                            <TextInput id="registration_number" v-model="form.registration_number" class="mt-1 block w-full" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="address" value="Head Office Address" />
                            <TextInput id="address" v-model="form.address" class="mt-1 block w-full" />
                        </div>
                    </div>
                </div>

                <!-- Letterhead Uploads -->
                <div class="card">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Header & Footer Banners</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Header -->
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                            <InputLabel value="Top Header Image (2480 × 320 px · PNG/SVG)" />
                            <div class="mt-2 flex h-32 items-center justify-center rounded-md border border-gray-200 bg-white">
                                <img v-if="headerPreview" :src="headerPreview" class="max-h-full max-w-full object-contain" alt="Header Preview" />
                                <span v-else class="text-sm text-gray-400">No image uploaded</span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <label class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Replace
                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml" class="hidden" @change="handleHeaderUpload" />
                                </label>
                                <SecondaryButton type="button" @click="removeHeader" :disabled="!headerPreview">Remove</SecondaryButton>
                            </div>
                            <InputError :message="form.errors.header_image" class="mt-2" />
                        </div>

                        <!-- Footer -->
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                            <InputLabel value="Bottom Footer Image (2480 × 180 px · PNG/SVG)" />
                            <div class="mt-2 flex h-32 items-center justify-center rounded-md border border-gray-200 bg-white">
                                <img v-if="footerPreview" :src="footerPreview" class="max-h-full max-w-full object-contain" alt="Footer Preview" />
                                <span v-else class="text-sm text-gray-400">No image uploaded</span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <label class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Replace
                                    <input type="file" accept="image/png,image/jpeg,image/svg+xml" class="hidden" @change="handleFooterUpload" />
                                </label>
                                <SecondaryButton type="button" @click="removeFooter" :disabled="!footerPreview">Remove</SecondaryButton>
                            </div>
                            <InputError :message="form.errors.footer_image" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Template Selection -->
                <div class="card">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Template Selection Engine</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div 
                            v-for="tpl in templateOptions" 
                            :key="tpl"
                            @click="form.template_type = tpl"
                            :class="['cursor-pointer rounded-lg border p-4 transition-all', form.template_type === tpl ? 'border-indigo-500 ring-2 ring-indigo-100' : 'border-gray-200 hover:border-indigo-300']"
                        >
                            <div class="mb-3 flex h-32 flex-col gap-1 rounded bg-gray-50 p-2">
                                <div :class="['h-3 rounded', tpl === 'modern' ? 'bg-indigo-500' : tpl === 'classic' ? 'bg-gray-800' : 'bg-gray-400']"></div>
                                <div class="h-2 w-3/4 rounded bg-gray-200"></div>
                                <div class="h-2 w-1/2 rounded bg-gray-200"></div>
                                <div :class="['mt-auto h-3 rounded', tpl === 'modern' ? 'bg-indigo-400' : tpl === 'classic' ? 'bg-gray-800' : 'bg-gray-400']"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold capitalize text-gray-900">{{ tpl }}</span>
                                <div :class="['h-4 w-4 rounded-full border', form.template_type === tpl ? 'border-indigo-500 bg-indigo-500' : 'border-gray-300']">
                                    <div v-if="form.template_type === tpl" class="m-0.5 h-2.5 w-2.5 rounded-full bg-white"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom HTML (Enterprise) -->
                <div v-if="form.template_type === 'custom'" class="card">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Custom HTML / CSS Template</h3>
                    <!-- 👇 FIXED: Using HTML entities for literal curly braces 👇 -->
                    <p class="mb-4 text-sm text-gray-500">
                        Use system variables like 
                        <code class="rounded bg-gray-100 px-1 py-0.5 text-indigo-600">&#123;&#123;employee_name&#125;&#125;</code>, 
                        <code class="rounded bg-gray-100 px-1 py-0.5 text-indigo-600">&#123;&#123;net_pay&#125;&#125;</code>.
                    </p>
                    <textarea 
                        v-model="form.custom_html" 
                        rows="10" 
                        class="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="<section class='payslip'>...</section>"
                    ></textarea>
                    <InputError :message="form.errors.custom_html" class="mt-2" />
                </div>

                <!-- Brand Tokens -->
                <div class="card">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Brand Tokens</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <InputLabel value="Primary Color" />
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" v-model="form.primary_color" class="h-9 w-9 cursor-pointer rounded border border-gray-300 p-0" />
                                <TextInput v-model="form.primary_color" class="flex-1" maxlength="7" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Accent Color" />
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" v-model="form.accent_color" class="h-9 w-9 cursor-pointer rounded border border-gray-300 p-0" />
                                <TextInput v-model="form.accent_color" class="flex-1" maxlength="7" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="font_family" value="Font Family" />
                            <select v-model="form.font_family" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="Inter">Inter</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Open Sans">Open Sans</option>
                                <option value="Merriweather">Merriweather (Serif)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="page_margin" value="Page Margins" />
                            <select v-model="form.page_margin" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="12mm">12mm (Narrow)</option>
                                <option value="18mm">18mm (Default)</option>
                                <option value="25mm">25mm (Wide)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4">
                    <SecondaryButton type="button" :disabled="form.processing" @click="form.reset()">Discard Changes</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Configuration</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Reusing the clean card styles from the layout */
.card {
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 1px 0 rgba(15,23,42,.02);
}
</style>