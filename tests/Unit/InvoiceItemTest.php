<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_item_belongs_to_invoice()
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

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 100.00,
            'vat_rate' => 21.00
        ]);

        $this->assertInstanceOf(Invoice::class, $item->invoice);
        $this->assertEquals('INV-001', $item->invoice->invoice_number);
    }

    public function test_invoice_item_calculates_subtotal()
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

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 100.00,
            'vat_rate' => 21.00
        ]);

        $this->assertEquals(1000.00, $item->subtotal);
    }

    public function test_invoice_item_calculates_vat_amount()
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

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 100.00,
            'vat_rate' => 21.00
        ]);

        $this->assertEquals(210.00, $item->vat_amount);
    }

    public function test_invoice_item_calculates_total()
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

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Web Development',
            'quantity' => 10,
            'unit_price' => 100.00,
            'vat_rate' => 21.00
        ]);

        $this->assertEquals(1210.00, $item->total);
    }
}