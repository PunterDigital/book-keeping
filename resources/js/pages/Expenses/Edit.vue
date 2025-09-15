<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Save, X, Upload, Download, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface ExpenseCategory {
    id: number;
    name: string;
}

interface Expense {
    id: number;
    date: string;
    amount: number;
    vat_amount: number;
    description: string;
    receipt_path?: string;
    category: ExpenseCategory;
    created_at: string;
    updated_at: string;
}

interface Props {
    expense: Expense;
    categories: ExpenseCategory[];
}

const props = defineProps<Props>();

// Debug: log the date format
console.log('Raw expense date:', props.expense.date);
console.log('Date type:', typeof props.expense.date);

// Helper function to format date for HTML date input
const formatDateForInput = (date: string): string => {
    if (!date) return '';
    
    // If it's already in YYYY-MM-DD format, return as is
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
    }
    
    // Try to parse various date formats
    const dateObj = new Date(date);
    if (isNaN(dateObj.getTime())) {
        console.error('Invalid date:', date);
        return '';
    }
    
    // Format as YYYY-MM-DD for HTML date input
    return dateObj.toISOString().split('T')[0];
};

const form = useForm({
    date: formatDateForInput(props.expense.date),
    amount: props.expense.amount.toString(),
    category_id: props.expense.category.id.toString(),
    description: props.expense.description,
    vat_amount: props.expense.vat_amount.toString(),
    receipt: null as File | null
});

// VAT calculation helper
const vatRates = [
    { label: '21% (Standard)', rate: 0.21 },
    { label: '12% (Reduced)', rate: 0.12 },
    { label: '0% (Exempt)', rate: 0 },
];

// Calculate current VAT rate from existing amounts
const currentVatRate = computed(() => {
    if (props.expense.amount > 0) {
        const rate = props.expense.vat_amount / props.expense.amount;
        return Math.round(rate * 100) / 100; // Round to 2 decimal places
    }
    return 0.21;
});

const selectedVatRate = ref(currentVatRate.value);

// Auto-calculate VAT when amount changes
const calculateVat = () => {
    if (form.amount) {
        const amount = parseFloat(form.amount);
        const vatAmount = amount * selectedVatRate.value;
        form.vat_amount = vatAmount.toFixed(2);
    }
};

const onVatRateChange = () => {
    calculateVat();
};

// File upload handling
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFileName = ref<string>('');

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.receipt = target.files[0];
        selectedFileName.value = target.files[0].name;
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const removeFile = () => {
    form.receipt = null;
    selectedFileName.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.put(`/expenses/${props.expense.id}`, {
        onSuccess: () => {
            // Will redirect to index with success message
        }
    });
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
    { title: 'Edit Expense', href: `/expenses/${props.expense.id}/edit` },
];
</script>

<template>
    <Head title="Edit Expense" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Expense</h1>
                <p class="text-gray-600 dark:text-gray-400">Update the expense details</p>
            </div>

            <!-- Form -->
            <div class="max-w-2xl">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-lg border inset card p-6 shadow-sm">
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Date -->
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Date <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="date"
                                    v-model="form.date"
                                    type="date"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    required
                                />
                                <div v-if="form.errors.date" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.date }}
                                </div>
                            </div>

                            <!-- Category -->
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Category <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    required
                                >
                                    <option value="">Select a category</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.category_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.category_id }}
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-6">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors placeholder-gray-400 dark:placeholder-gray-500"
                                placeholder="Describe the business expense..."
                                required
                            />
                            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.description }}
                            </div>
                        </div>

                        <!-- Amount and VAT -->
                        <div class="mt-6 grid gap-6 md:grid-cols-3">
                            <!-- Amount -->
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Amount (CZK) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    placeholder="0.00"
                                    @input="calculateVat"
                                    required
                                />
                                <div v-if="form.errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.amount }}
                                </div>
                            </div>

                            <!-- VAT Rate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    VAT Rate
                                </label>
                                <select
                                    v-model="selectedVatRate"
                                    @change="onVatRateChange"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                >
                                    <option v-for="rate in vatRates" :key="rate.rate" :value="rate.rate">
                                        {{ rate.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- VAT Amount -->
                            <div>
                                <label for="vat_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    VAT Amount (CZK) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="vat_amount"
                                    v-model="form.vat_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    placeholder="0.00"
                                    required
                                />
                                <div v-if="form.errors.vat_amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.vat_amount }}
                                </div>
                            </div>
                        </div>

                        <!-- Current Receipt -->
                        <div v-if="expense.receipt_path" class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Current Receipt
                            </label>
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="flex items-center">
                                    <Download class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    <span class="ml-2 text-gray-900 dark:text-white">Receipt file</span>
                                </div>
                                <a 
                                    :href="`/expenses/${expense.id}/receipt/download`" 
                                    download
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300"
                                >
                                    Download
                                </a>
                            </div>
                        </div>

                        <!-- Receipt Upload -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ expense.receipt_path ? 'Replace Receipt (Optional)' : 'Receipt (Optional)' }}
                            </label>
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 bg-gray-50 dark:bg-gray-950/50">
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    @change="handleFileChange"
                                    class="hidden"
                                />
                                
                                <div v-if="!selectedFileName" class="text-center">
                                    <Upload class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            @click="triggerFileInput"
                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-medium"
                                        >
                                            {{ expense.receipt_path ? 'Replace file' : 'Upload a file' }}
                                        </button>
                                        <span class="text-gray-500 dark:text-gray-400"> or drag and drop</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">PDF, PNG, JPG up to 10MB</p>
                                </div>
                                
                                <div v-else class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <Upload class="h-5 w-5 text-green-600 dark:text-green-400" />
                                        <span class="ml-2 text-gray-900 dark:text-white">{{ selectedFileName }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeFile"
                                        class="text-red-600 dark:text-red-400 hover:text-red-500 dark:hover:text-red-300"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <div v-if="form.errors.receipt" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.receipt }}
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                            Created {{ new Date(expense.created_at).toLocaleDateString() }}
                            • Last updated {{ new Date(expense.updated_at).toLocaleDateString() }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-3">
                        <Link 
                            href="/expenses"
                            class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                            <X class="w-4 h-4 mr-2" />
                            Cancel
                        </Link>
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <Save class="w-4 h-4 mr-2" />
                            <span v-if="form.processing">Updating...</span>
                            <span v-else>Update Expense</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>