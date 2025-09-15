<template>
    <AppLayout title="Invoices" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Invoices</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Manage and track all your invoices</p>
                    </div>
                    <Link 
                        :href="invoicesCreate().url"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors inline-flex items-center"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Create New Invoice
                    </Link>
                </div>
            </div>

            <!-- Flash Messages -->
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 shadow-sm">
                <p class="text-sm text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 shadow-sm">
                <p class="text-sm text-red-800 dark:text-red-200">{{ $page.props.flash.error }}</p>
            </div>

            <!-- Filters Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Filters</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                        <select 
                            v-model="filters.status" 
                            @change="applyFilters"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client</label>
                        <input 
                            type="text" 
                            v-model="filters.client"
                            @input="applyFilters"
                            placeholder="Search clients..."
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Invoice Number</label>
                        <input 
                            type="text" 
                            v-model="filters.number"
                            @input="applyFilters"
                            placeholder="Search invoice number..."
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                        />
                    </div>
                    <div class="flex items-end">
                        <button 
                            @click="clearFilters"
                            class="w-full rounded-lg bg-gray-500 dark:bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600 dark:hover:bg-gray-500 transition-colors inline-flex items-center justify-center"
                        >
                            <X class="w-4 h-4 mr-2" />
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Invoices Table Card -->
            <div class="rounded-lg border inset card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issue Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="invoice in filteredInvoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link :href="invoicesShow(invoice.id).url" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        {{ invoice.invoice_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ invoice.client_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ formatDate(invoice.issue_date) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" :class="invoice.is_overdue ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'">
                                    {{ formatDate(invoice.due_date) }}
                                    <span v-if="invoice.is_overdue" class="text-xs block">(Overdue)</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ invoice.formatted_total }}</div>
                                    <div v-if="invoice.exchange_rate_info" class="text-xs text-gray-500 dark:text-gray-400">{{ invoice.exchange_rate_info }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span 
                                        :class="getStatusClass(invoice.status)"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    >
                                        {{ invoice.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <div class="flex justify-center items-center space-x-2">
                                        <Link 
                                            :href="invoicesShow(invoice.id).url"
                                            class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                            title="View invoice"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                        <Link 
                                            :href="invoicesEdit(invoice.id).url"
                                            class="p-1 text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                            title="Edit invoice"
                                        >
                                            <Edit2 class="w-4 h-4" />
                                        </Link>
                                        <button 
                                            @click="deleteInvoice(invoice.id)"
                                            class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                                            title="Delete invoice"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredInvoices.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <FileText class="h-12 w-12 text-gray-400 mb-4" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No invoices found.</p>
                                        <Link :href="invoicesCreate().url" class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            Create your first invoice
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-700">
                            <FileText class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ invoices.length }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Invoices</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <DollarSign class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(totalValue) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Value (CZK)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                            <CheckCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(paidValue) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Paid (CZK)</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20">
                            <AlertCircle class="h-6 w-6 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(outstandingValue) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Outstanding (CZK)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { create as invoicesCreate, show as invoicesShow, edit as invoicesEdit, destroy as invoicesDestroy } from '@/routes/invoices';
import { Plus, X, Eye, Edit2, Trash2, FileText, DollarSign, CheckCircle, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    invoices: Array
});

const filters = ref({
    status: '',
    client: '',
    number: ''
});

const filteredInvoices = computed(() => {
    let result = props.invoices;

    if (filters.value.status) {
        result = result.filter(invoice => invoice.status === filters.value.status);
    }

    if (filters.value.client) {
        result = result.filter(invoice => 
            invoice.client_name.toLowerCase().includes(filters.value.client.toLowerCase())
        );
    }

    if (filters.value.number) {
        result = result.filter(invoice => 
            invoice.invoice_number.toLowerCase().includes(filters.value.number.toLowerCase())
        );
    }

    return result;
});

const totalValue = computed(() => {
    return props.invoices.reduce((sum, invoice) => {
        return sum + (invoice.total_czk || invoice.total);
    }, 0);
});

const paidValue = computed(() => {
    return props.invoices
        .filter(invoice => invoice.status === 'paid')
        .reduce((sum, invoice) => {
            return sum + (invoice.total_czk || invoice.total);
        }, 0);
});

const outstandingValue = computed(() => {
    return props.invoices
        .filter(invoice => ['draft', 'sent', 'overdue'].includes(invoice.status))
        .reduce((sum, invoice) => {
            return sum + (invoice.total_czk || invoice.total);
        }, 0);
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

const applyFilters = () => {
    // Filters are applied automatically via computed property
};

const clearFilters = () => {
    filters.value = {
        status: '',
        client: '',
        number: ''
    };
};

const deleteInvoice = (id) => {
    if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        router.visit(invoicesDestroy(id).url, { method: 'delete' });
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Invoices', href: '/invoices' },
];
</script>