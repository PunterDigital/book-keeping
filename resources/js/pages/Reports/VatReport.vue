<template>
    <AppLayout title="VAT Report" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">VAT Report</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Period: {{ vat_data?.period?.start }} - {{ vat_data?.period?.end }}
                            {{ vat_data?.period?.quarter ? `(Q${vat_data.period.quarter} ${vat_data.period.year})` : `(${vat_data?.period?.year})` }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                            <TrendingUp class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(totalOutputVat) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Output VAT (Sales)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20">
                            <TrendingDown class="h-6 w-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(totalInputVat) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Input VAT (Purchases)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div :class="[
                            'flex h-12 w-12 items-center justify-center rounded-lg',
                            totalNetVat >= 0 ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-purple-50 dark:bg-purple-900/20'
                        ]">
                            <Calculator :class="[
                                'h-6 w-6',
                                totalNetVat >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-purple-600 dark:text-purple-400'
                            ]" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(Math.abs(totalNetVat)) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ totalNetVat >= 0 ? 'VAT to Pay' : 'VAT to Reclaim' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VAT Breakdown Tables -->
            <div class="space-y-6">
                <!-- Output VAT Table -->
                <div class="rounded-lg border inset card shadow-sm" v-if="Object.keys(vat_data?.vat_summary?.output_vat || {}).length > 0">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Output VAT (from Invoices)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT Rate</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Base Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, rate) in vat_data.vat_summary.output_vat" :key="rate" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ rate }}%</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.base_amount || item.base) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.vat_amount || item.vat) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency((item.base_amount || item.base) + (item.vat_amount || item.vat)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Input VAT Table -->
                <div class="rounded-lg border inset card shadow-sm" v-if="Object.keys(vat_data?.vat_summary?.input_vat || {}).length > 0">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Input VAT (from Expenses)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT Rate</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Base Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, rate) in vat_data.vat_summary.input_vat" :key="rate" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ rate }}%</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.base_amount || item.base) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.vat_amount || item.vat) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency((item.base_amount || item.base) + (item.vat_amount || item.vat)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Net VAT Summary -->
                <div class="rounded-lg border inset card shadow-sm" v-if="Object.keys(vat_data?.vat_summary?.net_vat || {}).length > 0">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Net VAT Summary</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT Rate</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Net VAT Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, rate) in vat_data.vat_summary.net_vat" :key="rate" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ rate }}%</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(Math.abs(item.net_amount || item)) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <span :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            (item.net_amount || item) >= 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                        ]">
                                            {{ (item.net_amount || item) >= 0 ? 'To Pay' : 'To Reclaim' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Calculator, TrendingUp, TrendingDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    vat_data: Object,
    year: Number,
    quarter: Number,
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount || 0);
};

// Calculate totals from the VAT summary
const totalOutputVat = computed(() => {
    // Use totals from new service if available, fallback to old calculation
    if (props.vat_data?.vat_summary?.totals?.total_output_vat !== undefined) {
        return props.vat_data.vat_summary.totals.total_output_vat;
    }
    if (!props.vat_data?.vat_summary?.output_vat) return 0;
    return Object.values(props.vat_data.vat_summary.output_vat).reduce((sum, item) => sum + (item.vat_amount || item.vat || 0), 0);
});

const totalInputVat = computed(() => {
    // Use totals from new service if available, fallback to old calculation
    if (props.vat_data?.vat_summary?.totals?.total_input_vat !== undefined) {
        return props.vat_data.vat_summary.totals.total_input_vat;
    }
    if (!props.vat_data?.vat_summary?.input_vat) return 0;
    return Object.values(props.vat_data.vat_summary.input_vat).reduce((sum, item) => sum + (item.vat_amount || item.vat || 0), 0);
});

const totalNetVat = computed(() => {
    // Use totals from new service if available, fallback to old calculation
    if (props.vat_data?.vat_summary?.totals?.total_net_vat !== undefined) {
        return props.vat_data.vat_summary.totals.total_net_vat;
    }
    if (!props.vat_data?.vat_summary?.net_vat) return 0;
    return Object.values(props.vat_data.vat_summary.net_vat).reduce((sum, item) => sum + (item.net_amount || item || 0), 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'VAT Report', href: '/reports/vat' },
];
</script>