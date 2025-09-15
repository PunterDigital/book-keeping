<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class InvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        
        $client = Client::create([
            'company_name' => 'Test Client',
            'address' => 'Test Address'
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);
    }

    public function test_invoice_item_has_correct_fillable_attributes()
    {
        $item = new InvoiceItem();
        $fillable = $item->getFillable();

        $expectedFillable = [
            'invoice_id',
            'description',
            'quantity',
            'unit_price',
            'vat_rate',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_invoice_item_casts_types_correctly()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => '5',      // String that should be cast to int
            'unit_price' => '100',  // String that should be cast to float
            'vat_rate' => '21',     // String that should be cast to float
        ]);

        $this->assertIsFloat($item->quantity);
        $this->assertIsFloat($item->unit_price);
        $this->assertIsFloat($item->vat_rate);
        
        $this->assertEquals(5.0, $item->quantity);
        $this->assertEquals(100.0, $item->unit_price);
        $this->assertEquals(21.0, $item->vat_rate);
    }

    public function test_invoice_item_can_be_created_with_all_fields()
    {
        $itemData = [
            'invoice_id' => $this->invoice->id,
            'description' => 'Professional Consulting Service',
            'quantity' => 10,
            'unit_price' => 1500.50,
            'vat_rate' => 21.0,
        ];

        $item = InvoiceItem::create($itemData);

        $this->assertDatabaseHas('invoice_items', $itemData);
        $this->assertEquals('Professional Consulting Service', $item->description);
        $this->assertEquals(10, $item->quantity);
        $this->assertEquals(1500.50, $item->unit_price);
        $this->assertEquals(21.0, $item->vat_rate);
    }

    public function test_subtotal_attribute_calculation()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 3,
            'unit_price' => 250.75,
            'vat_rate' => 21.0,
        ]);

        $expectedSubtotal = 3 * 250.75; // 752.25
        $this->assertEquals($expectedSubtotal, $item->subtotal);
    }

    public function test_vat_amount_attribute_calculation()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 2,
            'unit_price' => 1000.00,
            'vat_rate' => 21.0,
        ]);

        $subtotal = 2 * 1000.00; // 2000.00
        $expectedVatAmount = $subtotal * (21.0 / 100); // 420.00
        $this->assertEquals($expectedVatAmount, $item->vat_amount);
    }

    public function test_vat_amount_with_different_rates()
    {
        // Test with 12% VAT rate
        $item12 = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Service with 12% VAT',
            'quantity' => 1,
            'unit_price' => 500.00,
            'vat_rate' => 12.0,
        ]);

        $expectedVat12 = 500.00 * 0.12; // 60.00
        $this->assertEquals($expectedVat12, $item12->vat_amount);

        // Test with 0% VAT rate
        $item0 = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Service with 0% VAT',
            'quantity' => 1,
            'unit_price' => 300.00,
            'vat_rate' => 0.0,
        ]);

        $this->assertEquals(0.0, $item0->vat_amount);
    }

    public function test_total_attribute_calculation()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 4,
            'unit_price' => 125.00,
            'vat_rate' => 21.0,
        ]);

        $subtotal = 4 * 125.00; // 500.00
        $vatAmount = $subtotal * 0.21; // 105.00
        $expectedTotal = $subtotal + $vatAmount; // 605.00
        
        $this->assertEquals($expectedTotal, $item->total);
    }

    public function test_belongs_to_invoice_relationship()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'vat_rate' => 21.0,
        ]);

        $this->assertInstanceOf(Invoice::class, $item->invoice);
        $this->assertEquals($this->invoice->id, $item->invoice->id);
        $this->assertEquals($this->invoice->invoice_number, $item->invoice->invoice_number);
    }

    public function test_calculations_with_decimal_quantities()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Hourly Service',
            'quantity' => 2.5, // 2.5 hours
            'unit_price' => 800.00, // per hour
            'vat_rate' => 21.0,
        ]);

        $expectedSubtotal = 2.5 * 800.00; // 2000.00
        $expectedVatAmount = $expectedSubtotal * 0.21; // 420.00
        $expectedTotal = $expectedSubtotal + $expectedVatAmount; // 2420.00

        $this->assertEquals($expectedSubtotal, $item->subtotal);
        $this->assertEquals($expectedVatAmount, $item->vat_amount);
        $this->assertEquals($expectedTotal, $item->total);
    }

    public function test_calculations_with_fractional_prices()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Fractional Price Service',
            'quantity' => 3,
            'unit_price' => 33.33, // 1/3 of 100
            'vat_rate' => 21.0,
        ]);

        $expectedSubtotal = 3 * 33.33; // 99.99
        $expectedVatAmount = $expectedSubtotal * 0.21; // 20.9979
        $expectedTotal = $expectedSubtotal + $expectedVatAmount; // 120.9879

        $this->assertEquals($expectedSubtotal, $item->subtotal);
        $this->assertEquals($expectedVatAmount, $item->vat_amount);
        $this->assertEquals($expectedTotal, $item->total);
    }

    public function test_zero_quantity_calculations()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Zero Quantity Item',
            'quantity' => 0,
            'unit_price' => 100.00,
            'vat_rate' => 21.0,
        ]);

        $this->assertEquals(0.0, $item->subtotal);
        $this->assertEquals(0.0, $item->vat_amount);
        $this->assertEquals(0.0, $item->total);
    }

    public function test_zero_price_calculations()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Free Service',
            'quantity' => 5,
            'unit_price' => 0.00,
            'vat_rate' => 21.0,
        ]);

        $this->assertEquals(0.0, $item->subtotal);
        $this->assertEquals(0.0, $item->vat_amount);
        $this->assertEquals(0.0, $item->total);
    }

    public function test_large_quantities_and_prices()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Bulk Service',
            'quantity' => 1000,
            'unit_price' => 999.99,
            'vat_rate' => 21.0,
        ]);

        $expectedSubtotal = 1000 * 999.99; // 999990.00
        $expectedVatAmount = $expectedSubtotal * 0.21; // 209997.90
        $expectedTotal = $expectedSubtotal + $expectedVatAmount; // 1209987.90

        $this->assertEquals($expectedSubtotal, $item->subtotal);
        $this->assertEquals($expectedVatAmount, $item->vat_amount);
        $this->assertEquals($expectedTotal, $item->total);
    }

    public function test_item_updates_correctly()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Original Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'vat_rate' => 21.0,
        ]);

        $originalSubtotal = $item->subtotal;

        $item->update([
            'description' => 'Updated Service',
            'quantity' => 2,
            'unit_price' => 150.00,
            'vat_rate' => 12.0,
        ]);

        $item->refresh();

        $this->assertEquals('Updated Service', $item->description);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(150.00, $item->unit_price);
        $this->assertEquals(12.0, $item->vat_rate);

        // Check that calculations are updated
        $newSubtotal = 2 * 150.00; // 300.00
        $this->assertEquals($newSubtotal, $item->subtotal);
        $this->assertNotEquals($originalSubtotal, $item->subtotal);
    }

    public function test_item_deletion()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'vat_rate' => 21.0,
        ]);

        $itemId = $item->id;
        $item->delete();

        $this->assertDatabaseMissing('invoice_items', ['id' => $itemId]);
    }

    public function test_relationship_configuration()
    {
        $item = new InvoiceItem();
        
        // Test invoice relationship
        $invoiceRelation = $item->invoice();
        $this->assertEquals(Invoice::class, $invoiceRelation->getRelated()::class);
        $this->assertEquals('invoice_id', $invoiceRelation->getForeignKeyName());
        $this->assertEquals('id', $invoiceRelation->getOwnerKeyName());
    }

    public function test_precision_handling_in_calculations()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Precision Test Service',
            'quantity' => 3,
            'unit_price' => 33.333333, // Many decimal places
            'vat_rate' => 21.0,
        ]);

        // Check that calculations handle precision correctly
        $subtotal = $item->subtotal;
        $vatAmount = $item->vat_amount;
        $total = $item->total;

        $this->assertTrue(is_float($subtotal));
        $this->assertTrue(is_float($vatAmount));
        $this->assertTrue(is_float($total));

        // Verify the relationship: total = subtotal + vat_amount
        $this->assertEquals($subtotal + $vatAmount, $total);
    }

    public function test_item_with_czech_description()
    {
        $item = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Konzultační služby v oblasti účetnictví a daní',
            'quantity' => 1,
            'unit_price' => 2500.00,
            'vat_rate' => 21.0,
        ]);

        $this->assertEquals('Konzultační služby v oblasti účetnictví a daní', $item->description);
        $this->assertEquals(2500.00, $item->subtotal);
        $this->assertEquals(525.00, $item->vat_amount); // 2500 * 0.21
        $this->assertEquals(3025.00, $item->total);
    }
}