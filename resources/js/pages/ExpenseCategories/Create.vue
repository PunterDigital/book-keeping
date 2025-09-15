<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, X } from 'lucide-vue-next';

const form = useForm({
    name: ''
});

const submit = () => {
    form.post('/expense-categories', {
        onSuccess: () => {
            // Will redirect to index with success message
        }
    });
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Categories', href: '/expense-categories' },
    { title: 'Create Category', href: '/expense-categories/create' },
];
</script>

<template>
    <Head title="Create Expense Category" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Expense Category</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Add a new category for organizing your expenses</p>
            </div>

            <!-- Form Card -->
            <div class="max-w-2xl">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-lg border inset card p-6 shadow-sm">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Category Name
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                placeholder="e.g. Office Supplies, Travel, Software"
                                required
                            />
                            <div v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="rounded-lg border inset card p-6 shadow-sm">
                        <div class="flex items-center justify-end space-x-3">
                            <a 
                                href="/expense-categories"
                                class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                            >
                                <X class="w-4 h-4 mr-2" />
                                Cancel
                            </a>
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <Save class="w-4 h-4 mr-2" />
                                <span v-if="form.processing">Creating...</span>
                                <span v-else>Create Category</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>