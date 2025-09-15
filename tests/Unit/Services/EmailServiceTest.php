<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\MonthlyReport;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonthlyReport as MonthlyReportMailable;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $service;
    protected MonthlyReport $report;
    protected Client $client;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new EmailService();
        
        // Set up test data
        $this->client = Client::create([
            'company_name' => 'Test Client s.r.o.',
            'contact_name' => 'Jan Novák',
            'address' => 'Test Address 123, Praha',
            'vat_id' => 'CZ12345678'
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Office Supplies'
        ]);

        $this->report = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 7, 15),
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        // Mock mail and storage
        Mail::fake();
        Storage::fake('s3');
    }

    public function test_generates_expense_csv_with_correct_format()
    {
        // Create test expenses
        $expense1 = Expense::create([
            'date' => Carbon::create(2024, 6, 20),
            'amount' => 1500.00,
            'category_id' => $this->category->id,
            'description' => 'Test Expense 1',
            'vat_amount' => 315.00
        ]);

        $expense2 = Expense::create([
            'date' => Carbon::create(2024, 7, 10),
            'amount' => 800.00,
            'category_id' => $this->category->id,
            'description' => 'Test Expense 2',
            'vat_amount' => 96.00
        ]);

        $expenses = collect([$expense1, $expense2]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateExpensesCsv');
        $method->setAccessible(true);

        $csvPath = $method->invoke($this->service, $expenses, $this->report);

        $this->assertTrue(file_exists($csvPath));
        
        $csvContent = file_get_contents($csvPath);
        
        // Check CSV headers (accounting for fputcsv quoting)
        $this->assertStringContainsString('Datum,"Částka (CZK)","DPH (CZK)",Kategorie,Popis,Účtenka', $csvContent);
        
        // Check data formatting
        $this->assertStringContainsString('20.06.2024', $csvContent);
        $this->assertStringContainsString('Test Expense 1', $csvContent);
        $this->assertStringContainsString('Office Supplies', $csvContent);
        
        // Check summary
        $this->assertStringContainsString('CELKEM', $csvContent);
        
        // Clean up
        unlink($csvPath);
    }

    public function test_generates_invoice_csv_with_correct_format()
    {
        // Create test invoice
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::create(2024, 6, 25),
            'due_date' => Carbon::create(2024, 7, 25),
            'subtotal' => 10000.00,
            'vat_amount' => 2100.00,
            'total' => 12100.00,
            'status' => 'sent'
        ]);

        $invoices = collect([$invoice]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateInvoicesCsv');
        $method->setAccessible(true);

        $csvPath = $method->invoke($this->service, $invoices, $this->report);

        $this->assertTrue(file_exists($csvPath));
        
        $csvContent = file_get_contents($csvPath);
        
        // Check CSV headers (accounting for fputcsv quoting)
        $this->assertStringContainsString('"Číslo faktury",Klient,"Datum vystavení"', $csvContent);
        
        // Check data
        $this->assertStringContainsString('2024001', $csvContent);
        $this->assertStringContainsString('Test Client s.r.o.', $csvContent);
        $this->assertStringContainsString('25.06.2024', $csvContent);
        $this->assertStringContainsString('Odesláno', $csvContent); // Status translation
        
        // Clean up
        unlink($csvPath);
    }

    public function test_creates_zip_archive_with_correct_structure()
    {
        // Create temporary CSV files
        $expensesCsv = storage_path('app/temp/test_expenses.csv');
        $invoicesCsv = storage_path('app/temp/test_invoices.csv');
        
        // Ensure temp directory exists
        if (!is_dir(dirname($expensesCsv))) {
            mkdir(dirname($expensesCsv), 0755, true);
        }
        
        file_put_contents($expensesCsv, "Test expenses CSV content");
        file_put_contents($invoicesCsv, "Test invoices CSV content");

        $files = [
            'expenses_csv' => $expensesCsv,
            'invoices_csv' => $invoicesCsv,
            'invoice_pdfs' => [],
            'receipt_pdfs' => []
        ];

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createZipArchive');
        $method->setAccessible(true);

        $zipPath = $method->invoke($this->service, $this->report, $files);

        $this->assertTrue(file_exists($zipPath));
        
        // Verify ZIP contents
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === TRUE);
        
        $this->assertNotFalse($zip->locateName('vydaje.csv'));
        $this->assertNotFalse($zip->locateName('faktury.csv'));
        $this->assertNotFalse($zip->locateName('prehled.txt'));
        
        $zip->close();

        // Clean up
        unlink($expensesCsv);
        unlink($invoicesCsv);
        unlink($zipPath);
    }

    public function test_generates_summary_file_with_correct_czech_content()
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateSummaryFile');
        $method->setAccessible(true);

        $summary = $method->invoke($this->service, $this->report);

        $this->assertStringContainsString('MĚSÍČNÍ PŘEHLED ÚČETNICTVÍ', $summary);
        $this->assertStringContainsString('16.06.2024 - 15.07.2024', $summary);
        $this->assertStringContainsString('vydaje.csv', $summary);
        $this->assertStringContainsString('faktury.csv', $summary);
        $this->assertStringContainsString('faktury_pdf/', $summary);
        $this->assertStringContainsString('uctenky_pdf/', $summary);
    }

    public function test_handles_missing_accountant_email_configuration()
    {
        // Temporarily unset accountant email
        config(['mail.accountant_email' => null]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Accountant email not configured');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sendEmailWithAttachment');
        $method->setAccessible(true);

        $method->invoke($this->service, $this->report, '/path/to/test.zip');
    }

    public function test_cleans_up_temporary_files()
    {
        // Create temporary files
        $tempFile1 = storage_path('app/temp/test1.csv');
        $tempFile2 = storage_path('app/temp/test2.zip');
        
        if (!is_dir(dirname($tempFile1))) {
            mkdir(dirname($tempFile1), 0755, true);
        }
        
        file_put_contents($tempFile1, 'test content');
        file_put_contents($tempFile2, 'test zip content');

        $this->assertTrue(file_exists($tempFile1));
        $this->assertTrue(file_exists($tempFile2));

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('cleanupTempFiles');
        $method->setAccessible(true);

        $method->invoke($this->service, [$tempFile1, $tempFile2]);

        $this->assertFalse(file_exists($tempFile1));
        $this->assertFalse(file_exists($tempFile2));
    }

    public function test_full_report_generation_workflow_without_email()
    {
        // Create test data
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::create(2024, 6, 25),
            'due_date' => Carbon::create(2024, 7, 25),
            'subtotal' => 5000.00,
            'vat_amount' => 1050.00,
            'total' => 6050.00,
            'status' => 'sent'
        ]);

        $expense = Expense::create([
            'date' => Carbon::create(2024, 7, 5),
            'amount' => 800.00,
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'vat_amount' => 168.00
        ]);

        // Mock successful email sending by setting accountant email
        config(['mail.accountant_email' => 'accountant@test.com']);

        // Test the full workflow
        $result = $this->service->generateAndSendMonthlyReport($this->report);

        $this->assertTrue($result);

        // Verify email was sent
        Mail::assertSent(MonthlyReportMailable::class);
    }

    public function test_handles_errors_gracefully()
    {
        // Create report with invalid period to trigger error
        $invalidReport = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 5, 15), // End before start
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        // Mock missing accountant email to trigger error
        config(['mail.accountant_email' => null]);

        $result = $this->service->generateAndSendMonthlyReport($invalidReport);

        $this->assertFalse($result);
    }
}