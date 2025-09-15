<template>
    <AppLayout title="Clients" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Manage your client information and relationships</p>
                    </div>
                    <Link
                        :href="clientsCreate().url"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors inline-flex items-center"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Add New Client
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

            <!-- Clients Table Card -->
            <div class="rounded-lg border inset card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact Person</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VAT ID</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoices</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Revenue</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link :href="clientsShow(client.id).url" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        {{ client.company_name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ client.contact_name || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ client.vat_id || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ client.invoices_count || 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(client.total_revenue || 0) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <div class="flex justify-center items-center space-x-2">
                                        <Link
                                            :href="clientsEdit(client.id).url"
                                            class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            <Edit2 class="w-4 h-4" />
                                        </Link>
                                        <button
                                            @click="deleteClient(client.id)"
                                            class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                                            :disabled="client.invoices_count > 0"
                                            :class="{ 'opacity-50 cursor-not-allowed': client.invoices_count > 0 }"
                                            :title="client.invoices_count > 0 ? 'Cannot delete client with invoices' : 'Delete client'"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="clients.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <Users class="h-12 w-12 text-gray-400 mb-4" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No clients found.</p>
                                        <Link :href="clientsCreate().url" class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            Add your first client
                                        </Link>
                                    </div>
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
import { Link, router } from '@inertiajs/vue3';
import { create as clientsCreate, show as clientsShow, edit as clientsEdit, destroy as clientsDestroy } from '@/routes/clients';
import { Plus, Edit2, Trash2, Users } from 'lucide-vue-next';

const props = defineProps({
    clients: Array
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount);
};

const deleteClient = (id) => {
    if (confirm('Are you sure you want to delete this client?')) {
        router.visit(clientsDestroy(id).url, { method: 'delete' });
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clients', href: '/client' },
];
</script>
