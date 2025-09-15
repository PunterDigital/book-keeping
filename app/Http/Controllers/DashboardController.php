<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $currentMonth = Carbon::now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Current month statistics
        $monthlyExpenses = Expense::whereBetween('date', [$startOfMonth, $endOfMonth])->get();
        $monthlyInvoices = Invoice::whereBetween('issue_date', [$startOfMonth, $endOfMonth])->get();

        $stats = [
            'expenses_this_month' => $monthlyExpenses->count(),
            'expenses_amount' => floatval($monthlyExpenses->sum('amount')),
            'expenses_vat' => floatval($monthlyExpenses->sum('vat_amount')),
            
            'invoices_this_month' => $monthlyInvoices->count(),
            'invoices_total' => floatval($monthlyInvoices->sum('total')),
            'invoices_subtotal' => floatval($monthlyInvoices->sum('subtotal')),
            'invoices_vat' => floatval($monthlyInvoices->sum('vat_amount')),
            
            'active_clients' => Client::has('invoices')->count(),
            'total_clients' => Client::count(),
        ];

        // Recent activity (last 8 expenses and invoices combined)
        $recentExpenses = Expense::with('category')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($expense) {
                return [
                    'type' => 'expense',
                    'id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'category' => $expense->category->name,
                    'date' => $expense->date,
                    'created_at' => $expense->created_at,
                ];
            });

        $recentInvoices = Invoice::with('client')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                return [
                    'type' => 'invoice',
                    'id' => $invoice->id,
                    'description' => "Invoice {$invoice->invoice_number} - {$invoice->client->company_name}",
                    'amount' => $invoice->total,
                    'category' => 'Invoice',
                    'date' => $invoice->issue_date,
                    'created_at' => $invoice->created_at,
                ];
            });

        $recentActivity = $recentExpenses->concat($recentInvoices)
            ->sortByDesc('created_at')
            ->take(8)
            ->values();

        // Next reporting period calculation (14th to 14th)
        // If today is the 15th or later, we're in the next reporting period
        $now = Carbon::now();
        if ($now->day >= 15) {
            // Period runs from 14th of current month to 14th of next month
            $nextReportStart = $now->copy()->day(14);
            $nextReportEnd = $now->copy()->addMonth()->day(14);
        } else {
            // Period runs from 14th of previous month to 14th of current month
            $nextReportStart = $now->copy()->subMonth()->day(14);
            $nextReportEnd = $now->copy()->day(14);
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'nextReportPeriod' => [
                'start' => $nextReportStart->format('j M'),
                'end' => $nextReportEnd->format('j M Y'),
            ]
        ]);
    }
}