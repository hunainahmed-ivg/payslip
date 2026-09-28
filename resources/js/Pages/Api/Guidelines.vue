<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

interface TokenRow {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
    expires_at: string | null;
}

const props = defineProps<{
    baseUrl: string;
    columns: string[];
    endpoints: {
        template: string;
        validate: string;
        commit: string;
        payroll_sync: string;
    };
    uiImportUrl: string;
    templateDownloadUrl: string;
    tokens: TokenRow[];
    plainTextToken?: string | null;
    success?: string;
}>();

const copied = ref<string | null>(null);

const tokenForm = useForm({
    name: 'Employee Import Integration',
});

const revokeForm = useForm({});

const copy = async (text: string, key: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        // ignore
    }
};

const createToken = () => {
    tokenForm.post(route('settings.api-guidelines.tokens.store'), {
        preserveScroll: true,
        onSuccess: () => tokenForm.reset('name'),
    });
};

const revokeToken = (id: number) => {
    if (!confirm('Revoke this API token? Integrations using it will stop working.')) return;
    revokeForm.delete(route('settings.api-guidelines.tokens.destroy', id), {
        preserveScroll: true,
    });
};

const validateCurl = `curl -X POST "${props.endpoints.validate}" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Accept: application/json" \\
  -F "file=@/path/to/employees.xlsx" \\
  -F "update_existing=0"`;

const commitCurl = `curl -X POST "${props.endpoints.commit}" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Accept: application/json" \\
  -F "file=@/path/to/employees.xlsx" \\
  -F "update_existing=1"`;

const templateCurl = `curl -X GET "${props.endpoints.template}" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -o employee-bulk-import-template.xlsx`;
</script>

