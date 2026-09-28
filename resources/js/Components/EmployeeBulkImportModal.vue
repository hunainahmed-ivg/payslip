<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'imported'): void;
}>();

interface Summary {
    total: number;
    valid: number;
    errors: number;
    created: number;
    updated: number;
    failed: number;
}

interface Row {
    line: number;
    employee_code?: string | null;
    full_name?: string | null;
    status: 'valid' | 'error';
    errors: string[];
}

interface Report {
    valid: boolean;
    dry_run?: boolean;
    committed?: boolean;
    summary: Summary;
    rows: Row[];
}

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const updateExisting = ref(false);
const processing = ref(false);
const report = ref<Report | null>(null);
const errorMessage = ref('');

const canCommit = computed(() => {
    return (
        report.value?.valid === true &&
        report.value.summary.errors === 0 &&
        report.value.summary.valid > 0
    );
});

const csrfToken = (): string => {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
};

const resetState = () => {
    selectedFile.value = null;
    report.value = null;
    errorMessage.value = '';
    updateExisting.value = false;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const close = () => {
    resetState();
    emit('close');
};

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    selectedFile.value = target.files?.[0] ?? null;
    report.value = null;
    errorMessage.value = '';
};

const postRequest = async (url: string): Promise<Report | null> => {
    if (!selectedFile.value) {
        errorMessage.value = 'Please select a file first.';
        return null;
    }

    processing.value = true;
    errorMessage.value = '';
    report.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('update_existing', updateExisting.value ? '1' : '0');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            if (data && data.rows && data.summary) {
                report.value = data as Report;
                errorMessage.value = 'Validation failed. Please fix the highlighted rows.';
                return data as Report;
            }

            if (data && data.errors) {
                errorMessage.value = Object.values(data.errors)
                    .flat()
                    .map(String)
                    .join(', ');
                return null;
            }

            errorMessage.value = data?.message ?? 'Request failed. Please try again.';
            return null;
        }

        report.value = data as Report;
        return data as Report;
    } catch (e) {
        errorMessage.value = 'Unable to process file. Please try again.';
        return null;
    } finally {
        processing.value = false;
    }
};

const validateFile = async () => {
    await postRequest(route('employees.bulk-import.dry-run'));
};

const commitImport = async () => {
    if (!canCommit.value) {
        errorMessage.value = 'Please validate the file successfully before committing.';
        return;
    }

    const data = await postRequest(route('employees.bulk-import.commit'));

    if (data?.committed) {
        emit('imported');
        close();
    }
};

const rowErrorText = (row: Row): string => {
    if (!row.errors || row.errors.length === 0) {
        return '';
    }

    if (row.line === 0) {
        return row.errors.join(' | ');
    }

    return `Row ${row.line}: ${row.errors.join(' | ')}`;
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div
                class="fixed inset-0 bg-gray-500 opacity-75"
                @click="close"
            ></div>

            <div class="relative w-full max-w-5xl rounded-lg bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Bulk Import Employees
                    </h2>

                    <button
                        type="button"
                        class="text-gray-500 hover:text-gray-700"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-4">
                    <a
                        :href="route('employees.bulk-import.template')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Download .xlsx Template
                    </a>
                </div>

                <div class="mt-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Upload File
                        </label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="mt-1 block w-full text-sm"
                            @change="onFileChange"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Allowed formats: .xlsx, .xls, .csv. Max size: 10MB.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="updateExisting"
                            type="checkbox"
                            class="rounded border-gray-300"
                        />
                        Allow updating existing employees by employee_code
                    </label>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="processing || !selectedFile"
                            @click="validateFile"
                        >
                            Validate File
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 disabled:opacity-50"
                            :disabled="processing || !canCommit"
                            @click="commitImport"
                        >
                            Commit Import
                        </button>
                    </div>

                    <div
                        v-if="errorMessage"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700"
                    >
                        {{ errorMessage }}
                    </div>
                </div>

                <div v-if="report" class="mt-6">
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-gray-500">Total</div>
                            <div class="font-semibold">
                                {{ report.summary.total }}
                            </div>
                        </div>

                        <div class="rounded-md bg-green-50 p-3">
                            <div class="text-gray-500">Valid</div>
                            <div class="font-semibold text-green-700">
                                {{ report.summary.valid }}
                            </div>
                        </div>

                        <div class="rounded-md bg-red-50 p-3">
                            <div class="text-gray-500">Errors</div>
                            <div class="font-semibold text-red-700">
                                {{ report.summary.errors }}
                            </div>
                        </div>

                        <div class="rounded-md bg-blue-50 p-3">
                            <div class="text-gray-500">Created</div>
                            <div class="font-semibold text-blue-700">
                                {{ report.summary.created }}
                            </div>
                        </div>

                        <div class="rounded-md bg-purple-50 p-3">
                            <div class="text-gray-500">Updated</div>
                            <div class="font-semibold text-purple-700">
                                {{ report.summary.updated }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Row</th>
                                    <th class="px-3 py-2 text-left">Employee Code</th>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">Errors</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="row in report.rows"
                                    :key="row.line"
                                    :class="row.status === 'error' ? 'bg-red-50' : ''"
                                >
                                    <td class="px-3 py-2">{{ row.line }}</td>
                                    <td class="px-3 py-2">
                                        {{ row.employee_code || '-' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ row.full_name || '-' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            v-if="row.status === 'valid'"
                                            class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700"
                                        >
                                            Valid
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700"
                                        >
                                            Error
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-red-700">
                                        {{ rowErrorText(row) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>