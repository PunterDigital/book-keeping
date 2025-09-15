<template>
    <AppLayout title="Client Statements" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Client Statements</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Generate client account statements and summaries</p>
                    </div>
                </div>
            </div>

            <!-- Statement Form -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Generate Client Statement</h3>
                <form @submit.prevent="generateStatement" class="space-y-4 max-w-xl">
                    <div>
                        <label for="client" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Client *</label>
                        <select
                            id="client"
                            v-model="form.client_id"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                            required
                        >
                            <option value="">Choose a client...</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">
                                {{ client.company_name }}
                            </option>
                        </select>
                        <div v-if="form.errors.client_id" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.client_id }}</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date *</label>
                            <input
                                type="date"
                                id="start_date"
                                v-model="form.start_date"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            />
                            <div v-if="form.errors.start_date" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.start_date }}</div>
                        </div>

                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date *</label>
                            <input
                                type="date"
                                id="end_date"
                                v-model="form.end_date"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                required
                            />
                            <div v-if="form.errors.end_date" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.end_date }}</div>
                        </div>
                    </div>

                    <div>
                        <label for="format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Format *</label>
                        <select
                            id="format"
                            v-model="form.format"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                            required
                        >
                            <option value="html">View HTML</option>
                            <option value="pdf">Download PDF</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex justify-center items-center rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ form.processing ? 'Generating...' : 'Generate Statement' }}
                        </button>

                        <!-- Quick Period Buttons -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Quick select:</span>
                            <button
                                type="button"
                                @click="setCurrentMonth"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Current Month
                            </button>
                            <button
                                type="button"
                                @click="setLastMonth"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Last Month
                            </button>
                            <button
                                type="button"
                                @click="setCurrentYear"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Current Year
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Statement Info -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Statement Information</h3>
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        Client statements provide a detailed overview of all transactions with a specific client during the selected period:
                    </p>
                    <ul class="text-gray-600 dark:text-gray-400 space-y-2 mt-3">
                        <li><strong>Invoice List:</strong> All invoices issued to the client during the period</li>
                        <li><strong>Payment Status:</strong> Current status of each invoice (paid, pending, overdue)</li>
                        <li><strong>Summary Totals:</strong> Total invoiced, paid, outstanding, and overdue amounts</li>
                        <li><strong>Transaction Details:</strong> Dates, amounts, and VAT breakdown for each invoice</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    clients: Array,
    current_year: Number,
});

const form = useForm({
    client_id: '',
    start_date: '',
    end_date: '',
    format: 'html'
});

const generateStatement = () => {
    form.post('/reports/client-statement', {
        preserveScroll: true,
        onSuccess: () => {
            // Handle success
        }
    });
};

const setCurrentMonth = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    form.start_date = new Date(year, month, 1).toISOString().split('T')[0];
    form.end_date = new Date(year, month + 1, 0).toISOString().split('T')[0];
};

const setLastMonth = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    const lastMonth = month === 0 ? 11 : month - 1;
    const lastMonthYear = month === 0 ? year - 1 : year;

    form.start_date = new Date(lastMonthYear, lastMonth, 1).toISOString().split('T')[0];
    form.end_date = new Date(lastMonthYear, lastMonth + 1, 0).toISOString().split('T')[0];
};

const setCurrentYear = () => {
    const year = props.current_year || new Date().getFullYear();
    form.start_date = `${year}-01-01`;
    form.end_date = `${year}-12-31`;
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Client Statements', href: '/reports/client-statement' },
];
</script>