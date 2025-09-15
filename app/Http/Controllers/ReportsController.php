<?php

namespace App\Http\Controllers;

use App\Services\ExpenseReportService;
use App\Services\CzechVatReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/Index', [
            'current_year' => Carbon::now()->year,
            'current_month' => Carbon::now()->month,
            'available_years' => $this->getAvailableYears(),
        ]);
    }

    public function expensesIndex(): Response
    {
        return Inertia::render('Reports/ExpensesIndex', [
            'current_year' => Carbon::now()->year,
            'current_month' => Carbon::now()->month,
            'available_years' => $this->getAvailableYears(),
        ]);
    }

    public function vatIndex(): Response
    {
        return Inertia::render('Reports/VatIndex', [
            'current_year' => Carbon::now()->year,
            'available_years' => $this->getAvailableYears(),
        ]);
    }

    public function clientStatementIndex(): Response
    {
        $clients = \App\Models\Client::orderBy('company_name')->get();

        return Inertia::render('Reports/ClientStatementIndex', [
            'clients' => $clients,
            'current_year' => Carbon::now()->year,
        ]);
    }

    public function expenseMonthly(Request $request, ExpenseReportService $reportService): HttpResponse|Response
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (Carbon::now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
            'format' => 'required|in:pdf,html'
        ]);

        $reportData = $reportService->generateMonthlyReport($validated['year'], $validated['month']);

        if ($validated['format'] === 'pdf') {
            return $reportService->downloadExpensesPdf($reportData);
        }

        return Inertia::render('Reports/ExpensesReport', [
            'report_data' => $reportData,
            'report_type' => 'monthly'
        ]);
    }

    public function expenseYearly(Request $request, ExpenseReportService $reportService): HttpResponse|Response
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (Carbon::now()->year + 1),
            'format' => 'required|in:pdf,html'
        ]);

        $reportData = $reportService->generateYearlyReport($validated['year']);

        if ($validated['format'] === 'pdf') {
            return $reportService->downloadExpensesPdf($reportData);
        }

        return Inertia::render('Reports/ExpensesReport', [
            'report_data' => $reportData,
            'report_type' => 'yearly'
        ]);
    }

    public function expenseCustom(Request $request, ExpenseReportService $reportService): HttpResponse|Response
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,html'
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $reportData = $reportService->generateCustomReport($startDate, $endDate);

        if ($validated['format'] === 'pdf') {
            return $reportService->downloadExpensesPdf($reportData);
        }

        return Inertia::render('Reports/ExpensesReport', [
            'report_data' => $reportData,
            'report_type' => 'custom'
        ]);
    }

    public function vatReport(Request $request, CzechVatReportingService $czechVatService): HttpResponse|Response
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (Carbon::now()->year + 1),
            'quarter' => 'nullable|integer|min:1|max:4',
            'format' => 'required|in:pdf,html'
        ]);

        // Calculate date range based on quarter or full year
        if (isset($validated['quarter'])) {
            $startMonth = ($validated['quarter'] - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $startDate = Carbon::create($validated['year'], $startMonth, 1)->startOfMonth();
            $endDate = Carbon::create($validated['year'], $endMonth, 1)->endOfMonth();
        } else {
            $startDate = Carbon::create($validated['year'], 1, 1)->startOfYear();
            $endDate = Carbon::create($validated['year'], 12, 31)->endOfYear();
        }

        // Use enhanced Czech VAT reporting
        $vatData = $czechVatService->generateVatReport($startDate, $endDate, $validated['quarter'] ?? null);

        if ($validated['format'] === 'pdf') {
            return $this->downloadVatReportPdf($vatData);
        }

        return Inertia::render('Reports/VatReport', [
            'vat_data' => $vatData,
            'year' => $validated['year'],
            'quarter' => $validated['quarter'] ?? null
        ]);
    }

    public function clientStatement(Request $request): HttpResponse|Response
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,html'
        ]);

        $statementData = $this->generateClientStatement(
            $validated['client_id'],
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        if ($validated['format'] === 'pdf') {
            return $this->downloadClientStatementPdf($statementData);
        }

        return Inertia::render('Reports/ClientStatement', [
            'statement_data' => $statementData
        ]);
    }

    private function getAvailableYears(): array
    {
        // Get years from expenses and invoices
        // Using strftime for SQLite compatibility
        $expenseYears = \App\Models\Expense::selectRaw("strftime('%Y', date) as year")
            ->distinct()
            ->pluck('year')
            ->filter()
            ->toArray();

        $invoiceYears = \App\Models\Invoice::selectRaw("strftime('%Y', issue_date) as year")
            ->distinct()
            ->pluck('year')
            ->filter()
            ->toArray();

        $years = array_unique(array_merge($expenseYears, $invoiceYears));
        sort($years);

        return $years;
    }

    private function generateVatReport(Carbon $startDate, Carbon $endDate, ?int $quarter): array
    {
        // Get all invoices and expenses in the period
        $invoices = \App\Models\Invoice::with(['client', 'items'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->orderBy('issue_date')
            ->get();

        $expenses = \App\Models\Expense::with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Calculate VAT summary
        $vatSummary = $this->calculateVatSummary($invoices, $expenses);

        return [
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y'),
                'quarter' => $quarter,
                'year' => $startDate->year
            ],
            'invoices' => $invoices,
            'expenses' => $expenses,
            'vat_summary' => $vatSummary
        ];
    }

    private function calculateVatSummary($invoices, $expenses): array
    {
        $summary = [
            'output_vat' => [], // DPH na výstupu (z faktur)
            'input_vat' => [],  // DPH na vstupu (z výdajů)
            'net_vat' => []     // Čistá DPH k doplatku/vrácení
        ];

        // Output VAT from invoices (convert to CZK)
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $rate = $item->vat_rate;
                if (!isset($summary['output_vat'][$rate])) {
                    $summary['output_vat'][$rate] = ['base' => 0, 'vat' => 0];
                }

                // Convert amounts to CZK using invoice exchange rate
                $exchangeRate = $invoice->exchange_rate ?? 1.0;
                $subtotalCzk = $invoice->currency === 'CZK' ? $item->subtotal : $item->subtotal * $exchangeRate;
                $vatAmountCzk = $invoice->currency === 'CZK' ? $item->vat_amount : $item->vat_amount * $exchangeRate;

                $summary['output_vat'][$rate]['base'] += $subtotalCzk;
                $summary['output_vat'][$rate]['vat'] += $vatAmountCzk;
            }
        }

        // Input VAT from expenses (convert to CZK)
        foreach ($expenses as $expense) {
            $rate = $expense->vat_rate;
            if (!isset($summary['input_vat'][$rate])) {
                $summary['input_vat'][$rate] = ['base' => 0, 'vat' => 0];
            }

            // Convert amounts to CZK using expense's getAmountInCzk() method
            $amountCzk = $expense->getAmountInCzk();
            $vatAmountCzk = $expense->currency === 'CZK' ? $expense->vat_amount : $expense->vat_amount * $expense->exchange_rate;

            $summary['input_vat'][$rate]['base'] += $amountCzk;
            $summary['input_vat'][$rate]['vat'] += $vatAmountCzk;
        }

        // Calculate net VAT
        $allRates = array_unique(array_merge(
            array_keys($summary['output_vat']),
            array_keys($summary['input_vat'])
        ));

        foreach ($allRates as $rate) {
            $outputVat = $summary['output_vat'][$rate]['vat'] ?? 0;
            $inputVat = $summary['input_vat'][$rate]['vat'] ?? 0;
            $summary['net_vat'][$rate] = $outputVat - $inputVat;
        }

        return $summary;
    }

    private function generateClientStatement(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $client = \App\Models\Client::findOrFail($clientId);

        $invoices = \App\Models\Invoice::with('items')
            ->where('client_id', $clientId)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->orderBy('issue_date')
            ->get();

        $summary = [
            'total_invoiced' => $invoices->sum(function ($invoice) {
                return $invoice->getTotalInCzk();
            }),
            'total_paid' => $invoices->where('status', 'paid')->sum(function ($invoice) {
                return $invoice->getTotalInCzk();
            }),
            'total_outstanding' => $invoices->whereIn('status', ['sent', 'draft'])->sum(function ($invoice) {
                return $invoice->getTotalInCzk();
            }),
            'overdue_amount' => $invoices->where('due_date', '<', now())->whereIn('status', ['sent', 'draft'])->sum(function ($invoice) {
                return $invoice->getTotalInCzk();
            }),
            'currency_note' => 'All amounts converted to CZK for reporting purposes'
        ];

        return [
            'client' => $client,
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y')
            ],
            'invoices' => $invoices,
            'summary' => $summary
        ];
    }

    private function downloadVatReportPdf(array $vatData): HttpResponse
    {
        $period = $vatData['period'];
        $filename = $this->generateVatReportFilename($period);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.vat-pdf', $vatData)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultPaperSize' => 'A4',
                'dpi' => 150,
                'defaultMediaType' => 'print',
                'isFontSubsettingEnabled' => true,
            ]);

        return $pdf->download($filename);
    }

    private function downloadClientStatementPdf(array $statementData): HttpResponse
    {
        $client = $statementData['client'];
        $period = $statementData['period'];
        $filename = $this->generateClientStatementFilename($client, $period);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.client-statement-pdf', $statementData)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultPaperSize' => 'A4',
                'dpi' => 150,
                'defaultMediaType' => 'print',
                'isFontSubsettingEnabled' => true,
            ]);

        return $pdf->download($filename);
    }

    private function generateVatReportFilename(array $period): string
    {
        if (isset($period['quarter'])) {
            return "vat_report_Q{$period['quarter']}_{$period['year']}.pdf";
        } else {
            return "vat_report_{$period['year']}.pdf";
        }
    }

    private function generateClientStatementFilename(\App\Models\Client $client, array $period): string
    {
        $clientName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $client->company_name);
        $start = str_replace('.', '', $period['start']);
        $end = str_replace('.', '', $period['end']);
        return "client_statement_{$clientName}_{$start}_{$end}.pdf";
    }
}