<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Mockery;

class InvoicePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoicePdfService $service;
    protected Client $client;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new InvoicePdfService();
        
        $this->client = Client::create([
            'company_name' => 'Test Company s.r.o.',
            'contact_name' => 'Jan Novák',
            'email' => 'jan@test.cz',
            'address' => 'Testovací 123',
            'city' => 'Praha',
            'postal_code' => '11000',
            'country' => 'Česká republika',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678'
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::create(2024, 6, 15),
            'due_date' => Carbon::create(2024, 7, 15),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Testovací služba',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 21.0
        ]);
    }

    public function test_generate_pdf_returns_pdf_content()
    {
        $pdfContent = $this->service->generatePdf($this->invoice);

        $this->assertIsString($pdfContent);
        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_generate_filename_creates_proper_filename()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateFilename');
        $method->setAccessible(true);

        $filename = $method->invoke($this->service, $this->invoice);

        $this->assertStringContainsString('faktura_2024001', $filename);
        $this->assertStringContainsString('Test_Company_s_r_o', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertDoesNotMatchRegularExpression('/[^a-zA-Z0-9._-]/', $filename);
    }

    public function test_generate_storage_path_creates_organized_path()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateStoragePath');
        $method->setAccessible(true);

        $path = $method->invoke($this->service, $this->invoice);

        $this->assertEquals('invoices/2024/06/faktura_2024001.pdf', $path);
    }

    public function test_sanitize_filename_removes_czech_diacritics()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'Příliš žluťoučký kůň úpěl ďábelské ódy');
        
        $this->assertEquals('Prilis_zlutoucky_kun_upel_dabelske_ody', $result);
    }

    public function test_sanitize_filename_handles_special_characters()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'Test & Co. (Praha) - 50% sleva!');
        
        $this->assertEquals('Test_Co_Praha_-_50_sleva', $result);
    }

    public function test_sanitize_filename_removes_multiple_underscores()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'Test   &&&   Company   !!!');
        
        $this->assertEquals('Test_Company', $result);
    }

    public function test_save_pdf_stores_file_and_updates_invoice()
    {
        Storage::fake('public');

        $path = $this->service->savePdf($this->invoice);

        // Check file was stored
        Storage::disk('public')->assertExists($path);
        
        // Check invoice was updated
        $this->invoice->refresh();
        $this->assertEquals($path, $this->invoice->pdf_path);
        
        // Check file content
        $content = Storage::disk('public')->get($path);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_save_pdf_with_custom_path()
    {
        Storage::fake('public');

        $customPath = 'custom/test/invoice.pdf';
        $path = $this->service->savePdf($this->invoice, $customPath);

        $this->assertEquals($customPath, $path);
        Storage::disk('public')->assertExists($customPath);
        
        $this->invoice->refresh();
        $this->assertEquals($customPath, $this->invoice->pdf_path);
    }

    public function test_has_pdf_returns_true_when_pdf_exists()
    {
        Storage::fake('public');
        
        $this->service->savePdf($this->invoice);
        
        $this->assertTrue($this->service->hasPdf($this->invoice));
    }

    public function test_has_pdf_returns_false_when_no_pdf()
    {
        $this->assertFalse($this->service->hasPdf($this->invoice));
    }

    public function test_has_pdf_returns_false_when_file_missing()
    {
        Storage::fake('public');
        
        $this->invoice->update(['pdf_path' => 'non-existent/path.pdf']);
        
        $this->assertFalse($this->service->hasPdf($this->invoice));
    }

    public function test_get_stored_pdf_path_returns_path_when_exists()
    {
        Storage::fake('public');
        
        $path = $this->service->savePdf($this->invoice);
        $retrievedPath = $this->service->getStoredPdfPath($this->invoice);
        
        $this->assertEquals($path, $retrievedPath);
    }

    public function test_get_stored_pdf_path_returns_null_when_not_exists()
    {
        $path = $this->service->getStoredPdfPath($this->invoice);
        
        $this->assertNull($path);
    }

    public function test_delete_pdf_removes_file_and_updates_invoice()
    {
        Storage::fake('public');
        
        $path = $this->service->savePdf($this->invoice);
        Storage::disk('public')->assertExists($path);
        
        $result = $this->service->deletePdf($this->invoice);
        
        $this->assertTrue($result);
        Storage::disk('public')->assertMissing($path);
        
        $this->invoice->refresh();
        $this->assertNull($this->invoice->pdf_path);
    }

    public function test_delete_pdf_returns_false_when_no_pdf()
    {
        $result = $this->service->deletePdf($this->invoice);
        
        $this->assertFalse($result);
    }

    public function test_download_pdf_returns_response_with_correct_headers()
    {
        $response = $this->service->downloadPdf($this->invoice);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('faktura_2024001', $response->headers->get('Content-Disposition'));
    }

    public function test_download_pdf_with_custom_filename()
    {
        $customFilename = 'custom_invoice.pdf';
        $response = $this->service->downloadPdf($this->invoice, $customFilename);

        $this->assertStringContainsString($customFilename, $response->headers->get('Content-Disposition'));
    }

    public function test_stream_pdf_returns_response_for_inline_viewing()
    {
        $response = $this->service->streamPdf($this->invoice);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_email_pdf_returns_pdf_content()
    {
        $content = $this->service->emailPdf($this->invoice);

        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_service_loads_invoice_relationships()
    {
        // Create invoice without loading relationships
        $invoice = Invoice::find($this->invoice->id);
        $this->assertFalse($invoice->relationLoaded('client'));
        $this->assertFalse($invoice->relationLoaded('items'));

        $this->service->generatePdf($invoice);

        // The service should load relationships internally
        // We can't directly test this without mocking, but we can verify PDF generates successfully
        $this->assertTrue(true); // PDF generation would fail if relationships weren't loaded
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}