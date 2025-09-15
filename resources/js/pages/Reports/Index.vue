<template>
    <AppLayout title="Reports" :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reports</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Generate business reports and statements</p>
                    </div>
                </div>
            </div>

            <!-- Reports Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link href="/reports/expenses" class="rounded-lg border inset card p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20">
                            <Receipt class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Expense Reports</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Generate monthly, yearly, or custom expense reports</p>
                            <div class="mt-3 text-blue-600 dark:text-blue-400 text-sm font-medium">View Reports →</div>
                        </div>
                    </div>
                </Link>
                
                <Link href="/reports/vat" class="rounded-lg border inset card p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <Calculator class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">VAT Reports</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Generate VAT reports for tax compliance</p>
                            <div class="mt-3 text-blue-600 dark:text-blue-400 text-sm font-medium">View Reports →</div>
                        </div>
                    </div>
                </Link>
                
                <Link href="/reports/client-statement" class="rounded-lg border inset card p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/20">
                            <Users class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Client Statements</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Generate client statements and account summaries</p>
                            <div class="mt-3 text-blue-600 dark:text-blue-400 text-sm font-medium">View Reports →</div>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { Receipt, Calculator, Users } from 'lucide-vue-next';

const props = defineProps({
    current_year: Number,
    current_month: Number,
    available_years: Array,
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];
</script>