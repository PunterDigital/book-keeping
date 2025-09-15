<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExpenseReportService
{
    public function generateMonthlyReport(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $expenses = Expense::with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $summary = $this->calculateSummary($expenses);
        $categoryBreakdown = $this->calculateCategoryBreakdown($expenses);
        $vatBreakdown = $this->calculateVatBreakdown($expenses);

        return [
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y'),
                'month_year' => $startDate->format('m/Y')
            ],
            'expenses' => $expenses,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'vat_breakdown' => $vatBreakdown
        ];
    }

    public function generateYearlyReport(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        $expenses = Expense::with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $summary = $this->calculateSummary($expenses);
        $categoryBreakdown = $this->calculateCategoryBreakdown($expenses);
        $vatBreakdown = $this->calculateVatBreakdown($expenses);
        $monthlyBreakdown = $this->calculateMonthlyBreakdown($expenses, $year);

        return [
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y'),
                'year' => $year
            ],
            'expenses' => $expenses,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'vat_breakdown' => $vatBreakdown,
            'monthly_breakdown' => $monthlyBreakdown
        ];
    }

    public function generateCustomReport(Carbon $startDate, Carbon $endDate): array
    {
        $expenses = Expense::with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $summary = $this->calculateSummary($expenses);
        $categoryBreakdown = $this->calculateCategoryBreakdown($expenses);
        $vatBreakdown = $this->calculateVatBreakdown($expenses);

        return [
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y')
            ],
            'expenses' => $expenses,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'vat_breakdown' => $vatBreakdown
        ];
    }

    public function generateExpensesPdf(array $reportData): string
    {
        $pdf = Pdf::loadView('reports.expenses-pdf', $reportData)
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

        return $pdf->output();
    }

    public function downloadExpensesPdf(array $reportData, string $filename = null): \Symfony\Component\HttpFoundation\Response
    {
        $filename = $filename ?: $this->generateExpenseReportFilename($reportData['period']);

        $pdf = Pdf::loadView('reports.expenses-pdf', $reportData)
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

    private function calculateSummary(Collection $expenses): array
    {
        $totalAmount = $expenses->sum(function ($expense) {
            return $expense->getAmountInCzk();
        });
        $totalVat = $expenses->sum(function ($expense) {
            // Convert VAT amount to CZK using same exchange rate as the expense
            if ($expense->currency === 'CZK') {
                return $expense->vat_amount;
            }
            return $expense->vat_amount * $expense->exchange_rate;
        });
        $totalWithVat = $totalAmount + $totalVat;

        return [
            'total_expenses' => $expenses->count(),
            'total_amount' => $totalAmount,
            'total_vat' => $totalVat,
            'total_with_vat' => $totalWithVat,
            'average_amount' => $expenses->count() > 0 ? $totalAmount / $expenses->count() : 0,
            'currency_note' => 'All amounts converted to CZK for reporting purposes'
        ];
    }

    private function calculateCategoryBreakdown(Collection $expenses): array
    {
        return $expenses->groupBy('category.name')
            ->map(function ($categoryExpenses) {
                $totalAmount = $categoryExpenses->sum(function ($expense) {
                    return $expense->getAmountInCzk();
                });
                $totalVat = $categoryExpenses->sum(function ($expense) {
                    // Convert VAT amount to CZK using same exchange rate as the expense
                    if ($expense->currency === 'CZK') {
                        return $expense->vat_amount;
                    }
                    return $expense->vat_amount * $expense->exchange_rate;
                });

                return [
                    'count' => $categoryExpenses->count(),
                    'total_amount' => $totalAmount,
                    'total_vat' => $totalVat,
                    'total_with_vat' => $totalAmount + $totalVat,
                    'percentage' => 0 // Will be calculated after we know total
                ];
            })
            ->sortByDesc('total_amount')
            ->toArray();
    }

    private function calculateVatBreakdown(Collection $expenses): array
    {
        return $expenses->groupBy('vat_rate')
            ->map(function ($vatExpenses) {
                $totalAmount = $vatExpenses->sum(function ($expense) {
                    return $expense->getAmountInCzk();
                });
                $totalVat = $vatExpenses->sum(function ($expense) {
                    // Convert VAT amount to CZK using same exchange rate as the expense
                    if ($expense->currency === 'CZK') {
                        return $expense->vat_amount;
                    }
                    return $expense->vat_amount * $expense->exchange_rate;
                });

                return [
                    'count' => $vatExpenses->count(),
                    'total_amount' => $totalAmount,
                    'total_vat' => $totalVat,
                    'total_with_vat' => $totalAmount + $totalVat
                ];
            })
            ->sortByDesc('total_amount')
            ->toArray();
    }

    private function calculateMonthlyBreakdown(Collection $expenses, int $year): array
    {
        $monthlyData = [];
        
        // Initialize all months with zero values
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->format('F'),
                'count' => 0,
                'total_amount' => 0,
                'total_vat' => 0,
                'total_with_vat' => 0
            ];
        }

        // Group expenses by month
        $expensesByMonth = $expenses->groupBy(function ($expense) {
            return Carbon::parse($expense->date)->month;
        });

        // Calculate monthly totals
        foreach ($expensesByMonth as $month => $monthExpenses) {
            $totalAmount = $monthExpenses->sum('amount');
            $totalVat = $monthExpenses->sum(function ($expense) {
                return $expense->vat_amount;
            });

            $monthlyData[$month] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->format('F'),
                'count' => $monthExpenses->count(),
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'total_with_vat' => $totalAmount + $totalVat
            ];
        }

        return array_values($monthlyData);
    }

    private function generateExpenseReportFilename(array $period): string
    {
        if (isset($period['year'])) {
            return "expense_report_{$period['year']}.pdf";
        } elseif (isset($period['month_year'])) {
            $monthYear = str_replace('/', '-', $period['month_year']);
            return "expense_report_{$monthYear}.pdf";
        } else {
            $start = str_replace(['.', '/', '\\'], ['', '-', '-'], $period['start']);
            $end = str_replace(['.', '/', '\\'], ['', '-', '-'], $period['end']);
            return "expense_report_{$start}_{$end}.pdf";
        }
    }
}