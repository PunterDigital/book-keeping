<template>
    <AppLayout title="Expense Reports" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expense Reports</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Generate expense reports for different periods</p>
                    </div>
                </div>
            </div>

            <!-- Report Type Selection -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Monthly Report -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monthly Report</h3>
                    <form @submit.prevent="generateMonthlyReport" class="space-y-4">
                        <div>
                            <label for="monthly-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                            <select
                                id="monthly-year"
                                v-model="monthlyForm.year"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="monthly-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Month</label>
                            <select
                                id="monthly-month"
                                v-model="monthlyForm.month"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option v-for="(month, index) in months" :key="index" :value="index + 1">{{ month }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="monthly-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format</label>
                            <select
                                id="monthly-format"
                                v-model="monthlyForm.format"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="html">View HTML</option>
                                <option value="pdf">Download PDF</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="monthlyForm.processing"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ monthlyForm.processing ? 'Generating...' : 'Generate Report' }}
                        </button>
                    </form>
                </div>

                <!-- Yearly Report -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Yearly Report</h3>
                    <form @submit.prevent="generateYearlyReport" class="space-y-4">
                        <div>
                            <label for="yearly-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                            <select
                                id="yearly-year"
                                v-model="yearlyForm.year"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="yearly-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format</label>
                            <select
                                id="yearly-format"
                                v-model="yearlyForm.format"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="html">View HTML</option>
                                <option value="pdf">Download PDF</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="yearlyForm.processing"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ yearlyForm.processing ? 'Generating...' : 'Generate Report' }}
                        </button>
                    </form>
                </div>

                <!-- Custom Period Report -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Custom Period</h3>
                    <form @submit.prevent="generateCustomReport" class="space-y-4">
                        <div>
                            <label for="custom-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                            <input
                                type="date"
                                id="custom-start"
                                v-model="customForm.start_date"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            />
                        </div>
                        <div>
                            <label for="custom-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                            <input
                                type="date"
                                id="custom-end"
                                v-model="customForm.end_date"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            />
                        </div>
                        <div>
                            <label for="custom-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format</label>
                            <select
                                id="custom-format"
                                v-model="customForm.format"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            >
                                <option value="html">View HTML</option>
                                <option value="pdf">Download PDF</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="customForm.processing"
                            class="w-full inline-flex justify-center items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ customForm.processing ? 'Generating...' : 'Generate Report' }}
                        </button>
                    </form>
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
    current_month: Number,
    available_years: Array,
});

const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const availableYears = computed(() => {
    if (props.available_years && props.available_years.length > 0) {
        return props.available_years;
    }
    // Fallback: show current year and previous years
    const currentYear = props.current_year || new Date().getFullYear();
    return [currentYear - 2, currentYear - 1, currentYear];
});

const monthlyForm = useForm({
    year: props.current_year,
    month: props.current_month,
    format: 'html'
});

const yearlyForm = useForm({
    year: props.current_year,
    format: 'html'
});

const customForm = useForm({
    start_date: '',
    end_date: '',
    format: 'html'
});

const generateMonthlyReport = () => {
    monthlyForm.post('/reports/expenses/monthly', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const generateYearlyReport = () => {
    yearlyForm.post('/reports/expenses/yearly', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const generateCustomReport = () => {
    customForm.post('/reports/expenses/custom', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Expense Reports', href: '/reports/expenses' },
];
</script>