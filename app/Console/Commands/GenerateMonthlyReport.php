<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\MonthlyReport;
use App\Jobs\SendMonthlyReport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-monthly
                            {--auto-send : Automatically send the report to the accountant}
                            {--period-start= : Override period start date (Y-m-d format)}
                            {--period-end= : Override period end date (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly report for the accountant (14th to 14th period)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly report generation...');

        // Calculate reporting period
        if ($this->option('period-start') && $this->option('period-end')) {
            $periodStart = Carbon::parse($this->option('period-start'))->setTime(0, 0, 0);
            $periodEnd = Carbon::parse($this->option('period-end'))->setTime(23, 59, 59);
            $this->info("Using custom period: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}");
        } else {
            // Default: Previous month's 14th to current month's 14th
            // This command is meant to run on the 15th to generate the previous period
            $now = Carbon::now();

            // Always generate for the just-completed period
            if ($now->day >= 15) {
                // If running on or after 15th, generate for 14th last month to 14th this month
                $periodStart = $now->copy()->subMonth()->day(14)->setTime(0, 0, 0);
                $periodEnd = $now->copy()->day(14)->setTime(23, 59, 59);
            } else {
                // If running before 15th (shouldn't normally happen), generate for month before
                $periodStart = $now->copy()->subMonths(2)->day(14)->setTime(0, 0, 0);
                $periodEnd = $now->copy()->subMonth()->day(14)->setTime(23, 59, 59);
            }

            $this->info("Generating report for period: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}");
        }

        // Check if report already exists
        $existingReport = MonthlyReport::where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($existingReport) {
            $this->warn('Report for this period already exists.');

            if ($this->confirm('Do you want to regenerate it?')) {
                $existingReport->update([
                    'generated_at' => now(),
                    'email_status' => 'pending',
                ]);
                $report = $existingReport;
                $this->info('Report regenerated.');
            } else {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        } else {
            // Create new report
            $report = MonthlyReport::create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'generated_at' => now(),
                'email_status' => 'pending',
            ]);
            $this->info('New report created.');
        }

        // Get statistics for the period
        $invoiceCount = Invoice::whereBetween('issue_date', [$periodStart, $periodEnd])->count();
        $expenseCount = Expense::whereBetween('date', [$periodStart, $periodEnd])->count();

        $this->info("Report includes:");
        $this->info("  - {$invoiceCount} invoices");
        $this->info("  - {$expenseCount} expenses");

        // Auto-send if requested
        if ($this->option('auto-send')) {
            $this->info('Dispatching email job...');
            SendMonthlyReport::dispatch($report);
            $this->info('Report will be sent to the accountant shortly.');
        } else {
            $this->info('Report generated successfully. Use --auto-send flag to send automatically.');
        }

        return Command::SUCCESS;
    }
}
