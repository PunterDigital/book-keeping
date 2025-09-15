<template>
    <AppLayout title="Expenses Report" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expenses Report</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Detailed breakdown of business expenses</p>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <Receipt class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ report_data.summary.total_expenses }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Expenses</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                            <DollarSign class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(report_data.summary.total_amount) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/20">
                            <Calculator class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(report_data.summary.total_vat) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total VAT</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/20">
                            <TrendingUp class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(report_data.summary.total_with_vat) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total with VAT</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Detail Table -->
            <div class="rounded-lg border inset card shadow-sm">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Expense Details</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="expense in report_data.expenses" :key="expense.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ formatDate(expense.date) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ expense.category?.name || 'No Category' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ expense.description }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(expense.amount) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(expense.vat_amount) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(expense.amount + expense.vat_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Breakdown Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Breakdown by Category</h3>
                    <div class="space-y-3">
                        <div v-for="(breakdown, category) in report_data.category_breakdown" :key="category" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ category || 'No Category' }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(breakdown.total_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Breakdown by VAT Rate</h3>
                    <div class="space-y-3">
                        <div v-for="(breakdown, rate) in report_data.vat_breakdown" :key="rate" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ rate }}% VAT</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(breakdown.total_vat) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Receipt, DollarSign, Calculator, TrendingUp } from 'lucide-vue-next';

const props = defineProps({
    report_data: Object,
    report_type: String,
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('cs-CZ');
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Expenses Report', href: '/reports/expenses' },
];
</script>