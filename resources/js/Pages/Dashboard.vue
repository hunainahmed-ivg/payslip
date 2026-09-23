<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h1>Dashboard</h1>
            <p>Welcome to your PayrollOS Enterprise Suite dashboard.</p>
        </template>

        <div class="grid">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Welcome Back!</h2>
                        <div class="sub">You're logged in to the system.</div>
                    </div>
                    <span class="badge success">Active</span>
                </div>
                <p class="text-gray-600">
                    Use the sidebar navigation to manage employees, run payroll, and configure system settings.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 22px;
}

.card {
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 1px 0 rgba(15,23,42,.02);
}

.card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 14px;
}

.card-head h2 {
    margin: 0; font-size: 15px; font-weight: 600; color: #0f172a;
}

.card-head .sub { color: #64748b; font-size: 12.5px; margin-top: 2px; }

.badge {
    font-size: 11px; font-weight: 500;
    padding: 3px 8px; border-radius: 999px;
    background: #eef2ff; color: #4338ca;
    border: 1px solid #e0e7ff;
}

.badge.success { background: #ecfdf5; color: #047857; border-color: #d1fae5; }
</style>