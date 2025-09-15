<template>
    <AppLayout title="Client Statement" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Client Statement</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Account overview and invoice details</p>
                    </div>
                </div>
            </div>

            <!-- Client Information Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ statement_data.client.company_name }}</h2>
                        <div class="text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                            <p v-if="statement_data.client.contact_name">{{ statement_data.client.contact_name }}</p>
                            <p>{{ statement_data.client.address }}</p>
                            <p v-if="statement_data.client.vat_id">VAT ID: {{ statement_data.client.vat_id }}</p>
                            <p v-if="statement_data.client.company_id">Company ID: {{ statement_data.client.company_id }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Period</p>
                        <p class="font-bold text-gray-900 dark:text-white">{{ formatDate(statement_data.period?.start) }} - {{ formatDate(statement_data.period?.end) }}</p>
                    </div>
                </div>
                
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-lg border inset card p-4 shadow-sm">
                        <div class="flex items-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ statement_data.invoices?.length || 0 }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Total Invoices</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border inset card p-4 shadow-sm">
                        <div class="flex items-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/20">
                                <DollarSign class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(statement_data.summary?.total_invoiced || 0) }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Total Invoiced</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border inset card p-4 shadow-sm">
                        <div class="flex items-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                                <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(statement_data.summary?.total_paid || 0) }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Paid</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border inset card p-4 shadow-sm">
                        <div class="flex items-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20">
                                <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(statement_data.summary?.total_outstanding || 0) }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Outstanding</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Details Table -->
            <div class="rounded-lg border inset card shadow-sm">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Details</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issue Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="invoice in statement_data.invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoice_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ formatDate(invoice.issue_date) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ formatDate(invoice.due_date) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="getStatusClass(invoice.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ getStatusText(invoice.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { FileText, DollarSign, CheckCircle, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    statement_data: Object,
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount || 0);
};

const formatDate = (date) => {
    if (!date) return '-';

    // Check if the date is already in dd.mm.yyyy format from the backend
    if (typeof date === 'string' && date.match(/^\d{2}\.\d{2}\.\d{4}$/)) {
        return date;
    }

    // Otherwise parse and format it
    const parsedDate = new Date(date);
    if (isNaN(parsedDate.getTime())) {
        return '-';
    }

    return parsedDate.toLocaleDateString('cs-CZ');
};

const getStatusText = (status) => {
    const statusMap = {
        'draft': 'Draft',
        'sent': 'Sent',
        'paid': 'Paid',
        'overdue': 'Overdue',
        'cancelled': 'Cancelled'
    };
    return statusMap[status] || status;
};

const getStatusClass = (status) => {
    const classMap = {
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        'sent': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'overdue': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'cancelled': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
    };
    return classMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Client Statement', href: '/reports/client-statement' },
];
</script>