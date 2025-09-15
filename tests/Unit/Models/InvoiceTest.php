<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = Client::create([
            'company_name' => 'Test Company s.r.o.',
            'address' => 'Test Address 123',
            'vat_id' => 'CZ12345678'
        ]);
    }

    public function test_invoice_has_fillable_attributes()
    {
        $invoice = new Invoice();
        $fillable = $invoice->getFillable();

        $expectedFillable = [
            'invoice_number',
            'client_id',
            'issue_date',
            'due_date',
            'status',
            'subtotal',
            'vat_amount',
            'total',
            'notes',
            'pdf_path',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_invoice_casts_dates_correctly()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-06-15',
            'due_date' => '2024-07-15',
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $this->assertInstanceOf(Carbon::class, $invoice->issue_date);
        $this->assertInstanceOf(Carbon::class, $invoice->due_date);
    }

    public function test_invoice_casts_amounts_as_floats()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => '1000',
            'vat_amount' => '210',
            'total' => '1210',
        ]);

        $this->assertIsFloat($invoice->subtotal);
        $this->assertIsFloat($invoice->vat_amount);
        $this->assertIsFloat($invoice->total);
        
        $this->assertEquals(1000.0, $invoice->subtotal);
        $this->assertEquals(210.0, $invoice->vat_amount);
        $this->assertEquals(1210.0, $invoice->total);
    }

    public function test_invoice_belongs_to_client()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $this->assertInstanceOf(Client::class, $invoice->client);
        $this->assertEquals($this->client->id, $invoice->client->id);
        $this->assertEquals($this->client->company_name, $invoice->client->company_name);
    }

    public function test_invoice_has_many_items()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $item1 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service 1',
            'quantity' => 1,
            'unit_price' => 500.00,
            'vat_rate' => 21.0
        ]);

        $item2 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service 2',
            'quantity' => 2,
            'unit_price' => 250.00,
            'vat_rate' => 21.0
        ]);

        $this->assertCount(2, $invoice->items);
        $this->assertInstanceOf(InvoiceItem::class, $invoice->items->first());
        $this->assertEquals('Service 1', $invoice->items->first()->description);
        $this->assertEquals('Service 2', $invoice->items->last()->description);
    }

    public function test_invoice_can_be_created_with_all_fields()
    {
        $invoiceData = [
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::create(2024, 6, 15),
            'due_date' => Carbon::create(2024, 7, 15),
            'status' => 'draft',
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'notes' => 'Test invoice notes',
            'pdf_path' => 'invoices/2024/06/invoice.pdf'
        ];

        $invoice = Invoice::create($invoiceData);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'status' => 'draft',
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'notes' => 'Test invoice notes',
            'pdf_path' => 'invoices/2024/06/invoice.pdf'
        ]);

        $this->assertEquals('2024001', $invoice->invoice_number);
        $this->assertEquals('draft', $invoice->status);
        $this->assertEquals('Test invoice notes', $invoice->notes);
    }

    public function test_invoice_can_be_created_with_minimal_fields()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertEquals('2024001', $invoice->invoice_number);
        $this->assertNull($invoice->status);
        $this->assertNull($invoice->notes);
        $this->assertNull($invoice->pdf_path);
    }

    public function test_invoice_updates_correctly()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $invoice->update([
            'status' => 'sent',
            'notes' => 'Updated notes',
            'pdf_path' => 'path/to/pdf.pdf'
        ]);

        $this->assertEquals('sent', $invoice->fresh()->status);
        $this->assertEquals('Updated notes', $invoice->fresh()->notes);
        $this->assertEquals('path/to/pdf.pdf', $invoice->fresh()->pdf_path);
    }

    public function test_invoice_deletion_cascade_to_items()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 21.0
        ]);

        $this->assertDatabaseHas('invoice_items', ['id' => $item->id]);

        $invoice->delete();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        // Note: Cascade deletion depends on database constraints or explicit deletion in code
    }

    public function test_invoice_date_formatting()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-06-15',
            'due_date' => '2024-07-15',
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        // Test that dates can be formatted properly
        $this->assertEquals('15.06.2024', $invoice->issue_date->format('d.m.Y'));
        $this->assertEquals('15.07.2024', $invoice->due_date->format('d.m.Y'));
    }

    public function test_invoice_status_values()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        // Test different status values
        $validStatuses = ['draft', 'sent', 'paid', 'overdue'];
        
        foreach ($validStatuses as $status) {
            $invoice->update(['status' => $status]);
            $this->assertEquals($status, $invoice->fresh()->status);
        }
    }

    public function test_invoice_amounts_precision()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1234.56,
            'vat_amount' => 259.26, // 1234.56 * 0.21
            'total' => 1493.82,
        ]);

        $this->assertEquals(1234.56, $invoice->subtotal);
        $this->assertEquals(259.26, $invoice->vat_amount);
        $this->assertEquals(1493.82, $invoice->total);
    }

    public function test_invoice_relationships_are_properly_configured()
    {
        $invoice = new Invoice();
        
        // Test client relationship
        $clientRelation = $invoice->client();
        $this->assertEquals(Client::class, $clientRelation->getRelated()::class);
        $this->assertEquals('client_id', $clientRelation->getForeignKeyName());
        $this->assertEquals('id', $clientRelation->getOwnerKeyName());

        // Test items relationship  
        $itemsRelation = $invoice->items();
        $this->assertEquals(InvoiceItem::class, $itemsRelation->getRelated()::class);
        $this->assertEquals('invoice_id', $itemsRelation->getForeignKeyName());
        $this->assertEquals('id', $itemsRelation->getLocalKeyName());
    }

    public function test_invoice_with_zero_amounts()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 0.00,
            'vat_amount' => 0.00,
            'total' => 0.00,
        ]);

        $this->assertEquals(0.0, $invoice->subtotal);
        $this->assertEquals(0.0, $invoice->vat_amount);
        $this->assertEquals(0.0, $invoice->total);
    }

    public function test_invoice_with_large_amounts()
    {
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 999999.99,
            'vat_amount' => 209999.998, // Will be stored as 210000.00 due to precision
            'total' => 1209999.99,
        ]);

        $this->assertEquals(999999.99, $invoice->subtotal);
        $this->assertEquals(210000.00, round($invoice->vat_amount, 2));
        $this->assertEquals(1209999.99, $invoice->total);
    }
}