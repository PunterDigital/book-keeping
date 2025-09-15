<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generatePdf(Invoice $invoice): string
    {
        // Load the invoice with related data
        $invoice->load(['client', 'items']);

        // Generate PDF
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultPaperSize' => 'A4',
                'dpi' => 150,
                'defaultMediaType' => 'print',
                'isFontSubsettingEnabled' => true,
                'isUnicodeEnabled' => true,
                'defaultEncoding' => 'UTF-8',
            ]);

        return $pdf->output();
    }

    public function downloadPdf(Invoice $invoice, string $filename = null): \Symfony\Component\HttpFoundation\Response
    {
        $invoice->load(['client', 'items']);
        
        $filename = $filename ?: $this->generateFilename($invoice);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultPaperSize' => 'A4',
                'dpi' => 150,
                'defaultMediaType' => 'print',
                'isFontSubsettingEnabled' => true,
                'isUnicodeEnabled' => true,
                'defaultEncoding' => 'UTF-8',
            ]);

        return $pdf->download($filename);
    }

    public function streamPdf(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $invoice->load(['client', 'items']);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultPaperSize' => 'A4',
                'dpi' => 150,
                'defaultMediaType' => 'print',
                'isFontSubsettingEnabled' => true,
                'isUnicodeEnabled' => true,
                'defaultEncoding' => 'UTF-8',
            ]);

        return $pdf->stream($this->generateFilename($invoice));
    }

    public function savePdf(Invoice $invoice, string $path = null): string
    {
        $path = $path ?: $this->generateStoragePath($invoice);

        $pdfContent = $this->generatePdf($invoice);

        // Try S3 first, fallback to local storage
        try {
            // Check if S3 is configured
            if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret')) {
                Storage::disk('s3')->put($path, $pdfContent);
            } else {
                // Use local storage if S3 is not configured
                Storage::disk('public')->put($path, $pdfContent);
            }
        } catch (\Exception $e) {
            // Fallback to local storage on S3 error
            \Log::warning('S3 upload failed for invoice PDF, using local storage: ' . $e->getMessage());
            Storage::disk('public')->put($path, $pdfContent);
        }

        // Update invoice record with PDF path
        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function emailPdf(Invoice $invoice): string
    {
        // Generate PDF for email attachment
        return $this->generatePdf($invoice);
    }

    private function generateFilename(Invoice $invoice): string
    {
        $clientName = $this->sanitizeFilename($invoice->client->company_name);
        return "faktura_{$invoice->invoice_number}_{$clientName}.pdf";
    }

    private function generateStoragePath(Invoice $invoice): string
    {
        $year = $invoice->issue_date->year;
        $month = $invoice->issue_date->format('m');
        
        return "invoices/{$year}/{$month}/faktura_{$invoice->invoice_number}.pdf";
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove Czech diacritics and special characters
        $replacements = [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
            'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
            'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
            'Á' => 'A', 'Č' => 'C', 'Ď' => 'D', 'É' => 'E', 'Ě' => 'E',
            'Í' => 'I', 'Ň' => 'N', 'Ó' => 'O', 'Ř' => 'R', 'Š' => 'S',
            'Ť' => 'T', 'Ú' => 'U', 'Ů' => 'U', 'Ý' => 'Y', 'Ž' => 'Z'
        ];
        
        $filename = strtr($filename, $replacements);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename;
    }

    public function getStoredPdfPath(Invoice $invoice): ?string
    {
        if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
            return $invoice->pdf_path;
        }
        
        return null;
    }

    public function hasPdf(Invoice $invoice): bool
    {
        return $this->getStoredPdfPath($invoice) !== null;
    }

    public function deletePdf(Invoice $invoice): bool
    {
        if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
            Storage::disk('public')->delete($invoice->pdf_path);
            $invoice->update(['pdf_path' => null]);
            return true;
        }
        
        return false;
    }
}