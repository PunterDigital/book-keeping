<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\MonthlyReport;
use App\Jobs\SendMonthlyReport;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(): Response
    {
        $reports = MonthlyReport::orderBy('period_start', 'desc')->get();
        
        // Get current reporting period (14th to 14th)
        // If today is the 15th or later, we're in the next reporting period
        $now = Carbon::now();
        if ($now->day >= 15) {
            // Period runs from 14th of current month to 14th of next month
            $periodStart = $now->copy()->day(14)->setTime(0, 0, 0);
            $periodEnd = $now->copy()->addMonth()->day(14)->setTime(23, 59, 59);
        } else {
            // Period runs from 14th of previous month to 14th of current month
            $periodStart = $now->copy()->subMonth()->day(14)->setTime(0, 0, 0);
            $periodEnd = $now->copy()->day(14)->setTime(23, 59, 59);
        }

        // Get current period data
        $currentInvoices = Invoice::whereBetween('issue_date', [$periodStart, $periodEnd])
            ->with('client')
            ->get();
            
        $currentExpenses = Expense::whereBetween('date', [$periodStart, $periodEnd])
            ->with('category')
            ->get();

        return Inertia::render('MonthlyReports/Index', [
            'reports' => $reports,
            'currentPeriod' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'currentInvoices' => $currentInvoices,
            'currentExpenses' => $currentExpenses,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $periodStart = Carbon::parse($request->period_start);
        $periodEnd = Carbon::parse($request->period_end);

        // Check if report already exists for this period
        $existingReport = MonthlyReport::where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($existingReport) {
            return redirect()->route('monthly-reports.index')
                ->with('error', 'Report for this period already exists.');
        }

        // Create the report record
        $report = MonthlyReport::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_at' => now(),
            'email_status' => 'pending',
        ]);

        return redirect()->route('monthly-reports.index')
            ->with('success', 'Monthly report generated successfully.');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'report_id' => 'required|exists:monthly_reports,id',
        ]);

        $report = MonthlyReport::findOrFail($request->report_id);

        // Dispatch the job to send the report in the background
        SendMonthlyReport::dispatch($report);

        return redirect()->route('monthly-reports.index')
            ->with('success', 'Monthly report is being prepared and will be sent to the accountant shortly.');
    }

    public function sendNow(Request $request, EmailService $emailService): RedirectResponse
    {
        $request->validate([
            'report_id' => 'required|exists:monthly_reports,id',
        ]);

        $report = MonthlyReport::findOrFail($request->report_id);

        try {
            $success = $emailService->generateAndSendMonthlyReport($report);

            if ($success) {
                $report->update([
                    'sent_at' => now(),
                    'email_status' => 'sent',
                ]);

                return redirect()->route('monthly-reports.index')
                    ->with('success', 'Monthly report sent to accountant successfully.');
            } else {
                $report->update(['email_status' => 'failed']);
                return redirect()->route('monthly-reports.index')
                    ->with('error', 'Failed to send monthly report.');
            }

        } catch (\Exception $e) {
            $report->update(['email_status' => 'failed']);
            
            return redirect()->route('monthly-reports.index')
                ->with('error', 'Failed to send report: ' . $e->getMessage());
        }
    }
}