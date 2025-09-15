<?php

namespace App\Jobs;

use App\Models\MonthlyReport;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MonthlyReport $report;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    public function __construct(MonthlyReport $report)
    {
        $this->report = $report;
    }

    public function handle(EmailService $emailService)
    {
        try {
            \Log::info('Starting monthly report email job', [
                'report_id' => $this->report->id,
                'period' => $this->report->period_start . ' to ' . $this->report->period_end
            ]);

            // Generate and send the monthly report
            $success = $emailService->generateAndSendMonthlyReport($this->report);

            if ($success) {
                $this->report->update([
                    'sent_at' => now(),
                    'email_status' => 'sent'
                ]);

                \Log::info('Monthly report email sent successfully', [
                    'report_id' => $this->report->id
                ]);
            } else {
                throw new \Exception('Email service returned false');
            }

        } catch (\Exception $e) {
            $this->report->update(['email_status' => 'failed']);
            
            \Log::error('Monthly report email job failed', [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
                'attempts' => $this->attempts()
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        // This method will be called when the job has exhausted all retry attempts
        $this->report->update(['email_status' => 'failed']);
        
        \Log::error('Monthly report email job permanently failed', [
            'report_id' => $this->report->id,
            'error' => $exception->getMessage()
        ]);
    }
}