<template>
    <AppLayout title="VAT Reports" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">VAT Reports</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Generate VAT reports for tax compliance</p>
                    </div>
                </div>
            </div>

            <!-- Report Type Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Quarterly VAT Report -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quarterly VAT Report</h3>
                    <form @submit.prevent="generateQuarterlyReport" class="space-y-4">
                        <div>
                            <label for="quarterly-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                            <select
                                id="quarterly-year"
                                v-model="quarterlyForm.year"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="quarterly-quarter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quarter</label>
                            <select
                                id="quarterly-quarter"
                                v-model="quarterlyForm.quarter"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="1">Q1 (Jan - Mar)</option>
                                <option value="2">Q2 (Apr - Jun)</option>
                                <option value="3">Q3 (Jul - Sep)</option>
                                <option value="4">Q4 (Oct - Dec)</option>
                            </select>
                        </div>
                        <div>
                            <label for="quarterly-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format</label>
                            <select
                                id="quarterly-format"
                                v-model="quarterlyForm.format"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="html">View HTML</option>
                                <option value="pdf">Download PDF</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="quarterlyForm.processing"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ quarterlyForm.processing ? 'Generating...' : 'Generate Report' }}
                        </button>
                    </form>
                </div>

                <!-- Annual VAT Report -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Annual VAT Report</h3>
                    <form @submit.prevent="generateAnnualReport" class="space-y-4">
                        <div>
                            <label for="annual-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                            <select
                                id="annual-year"
                                v-model="annualForm.year"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="annual-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format</label>
                            <select
                                id="annual-format"
                                v-model="annualForm.format"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="html">View HTML</option>
                                <option value="pdf">Download PDF</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="annualForm.processing"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ annualForm.processing ? 'Generating...' : 'Generate Report' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- VAT Summary Info -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">VAT Report Information</h3>
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        VAT reports provide a comprehensive overview of your input and output VAT for the selected period:
                    </p>
                    <ul class="text-gray-600 dark:text-gray-400 space-y-2 mt-3">
                        <li><strong>Output VAT:</strong> VAT collected from your invoices</li>
                        <li><strong>Input VAT:</strong> VAT paid on your expenses</li>
                        <li><strong>Net VAT:</strong> The difference between output and input VAT (amount to pay or reclaim)</li>
                    </ul>
                    <p class="text-gray-600 dark:text-gray-400 mt-3">
                        Reports are grouped by VAT rate and include detailed breakdowns of all transactions.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    current_year: Number,
    available_years: Array,
});

const availableYears = computed(() => {
    if (props.available_years && props.available_years.length > 0) {
        return props.available_years;
    }
    // Fallback: show current year and previous years
    const currentYear = props.current_year || new Date().getFullYear();
    return [currentYear - 2, currentYear - 1, currentYear];
});

// Calculate current quarter
const getCurrentQuarter = () => {
    const month = new Date().getMonth() + 1;
    return Math.ceil(month / 3);
};

const quarterlyForm = useForm({
    year: props.current_year,
    quarter: getCurrentQuarter(),
    format: 'html'
});

const annualForm = useForm({
    year: props.current_year,
    format: 'html'
});

const generateQuarterlyReport = () => {
    quarterlyForm.post('/reports/vat', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const generateAnnualReport = () => {
    // Annual report doesn't send quarter parameter
    const data = {
        year: annualForm.year,
        format: annualForm.format
    };

    annualForm.transform(() => data).post('/reports/vat', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'VAT Reports', href: '/reports/vat' },
];
</script>