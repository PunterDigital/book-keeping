<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Http\Controllers\InvoiceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->client = Client::create([
            'company_name' => 'Test Client Ltd.',
            'contact_name' => 'John Doe',
            'address' => '123 Business Street, Prague',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678'
        ]);
    }

    public function test_index_returns_invoices_with_client_data_ordered_by_date()
    {
        $this->actingAs($this->user);

        $client2 = Client::create([
            'company_name' => 'Another Client',
            'address' => '456 Another Street'
        ]);

        // Create invoices with different dates
        $invoice1 = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->addDays(25),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'sent'
        ]);

        $invoice2 = Invoice::create([
            'invoice_number' => 'INV-002',
            'client_id' => $client2->id,
            'issue_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(28),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'draft'
        ]);

        $response = $this->get('/invoices');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Index')
                ->has('invoices', 2)
                ->where('invoices.0.invoice_number', 'INV-002') // Most recent first
                ->where('invoices.0.client_name', 'Another Client')
                ->where('invoices.0.total', 605)
                ->where('invoices.1.invoice_number', 'INV-001') // Older second
                ->where('invoices.1.client_name', 'Test Client Ltd.')
        );
    }

    public function test_create_displays_form_with_clients_and_next_invoice_number()
    {
        $this->actingAs($this->user);

        // Create an inactive client to test filtering
        Client::create([
            'company_name' => 'Inactive Client',
            'address' => 'Test Address',
            'is_active' => false
        ]);

        $response = $this->get('/invoices/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Create')
                ->has('clients', 1) // Only active clients
                ->where('clients.0.company_name', 'Test Client Ltd.')
                ->has('nextInvoiceNumber')
                ->has('vatRates', 3) // Czech VAT rates: 0, 12, 21
        );
    }

    public function test_store_creates_invoice_with_items_and_calculates_totals()
    {
        $this->actingAs($this->user);

        $invoiceData = [
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'notes' => 'Test invoice with multiple items',
            'items' => [
                [
                    'description' => 'Web Development Services',
                    'quantity' => 40,
                    'unit_price' => 1000.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Domain Registration',
                    'quantity' => 1,
                    'unit_price' => 500.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);

        $response->assertRedirect('/invoices');

        // Verify invoice creation
        $invoice = Invoice::where('invoice_number', 'TEST-001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->client->id, $invoice->client_id);
        $this->assertEquals(40500.00, $invoice->subtotal); // (40*1000) + (1*500)
        $this->assertEquals(8505.00, $invoice->vat_amount); // 40500 * 0.21
        $this->assertEquals(49005.00, $invoice->total); // 40500 + 8505
        $this->assertEquals('draft', $invoice->status);

        // Verify invoice items
        $this->assertEquals(2, $invoice->items()->count());
        
        $item1 = $invoice->items()->where('description', 'Web Development Services')->first();
        $this->assertEquals(40, $item1->quantity);
        $this->assertEquals(1000.00, $item1->unit_price);
        $this->assertEquals(21, $item1->vat_rate);
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->post('/invoices', []);

        $response->assertSessionHasErrors([
            'invoice_number', 
            'client_id', 
            'issue_date', 
            'due_date', 
            'items'
        ]);
        $this->assertEquals(0, Invoice::count());
    }

    public function test_store_validates_unique_invoice_number()
    {
        $this->actingAs($this->user);

        Invoice::create([
            'invoice_number' => 'DUPLICATE-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000,
            'vat_amount' => 210,
            'total' => 1210,
            'status' => 'draft'
        ]);

        $invoiceData = [
            'invoice_number' => 'DUPLICATE-001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);

        $response->assertSessionHasErrors(['invoice_number']);
        $this->assertEquals(1, Invoice::count()); // Only the first one
    }

    public function test_store_validates_due_date_after_issue_date()
    {
        $this->actingAs($this->user);

        $invoiceData = [
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-02-15',
            'due_date' => '2024-02-10', // Before issue date
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);

        $response->assertSessionHasErrors(['due_date']);
    }

    public function test_store_validates_vat_rates()
    {
        $this->actingAs($this->user);

        $invoiceData = [
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'vat_rate' => 15 // Invalid VAT rate
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);

        $response->assertSessionHasErrors(['items.0.vat_rate']);
    }

    public function test_show_displays_invoice_with_client_and_items()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'sent',
            'notes' => 'Test invoice notes'
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 21
        ]);

        $response = $this->get("/invoices/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Show')
                ->where('invoice.invoice_number', 'TEST-001')
                ->where('invoice.client.company_name', 'Test Client Ltd.')
                ->where('invoice.subtotal', 1000)
                ->where('invoice.total', 1210)
                ->where('invoice.status', 'sent')
                ->has('invoice.items', 1)
                ->where('invoice.items.0.description', 'Test Service')
        );
    }

    public function test_edit_displays_form_with_invoice_data()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 2,
            'unit_price' => 500.00,
            'vat_rate' => 21
        ]);

        $response = $this->get("/invoices/{$invoice->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Edit')
                ->where('invoice.invoice_number', 'TEST-001')
                ->where('invoice.client_id', $this->client->id)
                ->has('invoice.items', 1)
                ->where('invoice.items.0.description', 'Test Service')
                ->where('invoice.items.0.quantity', 2)
                ->has('clients')
                ->has('vatRates', 3)
        );
    }

    public function test_update_modifies_invoice_and_items()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Original Service',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 21
        ]);

        $updateData = [
            'invoice_number' => 'TEST-001-UPDATED',
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-20',
            'due_date' => '2024-02-19',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'id' => $item->id,
                    'description' => 'Updated Service',
                    'quantity' => 2,
                    'unit_price' => 750.00,
                    'vat_rate' => 12
                ],
                [
                    'description' => 'New Service',
                    'quantity' => 1,
                    'unit_price' => 500.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->put("/invoices/{$invoice->id}", $updateData);

        $response->assertRedirect('/invoices');

        // Verify invoice update
        $invoice->refresh();
        $this->assertEquals('TEST-001-UPDATED', $invoice->invoice_number);
        $this->assertEquals(2000.00, $invoice->subtotal); // (2*750) + (1*500)
        $this->assertEquals(285.00, $invoice->vat_amount); // (1500*0.12) + (500*0.21)
        $this->assertEquals(2285.00, $invoice->total);

        // Verify items update
        $this->assertEquals(2, $invoice->items()->count());
        
        $updatedItem = $invoice->items()->find($item->id);
        $this->assertEquals('Updated Service', $updatedItem->description);
        $this->assertEquals(2, $updatedItem->quantity);
        $this->assertEquals(12, $updatedItem->vat_rate);

        $newItem = $invoice->items()->where('description', 'New Service')->first();
        $this->assertNotNull($newItem);
        $this->assertEquals(21, $newItem->vat_rate);
    }

    public function test_update_validates_unique_invoice_number_except_self()
    {
        $this->actingAs($this->user);

        $invoice1 = Invoice::create([
            'invoice_number' => 'EXISTING-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000,
            'vat_amount' => 210,
            'total' => 1210,
            'status' => 'draft'
        ]);

        $invoice2 = Invoice::create([
            'invoice_number' => 'TEST-002',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500,
            'vat_amount' => 105,
            'total' => 605,
            'status' => 'draft'
        ]);

        $updateData = [
            'invoice_number' => 'EXISTING-001', // Conflicts with invoice1
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->put("/invoices/{$invoice2->id}", $updateData);

        $response->assertSessionHasErrors(['invoice_number']);
    }

    public function test_destroy_deletes_invoice_and_items()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vat_rate' => 21
        ]);

        $response = $this->delete("/invoices/{$invoice->id}");

        $response->assertRedirect('/invoices');
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $invoice->id]);
    }

    public function test_update_status_changes_invoice_status()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $response = $this->patch("/invoices/{$invoice->id}/status", [
            'status' => 'sent'
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'sent'
        ]);
    }

    public function test_update_status_validates_status_values()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $response = $this->patch("/invoices/{$invoice->id}/status", [
            'status' => 'invalid-status'
        ]);

        $response->assertSessionHasErrors(['status']);
    }

    public function test_generate_invoice_number_creates_sequential_numbers()
    {
        $controller = new InvoiceController();
        $method = new \ReflectionMethod($controller, 'generateInvoiceNumber');
        $method->setAccessible(true);

        // Test with no existing invoices
        $number1 = $method->invoke($controller, null);
        $this->assertEquals(date('Y') . '001', $number1);

        // Create an invoice and test next number
        $lastInvoice = Invoice::create([
            'invoice_number' => date('Y') . '001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000,
            'vat_amount' => 210,
            'total' => 1210,
            'status' => 'draft'
        ]);

        $number2 = $method->invoke($controller, $lastInvoice);
        $this->assertEquals(date('Y') . '002', $number2);
    }

    public function test_vat_calculations_with_different_rates()
    {
        $this->actingAs($this->user);

        $invoiceData = [
            'invoice_number' => 'VAT-TEST',
            'client_id' => $this->client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'items' => [
                [
                    'description' => 'Standard VAT Item',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'vat_rate' => 21 // 21% VAT
                ],
                [
                    'description' => 'Reduced VAT Item',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'vat_rate' => 12 // 12% VAT
                ],
                [
                    'description' => 'Zero VAT Item',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'vat_rate' => 0 // 0% VAT
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);

        $response->assertRedirect('/invoices');

        $invoice = Invoice::where('invoice_number', 'VAT-TEST')->first();
        $this->assertEquals(3000.00, $invoice->subtotal); // 3 * 1000
        $this->assertEquals(330.00, $invoice->vat_amount); // (1000*0.21) + (1000*0.12) + (1000*0.0)
        $this->assertEquals(3330.00, $invoice->total); // 3000 + 330
    }

    public function test_invoice_is_overdue_detection()
    {
        $this->actingAs($this->user);

        $overdueInvoice = Invoice::create([
            'invoice_number' => 'OVERDUE-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now()->subDays(40),
            'due_date' => Carbon::now()->subDays(10), // 10 days overdue
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'sent'
        ]);

        $currentInvoice = Invoice::create([
            'invoice_number' => 'CURRENT-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now()->subDays(10),
            'due_date' => Carbon::now()->addDays(20), // Not due yet
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        $response = $this->get('/invoices');

        $response->assertInertia(fn ($page) =>
            $page->where('invoices.0.invoice_number', 'CURRENT-001') // Most recent first
                ->where('invoices.0.is_overdue', false)
                ->where('invoices.1.invoice_number', 'OVERDUE-001')
                ->where('invoices.1.is_overdue', true)
        );
    }
}