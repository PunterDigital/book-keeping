<template>
    <AppLayout title="Client Details">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ client.company_name }}</h2>
                    <div class="space-x-2">
                        <Link 
                            :href="clientsEdit(client.id).url"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                        >
                            Edit Client
                        </Link>
                        <Link 
                            :href="clientsIndex().url"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                        >
                            Back to List
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Contact Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Contact Person</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ client.contact_name || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Email</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">
                                    <a v-if="client.email" :href="`mailto:${client.email}`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ client.email }}
                                    </a>
                                    <span v-else>-</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Phone</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ client.phone || '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Company Details</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Address</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">
                                    {{ client.address }}<br v-if="client.city || client.postal_code">
                                    <span v-if="client.postal_code">{{ client.postal_code }}</span>
                                    <span v-if="client.city">{{ client.city }}</span><br v-if="client.city || client.postal_code">
                                    {{ client.country || 'Czech Republic' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">VAT ID (DIČ)</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ client.vat_id || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Company ID (IČO)</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ client.company_id || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</dt>
                                <dd class="text-sm">
                                    <span 
                                        :class="client.is_active !== false ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-full text-xs font-medium"
                                    >
                                        {{ client.is_active !== false ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="client.notes" class="md:col-span-2 rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
                        <h3 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">Notes</h3>
                        <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ client.notes }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoices</h3>
                    <Link 
                        :href="invoicesCreate({ client_id: client.id }).url"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition-colors"
                    >
                        Create Invoice
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Invoice Number</th>
                                <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Issue Date</th>
                                <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Due Date</th>
                                <th class="px-4 py-2 text-right text-gray-900 dark:text-white">Total</th>
                                <th class="px-4 py-2 text-center text-gray-900 dark:text-white">Status</th>
                                <th class="px-4 py-2 text-center text-gray-900 dark:text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in client.invoices" :key="invoice.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-2">
                                    <Link :href="invoicesShow(invoice.id).url" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ invoice.invoice_number }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ formatDate(invoice.issue_date) }}</td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ formatDate(invoice.due_date) }}</td>
                                <td class="px-4 py-2 text-right text-gray-900 dark:text-white">{{ formatCurrency(invoice.total) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span 
                                        :class="getStatusClass(invoice.status)"
                                        class="px-2 py-1 rounded-full text-xs font-medium"
                                    >
                                        {{ invoice.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <Link 
                                        :href="invoicesShow(invoice.id).url"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!client.invoices || client.invoices.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No invoices found for this client.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { index as clientsIndex, edit as clientsEdit } from '@/routes/clients';
import { create as invoicesCreate, show as invoicesShow } from '@/routes/invoices';

const props = defineProps({
    client: Object
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
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
        'sent': 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
        'overdue': 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
        'cancelled': 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400';
};
</script>