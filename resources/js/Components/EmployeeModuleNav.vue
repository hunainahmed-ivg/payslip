<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        employeeId: number;
        active: 'details' | 'salary-structure';
        canViewSalaryStructure?: boolean;
    }>(),
    {
        canViewSalaryStructure: false,
    },
);

const tabs = [
    {
        key: 'details' as const,
        label: 'Details',
        href: () => route('employees.show', props.employeeId),
        visible: true,
    },
    {
        key: 'salary-structure' as const,
        label: 'Salary Structure',
        href: () => route('employees.salary-structure', props.employeeId),
        visible: props.canViewSalaryStructure,
    },
].filter((tab) => tab.visible);
</script>

<template>
    <nav
        v-if="tabs.length > 1"
        class="mb-6 flex flex-wrap gap-1 border-b border-gray-200"
        aria-label="Employee modules"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href()"
            class="relative -mb-px px-4 py-2.5 text-sm font-medium transition"
            :class="
                active === tab.key
                    ? 'border-b-2 border-indigo-600 text-indigo-700'
                    : 'border-b-2 border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
