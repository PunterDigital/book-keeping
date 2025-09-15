<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Receipt, Users, TrendingUp, ArrowUpRight, ArrowDownLeft } from 'lucide-vue-next';

interface Stats {
    expenses_this_month: number;
    expenses_amount: number;
    expenses_vat: number;
    invoices_this_month: number;
    invoices_total: number;
    invoices_subtotal: number;
    invoices_vat: number;
    active_clients: number;
    total_clients: number;
}

interface Activity {
    type: 'expense' | 'invoice';
    id: number;
    description: string;
    amount: number;
    category: string;
    date: string;
    created_at: string;
}

interface NextReportPeriod {
    start: string;
    end: string;
}

interface Props {
    stats: Stats;
    recentActivity: Activity[];
    nextReportPeriod: NextReportPeriod;
}

const props = defineProps<Props>();

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK'
    }).format(amount);
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard - Bookkeeping" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <FileText class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.invoices_this_month }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Invoices This Month</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                            <Receipt class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.expenses_this_month }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Expenses This Month</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/20">
                            <Users class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.active_clients }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Active Clients</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/20">
                            <TrendingUp class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(stats.invoices_total) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Revenue This Month</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Quick Actions</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Link href="/expenses/create" class="flex items-center justify-center rounded-lg bg-blue-50 p-4 text-center transition-colors hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30">
                            <div>
                                <Receipt class="mx-auto mb-2 h-6 w-6 text-blue-600 dark:text-blue-400" />
                                <p class="text-sm font-medium text-blue-900 dark:text-blue-300">Add Expense</p>
                            </div>
                        </Link>

                        <Link href="/invoices/create" class="flex items-center justify-center rounded-lg bg-green-50 p-4 text-center transition-colors hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30">
                            <div>
                                <FileText class="mx-auto mb-2 h-6 w-6 text-green-600 dark:text-green-400" />
                                <p class="text-sm font-medium text-green-900 dark:text-green-300">Create Invoice</p>
                            </div>
                        </Link>

                        <Link href="/clients/create" class="flex items-center justify-center rounded-lg bg-purple-50 p-4 text-center transition-colors hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/30">
                            <div>
                                <Users class="mx-auto mb-2 h-6 w-6 text-purple-600 dark:text-purple-400" />
                                <p class="text-sm font-medium text-purple-900 dark:text-purple-300">Add Client</p>
                            </div>
                        </Link>

                        <Link href="/reports" class="flex items-center justify-center rounded-lg bg-orange-50 p-4 text-center transition-colors hover:bg-orange-100 dark:bg-orange-900/20 dark:hover:bg-orange-900/30">
                            <div>
                                <TrendingUp class="mx-auto mb-2 h-6 w-6 text-orange-600 dark:text-orange-400" />
                                <p class="text-sm font-medium text-orange-900 dark:text-orange-300">Monthly Report</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="rounded-lg border inset card p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                    <div class="space-y-3" v-if="recentActivity.length > 0">
                        <div
                            v-for="activity in recentActivity"
                            :key="`${activity.type}-${activity.id}`"
                            class="flex items-center space-x-3 p-3 rounded-lg border dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 hover:bg-gray-100/50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg"
                                 :class="activity.type === 'expense'
                                    ? 'bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800'
                                    : 'bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800'"
                            >
                                <ArrowDownLeft v-if="activity.type === 'expense'"
                                              class="h-5 w-5 text-red-600 dark:text-red-400" />
                                <ArrowUpRight v-else
                                             class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ activity.description }}
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ activity.category }} • {{ new Date(activity.date).toLocaleDateString() }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-sm font-semibold"
                                   :class="activity.type === 'expense'
                                      ? 'text-red-600 dark:text-red-400'
                                      : 'text-green-600 dark:text-green-400'"
                                >
                                    {{ activity.type === 'expense' ? '-' : '+' }}{{ formatCurrency(activity.amount) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <p class="text-gray-500 dark:text-gray-400">No recent activity</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Start by adding your first expense or invoice</p>
                    </div>
                </div>
            </div>

            <!-- Next Monthly Report -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Accountant Report</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Current period: {{ nextReportPeriod.start }} - {{ nextReportPeriod.end }}
                        </p>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ stats.expenses_this_month }} expenses ({{ formatCurrency(stats.expenses_amount) }}) •
                            {{ stats.invoices_this_month }} invoices ({{ formatCurrency(stats.invoices_total) }})
                        </div>
                    </div>
                    <Link href="/reports" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        View Reports
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