<template>
    <Head title="API Guidelines" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>API Guidelines</h1>
                <p>Import employees with Excel/CSV in the UI, or through authenticated API endpoints.</p>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <div v-if="success" class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ success }}</div>

            <div v-if="plainTextToken" class="rounded-md border border-amber-200 bg-amber-50 p-4">
                <div class="text-sm font-semibold text-amber-900">New API token (copy now)</div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="flex-1 break-all rounded bg-white px-3 py-2 text-xs text-gray-800">{{ plainTextToken }}</code>
                    <SecondaryButton type="button" @click="copy(plainTextToken, 'token')">
                        {{ copied === 'token' ? 'Copied' : 'Copy' }}
                    </SecondaryButton>
                </div>
                <p class="mt-2 text-xs text-amber-800">This plain-text token is shown only once.</p>
            </div>

            <!-- UI import -->
            <div class="card">
                <h2 class="text-sm font-semibold text-gray-900">Option A — Import from Excel / CSV (UI)</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700">
                    <li>Open <Link :href="uiImportUrl" class="font-medium text-indigo-600 hover:underline">Employees</Link> and click <strong>Bulk Import Employees</strong>.</li>
                    <li>
                        Download the template:
                        <a :href="templateDownloadUrl" class="font-medium text-indigo-600 hover:underline">employee-bulk-import-template.xlsx</a>
                    </li>
                    <li>Fill one row per employee. Do not rename column headers.</li>
                    <li>Upload the file, run dry-run validation, then commit when the report is clean.</li>
                </ol>
                <p class="mt-3 text-sm text-gray-500">Allowed formats: <code>.xlsx</code>, <code>.xls</code>, <code>.csv</code> · Max size: 10MB.</p>
            </div>

            <!-- Columns -->
            <div class="card">
                <h2 class="text-sm font-semibold text-gray-900">Required file columns</h2>
                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Column</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="col in columns" :key="col">
                                <td class="px-4 py-2 font-mono text-xs text-indigo-700">{{ col }}</td>
                                <td class="px-4 py-2 text-gray-600">
                                    <span v-if="col === 'employee_code'">Required, unique employee ID</span>
                                    <span v-else-if="col === 'full_name'">Required</span>
                                    <span v-else-if="col === 'email'">Optional, must be valid if provided</span>
                                    <span v-else-if="col === 'branch_code'">Required, must match an existing branch code</span>
                                    <span v-else-if="col === 'joined_on'">Optional, format YYYY-MM-DD</span>
                                    <span v-else-if="col === 'base_salary'">Required, numeric</span>
                                    <span v-else-if="col === 'currency_code'">Required, 3 letters (USD, PKR, AED…)</span>
                                    <span v-else-if="col === 'is_active'">Optional: yes/no, true/false, 1/0</span>
                                    <span v-else>Optional</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- API auth -->
            <div class="card">
                <h2 class="text-sm font-semibold text-gray-900">Option B — Import via API (Sanctum Bearer token)</h2>
                <p class="mt-2 text-sm text-gray-600">
                    All employee import API routes require <code>Authorization: Bearer &lt;token&gt;</code>.
                    Create a token below for your admin user.
                </p>

                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="createToken">
                    <div class="min-w-[240px] flex-1">
                        <InputLabel for="token_name" value="Token name" />
                        <TextInput id="token_name" v-model="tokenForm.name" class="mt-1 block w-full" required />
                        <InputError :message="tokenForm.errors.name" class="mt-2" />
                    </div>
                    <PrimaryButton :disabled="tokenForm.processing">Generate API Token</PrimaryButton>
                </form>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Created</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Last used</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="token in tokens" :key="token.id">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ token.name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ token.created_at || '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ token.last_used_at || 'Never' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <DangerButton type="button" @click="revokeToken(token.id)">Revoke</DangerButton>
                                </td>
                            </tr>
                            <tr v-if="!tokens.length">
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">No API tokens yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Endpoints -->
            <div class="card">
                <h2 class="text-sm font-semibold text-gray-900">API endpoints</h2>
                <div class="mt-4 space-y-4 text-sm">
                    <div>
                        <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">1. Download template</div>
                        <code class="block rounded bg-gray-100 px-3 py-2 text-xs text-indigo-700">GET {{ endpoints.template }}</code>
                    </div>
                    <div>
                        <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">2. Validate file (dry-run, no DB write)</div>
                        <code class="block rounded bg-gray-100 px-3 py-2 text-xs text-indigo-700">POST {{ endpoints.validate }}</code>
                        <p class="mt-1 text-xs text-gray-500">multipart/form-data · fields: <code>file</code>, optional <code>update_existing</code></p>
                    </div>
                    <div>
                        <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">3. Commit import</div>
                        <code class="block rounded bg-gray-100 px-3 py-2 text-xs text-indigo-700">POST {{ endpoints.commit }}</code>
                        <p class="mt-1 text-xs text-gray-500">multipart/form-data · fields: <code>file</code>, optional <code>update_existing=1</code> to update existing employee codes</p>
                    </div>
                </div>
            </div>

            <!-- Sample curls -->
            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sample cURL — download template</h2>
                    <SecondaryButton type="button" @click="copy(templateCurl, 'tpl')">{{ copied === 'tpl' ? 'Copied' : 'Copy' }}</SecondaryButton>
                </div>
                <pre class="code-block">{{ templateCurl }}</pre>
            </div>

            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sample cURL — validate</h2>
                    <SecondaryButton type="button" @click="copy(validateCurl, 'val')">{{ copied === 'val' ? 'Copied' : 'Copy' }}</SecondaryButton>
                </div>
                <pre class="code-block">{{ validateCurl }}</pre>
            </div>

            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sample cURL — commit</h2>
                    <SecondaryButton type="button" @click="copy(commitCurl, 'commit')">{{ copied === 'commit' ? 'Copied' : 'Copy' }}</SecondaryButton>
                </div>
                <pre class="code-block">{{ commitCurl }}</pre>
            </div>

            <div class="card">
                <h2 class="text-sm font-semibold text-gray-900">Response shape</h2>
                <pre class="code-block mt-3">{
  "valid": true,
  "committed": false,
  "summary": { "total": 10, "valid": 10, "errors": 0, "created": 0, "updated": 0 },
  "rows": [
    { "line": 2, "status": "valid", "errors": [], "employee_code": "EMP-9001" }
  ]
}</pre>
                <p class="mt-3 text-sm text-gray-600">
                    HTTP <code>200</code> when valid / committed · <code>422</code> when row validation fails · <code>401</code> when the Bearer token is missing or invalid.
                </p>
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
.code-block {
    background: #0b1220;
    color: #cbd5e1;
    border-radius: 10px;
    padding: 14px 16px;
    font-family: Consolas, monospace;
    font-size: 12px;
    line-height: 1.6;
    overflow-x: auto;
    white-space: pre;
    margin: 0;
}
</style>
