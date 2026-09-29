<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface SyncEvent {
    id: number;
    period: string | null;
    status: string;
    records_total: number | null;
    records_created: number | null;
    records_updated: number | null;
    records_failed: number | null;
    created_at: string;
    error_message: string | null;
}

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
    companyId: number | null;
    companyName: string | null;
    companyHeader: string;
    secretConfigured: boolean;
    secretMasked: string;
    hasCompanySecret: boolean;
    signatureHeader: string;
    timestampHeader: string;
    idempotencyHeader: string;
    apiSyncCount: number;
    csvSyncCount: number;
    recentApiSyncs: ApiSyncRow[];
    recentEvents: SyncEvent[];
    success?: string;
    plainWebhookSecret?: string | null;
}>();

const rotateForm = useForm({});

const copied = ref<string | null>(null);
const syncPeriod = ref(new Date().toISOString().slice(0, 7));
const syncLoading = ref(false);
const syncResult = ref<{
    period: string;
    api_records: number;
    last_event: SyncEvent | null;
} | null>(null);
const syncError = ref<string | null>(null);

const copy = async (text: string, key: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        // clipboard blocked
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
      "late_count": 0,
      "bonus_amount": 500,
      "overtime_pay": 0
    }
  ]
}`;

const sampleCurl = computed(() => {
    const companyLine = props.companyId
        ? `  -H "${props.companyHeader}: ${props.companyId}" \\\n`
        : '';
    return `curl -X POST ${props.webhookUrl} \\
  -H "Content-Type: application/json" \\
${companyLine}  -H "${props.signatureHeader}: sha256=<hmac_hex>" \\
  -H "${props.timestampHeader}: <unix_timestamp>" \\
  -H "${props.idempotencyHeader}: <unique-key>" \\
  -d '${samplePayload.replace(/\s+/g, ' ')}'`;
});

const checkSyncStatus = async () => {
    syncLoading.value = true;
    syncError.value = null;
    try {
        const response = await fetch(
            route('settings.integrations.sync-status') + '?period=' + encodeURIComponent(syncPeriod.value),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );
        if (!response.ok) {
            throw new Error('Unable to load sync status.');
        }
        syncResult.value = await response.json();
    } catch (e) {
        syncError.value = e instanceof Error ? e.message : 'Unable to load sync status.';
    } finally {
        syncLoading.value = false;
    }
};
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Integrations</h1>
                <p>VirtuoHR webhook endpoint, HMAC authentication, and monthly sync status.</p>
            </div>
        </template>

        <div class="py-6">
            <div v-if="success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ success }}
            </div>
            <div
                v-if="plainWebhookSecret"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                <strong>New webhook secret (copy now):</strong>
                <code class="mt-2 block break-all rounded bg-white px-2 py-1">{{ plainWebhookSecret }}</code>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">VirtuoHR Webhook</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium uppercase"
                            :class="secretConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                        >
                            {{ secretConfigured ? 'Configured' : 'Secret Missing' }}
                        </span>
                    </div>

                    <p v-if="companyName" class="mb-3 text-sm text-gray-600">
                        Tenant: <strong>{{ companyName }}</strong>
                        <span v-if="companyId"> · ID {{ companyId }}</span>
                    </p>

                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Endpoint</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 rounded-md bg-gray-100 px-3 py-2 text-xs text-indigo-600">{{ webhookUrl }}</code>
                                <button class="rounded-md border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(webhookUrl, 'url')">
                                    {{ copied === 'url' ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Method</div>
                                <code class="rounded-md bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-900">POST</code>
                            </div>
                            <div>
                                <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Auth</div>
                                <code class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">HMAC-SHA256</code>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Signature Header</div>
                            <code class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">{{ signatureHeader }}: sha256=&lt;hex&gt;</code>
                        </div>
                        <div v-if="companyId">
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Company Header</div>
                            <code class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">{{ companyHeader }}: {{ companyId }}</code>
                        </div>
                        <div>
                            <div class="mb-1 text-xs uppercase tracking-wider text-gray-500">Webhook Secret (masked)</div>
                            <code class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-700">{{ secretMasked }}</code>
                            <p class="mt-1 text-xs text-gray-400">
                                Each company has its own secret. Generate one below, or use the global
                                <code>VIRTUOHR_WEBHOOK_SECRET</code> fallback when no company header is sent.
                            </p>
                            <form
                                class="mt-3"
                                @submit.prevent="rotateForm.post(route('settings.integrations.webhook-secret'), { preserveScroll: true })"
                            >
                                <button
                                    type="submit"
                                    class="rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                    :disabled="rotateForm.processing || !companyId"
                                >
                                    {{ hasCompanySecret ? 'Rotate company webhook secret' : 'Generate company webhook secret' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Check VirtuoHR Sync Status</h2>
                    <p class="mb-4 text-sm text-gray-600">
                        VirtuoHR pushes attendance and leave data to this webhook at month-end. Select a period to confirm what has been received.
                    </p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-gray-500">Period</label>
                            <input
                                v-model="syncPeriod"
                                type="month"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="syncLoading"
                            @click="checkSyncStatus"
                        >
                            {{ syncLoading ? 'Checking…' : 'Check Sync Status' }}
                        </button>
                    </div>
                    <div v-if="syncError" class="mt-3 text-sm text-red-600">{{ syncError }}</div>
                    <div v-if="syncResult" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
                        <div class="font-semibold text-gray-900">Period {{ syncResult.period }}</div>
                        <div class="mt-1 text-gray-600">API records received: <strong>{{ syncResult.api_records }}</strong></div>
                        <div v-if="syncResult.last_event" class="mt-2 text-gray-600">
                            Last webhook:
                            <strong>{{ syncResult.last_event.status }}</strong>
                            · {{ syncResult.last_event.records_total ?? 0 }} records
                            · {{ new Date(syncResult.last_event.created_at).toLocaleString() }}
                        </div>
                        <div v-else class="mt-2 text-gray-500">No webhook events recorded for this period yet.</div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs uppercase tracking-wider text-gray-500">API Syncs</div>
                            <div class="mt-1 text-2xl font-bold text-indigo-600">{{ apiSyncCount }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs uppercase tracking-wider text-gray-500">CSV Imports</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-600">{{ csvSyncCount }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Sample Monthly Payload</h2>
                    <button class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(samplePayload, 'payload')">
                        {{ copied === 'payload' ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <pre class="code-block">{{ samplePayload }}</pre>
            </div>

            <div class="card mb-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Test Command (cURL with HMAC)</h2>
                    <button class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="copy(sampleCurl, 'curl')">
                        {{ copied === 'curl' ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <pre class="code-block">{{ sampleCurl }}</pre>
            </div>

            <div class="card">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Recent Webhook Events</h2>
                <div v-if="recentEvents.length" class="space-y-2">
                    <div
                        v-for="event in recentEvents"
                        :key="event.id"
                        class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2 text-sm"
                    >
                        <div>
                            <span class="font-medium text-gray-900">{{ event.period ?? '—' }}</span>
                            <span class="ml-2 text-xs uppercase text-gray-500">{{ event.status }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ event.records_total ?? 0 }} records · {{ new Date(event.created_at).toLocaleString() }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No webhook events yet.</p>
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
