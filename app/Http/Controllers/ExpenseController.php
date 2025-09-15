<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $expenses = Expense::with('category')
            ->orderBy('date', 'desc')
            ->get();


        $categories = ExpenseCategory::orderBy('name')->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories
        ]);
    }

    public function create(ExchangeRateService $exchangeRateService): Response
    {
        $categories = ExpenseCategory::orderBy('name')->get();
        $availableCurrencies = $exchangeRateService->getSupportedCurrencies();

        return Inertia::render('Expenses/Create', [
            'categories' => $categories,
            'availableCurrencies' => $availableCurrencies
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:1000',
            'vat_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            // Try S3 first, fallback to local storage
            try {
                // Check if S3 is configured
                if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret')) {
                    $receiptPath = $request->file('receipt')->store('receipts', 's3');
                } else {
                    // Use local storage if S3 is not configured
                    $receiptPath = $request->file('receipt')->store('receipts', 'public');
                }
            } catch (\Exception $e) {
                // Fallback to local storage on S3 error
                \Log::warning('S3 upload failed, using local storage: ' . $e->getMessage());
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
            }
        }

        Expense::create([
            'date' => $request->date,
            'amount' => $request->amount,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'vat_amount' => $request->vat_amount,
            'currency' => $request->currency,
            'exchange_rate' => $request->exchange_rate,
            'receipt_path' => $receiptPath,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense created successfully.');
    }

    public function show(Expense $expense): Response
    {
        $expense->load('category');

        return Inertia::render('Expenses/Show', [
            'expense' => $expense
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $expense->load('category');
        $categories = ExpenseCategory::orderBy('name')->get();

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:1000',
            'vat_amount' => 'required|numeric|min:0',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $receiptPath = $expense->receipt_path;
        if ($request->hasFile('receipt')) {
            // Try S3 first, fallback to local storage
            try {
                // Delete old receipt if exists
                if ($receiptPath) {
                    // Determine which disk the old file is on
                    if (strpos($receiptPath, 's3') !== false || config('filesystems.disks.s3.key')) {
                        try {
                            Storage::disk('s3')->delete($receiptPath);
                        } catch (\Exception $e) {
                            Storage::disk('public')->delete($receiptPath);
                        }
                    } else {
                        Storage::disk('public')->delete($receiptPath);
                    }
                }
                
                // Upload new receipt
                if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret')) {
                    $receiptPath = $request->file('receipt')->store('receipts', 's3');
                } else {
                    $receiptPath = $request->file('receipt')->store('receipts', 'public');
                }
            } catch (\Exception $e) {
                // Fallback to local storage on S3 error
                \Log::warning('S3 upload failed, using local storage: ' . $e->getMessage());
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
            }
        }

        $expense->update([
            'date' => $request->date,
            'amount' => $request->amount,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'vat_amount' => $request->vat_amount,
            'receipt_path' => $receiptPath,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        // Delete receipt file if exists
        if ($expense->receipt_path) {
            Storage::disk('s3')->delete($expense->receipt_path);
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    public function downloadReceipt(Expense $expense)
    {
        if (!$expense->receipt_path) {
            abort(404, 'Receipt not found');
        }

        try {
            // Determine which disk to use
            $disk = 's3'; // Default to S3
            
            // Check if file exists on S3
            if (config('filesystems.disks.s3.key') && Storage::disk('s3')->exists($expense->receipt_path)) {
                $disk = 's3';
            } elseif (Storage::disk('public')->exists($expense->receipt_path)) {
                $disk = 'public';
            } else {
                abort(404, 'Receipt file not found');
            }

            // Generate a proper filename
            $fileName = 'expense_' . $expense->id . '_' . date('Y-m-d', strtotime($expense->date)) . '_receipt.' . pathinfo($expense->receipt_path, PATHINFO_EXTENSION);
            
            // For S3, we need to use streaming response
            if ($disk === 's3') {
                $headers = [
                    'Content-Type' => Storage::disk($disk)->mimeType($expense->receipt_path),
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Cache-Control' => 'no-cache, must-revalidate',
                ];
                
                // Stream the file from S3
                return response()->stream(
                    function () use ($disk, $expense) {
                        echo Storage::disk($disk)->get($expense->receipt_path);
                    },
                    200,
                    $headers
                );
            } else {
                // For local storage, use the simpler download method
                return Storage::disk($disk)->download($expense->receipt_path, $fileName);
            }

        } catch (\Exception $e) {
            \Log::error('Receipt download failed: ' . $e->getMessage());
            abort(500, 'Failed to download receipt: ' . $e->getMessage());
        }
    }
}