<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Exchange rate API
    Route::get('api/exchange-rates/{from}/{to}', function ($from, $to) {
        $service = new App\Services\ExchangeRateService();
        $rate = $service->getCurrentRate($from, $to);
        return response()->json(['rate' => $rate]);
    });
    
    // Expense routes
    Route::resource('expenses', App\Http\Controllers\ExpenseController::class);
    Route::get('expenses/{expense}/receipt/download', [App\Http\Controllers\ExpenseController::class, 'downloadReceipt'])->name('expenses.receipt.download');
    Route::resource('expense-categories', App\Http\Controllers\ExpenseCategoryController::class);
    
    // Client routes
    Route::resource('clients', App\Http\Controllers\ClientController::class);
    
    // Invoice routes
    Route::resource('invoices', App\Http\Controllers\InvoiceController::class);
    Route::patch('invoices/{invoice}/status', [App\Http\Controllers\InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    
    // Invoice PDF routes
    Route::get('invoices/{invoice}/pdf/download', [App\Http\Controllers\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf.download');
    Route::get('invoices/{invoice}/pdf/stream', [App\Http\Controllers\InvoiceController::class, 'streamPdf'])->name('invoices.pdf.stream');
    Route::post('invoices/{invoice}/pdf/generate', [App\Http\Controllers\InvoiceController::class, 'generatePdf'])->name('invoices.pdf.generate');
    Route::delete('invoices/{invoice}/pdf', [App\Http\Controllers\InvoiceController::class, 'deletePdf'])->name('invoices.pdf.delete');
    
    // Report routes
    Route::get('reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/expenses', [App\Http\Controllers\ReportsController::class, 'expensesIndex'])->name('reports.expenses.index');
    Route::get('reports/vat', [App\Http\Controllers\ReportsController::class, 'vatIndex'])->name('reports.vat.index');
    Route::get('reports/client-statement', [App\Http\Controllers\ReportsController::class, 'clientStatementIndex'])->name('reports.client-statement.index');
    Route::post('reports/expenses/monthly', [App\Http\Controllers\ReportsController::class, 'expenseMonthly'])->name('reports.expenses.monthly');
    Route::post('reports/expenses/yearly', [App\Http\Controllers\ReportsController::class, 'expenseYearly'])->name('reports.expenses.yearly');
    Route::post('reports/expenses/custom', [App\Http\Controllers\ReportsController::class, 'expenseCustom'])->name('reports.expenses.custom');
    Route::post('reports/vat', [App\Http\Controllers\ReportsController::class, 'vatReport'])->name('reports.vat');
    Route::post('reports/client-statement', [App\Http\Controllers\ReportsController::class, 'clientStatement'])->name('reports.client-statement');

    // Monthly Report Email System routes
    Route::get('monthly-reports', [App\Http\Controllers\ReportController::class, 'index'])->name('monthly-reports.index');
    Route::post('monthly-reports/generate', [App\Http\Controllers\ReportController::class, 'generate'])->name('monthly-reports.generate');
    Route::post('monthly-reports/send', [App\Http\Controllers\ReportController::class, 'send'])->name('monthly-reports.send');
    Route::post('monthly-reports/send-now', [App\Http\Controllers\ReportController::class, 'sendNow'])->name('monthly-reports.send-now');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
