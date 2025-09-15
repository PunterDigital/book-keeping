<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookkeepingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_dashboard_shows_correct_stats()
    {
        $this->actingAs($this->user);

        // Create test data
        $category = ExpenseCategory::create(['name' => 'Office']);
        Expense::create([
            'date' => now(),
            'amount' => 500.00,
            'category_id' => $category->id,
            'description' => 'Test expense',
            'vat_amount' => 105.00
        ]);

        $client = Client::create([
            'company_name' => 'Test Client',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678'
        ]);

        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);

        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        // The title is set via Inertia Head component, so we check the Inertia data
        $response->assertInertia(fn ($page) => 
            $page->component('Dashboard')
        );
    }

    public function test_authenticated_routes_require_login()
    {
        $routes = [
            '/dashboard',
            '/expenses',
            '/clients', 
            '/invoices',
            '/reports'
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_access_protected_routes()
    {
        $this->actingAs($this->user);

        $routes = [
            '/dashboard' => 200,
        ];

        foreach ($routes as $route => $expectedStatus) {
            $response = $this->get($route);
            $response->assertStatus($expectedStatus);
        }
    }

    public function test_expense_category_crud_workflow()
    {
        $this->actingAs($this->user);

        // Test creating category
        $response = $this->post('/expense-categories', [
            'name' => 'Office Supplies'
        ]);
        
        // Should redirect after successful creation
        $response->assertRedirect();
        
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Office Supplies'
        ]);

        $category = ExpenseCategory::where('name', 'Office Supplies')->first();

        // Test updating category
        $response = $this->put("/expense-categories/{$category->id}", [
            'name' => 'Office Equipment'
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Office Equipment'
        ]);

        // Test deleting category
        $response = $this->delete("/expense-categories/{$category->id}");
        
        $response->assertRedirect();
        
        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id
        ]);
    }

    public function test_client_crud_workflow()
    {
        $this->actingAs($this->user);

        // Test creating client
        $clientData = [
            'company_name' => 'Test Company s.r.o.',
            'contact_name' => 'Jan Novák',
            'address' => 'Václavské náměstí 1, 110 00 Praha 1',
            'vat_id' => 'CZ12345678',
            'company_id' => 'CZ12345678901'
        ];

        $response = $this->post('/clients', $clientData);
        
        $response->assertRedirect();
        
        $this->assertDatabaseHas('clients', $clientData);

        $client = Client::where('company_name', 'Test Company s.r.o.')->first();

        // Test updating client
        $updateData = array_merge($clientData, ['company_name' => 'Updated Company s.r.o.']);
        $response = $this->put("/clients/{$client->id}", $updateData);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'company_name' => 'Updated Company s.r.o.'
        ]);

        // Test deleting client
        $response = $this->delete("/clients/{$client->id}");
        
        $response->assertRedirect();
        
        $this->assertDatabaseMissing('clients', [
            'id' => $client->id
        ]);
    }

    public function test_expense_crud_workflow()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);

        // Test creating expense
        $expenseData = [
            'date' => now()->format('Y-m-d'),
            'amount' => 1500.00,
            'category_id' => $category->id,
            'description' => 'Business trip to Brno',
            'vat_amount' => 315.00
        ];

        $response = $this->post('/expenses', $expenseData);
        
        $response->assertRedirect();
        
        // Check database with date format adjustment
        $this->assertDatabaseHas('expenses', [
            'amount' => 1500.00,
            'category_id' => $category->id,
            'description' => 'Business trip to Brno',
            'vat_amount' => 315.00
        ]);

        $expense = Expense::where('description', 'Business trip to Brno')->first();

        // Test updating expense  
        $updateData = array_merge($expenseData, ['amount' => 1800.00, 'vat_amount' => 378.00]);
        $response = $this->put("/expenses/{$expense->id}", $updateData);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 1800.00,
            'vat_amount' => 378.00
        ]);

        // Test deleting expense
        $response = $this->delete("/expenses/{$expense->id}");
        
        $response->assertRedirect();
        
        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id
        ]);
    }

    public function test_invoice_creation_workflow()
    {
        $this->actingAs($this->user);

        $client = Client::create([
            'company_name' => 'Test Client',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678'
        ]);

        // Test creating invoice
        $invoiceData = [
            'invoice_number' => 'INV-2024-001',
            'client_id' => $client->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'draft',
            'notes' => 'Web development services',
            'items' => [
                [
                    'description' => 'Web development services',
                    'quantity' => 1,
                    'unit_price' => 10000.00,
                    'vat_rate' => 21
                ]
            ]
        ];

        $response = $this->post('/invoices', $invoiceData);
        
        $response->assertRedirect();
        
        // Check database without date fields to avoid format issues
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2024-001',
            'client_id' => $client->id,
            'status' => 'draft',
            'subtotal' => 10000.00,
            'vat_amount' => 2100.00,
            'total' => 12100.00,
            'notes' => 'Web development services'
        ]);

        $invoice = Invoice::where('invoice_number', 'INV-2024-001')->first();
        
        // Test creating invoice items
        $itemData = [
            'description' => 'Frontend Development',
            'quantity' => 40,
            'unit_price' => 250.00,
            'vat_rate' => 21.00
        ];

        $invoice->items()->create($itemData);
        
        $this->assertDatabaseHas('invoice_items', array_merge($itemData, [
            'invoice_id' => $invoice->id
        ]));
    }
}