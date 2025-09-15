<template>
    <AppLayout title="Edit Invoice" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Invoice: {{ invoice.invoice_number }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Modify invoice details and line items</p>
            </div>

            <!-- Form Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">

                        <form @submit.prevent="submit">
                            <!-- Invoice Header -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div>
                                    <label for="invoice_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Invoice Number *</label>
                                    <input 
                                        type="text" 
                                        id="invoice_number"
                                        v-model="form.invoice_number"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    />
                                    <div v-if="form.errors.invoice_number" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.invoice_number }}</div>
                                </div>

                                <div>
                                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Client *</label>
                                    <select 
                                        id="client_id"
                                        v-model="form.client_id"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    >
                                        <option value="">Select a client...</option>
                                        <option v-for="client in clients" :key="client.id" :value="client.id">
                                            {{ client.company_name }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.client_id" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.client_id }}</div>
                                </div>

                                <div>
                                    <label for="issue_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Issue Date *</label>
                                    <input 
                                        type="date" 
                                        id="issue_date"
                                        v-model="form.issue_date"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    />
                                    <div v-if="form.errors.issue_date" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.issue_date }}</div>
                                </div>

                                <div>
                                    <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Due Date *</label>
                                    <input 
                                        type="date" 
                                        id="due_date"
                                        v-model="form.due_date"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    />
                                    <div v-if="form.errors.due_date" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.due_date }}</div>
                                </div>
                            </div>

                            <!-- Invoice Items -->
                            <div class="mb-8">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Items</h3>
                                    <button 
                                        type="button"
                                        @click="addItem"
                                        class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
                                    >
                                        Add Item
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description *</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Qty *</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Unit Price *</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">VAT %</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Subtotal</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">VAT Amount</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Total</th>
                                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="(item, index) in form.items" :key="item.id || index" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <td class="px-6 py-4">
                                                    <textarea
                                                        v-model="item.description"
                                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors text-sm resize-y min-h-[60px]"
                                                        placeholder="Item description"
                                                        rows="2"
                                                        required
                                                    ></textarea>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input 
                                                        type="number" 
                                                        v-model.number="item.quantity"
                                                        @input="calculateItemTotals(index)"
                                                        class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors text-sm text-center"
                                                        min="1"
                                                        required
                                                    />
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input 
                                                        type="number" 
                                                        v-model.number="item.unit_price"
                                                        @input="calculateItemTotals(index)"
                                                        class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors text-sm text-right"
                                                        step="0.01"
                                                        min="0"
                                                        required
                                                    />
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <select 
                                                        v-model.number="item.vat_rate"
                                                        @change="calculateItemTotals(index)"
                                                        class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors text-sm text-center"
                                                    >
                                                        <option v-for="rate in vatRates" :key="rate" :value="rate">{{ rate }}%</option>
                                                    </select>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-gray-100">{{ formatCurrency(item.subtotal) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-gray-100">{{ formatCurrency(item.vat_amount) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.total) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <button 
                                                        type="button"
                                                        @click="removeItem(index)"
                                                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        :disabled="form.items.length === 1"
                                                        :class="{ 'opacity-50 cursor-not-allowed': form.items.length === 1 }"
                                                        title="Remove item"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <td colspan="4" class="px-6 py-3 text-right font-medium text-gray-900 dark:text-white">Totals:</td>
                                                <td class="px-6 py-3 text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoiceSubtotal) }}</td>
                                                <td class="px-6 py-3 text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoiceVatAmount) }}</td>
                                                <td class="px-6 py-3 text-right font-bold text-lg text-gray-900 dark:text-white">{{ formatCurrency(invoiceTotal) }}</td>
                                                <td class="px-6 py-3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div v-if="form.errors.items" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.items }}</div>
                            </div>

                            <!-- Notes -->
                            <div class="mb-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                                <textarea 
                                    id="notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    placeholder="Additional notes or payment terms..."
                                ></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end space-x-3">
                                <Link 
                                    :href="invoicesShow(invoice.id).url"
                                    class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                >
                                    Cancel
                                </Link>
                                <button 
                                    type="submit"
                                    :disabled="form.processing || form.items.length === 0"
                                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    {{ form.processing ? 'Updating...' : 'Update Invoice' }}
                                </button>
                            </div>
                        </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { show as invoicesShow, update as invoicesUpdate } from '@/routes/invoices';

const props = defineProps({
    invoice: Object,
    clients: Array,
    vatRates: Array
});

const form = useForm({
    invoice_number: props.invoice.invoice_number || '',
    client_id: props.invoice.client_id || '',
    issue_date: props.invoice.issue_date || '',
    due_date: props.invoice.due_date || '',
    notes: props.invoice.notes || '',
    items: props.invoice.items?.map(item => ({
        id: item.id,
        description: item.description || '',
        quantity: item.quantity || 1,
        unit_price: item.unit_price || 0,
        vat_rate: item.vat_rate || 21,
        subtotal: item.subtotal || 0,
        vat_amount: item.vat_amount || 0,
        total: item.total || 0
    })) || []
});

const invoiceSubtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
});

const invoiceVatAmount = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.vat_amount || 0), 0);
});

const invoiceTotal = computed(() => {
    return invoiceSubtotal.value + invoiceVatAmount.value;
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount || 0);
};

const calculateItemTotals = (index) => {
    const item = form.items[index];
    const quantity = Number(item.quantity) || 0;
    const unitPrice = Number(item.unit_price) || 0;
    const vatRate = Number(item.vat_rate) || 0;

    item.subtotal = quantity * unitPrice;
    item.vat_amount = item.subtotal * (vatRate / 100);
    item.total = item.subtotal + item.vat_amount;
};

const addItem = () => {
    form.items.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        vat_rate: 21,
        subtotal: 0,
        vat_amount: 0,
        total: 0
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.submit(invoicesUpdate.put(props.invoice.id));
};

// Calculate totals for existing items on mount
onMounted(() => {
    form.items.forEach((_, index) => {
        calculateItemTotals(index);
    });
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Invoices', href: '/invoices' },
    { title: `Edit ${props.invoice.invoice_number}`, href: `/invoices/${props.invoice.id}/edit` },
];
</script>