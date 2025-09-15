<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\MonthlyReport;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonthlyReport as MonthlyReportMailable;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use ZipArchive;

class EmailService
{
    public function generateAndSendMonthlyReport(MonthlyReport $report): bool
    {
        try {
            // Validate email configuration before proceeding
            $this->validateEmailConfiguration();

            \Log::info('Starting monthly report generation', [
                'report_id' => $report->id,
                'period' => $report->period_start->format('Y-m-d') . ' to ' . $report->period_end->format('Y-m-d'),
                'mailer' => config('mail.default')
            ]);
            // Get data for the reporting period
            $invoices = Invoice::whereBetween('issue_date', [$report->period_start, $report->period_end])
                ->with(['client', 'items'])
                ->get();

            $expenses = Expense::whereBetween('date', [$report->period_start, $report->period_end])
                ->with('category')
                ->get();

            // Generate CSV files
            $expensesCsvPath = $this->generateExpensesCsv($expenses, $report);
            $invoicesCsvPath = $this->generateInvoicesCsv($invoices, $report);

            // Collect PDF files
            $invoicePdfPaths = $this->collectInvoicePdfs($invoices);
            $receiptPdfPaths = $this->collectReceiptPdfs($expenses);

            // Create ZIP archive
            $zipPath = $this->createZipArchive($report, [
                'expenses_csv' => $expensesCsvPath,
                'invoices_csv' => $invoicesCsvPath,
                'invoice_pdfs' => $invoicePdfPaths,
                'receipt_pdfs' => $receiptPdfPaths
            ]);

            // Send email with ZIP attachment
            $emailSent = $this->sendEmailWithAttachment($report, $zipPath);

            // Cleanup temporary files
            $this->cleanupTempFiles([
                $expensesCsvPath,
                $invoicesCsvPath,
                $zipPath
            ]);

            return $emailSent;

        } catch (\Exception $e) {
            \Log::error('Monthly report generation failed: ' . $e->getMessage(), [
                'report_id' => $report->id,
                'period' => $report->period_start . ' to ' . $report->period_end,
                'error_type' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]);

            // Clean up any temporary files that may have been created
            $this->cleanupTempFiles([
                $expensesCsvPath ?? null,
                $invoicesCsvPath ?? null,
                $zipPath ?? null
            ]);

            return false;
        }
    }

    private function generateExpensesCsv($expenses, MonthlyReport $report): string
    {
        $filename = 'expenses_' . $report->period_start->format('Y-m-d') . '_to_' . $report->period_end->format('Y-m-d') . '.csv';
        $path = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');

        // CSV Header (Czech labels for accountant)
        fputcsv($file, [
            'Datum',
            'Částka (CZK)',
            'DPH (CZK)',
            'Kategorie',
            'Popis',
            'Účtenka'
        ]);

        foreach ($expenses as $expense) {
            fputcsv($file, [
                $expense->date->format('d.m.Y'),
                number_format($expense->amount, 2, ',', ' '),
                number_format($expense->vat_amount, 2, ',', ' '),
                $expense->category->name,
                $expense->description,
                $expense->receipt_path ? 'Ano' : 'Ne'
            ]);
        }

        // Add summary row
        $totalAmount = $expenses->sum('amount');
        $totalVat = $expenses->sum('vat_amount');

        fputcsv($file, []);
        fputcsv($file, [
            'CELKEM',
            number_format($totalAmount, 2, ',', ' '),
            number_format($totalVat, 2, ',', ' '),
            '',
            'Počet výdajů: ' . $expenses->count(),
            ''
        ]);

        fclose($file);
        return $path;
    }

