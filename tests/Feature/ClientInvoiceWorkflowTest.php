<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ClientInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_complete_client_management_workflow()
    {
        $this->actingAs($this->user);

        // 1. Navigate to clients index (should be empty)
        $response = $this->get('/clients');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Index')
                ->has('clients', 0)
        );

        // 2. Navigate to create client form
        $response = $this->get('/clients/create');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Create')
        );

        // 3. Create a new client
        $clientData = [
            'company_name' => 'Innovative Tech Solutions s.r.o.',
            'contact_name' => 'Jan Novák',
            'email' => 'jan.novak@techsolutions.cz',
            'phone' => '+420 724 123 456',
            'address' => 'Václavské náměstí 28',
            'city' => 'Praha 1',
            'postal_code' => '110 00',
            'country' => 'Czech Republic',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678',
            'notes' => 'Important client - provides web development services'
        ];

        $response = $this->post('/clients', $clientData);
        $response->assertRedirect('/clients');

        // 4. Verify client appears in index
        $response = $this->get('/clients');
        $response->assertInertia(fn ($page) =>
            $page->has('clients', 1)
                ->where('clients.0.company_name', 'Innovative Tech Solutions s.r.o.')
                ->where('clients.0.contact_name', 'Jan Novák')
                ->where('clients.0.invoices_count', 0)
                ->where('clients.0.total_revenue', 0)
        );

        $client = Client::first();

        // 5. View client details
        $response = $this->get("/clients/{$client->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Show')
                ->where('client.company_name', 'Innovative Tech Solutions s.r.o.')
                ->where('client.vat_id', 'CZ12345678')
                ->has('client.invoices', 0)
        );

        // 6. Edit client information
        $response = $this->get("/clients/{$client->id}/edit");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Edit')
                ->where('client.company_name', 'Innovative Tech Solutions s.r.o.')
        );

        // 7. Update client
        $updatedData = [
            'company_name' => 'Innovative Tech Solutions s.r.o.',
            'contact_name' => 'Jan Novák',
            'email' => 'j.novak@techsolutions.cz', // Updated email
            'phone' => '+420 724 123 456',
            'address' => 'Václavské náměstí 28',
            'city' => 'Praha 1',
            'postal_code' => '110 00',
            'country' => 'Czech Republic',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678',
            'notes' => 'VIP client - provides web development services',
            'is_active' => true
        ];

        $response = $this->put("/clients/{$client->id}", $updatedData);
        $response->assertRedirect('/clients');

        // 8. Verify update
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'email' => 'j.novak@techsolutions.cz',
            'notes' => 'VIP client - provides web development services'
        ]);
    }

    public function test_complete_invoice_creation_and_management_workflow()
    {
        $this->actingAs($this->user);

        // Setup: Create a client first
        $client = Client::create([
            'company_name' => 'Test Client Ltd.',
            'contact_name' => 'John Doe',
            'address' => '123 Business Street, Prague',
            'vat_id' => 'CZ87654321',
            'company_id' => '87654321'
        ]);

        // 1. Navigate to invoices index (should be empty)
        $response = $this->get('/invoices');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Index')
                ->has('invoices', 0)
        );

        // 2. Navigate to create invoice form
        $response = $this->get('/invoices/create');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Create')
                ->has('clients', 1)
                ->where('clients.0.company_name', 'Test Client Ltd.')
                ->has('nextInvoiceNumber')
                ->has('vatRates', 3)
        );

        // 3. Create invoice with multiple items
        $invoiceData = [
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'notes' => 'Website development project - Phase 1',
            'items' => [
                [
                    'description' => 'Frontend Development (React.js)',
                    'quantity' => 80,
                    'unit_price' => 1200.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Backend API Development (Laravel)',
                    'quantity' => 60,
                    'unit_price' => 1500.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Database Design and Setup',
                    'quantity' => 20,
                    'unit_price' => 1000.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Project Documentation',
                    'quantity' => 10,
                    'unit_price' => 800.00,
                    'vat_rate' => 12
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);
        $response->assertRedirect('/invoices');

        // 4. Verify invoice creation
        $invoice = Invoice::where('invoice_number', '2024001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($client->id, $invoice->client_id);
        $this->assertEquals('draft', $invoice->status);
        
        // Verify calculations: (80*1200) + (60*1500) + (20*1000) + (10*800) = 214000
        $this->assertEquals(214000.00, $invoice->subtotal);
        // VAT: (206000 * 0.21) + (8000 * 0.12) = 43260 + 960 = 44220
        $this->assertEquals(44220.00, $invoice->vat_amount);
        // Total: 214000 + 44220 = 258220
        $this->assertEquals(258220.00, $invoice->total);
        $this->assertEquals(4, $invoice->items()->count());

        // 5. View invoice in list
        $response = $this->get('/invoices');
        $response->assertInertia(fn ($page) =>
            $page->has('invoices', 1)
                ->where('invoices.0.invoice_number', '2024001')
                ->where('invoices.0.client_name', 'Test Client Ltd.')
                ->where('invoices.0.total', 258220)
                ->where('invoices.0.status', 'draft')
        );

        // 6. View invoice details
        $response = $this->get("/invoices/{$invoice->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Show')
                ->where('invoice.invoice_number', '2024001')
                ->where('invoice.client.company_name', 'Test Client Ltd.')
                ->where('invoice.subtotal', 214000)
                ->where('invoice.vat_amount', 44220)
                ->where('invoice.total', 258220)
                ->where('invoice.status', 'draft')
                ->has('invoice.items', 4)
                ->where('invoice.items.0.description', 'Frontend Development (React.js)')
                ->where('invoice.items.0.quantity', 80)
                ->where('invoice.items.0.vat_rate', 21)
        );

        // 7. Update invoice status to sent
        $response = $this->patch("/invoices/{$invoice->id}/status", [
            'status' => 'sent'
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'sent'
        ]);

        // 8. Edit invoice
        $response = $this->get("/invoices/{$invoice->id}/edit");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Invoices/Edit')
                ->where('invoice.invoice_number', '2024001')
                ->where('invoice.client_id', $client->id)
                ->has('invoice.items', 4)
        );

        // 9. Update invoice (add new item, modify existing)
        $updateData = [
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'notes' => 'Website development project - Phase 1 (Updated)',
            'items' => [
                [
                    'id' => $invoice->items()->first()->id,
                    'description' => 'Frontend Development (React.js) - Updated',
                    'quantity' => 85, // Increased quantity
                    'unit_price' => 1200.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Additional Testing Services',
                    'quantity' => 20,
                    'unit_price' => 900.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->put("/invoices/{$invoice->id}", $updateData);
        $response->assertRedirect('/invoices');

        // 10. Verify invoice update
        $invoice->refresh();
        $this->assertEquals('Website development project - Phase 1 (Updated)', $invoice->notes);
        $this->assertEquals(2, $invoice->items()->count());
        
        // New calculations: (85*1200) + (20*900) = 102000 + 18000 = 120000
        $this->assertEquals(120000.00, $invoice->subtotal);
        // VAT: 120000 * 0.21 = 25200
        $this->assertEquals(25200.00, $invoice->vat_amount);
        // Total: 120000 + 25200 = 145200
        $this->assertEquals(145200.00, $invoice->total);

        // 11. Mark invoice as paid
        $response = $this->patch("/invoices/{$invoice->id}/status", [
            'status' => 'paid'
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid'
        ]);

        // 12. Verify client shows updated revenue
        $response = $this->get("/clients/{$client->id}");
        $response->assertInertia(fn ($page) =>
            $page->where('client.invoices.0.total', 145200)
                ->where('client.invoices.0.status', 'paid')
        );
    }

    public function test_client_protection_against_deletion_with_invoices()
    {
        $this->actingAs($this->user);

        // Create client
        $client = Client::create([
            'company_name' => 'Protected Client',
            'address' => 'Test Address'
        ]);

        // Try to delete client without invoices (should work)
        $response = $this->delete("/clients/{$client->id}");
        $response->assertRedirect('/clients');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);

        // Create new client with invoice
        $client = Client::create([
            'company_name' => 'Client with Invoice',
            'address' => 'Test Address'
        ]);

        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        // Try to delete client with invoices (should fail)
        $response = $this->delete("/clients/{$client->id}");
        $response->assertRedirect('/clients');
        $response->assertSessionHas('error', 'Cannot delete client with existing invoices.');
        $this->assertDatabaseHas('clients', ['id' => $client->id]);

        // Delete the invoice first, then client (should work)
        $response = $this->delete("/invoices/{$invoice->id}");
        $response->assertRedirect('/invoices');

        $response = $this->delete("/clients/{$client->id}");
        $response->assertRedirect('/clients');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_invoice_overdue_detection_workflow()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Client',
            'address' => 'Test Address'
        ]);

        // Create overdue invoice
        $overdueInvoice = Invoice::create([
            'invoice_number' => 'OVERDUE-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now()->subDays(45),
            'due_date' => Carbon::now()->subDays(15), // 15 days overdue
            'subtotal' => 5000.00,
            'vat_amount' => 1050.00,
            'total' => 6050.00,
            'status' => 'sent'
        ]);

        // Create current invoice
        $currentInvoice = Invoice::create([
            'invoice_number' => 'CURRENT-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->addDays(25), // Due in 25 days
            'subtotal' => 2000.00,
            'vat_amount' => 420.00,
            'total' => 2420.00,
            'status' => 'sent'
        ]);

        // Create paid invoice (should not be marked overdue)
        $paidInvoice = Invoice::create([
            'invoice_number' => 'PAID-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now()->subDays(60),
            'due_date' => Carbon::now()->subDays(30), // Was due 30 days ago
            'subtotal' => 3000.00,
            'vat_amount' => 630.00,
            'total' => 3630.00,
            'status' => 'paid' // But it's paid, so not overdue
        ]);

        $response = $this->get('/invoices');

        $response->assertInertia(fn ($page) =>
            $page->has('invoices', 3)
                ->where('invoices.0.invoice_number', 'CURRENT-001') // Most recent first
                ->where('invoices.0.is_overdue', false)
                ->where('invoices.1.invoice_number', 'OVERDUE-001')
                ->where('invoices.1.is_overdue', true) // This one is overdue
                ->where('invoices.2.invoice_number', 'PAID-001')
                ->where('invoices.2.is_overdue', false) // Paid invoices are never overdue
        );
    }

    public function test_czech_vat_rate_compliance_workflow()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Czech VAT Test Client',
            'address' => 'Prague, Czech Republic',
            'vat_id' => 'CZ12345678'
        ]);

        // Create invoice with all Czech VAT rates
        $invoiceData = [
            'invoice_number' => 'VAT-COMPLIANCE-001',
            'client_id' => $client->id,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-02-14',
            'notes' => 'Testing Czech VAT compliance with all rates',
            'items' => [
                [
                    'description' => 'Standard VAT Services (21%)',
                    'quantity' => 1,
                    'unit_price' => 10000.00,
                    'vat_rate' => 21
                ],
                [
                    'description' => 'Reduced VAT Services (12%)',
                    'quantity' => 1,
                    'unit_price' => 5000.00,
                    'vat_rate' => 12
                ],
                [
                    'description' => 'Zero VAT Services (0%)',
                    'quantity' => 1,
                    'unit_price' => 2000.00,
                    'vat_rate' => 0
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);
        $response->assertRedirect('/invoices');

        $invoice = Invoice::where('invoice_number', 'VAT-COMPLIANCE-001')->first();
        
        // Verify calculations
        $this->assertEquals(17000.00, $invoice->subtotal); // 10000 + 5000 + 2000
        // VAT: (10000 * 0.21) + (5000 * 0.12) + (2000 * 0.0) = 2100 + 600 + 0 = 2700
        $this->assertEquals(2700.00, $invoice->vat_amount);
        $this->assertEquals(19700.00, $invoice->total); // 17000 + 2700

        // Verify individual items have correct VAT calculations
        $items = $invoice->items()->orderBy('vat_rate', 'desc')->get();
        
        $standardVatItem = $items->where('vat_rate', 21)->first();
        $this->assertEquals(10000.00, $standardVatItem->subtotal);
        $this->assertEquals(2100.00, $standardVatItem->vat_amount);
        $this->assertEquals(12100.00, $standardVatItem->total);

        $reducedVatItem = $items->where('vat_rate', 12)->first();
        $this->assertEquals(5000.00, $reducedVatItem->subtotal);
        $this->assertEquals(600.00, $reducedVatItem->vat_amount);
        $this->assertEquals(5600.00, $reducedVatItem->total);

        $zeroVatItem = $items->where('vat_rate', 0)->first();
        $this->assertEquals(2000.00, $zeroVatItem->subtotal);
        $this->assertEquals(0.00, $zeroVatItem->vat_amount);
        $this->assertEquals(2000.00, $zeroVatItem->total);
    }

    public function test_dashboard_integration_with_client_and_invoice_data()
    {
        $this->actingAs($this->user);

        // Create multiple clients
        $client1 = Client::create([
            'company_name' => 'Active Client 1',
            'address' => 'Address 1'
        ]);

        $client2 = Client::create([
            'company_name' => 'Active Client 2',
            'address' => 'Address 2'
        ]);

        $client3 = Client::create([
            'company_name' => 'Inactive Client',
            'address' => 'Address 3',
            'is_active' => false
        ]);

        // Create invoices for current month
        Invoice::create([
            'invoice_number' => 'DASH-001',
            'client_id' => $client1->id,
            'issue_date' => Carbon::now()->startOfMonth()->addDays(5),
            'due_date' => Carbon::now()->startOfMonth()->addDays(35),
            'subtotal' => 10000.00,
            'vat_amount' => 2100.00,
            'total' => 12100.00,
            'status' => 'paid'
        ]);

        Invoice::create([
            'invoice_number' => 'DASH-002',
            'client_id' => $client2->id,
            'issue_date' => Carbon::now()->startOfMonth()->addDays(10),
            'due_date' => Carbon::now()->startOfMonth()->addDays(40),
            'subtotal' => 5000.00,
            'vat_amount' => 1050.00,
            'total' => 6050.00,
            'status' => 'sent'
        ]);

        // Create invoice for previous month (should not be counted)
        Invoice::create([
            'invoice_number' => 'PREV-001',
            'client_id' => $client1->id,
            'issue_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(15),
            'due_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(45),
            'subtotal' => 8000.00,
            'vat_amount' => 1680.00,
            'total' => 9680.00,
            'status' => 'paid'
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->where('stats.invoices_this_month', 2) // Only current month
                ->where('stats.invoices_total', 18150) // 12100 + 6050
                ->where('stats.invoices_subtotal', 15000) // 10000 + 5000
                ->where('stats.invoices_vat', 3150) // 2100 + 1050
                ->where('stats.active_clients', 2) // Clients with invoices (client1 and client2)
                ->where('stats.total_clients', 3) // All clients including inactive
                ->has('recentActivity') // Should include recent invoices
        );
    }
}