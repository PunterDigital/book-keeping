<template>
  <AppLayout title="Monthly Reports">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">📊 Monthly Accountant Reports</h2>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Generate and send automated monthly reports to your accountant
          </p>
        </div>
        
        <button 
          @click="showGenerateModal = true"
          class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors flex items-center"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          Generate Report
        </button>
      </div>

      <!-- Current Period Info -->
      <div class="rounded-lg border border-sidebar-border/70 bg-white p-6 shadow-sm dark:border-sidebar-border dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📅 Current Reporting Period</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Period</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(currentPeriod.start) }} - {{ formatDate(currentPeriod.end) }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Invoices</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ currentInvoices.length }} invoices</p>
          </div>
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Expenses</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ currentExpenses.length }} expenses</p>
          </div>
        </div>
      </div>

      <!-- Reports History -->
      <div class="rounded-lg border border-sidebar-border/70 bg-white shadow-sm dark:border-sidebar-border dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-sidebar-border/70 dark:border-sidebar-border">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Report History</h3>
        </div>
        
        <div v-if="reports.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
          <MailIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
          <p class="font-medium">No reports generated yet</p>
          <p class="text-sm">Generate your first monthly report for your accountant</p>
        </div>
        
        <div v-else>
          <div 
            v-for="report in reports" 
            :key="report.id"
            class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0 p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center mb-2">
                  <CalendarIcon class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
                  <span class="font-medium text-gray-900 dark:text-white">
                    {{ formatDate(report.period_start) }} - {{ formatDate(report.period_end) }}
                  </span>
                  <span 
                    :class="getStatusBadgeClass(report.email_status)"
                    class="ml-3 px-2 py-1 text-xs rounded-full font-medium"
                  >
                    {{ getStatusLabel(report.email_status) }}
                  </span>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 dark:text-gray-400">
                  <div class="flex items-center">
                    <ClockIcon class="w-4 h-4 mr-1" />
                    Generated: {{ formatDateTime(report.generated_at) }}
                  </div>
                  <div v-if="report.sent_at" class="flex items-center">
                    <CheckIcon class="w-4 h-4 mr-1" />
                    Sent: {{ formatDateTime(report.sent_at) }}
                  </div>
                </div>
              </div>
              
              <div class="flex items-center space-x-2">
                <button 
                  v-if="report.email_status === 'pending' || report.email_status === 'failed'"
                  @click="sendReport(report.id)"
                  :disabled="sending[report.id]"
                  class="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors flex items-center"
                >
                  <template v-if="sending[report.id]">
                    <LoaderIcon class="w-4 h-4 mr-1 animate-spin" />
                    Sending...
                  </template>
                  <template v-else>
                    <SendIcon class="w-4 h-4 mr-1" />
                    Send Email
                  </template>
                </button>
                
                <button 
                  @click="sendReportNow(report.id)"
                  :disabled="sending[report.id]"
                  class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center"
                >
                  <template v-if="sending[report.id]">
                    <LoaderIcon class="w-4 h-4 mr-1 animate-spin" />
                    Sending...
                  </template>
                  <template v-else>
                    <ZapIcon class="w-4 h-4 mr-1" />
                    Send Now
                  </template>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Generate Report Modal -->
    <Dialog :open="showGenerateModal" @update:open="showGenerateModal = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Generate Monthly Report</DialogTitle>
        </DialogHeader>
        
        <form @submit.prevent="generateReport" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Period Start</label>
              <input 
                v-model="generateForm.period_start"
                type="date" 
                required
                class="w-full border border-sidebar-border/70 dark:border-sidebar-border rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Period End</label>
              <input 
                v-model="generateForm.period_end"
                type="date" 
                required
                class="w-full border border-sidebar-border/70 dark:border-sidebar-border rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>
          
          <div class="flex justify-end space-x-3 pt-4">
            <button 
              type="button"
              @click="closeGenerateModal"
              class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
            >
              Cancel
            </button>
            <button 
              type="submit"
              :disabled="generateForm.processing"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center"
            >
              <template v-if="generateForm.processing">
                <LoaderIcon class="w-4 h-4 mr-2 animate-spin" />
                Generating...
              </template>
              <template v-else>
                Generate Report
              </template>
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Dialog from '@/Components/ui/dialog/Dialog.vue'
import DialogContent from '@/Components/ui/dialog/DialogContent.vue'
import DialogHeader from '@/Components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/Components/ui/dialog/DialogTitle.vue'
import { 
  PlusIcon, 
  CalendarIcon, 
  ClockIcon, 
  CheckIcon, 
  SendIcon, 
  MailIcon,
  LoaderIcon,
  ZapIcon
} from 'lucide-vue-next'

export default {
  components: {
    AppLayout,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    PlusIcon,
    CalendarIcon,
    ClockIcon,
    CheckIcon,
    SendIcon,
    MailIcon,
    LoaderIcon,
    ZapIcon
  },
  
  props: {
    reports: Array,
    currentPeriod: Object,
    currentInvoices: Array,
    currentExpenses: Array
  },
  
  setup(props) {
    const showGenerateModal = ref(false)
    const sending = ref({})
    
    const generateForm = reactive({
      period_start: props.currentPeriod.start,
      period_end: props.currentPeriod.end,
      processing: false
    })

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('cs-CZ')
    }

    const formatDateTime = (dateString) => {
      return new Date(dateString).toLocaleString('cs-CZ')
    }

    const getStatusLabel = (status) => {
      const labels = {
        pending: 'Pending',
        sent: 'Sent',
        failed: 'Failed'
      }
      return labels[status] || status
    }

    const getStatusBadgeClass = (status) => {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        sent: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const generateReport = () => {
      generateForm.processing = true
      
      router.post('/monthly-reports/generate', {
        period_start: generateForm.period_start,
        period_end: generateForm.period_end
      }, {
        onFinish: () => {
          generateForm.processing = false
          closeGenerateModal()
        }
      })
    }

    const sendReport = (reportId) => {
      sending.value[reportId] = true
      
      router.post('/monthly-reports/send', {
        report_id: reportId
      }, {
        onFinish: () => {
          sending.value[reportId] = false
        }
      })
    }

    const sendReportNow = (reportId) => {
      sending.value[reportId] = true
      
      router.post('/monthly-reports/send-now', {
        report_id: reportId
      }, {
        onFinish: () => {
          sending.value[reportId] = false
        }
      })
    }

    const closeGenerateModal = () => {
      showGenerateModal.value = false
      generateForm.processing = false
    }

    return {
      showGenerateModal,
      generateForm,
      sending,
      formatDate,
      formatDateTime,
      getStatusLabel,
      getStatusBadgeClass,
      generateReport,
      sendReport,
      sendReportNow,
      closeGenerateModal
    }
  }
}
</script>