<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Package } from 'lucide-vue-next';
import { ref } from 'vue';

interface ExpenseCategory {
    id: number;
    name: string;
    expenses_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    categories: ExpenseCategory[];
}

const props = defineProps<Props>();

const deleteCategory = (category: ExpenseCategory) => {
    if (confirm(`Are you sure you want to delete "${category.name}"?`)) {
        router.delete(`/expense-categories/${category.id}`, {
            onSuccess: () => {
                // Success message will be handled by the flash message system
            }
        });
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Categories', href: '/expense-categories' },
];
</script>

<template>
    <Head title="Expense Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expense Categories</h1>
                        <p class="text-gray-600 dark:text-gray-400">Manage your expense categories</p>
                    </div>
                    <Link 
                        href="/expense-categories/create"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                        <Plus class="w-4 h-4 inline mr-2" />
                        Add Category
                    </Link>
                </div>
            </div>

            <!-- Categories Grid -->
            <div v-if="categories.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div 
                    v-for="category in categories" 
                    :key="category.id"
                    class="rounded-lg border inset card p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                <Package class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ category.name }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ category.expenses_count || 0 }} expenses
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-1">
                            <Link 
                                :href="`/expense-categories/${category.id}/edit`"
                                class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                                title="Edit category"
                            >
                                <Edit class="w-4 h-4" />
                            </Link>
                            <button 
                                @click="deleteCategory(category)"
                                class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                                title="Delete category"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Created {{ new Date(category.created_at).toLocaleDateString() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-lg border inset card p-12 shadow-sm text-center">
                <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-700">
                    <Package class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No categories yet</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get started by creating your first expense category.</p>
                <Link 
                    href="/expense-categories/create"
                    class="mt-6 inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                >
                    <Plus class="w-4 h-4 mr-2" />
                    Add Your First Category
                </Link>
            </div>
        </div>
    </AppLayout>
</template>