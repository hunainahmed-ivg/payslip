<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface AuditEntry {
    id: number;
    action: string;
    subject: string | null;
    description: string | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

defineProps<{
    logs: AuditEntry[];
    environment: {
        app_env: string;
        app_debug: boolean;
        session_driver: string;
        queue_connection: string;
        mail_mailer: string;
        db_connection: string;
    };
}>();

const badgeClass = (action: string) => {
    if (action.includes('approved')) return 'bg-emerald-50 text-emerald-700';
    if (action.includes('published')) return 'bg-indigo-50 text-indigo-700';
    if (action.includes('rejected')) return 'bg-red-50 text-red-700';
    if (action.includes('created')) return 'bg-amber-50 text-amber-700';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <Head title="Security & Audit" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Security & Audit</h1>
                <p>Environment hardening overview and a complete activity trail of critical payroll actions.</p>
            </div>
        </template>

        <div class="py-6">
            <!-- Environment overview -->
            <div class="card mb-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Security & Environment Overview</h2>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Environment</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ environment.app_env }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Debug Mode</div>
                        <div class="mt-1 text-sm font-semibold" :class="environment.app_debug ? 'text-red-600' : 'text-emerald-600'">
                            {{ environment.app_debug ? 'ON — disable in production' : 'OFF' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">CSRF Protection</div>
                        <div class="mt-1 text-sm font-semibold text-emerald-600">Enabled</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Session Driver</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ environment.session_driver }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Queue</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ environment.queue_connection }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Mailer</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ environment.mail_mailer }}</div>
                    </div>
                </div>
            </div>

            <!-- Audit trail -->
            <div class="card overflow-hidden p-0">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Audit Trail (latest 200 events)</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">When</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="px-6 py-3 text-sm text-gray-500">{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ log.user?.name ?? 'System' }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="badgeClass(log.action)">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ log.subject ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ log.description ?? '—' }}</td>
                            <td class="px-6 py-3 text-xs text-gray-400">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!logs.length">
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                No audit events yet — approve a payroll run or save branding to generate one.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
.card.p-0 {
    padding: 0;
}
</style>