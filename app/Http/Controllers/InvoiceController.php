<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use App\Services\InvoicePdfService;
use App\Services\CzechInvoiceValidationService;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = Invoice::with('client')
            ->orderBy('issue_date', 'desc')
            ->orderBy('invoice_number', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_name' => $invoice->client->company_name,
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'total' => (float)$invoice->total,
                    'total_czk' => $invoice->getTotalInCzk(),
                    'currency' => $invoice->currency ?? 'CZK',
                    'exchange_rate' => (float)($invoice->exchange_rate ?? 1.0),
                    'formatted_total' => $invoice->getFormattedTotal(),
                    'exchange_rate_info' => $invoice->getExchangeRateInfo(),
                    'status' => $invoice->status ?? 'draft',
                    'is_overdue' => $invoice->due_date->isPast() && in_array($invoice->status, ['sent', 'draft']),
                ];
            });

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices
        ]);
    }

    public function create(CzechInvoiceValidationService $validationService, ExchangeRateService $exchangeRateService): Response
    {
        $clients = Client::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'vat_id']);

        // Generate next invoice number using Czech standards
        $nextNumber = $validationService->getRecommendedInvoiceNumber();

        // Get available currencies from exchange rate service
        $availableCurrencies = $exchangeRateService->getSupportedCurrencies();

        return Inertia::render('Invoices/Create', [
            'clients' => $clients,
            'nextInvoiceNumber' => $nextNumber,
            'vatRates' => [0, 12, 21], // Czech VAT rates
            'availableCurrencies' => $availableCurrencies,
        ]);
    }

    public function store(Request $request, CzechInvoiceValidationService $validationService): RedirectResponse
    {
        // First validate with Laravel rules
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'client_id' => 'required|exists:clients,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:draft,sent,paid,overdue',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.vat_rate' => 'required|numeric|in:0,12,21',
        ]);

        // Then validate with Czech compliance rules
        $czechValidation = $validationService->validateForSave($validated);
        if (!$czechValidation['valid']) {
            return redirect()->back()
                ->withErrors($czechValidation['errors'])
                ->withInput()
                ->with('error', 'Faktura nesplňuje české právní požadavky.');
        }

        \DB::transaction(function () use ($validated) {
            // Calculate totals
            $subtotal = 0;
            $vatAmount = 0;

            foreach ($validated['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemVat = $itemSubtotal * ($item['vat_rate'] / 100);
                
                $subtotal += $itemSubtotal;
                $vatAmount += $itemVat;
            }

            $total = $subtotal + $vatAmount;

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $validated['invoice_number'],
                'client_id' => $validated['client_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'draft',
            ]);

            // Create invoice items
            foreach ($validated['items'] as $item) {
                \App\Models\InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'vat_rate' => $item['vat_rate'],
                ]);
            }
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice, CzechInvoiceValidationService $validationService): Response
    {
        $invoice->load(['client', 'items']);

        // Validate Czech compliance
        $complianceCheck = $validationService->validateInvoiceFormat($invoice);

        return Inertia::render('Invoices/Show', [
            'compliance' => $complianceCheck,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client' => $invoice->client,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'subtotal' => (float)$invoice->subtotal,
                'vat_amount' => (float)$invoice->vat_amount,
                'total' => (float)$invoice->total,
                'currency' => $invoice->currency ?? 'CZK',
                'exchange_rate' => (float)($invoice->exchange_rate ?? 1.0),
                'formatted_total' => $invoice->getFormattedTotal(),
                'exchange_rate_info' => $invoice->getExchangeRateInfo(),
                'notes' => $invoice->notes,
                'status' => $invoice->status ?? 'draft',
                'pdf_path' => $invoice->pdf_path,
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => (float)$item->unit_price,
                        'vat_rate' => $item->vat_rate,
                        'subtotal' => $item->subtotal,
                        'vat_amount' => $item->vat_amount,
                        'total' => $item->total,
                    ];
                })
            ]
        ]);
    }

    public function edit(Invoice $invoice, CzechInvoiceValidationService $validationService): Response
    {
        $invoice->load(['client', 'items']);

        $clients = Client::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'vat_id']);

        // Validate Czech compliance for current invoice
        $complianceCheck = $validationService->validateInvoiceFormat($invoice);

        return Inertia::render('Invoices/Edit', [
            'compliance' => $complianceCheck,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'notes' => $invoice->notes,
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'vat_rate' => $item->vat_rate,
                    ];
                }),
            ],
            'clients' => $clients,
            'vatRates' => [0, 12, 21],
        ]);
    }

    public function update(Request $request, Invoice $invoice, CzechInvoiceValidationService $validationService): RedirectResponse
    {
        // First validate with Laravel rules
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:invoices,invoice_number,' . $invoice->id,
            'client_id' => 'required|exists:clients,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:draft,sent,paid,overdue',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:invoice_items,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.vat_rate' => 'required|numeric|in:0,12,21',
        ]);

        // Then validate with Czech compliance rules
        $czechValidation = $validationService->validateForSave($validated);
        if (!$czechValidation['valid']) {
            return redirect()->back()
                ->withErrors($czechValidation['errors'])
                ->withInput()
                ->with('error', 'Faktura nesplňuje české právní požadavky.');
        }

        \DB::transaction(function () use ($validated, $invoice) {
            // Calculate totals
            $subtotal = 0;
            $vatAmount = 0;

            foreach ($validated['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemVat = $itemSubtotal * ($item['vat_rate'] / 100);
                
                $subtotal += $itemSubtotal;
                $vatAmount += $itemVat;
            }

            $total = $subtotal + $vatAmount;

            // Update invoice
            $invoice->update([
                'invoice_number' => $validated['invoice_number'],
                'client_id' => $validated['client_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? $invoice->status,
            ]);

            // Get existing item IDs
            $existingItemIds = $invoice->items()->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create items
            foreach ($validated['items'] as $item) {
                if (isset($item['id']) && in_array($item['id'], $existingItemIds)) {
                    // Update existing item
                    \App\Models\InvoiceItem::where('id', $item['id'])->update([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'vat_rate' => $item['vat_rate'],
                    ]);
                    $updatedItemIds[] = $item['id'];
                } else {
                    // Create new item
                    $newItem = \App\Models\InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'vat_rate' => $item['vat_rate'],
                    ]);
                    $updatedItemIds[] = $newItem->id;
                }
            }

            // Delete removed items
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                \App\Models\InvoiceItem::whereIn('id', $itemsToDelete)->delete();
            }
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        // Delete invoice items first
        $invoice->items()->delete();
        
        // Delete PDF if exists
        if ($invoice->pdf_path) {
            \Storage::disk('s3')->delete($invoice->pdf_path);
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,paid,cancelled',
        ]);

        $invoice->update(['status' => $validated['status']]);

        return redirect()->back()
            ->with('success', 'Invoice status updated successfully.');
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfService $pdfService): HttpResponse
    {
        return $pdfService->downloadPdf($invoice);
    }

    public function streamPdf(Invoice $invoice, InvoicePdfService $pdfService): HttpResponse
    {
        return $pdfService->streamPdf($invoice);
    }

    public function generatePdf(Invoice $invoice, InvoicePdfService $pdfService): RedirectResponse
    {
        try {
            $pdfService->savePdf($invoice);
            
            return redirect()->back()
                ->with('success', 'PDF was generated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function deletePdf(Invoice $invoice, InvoicePdfService $pdfService): RedirectResponse
    {
        try {
            $pdfService->deletePdf($invoice);
            
            return redirect()->back()
                ->with('success', 'PDF was deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to delete PDF: ' . $e->getMessage());
        }
    }

    private function generateInvoiceNumber($lastInvoice = null): string
    {
        $year = date('Y');
        $nextNumber = 1;

        if ($lastInvoice) {
            // Extract number from format like "2024001" or "INV-2024-001"
            preg_match('/(\d+)$/', $lastInvoice->invoice_number, $matches);
            if (!empty($matches)) {
                $lastNumber = intval($matches[1]);
                // If the number contains year, extract just the sequence
                if ($lastNumber > 10000) {
                    $lastNumber = $lastNumber % 1000;
                }
                $nextNumber = $lastNumber + 1;
            }
        }

        return $year . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}