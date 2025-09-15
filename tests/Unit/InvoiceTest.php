<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_belongs_to_client()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address 123, Prague',
            'vat_id' => 'CZ12345678'
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);

        $this->assertInstanceOf(Client::class, $invoice->client);
        $this->assertEquals('Test Company', $invoice->client->company_name);
    }

    public function test_invoice_has_many_items()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678'
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 100.00,
            'vat_rate' => 21.00
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulting',
            'quantity' => 5,
            'unit_price' => 150.00,
            'vat_rate' => 21.00
        ]);

        $this->assertCount(2, $invoice->items);
        $this->assertInstanceOf(InvoiceItem::class, $invoice->items->first());
    }

    public function test_invoice_casts_dates_and_amounts()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678'
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'subtotal' => '1000.00',
            'vat_amount' => '210.00',
            'total' => '1210.00'
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $invoice->issue_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $invoice->due_date);
        $this->assertIsFloat($invoice->subtotal);
        $this->assertIsFloat($invoice->vat_amount);
        $this->assertIsFloat($invoice->total);
    }
}