<template>
    <AppLayout title="Edit Client">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Client</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Update client information and business details</p>
            </div>

            <!-- Form Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="company_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company Name *</label>
                                    <input 
                                        type="text" 
                                        id="company_name"
                                        v-model="form.company_name"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    />
                                    <div v-if="form.errors.company_name" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.company_name }}</div>
                                </div>

                                <div>
                                    <label for="contact_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact Person</label>
                                    <input 
                                        type="text" 
                                        id="contact_name"
                                        v-model="form.contact_name"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                    <input 
                                        type="email" 
                                        id="email"
                                        v-model="form.email"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                    <div v-if="form.errors.email" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.email }}</div>
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                                    <input 
                                        type="tel" 
                                        id="phone"
                                        v-model="form.phone"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address *</label>
                                    <textarea 
                                        id="address"
                                        v-model="form.address"
                                        rows="2"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    ></textarea>
                                    <div v-if="form.errors.address" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.address }}</div>
                                </div>

                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">City</label>
                                    <input 
                                        type="text" 
                                        id="city"
                                        v-model="form.city"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Postal Code</label>
                                    <input
                                        type="text"
                                        id="postal_code"
                                        v-model="form.postal_code"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Country *</label>
                                    <select
                                        id="country"
                                        v-model="form.country"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                        required
                                    >
                                        <option value="Czech Republic">Czech Republic</option>
                                        <option value="Austria">Austria</option>
                                        <option value="Belgium">Belgium</option>
                                        <option value="Bulgaria">Bulgaria</option>
                                        <option value="Croatia">Croatia</option>
                                        <option value="Cyprus">Cyprus</option>
                                        <option value="Denmark">Denmark</option>
                                        <option value="Estonia">Estonia</option>
                                        <option value="Finland">Finland</option>
                                        <option value="France">France</option>
                                        <option value="Germany">Germany</option>
                                        <option value="Greece">Greece</option>
                                        <option value="Hungary">Hungary</option>
                                        <option value="Ireland">Ireland</option>
                                        <option value="Italy">Italy</option>
                                        <option value="Latvia">Latvia</option>
                                        <option value="Lithuania">Lithuania</option>
                                        <option value="Luxembourg">Luxembourg</option>
                                        <option value="Malta">Malta</option>
                                        <option value="Netherlands">Netherlands</option>
                                        <option value="Poland">Poland</option>
                                        <option value="Portugal">Portugal</option>
                                        <option value="Romania">Romania</option>
                                        <option value="Slovakia">Slovakia</option>
                                        <option value="Slovenia">Slovenia</option>
                                        <option value="Spain">Spain</option>
                                        <option value="Sweden">Sweden</option>
                                    </select>
                                    <div v-if="form.errors.country" class="text-red-600 dark:text-red-400 text-sm mt-2">{{ form.errors.country }}</div>
                                </div>

                                <div>
                                    <label for="vat_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">VAT ID (DIČ)</label>
                                    <input 
                                        type="text" 
                                        id="vat_id"
                                        v-model="form.vat_id"
                                        placeholder="CZ12345678"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div>
                                    <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company ID (IČO)</label>
                                    <input 
                                        type="text" 
                                        id="company_id"
                                        v-model="form.company_id"
                                        placeholder="12345678"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                                    <textarea 
                                        id="notes"
                                        v-model="form.notes"
                                        rows="3"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white transition-colors"
                                    ></textarea>
                                </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active Client</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Actions Card -->
            <div class="rounded-lg border inset card p-6 shadow-sm">
                <div class="flex items-center justify-end space-x-3">
                    <Link 
                        :href="clientsIndex.url()"
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                        <X class="w-4 h-4 mr-2" />
                        Cancel
                    </Link>
                    <button 
                        type="submit"
                        @click="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <Save class="w-4 h-4 mr-2" />
                        {{ form.processing ? 'Updating...' : 'Update Client' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { index as clientsIndex, update as clientsUpdate } from '@/routes/clients';
import { Save, X } from 'lucide-vue-next';

const props = defineProps({
    client: Object
});

const form = useForm({
    company_name: props.client.company_name || '',
    contact_name: props.client.contact_name || '',
    email: props.client.email || '',
    phone: props.client.phone || '',
    address: props.client.address || '',
    city: props.client.city || '',
    postal_code: props.client.postal_code || '',
    country: props.client.country || 'Czech Republic',
    vat_id: props.client.vat_id || '',
    company_id: props.client.company_id || '',
    notes: props.client.notes || '',
    is_active: props.client.is_active !== false
});

const submit = () => {
    form.submit(clientsUpdate.put(props.client.id));
};
</script>