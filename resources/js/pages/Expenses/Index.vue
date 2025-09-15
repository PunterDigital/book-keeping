<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Receipt, Filter, Search, Download } from 'lucide-vue-next';
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
    expenses: Expense[];
    categories: ExpenseCategory[];
}

const props = defineProps<Props>();

// Filtering
const searchTerm = ref('');
const selectedCategory = ref('');
const selectedMonth = ref('');

// Get unique months from expenses
const availableMonths = computed(() => {
    const months = new Set();
    props.expenses.forEach(expense => {
        const date = new Date(expense.date);
        const monthYear = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        months.add(monthYear);
    });
    return Array.from(months).sort().reverse();
});

// Filtered expenses
const filteredExpenses = computed(() => {
    return props.expenses.filter(expense => {
        const matchesSearch = expense.description.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
                            expense.category.name.toLowerCase().includes(searchTerm.value.toLowerCase());
        
        const matchesCategory = selectedCategory.value === '' || 
                              expense.category.id.toString() === selectedCategory.value;
        
        const matchesMonth = selectedMonth.value === '' || 
                           expense.date.substring(0, 7) === selectedMonth.value;
        
        return matchesSearch && matchesCategory && matchesMonth;
    });
});

// Totals
const totalAmount = computed(() => {
    return filteredExpenses.value.reduce((sum, expense) => sum + expense.amount, 0);
});

const totalVat = computed(() => {
    return filteredExpenses.value.reduce((sum, expense) => sum + expense.vat_amount, 0);
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount);
};

const deleteExpense = (expense: Expense) => {
    if (confirm(`Are you sure you want to delete this expense: "${expense.description}"?`)) {
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => {
                // Success message will be handled by the flash message system
            }
        });
    }
};

const clearFilters = () => {
    searchTerm.value = '';
    selectedCategory.value = '';
    selectedMonth.value = '';
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];
</script>

<template>
    <Head title="Expenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expenses</h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ filteredExpenses.length }} of {{ expenses.length }} expenses
                        • Total: {{ formatCurrency(totalAmount) }}
                        • VAT: {{ formatCurrency(totalVat) }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link 
                        href="/expense-categories"
                        class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        <Filter class="w-4 h-4 mr-2" />
                        Categories
                    </Link>
                    <Link 
                        href="/expenses/create"
                        class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Add Expense
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border inset card p-4 mb-6 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search expenses..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                        />
                    </div>

                    <!-- Category Filter -->
                    <select 
                        v-model="selectedCategory"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                    >
                        <option value="">All Categories</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id.toString()">
                            {{ category.name }}
                        </option>
                    </select>

                    <!-- Month Filter -->
                    <select 
                        v-model="selectedMonth"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                    >
                        <option value="">All Months</option>
                        <option v-for="month in availableMonths" :key="month" :value="month">
                            {{ new Date(month + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'long' }) }}
                        </option>
                    </select>

                    <!-- Clear Filters -->
                    <button 
                        @click="clearFilters"
                        class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Expenses List -->
            <div v-if="filteredExpenses.length > 0" class="space-y-4">
                <div 
                    v-for="expense in filteredExpenses" 
                    :key="expense.id"
                    class="rounded-lg border inset card p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                                <Receipt class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ expense.description }}</h3>
                                <div class="flex items-center space-x-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <span>{{ expense.category.name }}</span>
                                    <span>•</span>
                                    <span>{{ new Date(expense.date).toLocaleDateString() }}</span>
                                    <span v-if="expense.receipt_path">•</span>
                                    <a v-if="expense.receipt_path" :href="`/expenses/${expense.id}/receipt/download`" download class="flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300">
                                        <Download class="w-3 h-3 mr-1" />
                                        Receipt
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    {{ formatCurrency(expense.amount) }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    VAT: {{ formatCurrency(expense.vat_amount) }}
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <Link 
                                    :href="`/expenses/${expense.id}/edit`"
                                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                    title="Edit expense"
                                >
                                    <Edit class="w-4 h-4" />
                                </Link>
                                <button 
                                    @click="deleteExpense(expense)"
                                    class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                                    title="Delete expense"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <Receipt class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    {{ expenses.length === 0 ? 'No expenses yet' : 'No expenses match your filters' }}
                </h3>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ expenses.length === 0 
                        ? 'Get started by adding your first business expense.' 
                        : 'Try adjusting your search criteria or clear the filters.' 
                    }}
                </p>
                <div class="mt-4 space-x-3">
                    <Link 
                        v-if="expenses.length === 0"
                        href="/expenses/create"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Add Your First Expense
                    </Link>
                    <button 
                        v-if="expenses.length > 0"
                        @click="clearFilters"
                        class="inline-flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>