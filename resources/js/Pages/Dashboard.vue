<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps<{
    showAdminStats?: boolean;
    stats?: {
        open_drafts: number;
        pending_stamps: number;
        approved_ready: number;
        latest_period: string | null;
    } | null;
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h1>Dashboard</h1>
            <p>Overview of payroll activity and pending actions.</p>
        </template>

        <div class="grid">
            <template v-if="showAdminStats && stats">
                <div class="stats">
                    <div class="card">
                        <div class="label">Open Draft Runs</div>
                        <div class="value">{{ stats.open_drafts }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Approved, Ready to Publish</div>
                        <div class="value">{{ stats.approved_ready }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Pending Stamped Requests</div>
                        <div class="value">{{ stats.pending_stamps }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Latest Period</div>
                        <div class="value text-lg">{{ stats.latest_period ?? '—' }}</div>
                    </div>
                </div>
                <div class="card">
                    <h2>Quick Actions</h2>
                    <div class="actions">
                        <Link :href="route('payroll-runs.index')" class="action">Generate Draft Payroll</Link>
                        <Link :href="route('payroll.import')" class="action">Import Monthly Data</Link>
                        <Link :href="route('stamped-requests.index')" class="action">Review Stamped Requests</Link>
                    </div>
                </div>
            </template>
            <div v-else class="card">
                <h2>Employee Portal</h2>
                <p class="sub">View and download your published payslips, or request an official stamped copy.</p>
                <Link :href="route('portal.payslips')" class="action">Go to My Payslips</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.grid { display: grid; gap: 22px; }
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}
.card {
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
    padding: 20px;
}
.label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
.value { margin-top: 8px; font-size: 28px; font-weight: 650; color: #0f172a; }
.value.text-lg { font-size: 20px; }
h2 { margin: 0 0 8px; font-size: 15px; font-weight: 600; }
.sub { color: #64748b; font-size: 13px; margin: 0 0 14px; }
.actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.action {
    display: inline-flex;
    padding: 9px 14px;
    border-radius: 8px;
    background: #1d4ed8;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
}
.action:hover { background: #1e40af; }
</style>
