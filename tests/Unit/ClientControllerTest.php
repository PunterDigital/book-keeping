<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Http\Controllers\ClientController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_index_returns_clients_with_invoice_counts_and_revenue()
    {
        $this->actingAs($this->user);

        // Create test clients
        $client1 = Client::create([
            'company_name' => 'Test Company 1',
            'contact_name' => 'John Doe',
            'address' => '123 Test Street',
            'vat_id' => 'CZ12345678'
        ]);

        $client2 = Client::create([
            'company_name' => 'Test Company 2',
            'contact_name' => 'Jane Smith',
            'address' => '456 Test Avenue',
            'vat_id' => 'CZ87654321'
        ]);

        // Create invoices for client1
        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client1->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        Invoice::create([
            'invoice_number' => 'INV-002',
            'client_id' => $client1->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        $response = $this->get('/clients');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Index')
                ->has('clients', 2)
                ->where('clients.0.company_name', 'Test Company 1')
                ->where('clients.0.invoices_count', 2)
                ->where('clients.0.total_revenue', 1815)
                ->where('clients.1.company_name', 'Test Company 2')
                ->where('clients.1.invoices_count', 0)
                ->where('clients.1.total_revenue', 0)
        );
    }

    public function test_create_displays_create_form()
    {
        $this->actingAs($this->user);

        $response = $this->get('/clients/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Create')
        );
    }

    public function test_store_creates_new_client_with_required_fields()
    {
        $this->actingAs($this->user);

        $clientData = [
            'company_name' => 'New Test Company',
            'contact_name' => 'Test Contact',
            'email' => 'test@company.com',
            'phone' => '+420123456789',
            'address' => '123 Business Street, Prague',
            'city' => 'Prague',
            'postal_code' => '11000',
            'country' => 'Czech Republic',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678',
            'notes' => 'Test client for unit testing'
        ];

        $response = $this->post('/clients', $clientData);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', [
            'company_name' => 'New Test Company',
            'contact_name' => 'Test Contact',
            'email' => 'test@company.com',
            'vat_id' => 'CZ12345678'
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->post('/clients', []);

        $response->assertSessionHasErrors(['company_name', 'address']);
        $this->assertEquals(0, Client::count());
    }

    public function test_store_validates_email_format()
    {
        $this->actingAs($this->user);

        $response = $this->post('/clients', [
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'email' => 'invalid-email'
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_store_sets_default_country()
    {
        $this->actingAs($this->user);

        $clientData = [
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ];

        $response = $this->post('/clients', $clientData);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', [
            'company_name' => 'Test Company',
            'country' => 'Czech Republic'
        ]);
    }

    public function test_show_displays_client_with_invoices()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'contact_name' => 'John Doe',
            'email' => 'john@test.com',
            'address' => '123 Test Street',
            'vat_id' => 'CZ12345678'
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $response = $this->get("/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Show')
                ->where('client.company_name', 'Test Company')
                ->where('client.contact_name', 'John Doe')
                ->where('client.email', 'john@test.com')
                ->has('client.invoices', 1)
                ->where('client.invoices.0.invoice_number', 'INV-001')
        );
    }

    public function test_edit_displays_edit_form_with_client_data()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'contact_name' => 'John Doe',
            'address' => '123 Test Street',
            'vat_id' => 'CZ12345678'
        ]);

        $response = $this->get("/clients/{$client->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Clients/Edit')
                ->where('client.company_name', 'Test Company')
                ->where('client.contact_name', 'John Doe')
        );
    }

    public function test_update_modifies_existing_client()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Old Company Name',
            'contact_name' => 'Old Contact',
            'address' => 'Old Address',
            'vat_id' => 'CZ12345678'
        ]);

        $updateData = [
            'company_name' => 'Updated Company Name',
            'contact_name' => 'Updated Contact',
            'email' => 'updated@company.com',
            'phone' => '+420987654321',
            'address' => 'Updated Address',
            'city' => 'Updated City',
            'postal_code' => '22000',
            'country' => 'Czech Republic',
            'vat_id' => 'CZ87654321',
            'company_id' => '87654321',
            'notes' => 'Updated notes',
            'is_active' => false
        ];

        $response = $this->put("/clients/{$client->id}", $updateData);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'company_name' => 'Updated Company Name',
            'contact_name' => 'Updated Contact',
            'email' => 'updated@company.com',
            'is_active' => false
        ]);
    }

    public function test_update_validates_data()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $response = $this->put("/clients/{$client->id}", [
            'company_name' => '', // Required field
            'email' => 'invalid-email' // Invalid email
        ]);

        $response->assertSessionHasErrors(['company_name', 'email']);
    }

    public function test_destroy_deletes_client_without_invoices()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $response = $this->delete("/clients/{$client->id}");

        $response->assertRedirect('/clients');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_destroy_prevents_deletion_of_client_with_invoices()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'draft'
        ]);

        $response = $this->delete("/clients/{$client->id}");

        $response->assertRedirect('/clients');
        $response->assertSessionHas('error', 'Cannot delete client with existing invoices.');
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_clients_ordered_by_company_name()
    {
        $this->actingAs($this->user);

        Client::create(['company_name' => 'Zebra Company', 'address' => 'Address']);
        Client::create(['company_name' => 'Alpha Company', 'address' => 'Address']);
        Client::create(['company_name' => 'Beta Company', 'address' => 'Address']);

        $response = $this->get('/clients');

        $response->assertInertia(fn ($page) =>
            $page->where('clients.0.company_name', 'Alpha Company')
                ->where('clients.1.company_name', 'Beta Company')
                ->where('clients.2.company_name', 'Zebra Company')
        );
    }

    public function test_client_is_active_defaults_to_true()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_client_can_be_set_inactive()
    {
        $this->actingAs($this->user);

        $clientData = [
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'is_active' => false
        ];

        $response = $this->post('/clients', $clientData);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', [
            'company_name' => 'Test Company',
            'is_active' => false
        ]);
    }

    public function test_client_full_address_attribute()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => '123 Main Street',
            'city' => 'Prague',
            'postal_code' => '11000',
            'country' => 'Czech Republic'
        ]);

        $expectedAddress = '123 Main Street, Prague, 11000, Czech Republic';
        $this->assertEquals($expectedAddress, $client->full_address);
    }

    public function test_client_total_revenue_attribute()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'paid'
        ]);

        Invoice::create([
            'invoice_number' => 'INV-002',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        $this->assertEquals(1815.0, $client->total_revenue);
    }

    public function test_client_unpaid_amount_attribute()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        // Paid invoice (should not be included)
        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'paid'
        ]);

        // Unpaid invoice (should be included)
        Invoice::create([
            'invoice_number' => 'INV-002',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        $this->assertEquals(605.0, $client->unpaid_amount);
    }
}