    private function generateInvoicesCsv($invoices, MonthlyReport $report): string
    {
        $filename = 'invoices_' . $report->period_start->format('Y-m-d') . '_to_' . $report->period_end->format('Y-m-d') . '.csv';
        $path = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');

        // CSV Header (Czech labels for accountant)
        fputcsv($file, [
            'Číslo faktury',
            'Klient',
            'Datum vystavení',
            'Datum splatnosti',
            'Základ (CZK)',
            'DPH (CZK)',
            'Celkem (CZK)',
            'Status',
            'DIČ klienta'
        ]);

        foreach ($invoices as $invoice) {
            $statusLabels = [
                'draft' => 'Koncept',
                'sent' => 'Odesláno',
                'paid' => 'Zaplaceno',
                'overdue' => 'Po splatnosti'
            ];

            fputcsv($file, [
                $invoice->invoice_number,
                $invoice->client->company_name,
                $invoice->issue_date->format('d.m.Y'),
                $invoice->due_date->format('d.m.Y'),
                number_format($invoice->subtotal, 2, ',', ' '),
                number_format($invoice->vat_amount, 2, ',', ' '),
                number_format($invoice->total, 2, ',', ' '),
                $statusLabels[$invoice->status] ?? $invoice->status,
                $invoice->client->vat_id ?? ''
            ]);
        }

        // Add summary row
        $totalSubtotal = $invoices->sum('subtotal');
        $totalVat = $invoices->sum('vat_amount');
        $totalAmount = $invoices->sum('total');

        fputcsv($file, []);
        fputcsv($file, [
            'CELKEM',
            '',
            '',
            '',
            number_format($totalSubtotal, 2, ',', ' '),
            number_format($totalVat, 2, ',', ' '),
            number_format($totalAmount, 2, ',', ' '),
            'Počet faktur: ' . $invoices->count(),
            ''
        ]);

        fclose($file);
        return $path;
    }

    private function collectInvoicePdfs($invoices): array
    {
        $pdfPaths = [];

        foreach ($invoices as $invoice) {
            if ($invoice->pdf_path && Storage::disk('s3')->exists($invoice->pdf_path)) {
                // Download PDF from S3 to temp local storage
                $localPath = storage_path('app/temp/invoices/' . basename($invoice->pdf_path));

                // Ensure directory exists
                if (!is_dir(dirname($localPath))) {
                    if (!mkdir($concurrentDirectory = dirname($localPath), 0755, true) && !is_dir($concurrentDirectory)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }
                }

                $pdfContent = Storage::disk('s3')->get($invoice->pdf_path);
                file_put_contents($localPath, $pdfContent);

                $pdfPaths[] = $localPath;
            }
        }

        return $pdfPaths;
    }

    private function collectReceiptPdfs($expenses): array
    {
        $pdfPaths = [];

        foreach ($expenses as $expense) {
            if ($expense->receipt_path && Storage::disk('s3')->exists($expense->receipt_path)) {
                // Download receipt from S3 to temp local storage
                $localPath = storage_path('app/temp/receipts/' . basename($expense->receipt_path));

                // Ensure directory exists
                if (!is_dir(dirname($localPath))) {
                    if (!mkdir($concurrentDirectory = dirname($localPath), 0755, true) && !is_dir($concurrentDirectory)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }
                }

                $receiptContent = Storage::disk('s3')->get($expense->receipt_path);
                file_put_contents($localPath, $receiptContent);

                $pdfPaths[] = $localPath;
            }
        }

        return $pdfPaths;
    }

