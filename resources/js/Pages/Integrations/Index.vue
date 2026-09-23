<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface ApiSyncRow {
    id: number;
    period: string;
    total_working_days: number;
    attended_days: number | null;
    unpaid_leave_days: string;
    overtime_hours: string;
    updated_at: string;
    employee: { id: number; employee_code: string; full_name: string } | null;
}

const props = defineProps<{
    webhookUrl: string;
    tokenConfigured: boolean;
    tokenMasked: string;
    apiSyncCount: number;
    csvSyncCount: number;
    recentApiSyncs: ApiSyncRow[];
}>();

const copied = ref<string | null>(null);

const copy = async (text: string, key: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        // clipboard blocked — ignore silently
    }
};

const samplePayload = `{
  "period": "2026-10",
  "records": [
    {
      "employee_code": "EMP-8042",
      "total_working_days": 22,
      "attended_days": 20,
      "unpaid_leave_days": 2,
      "paid_leave_days": 0,
      "overtime_hours": 12.5,
      "late_count": 0
    }
  ]
}`;

const sampleCurl = `curl -X POST ${props.webhookUrl} \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer <VIRTUOHR_API_TOKEN>" \\
  -d '${samplePayload.replace(/\s+/g, ' ')}'`;
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Integrations</h1>
                <p>VirtuoHR webhook endpoint, API authentication and ingestion statistics.</p>
            </div>
        </template>

        <div class="py-6">
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Webhook configuration -->
                <div class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">VirtuoHR Webhook</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                            :class="tokenConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                        >
                            {{ tokenConfigured ? 'Active' : 'Token Missing' }}
                        </span>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Endpoint</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 rounded-md bg-gray-100 px-3 py-2 text-xs text-indigo-600">{{ webhookUrl }}</code>
                                <button class="rounded-md border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(webhookUrl, 'url')">
                                    {{ copied === 'url' ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Method</div>
                                <code class="rounded-md bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-900">POST</code>
                            </div>
                            <div>
                                <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Auth Header</div>
                                <code class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">Authorization: Bearer …</code>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Shared Secret (masked)</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">{{ tokenMasked }}</code>
                                <button
                                    class="rounded-md border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                                    :disabled="!tokenConfigured"
                                    @click="copy(tokenMasked, 'token')"
                                >
                                    {{ copied === 'token' ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Full token sirf server .env mein hai — kabhi UI par expose nahi hota.</p>
                        </div>
                    </div>
                </div>

                <!-- Ingestion mix -->
                <div class="card">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Ingestion Mix (payroll_inputs)</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs uppercase tracking-wider text-gray-500">API Syncs</div>
                            <div class="mt-1 text-2xl font-bold text-indigo-600">{{ apiSyncCount }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs uppercase tracking-wider text-gray-500">CSV Imports</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-600">{{ csvSyncCount }}</div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 text-xs uppercase tracking-wider text-gray-500">Recent API Syncs</div>
                        <div v-if="recentApiSyncs.length" class="space-y-2">
                            <div
                                v-for="row in recentApiSyncs"
                                :key="row.id"
                                class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2 text-sm"
                            >
                                <div>
                                    <span class="font-medium text-gray-900">{{ row.employee?.full_name ?? '—' }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ row.employee?.employee_code }}</span>
                                </div>
                                <div class="text-xs text-gray-500">{{ row.period }} · unpaid {{ row.unpaid_leave_days }}d</div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">No API syncs yet — use the sample cURL below.</p>
                    </div>
                </div>
            </div>

            <!-- Sample payload -->
            <div class="card mb-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sample Payload (Phase 4 standard)</h2>
                    <button class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(samplePayload, 'payload')">
                        {{ copied === 'payload' ? '✓ Copied' : 'Copy' }}
                    </button>
                </div>
                <pre class="code-block">{{ samplePayload }}</pre>
            </div>

            <!-- Sample cURL -->
            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Test Command (cURL)</h2>
                    <button class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(sampleCurl, 'curl')">
                        {{ copied === 'curl' ? '✓ Copied' : 'Copy' }}
                    </button>
                </div>
                <pre class="code-block">{{ sampleCurl }}</pre>
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
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 12px;
    line-height: 1.6;
    overflow-x: auto;
    white-space: pre;
    margin: 0;
}
</style>