<template>
    <AppLayout title="Invoice Details" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Invoice Details Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <!-- Header -->
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ invoice.invoice_number }}</h1>
                                <div class="mt-2 flex items-center space-x-4">
                                    <span 
                                        :class="getStatusClass(invoice.status)"
                                        class="px-3 py-1 rounded-full text-sm font-medium"
                                    >
                                        {{ invoice.status }}
                                    </span>
                                    <span v-if="isOverdue" class="text-red-600 text-sm font-medium">
                                        Overdue by {{ overdueDays }} days
                                    </span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button 
                                    v-if="invoice.status !== 'paid'"
                                    @click="updateStatus('paid')"
                                    class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
                                >
                                    Mark as Paid
                                </button>
                                <button 
                                    v-if="invoice.status === 'draft'"
                                    @click="updateStatus('sent')"
                                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                >
                                    Mark as Sent
                                </button>
                                <Link 
                                    :href="invoicesEdit(invoice.id).url"
                                    class="inline-flex items-center rounded-lg bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700 transition-colors"
                                >
                                    Edit
                                </Link>
                                <button 
                                    @click="generatePDF"
                                    class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 transition-colors"
                                >
                                    Generate PDF
                                </button>
                                <button 
                                    @click="downloadPDF"
                                    class="inline-flex items-center rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 transition-colors"
                                >
                                    Download PDF
                                </button>
                                <Link 
                                    :href="invoicesIndex().url"
                                    class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm"
                                >
                                    Back to List
                                </Link>
                            </div>
                        </div>

                        <!-- Invoice Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <!-- Bill To -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Bill To</h3>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ invoice.client.company_name }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                        {{ invoice.client.address }}
                                    </div>
                                    <div v-if="invoice.client.vat_id" class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                                        <strong>VAT ID:</strong> {{ invoice.client.vat_id }}
                                    </div>
                                    <div v-if="invoice.client.tax_id" class="text-sm text-gray-600 dark:text-gray-300">
                                        <strong>Company ID:</strong> {{ invoice.client.tax_id }}
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Info -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Invoice Information</h3>
                                <dl class="space-y-2">
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Issue Date:</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(invoice.issue_date) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Due Date:</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100" :class="{ 'text-red-600 dark:text-red-400': isOverdue }">
                                            {{ formatDate(invoice.due_date) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</dt>
                                        <dd>
                                            <span
                                                :class="getStatusClass(invoice.status)"
                                                class="px-2 py-1 rounded-full text-xs font-medium"
                                            >
                                                {{ invoice.status }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="invoice.currency && invoice.currency !== 'CZK'" class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Currency:</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ invoice.currency }}</dd>
                                    </div>
                                    <div v-if="invoice.exchange_rate_info" class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Exchange Rate:</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ invoice.exchange_rate_info }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Invoice Items -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Qty</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Unit Price ({{ invoice.currency }})</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">VAT %</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Subtotal ({{ invoice.currency }})</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">VAT Amount ({{ invoice.currency }})</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Total ({{ invoice.currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="item in invoice.items" :key="item.id" class="border-b">
                                            <td class="px-6 py-4 whitespace-nowrap border-r">{{ item.description }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap border-r text-center">{{ item.quantity }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap border-r text-right">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap border-r text-center">{{ item.vat_rate }}%</td>
                                            <td class="px-6 py-4 whitespace-nowrap border-r text-right">{{ formatCurrency(item.subtotal) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap border-r text-right">{{ formatCurrency(item.vat_amount) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium">{{ formatCurrency(item.total) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                                        <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900 dark:text-white">Subtotal:</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoice.subtotal) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoice.vat_amount) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-lg text-gray-900 dark:text-white">{{ formatCurrency(invoice.total) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Reverse Charge Notice -->
                        <div v-if="isReverseChargeApplicable" class="mb-8">
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 rounded-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                            Přenesení daňové povinnosti (Reverse Charge)
                                        </h3>
                                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>{{ reverseChargeStatement }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="invoice.notes" class="mb-8">
                            <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">Notes</h3>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ invoice.notes }}</p>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">Payment Information</h3>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                <p><strong>Total Amount Due:</strong> {{ formatCurrency(invoice.total) }}</p>
                                <p v-if="invoice.exchange_rate_info" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    {{ invoice.exchange_rate_info }}
                                </p>
                                <p><strong>Payment Terms:</strong> Net 30 days</p>
                                <p class="mt-2">Please remit payment by the due date to avoid late fees.</p>
                            </div>
                        </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as invoicesIndex, show, edit as invoicesEdit, updateStatus as invoicesUpdateStatus } from '@/routes/invoices';
import pdf from '@/routes/invoices/pdf';

const props = defineProps({
    invoice: Object
});

const isOverdue = computed(() => {
    const today = new Date();
    const dueDate = new Date(props.invoice.due_date);
    return dueDate < today && ['draft', 'sent'].includes(props.invoice.status);
});

const overdueDays = computed(() => {
    if (!isOverdue.value) return 0;
    const today = new Date();
    const dueDate = new Date(props.invoice.due_date);
    const diffTime = Math.abs(today - dueDate);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
});

const formatCurrency = (amount) => {
    const currency = props.invoice.currency || 'CZK';
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: currency
    }).format(amount);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('cs-CZ');
};

const getStatusClass = (status) => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        'sent': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'overdue': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'cancelled': 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
};

// Reverse charge logic
const isReverseChargeApplicable = computed(() => {
    const euCountries = ['AUSTRIA', 'BELGIUM', 'BULGARIA', 'CROATIA', 'CYPRUS', 'DENMARK', 'ESTONIA', 'FINLAND', 'FRANCE', 'GERMANY', 'GREECE', 'HUNGARY', 'IRELAND', 'ITALY', 'LATVIA', 'LITHUANIA', 'LUXEMBOURG', 'MALTA', 'NETHERLANDS', 'POLAND', 'PORTUGAL', 'ROMANIA', 'SLOVAKIA', 'SLOVENIA', 'SPAIN', 'SWEDEN'];
    const clientCountry = props.invoice.client?.country?.toUpperCase() || '';

    // Debug logging
    console.log('Debug Reverse Charge:', {
        clientCountry,
        vatId: props.invoice.client?.vat_id,
        total: props.invoice.total,
        client: props.invoice.client
    });

    const isApplicable = euCountries.includes(clientCountry) &&
           props.invoice.client?.vat_id &&
           props.invoice.total > 0;

    console.log('Reverse charge applicable:', isApplicable);
    return isApplicable;
});

const reverseChargeStatement = computed(() => {
    return "Přenesení daňové povinnosti - zákazník je povinen odvést DPH podle §92a zákona o DPH";
});

const updateStatus = (status) => {
    router.visit(invoicesUpdateStatus.patch(props.invoice.id).url, {
        method: 'patch',
        data: { status }
    });
};

const downloadPDF = () => {
    window.open(pdf.download(props.invoice.id).url, '_blank');
};

const generatePDF = () => {
    router.post(pdf.generate(props.invoice.id).url);
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Invoices', href: '/invoices' },
    { title: props.invoice.invoice_number, href: show(props.invoice.id).url },
];
</script>