    private function createZipArchive(MonthlyReport $report, array $files): string
    {
        $zipFilename = 'monthly_report_' . $report->period_start->format('Y-m-d') . '_to_' . $report->period_end->format('Y-m-d') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFilename);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new \Exception('Cannot create ZIP archive: ' . $zipPath);
        }

        // Add CSV files
        if (file_exists($files['expenses_csv'])) {
            $zip->addFile($files['expenses_csv'], 'vydaje.csv');
        }

        if (file_exists($files['invoices_csv'])) {
            $zip->addFile($files['invoices_csv'], 'faktury.csv');
        }

        // Add invoice PDFs to subfolder
        foreach ($files['invoice_pdfs'] as $pdfPath) {
            if (file_exists($pdfPath)) {
                $zip->addFile($pdfPath, 'faktury_pdf/' . basename($pdfPath));
            }
        }

        // Add receipt PDFs to subfolder
        foreach ($files['receipt_pdfs'] as $pdfPath) {
            if (file_exists($pdfPath)) {
                $zip->addFile($pdfPath, 'uctenky_pdf/' . basename($pdfPath));
            }
        }

        // Add summary info file
        $summaryPath = storage_path('app/temp/summary.txt');
        $summaryContent = $this->generateSummaryFile($report);
        file_put_contents($summaryPath, $summaryContent);
        $zip->addFile($summaryPath, 'prehled.txt');

        $zip->close();

        // Cleanup summary file
        if (file_exists($summaryPath)) {
            unlink($summaryPath);
        }

        return $zipPath;
    }

    private function generateSummaryFile(MonthlyReport $report): string
    {
        $periodStart = $report->period_start->format('d.m.Y');
        $periodEnd = $report->period_end->format('d.m.Y');
        $generatedAt = $report->generated_at->format('d.m.Y H:i');

        return "MĚSÍČNÍ PŘEHLED ÚČETNICTVÍ\n" .
               "=============================\n\n" .
               "Období: {$periodStart} - {$periodEnd}\n" .
               "Vygenerováno: {$generatedAt}\n\n" .
               "OBSAH ARCHIVU:\n" .
               "- vydaje.csv - Přehled všech výdajů\n" .
               "- faktury.csv - Přehled všech faktur\n" .
               "- faktury_pdf/ - PDF kopie všech faktur\n" .
               "- uctenky_pdf/ - PDF kopie všech účtenek\n\n" .
               "Pro dotazy kontaktujte: " . config('mail.from.address');
    }

    private function sendEmailWithAttachment(MonthlyReport $report, string $zipPath): bool
    {
        $accountantEmail = config('mail.accountant_email', env('ACCOUNTANT_EMAIL'));

        if (!$accountantEmail) {
            throw new \Exception('Accountant email not configured');
        }

        $periodStart = $report->period_start->format('d.m.Y');
        $periodEnd = $report->period_end->format('d.m.Y');

        $subject = "Měsíční přehled účetnictví {$periodStart} - {$periodEnd}";

        try {
            // Try to send with current mailer (likely SES)
            Mail::to($accountantEmail)->send(new MonthlyReportMailable($report, $zipPath));
            \Log::info('Monthly report email sent successfully via ' . config('mail.default'));
            return true;

        } catch (\Exception $e) {
            \Log::warning('Failed to send email with primary mailer (' . config('mail.default') . '): ' . $e->getMessage());

            // Try fallback to log driver for development/testing
            if (app()->environment(['local', 'testing'])) {
                try {
                    \Log::info('Attempting to send monthly report via log driver as fallback');

                    // Temporarily switch to log mailer
                    config(['mail.default' => 'log']);
                    Mail::to($accountantEmail)->send(new MonthlyReportMailable($report, $zipPath));

                    \Log::info('Monthly report logged successfully (fallback method)', [
                        'report_id' => $report->id,
                        'period' => $periodStart . ' - ' . $periodEnd,
                        'zip_size' => file_exists($zipPath) ? filesize($zipPath) : 0
                    ]);

                    return true;

                } catch (\Exception $fallbackException) {
                    \Log::error('Even fallback log method failed: ' . $fallbackException->getMessage());
                    throw new \Exception('Both primary and fallback email methods failed: ' . $e->getMessage());
                }
            } else {
                // In production, don't use fallback - re-throw original exception
                throw $e;
            }
        }
    }

    private function cleanupTempFiles(array $filePaths): void
    {
        foreach ($filePaths as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }

        // Clean up temporary directories
        $tempDirs = [
            storage_path('app/temp/invoices'),
            storage_path('app/temp/receipts')
        ];

        foreach ($tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($filePath) ? $this->removeDirectory($filePath) : unlink($filePath);
        }

        rmdir($dir);
    }

    private function validateEmailConfiguration(): void
    {
        $mailer = config('mail.default');
        $accountantEmail = config('mail.accountant_email', env('ACCOUNTANT_EMAIL'));

        if (!$accountantEmail) {
            throw new \Exception('ACCOUNTANT_EMAIL environment variable is not configured');
        }

        if ($mailer === 'ses') {
            // Check if AWS SES credentials are configured
            $awsKey = env('AWS_SES_KEY') ?: env('AWS_ACCESS_KEY_ID');
            $awsSecret = env('AWS_SES_SECRET') ?: env('AWS_SECRET_ACCESS_KEY');
            $awsRegion = env('AWS_SES_REGION') ?: env('AWS_DEFAULT_REGION');

            if (!$awsKey || !$awsSecret || !$awsRegion) {
                \Log::warning('AWS SES credentials are incomplete, may cause email failures', [
                    'has_key' => !empty($awsKey),
                    'has_secret' => !empty($awsSecret),
                    'has_region' => !empty($awsRegion)
                ]);
            }
        }

        \Log::info('Email configuration validated', [
            'mailer' => $mailer,
            'accountant_email' => $accountantEmail
        ]);
    }
}
