<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->client = Client::create([
            'company_name' => 'Test Client s.r.o.',
            'contact_name' => 'Jan Novák',
            'email' => 'jan@testclient.cz',
            'phone' => '+420 123 456 789',
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
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        // Add invoice items
        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Testovací služba 1',
            'quantity' => 1,
            'unit_price' => 500.00,
            'vat_rate' => 21.0
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Testovací služba 2',
            'quantity' => 2,
            'unit_price' => 250.00,
            'vat_rate' => 21.0
        ]);
    }

    public function test_can_generate_invoice_pdf()
    {
        $this->actingAs($this->user);

        $pdfService = new InvoicePdfService();
        $pdfContent = $pdfService->generatePdf($this->invoice);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_can_download_invoice_pdf()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('invoices.pdf.download', $this->invoice));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
    }

    public function test_can_stream_invoice_pdf()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('invoices.pdf.stream', $this->invoice));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_generate_and_save_invoice_pdf()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('invoices.pdf.generate', $this->invoice));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'PDF was generated successfully.');

        // Check that invoice was updated with PDF path
        $this->invoice->refresh();
        $this->assertNotNull($this->invoice->pdf_path);
    }

    public function test_can_delete_invoice_pdf()
    {
        $this->actingAs($this->user);

        // First generate PDF
        $pdfService = new InvoicePdfService();
        $pdfService->savePdf($this->invoice);

        $this->invoice->refresh();
        $this->assertNotNull($this->invoice->pdf_path);

        // Then delete it
        $response = $this->delete(route('invoices.pdf.delete', $this->invoice));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'PDF was deleted successfully.');

        $this->invoice->refresh();
        $this->assertNull($this->invoice->pdf_path);
    }

    public function test_invoice_pdf_contains_czech_formatting()
    {
        $this->actingAs($this->user);

        $pdfService = new InvoicePdfService();
        
        // We can't easily test PDF content, but we can test the view renders correctly
        $view = view('invoices.pdf', ['invoice' => $this->invoice->load(['client', 'items'])]);
        $html = $view->render();

        // Check for Czech text and formatting
        $this->assertStringContainsString('FAKTURA – DAŇOVÝ DOKLAD', $html);
        $this->assertStringContainsString('Odběratel:', $html);
        $this->assertStringContainsString('Datum vystavení:', $html);
        $this->assertStringContainsString('Datum splatnosti:', $html);
        $this->assertStringContainsString('IČO:', $html);
        $this->assertStringContainsString('DIČ', $html);
        $this->assertStringContainsString('Kč', $html);
        
        // Check client data is present
        $this->assertStringContainsString($this->client->company_name, $html);
        $this->assertStringContainsString($this->invoice->invoice_number, $html);
    }

    public function test_invoice_pdf_calculates_vat_correctly()
    {
        $this->actingAs($this->user);

        $view = view('invoices.pdf', ['invoice' => $this->invoice->load(['client', 'items'])]);
        $html = $view->render();

        // Check VAT calculations are present in the HTML
        $this->assertStringContainsString('1 000,00 Kč', $html); // subtotal
        $this->assertStringContainsString('210,00 Kč', $html);   // VAT amount
        $this->assertStringContainsString('1 210,00 Kč', $html); // total
        $this->assertStringContainsString('21%', $html);         // VAT rate
    }

    public function test_pdf_service_filename_generation()
    {
        $pdfService = new InvoicePdfService();
        
        $reflection = new \ReflectionClass($pdfService);
        $method = $reflection->getMethod('generateFilename');
        $method->setAccessible(true);
        
        $filename = $method->invoke($pdfService, $this->invoice);
        
        $this->assertStringContainsString('faktura_2024001', $filename);
        $this->assertStringContainsString('Test_Client_s_r_o', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function test_unauthenticated_user_cannot_access_pdf_routes()
    {
        $response = $this->get(route('invoices.pdf.download', $this->invoice));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('invoices.pdf.stream', $this->invoice));
        $response->assertRedirect(route('login'));

        $response = $this->post(route('invoices.pdf.generate', $this->invoice));
        $response->assertRedirect(route('login'));

        $response = $this->delete(route('invoices.pdf.delete', $this->invoice));
        $response->assertRedirect(route('login'));
    }
}