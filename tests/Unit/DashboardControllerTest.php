<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Client;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ExpenseCategory $category;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->category = ExpenseCategory::create(['name' => 'Test Category']);
        $this->client = Client::create([
            'company_name' => 'Test Client',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678'
        ]);
    }

    public function test_dashboard_displays_current_month_statistics()
    {
        $this->actingAs($this->user);

        // Create expenses for current month
        Expense::create([
            'date' => Carbon::now()->startOfMonth()->addDays(5),
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Current month expense 1',
            'vat_amount' => 210.00
        ]);

        Expense::create([
            'date' => Carbon::now()->startOfMonth()->addDays(10),
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'Current month expense 2',
            'vat_amount' => 105.00
        ]);

        // Create expense for previous month (should not be counted)
        Expense::create([
            'date' => Carbon::now()->subMonth(),
            'amount' => 2000.00,
            'category_id' => $this->category->id,
            'description' => 'Previous month expense',
            'vat_amount' => 420.00
        ]);

        // Create invoices for current month
        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now()->startOfMonth()->addDays(3),
            'due_date' => Carbon::now()->startOfMonth()->addDays(33),
            'subtotal' => 5000.00,
            'vat_amount' => 1050.00,
            'total' => 6050.00
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->where('stats.expenses_this_month', 2)
                ->where('stats.expenses_amount', 1500)
                ->where('stats.expenses_vat', 315)
                ->where('stats.invoices_this_month', 1)
                ->where('stats.invoices_total', 6050)
                ->where('stats.active_clients', 1)
                ->where('stats.total_clients', 1)
        );
    }

    public function test_dashboard_handles_empty_data()
    {
        $this->actingAs($this->user);
        
        // Remove the client created in setUp to test truly empty state
        $this->client->delete();

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->where('stats.expenses_this_month', 0)
                ->where('stats.expenses_amount', 0)
                ->where('stats.expenses_vat', 0)
                ->where('stats.invoices_this_month', 0)
                ->where('stats.invoices_total', 0)
                ->where('stats.active_clients', 0)
                ->where('stats.total_clients', 0)
        );
    }

    public function test_dashboard_recent_activity_includes_expenses_and_invoices()
    {
        $this->actingAs($this->user);

        // Create recent expense first (older timestamp)
        $expense = Expense::create([
            'date' => Carbon::now()->subDays(2),
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Recent expense',
            'vat_amount' => 210.00
        ]);
        $expense->created_at = Carbon::now()->subMinutes(60);
        $expense->save();

        // Create recent invoice second (newer timestamp)
        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now()->subDays(1),
            'due_date' => Carbon::now()->addDays(29),
            'subtotal' => 5000.00,
            'vat_amount' => 1050.00,
            'total' => 6050.00
        ]);
        $invoice->created_at = Carbon::now()->subMinutes(30);
        $invoice->save();

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->has('recentActivity', 2)
                ->where('recentActivity.0.type', 'invoice') // Most recent first
                ->where('recentActivity.0.description', "Invoice INV-001 - {$this->client->company_name}")
                ->where('recentActivity.1.type', 'expense')
                ->where('recentActivity.1.description', 'Recent expense')
        );
    }

    public function test_dashboard_limits_recent_activity_to_8_items()
    {
        $this->actingAs($this->user);

        // Create 10 expenses (more than the limit of 8)
        for ($i = 0; $i < 10; $i++) {
            Expense::create([
                'date' => Carbon::now()->subDays($i),
                'amount' => 100.00 * ($i + 1),
                'category_id' => $this->category->id,
                'description' => "Expense {$i}",
                'vat_amount' => 21.00 * ($i + 1),
                'created_at' => Carbon::now()->subMinutes($i)
            ]);
        }

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard')
                ->has('recentActivity', 8) // Should be limited to 8
        );
    }

    public function test_dashboard_recent_activity_ordered_by_creation_date()
    {
        $this->actingAs($this->user);

        // Create expenses with specific creation times
        $older = Expense::create([
            'date' => Carbon::now(),
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Older expense',
            'vat_amount' => 210.00
        ]);
        $older->created_at = Carbon::now()->subHours(2);
        $older->save();

        $newer = Expense::create([
            'date' => Carbon::now(),
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'Newer expense',
            'vat_amount' => 105.00
        ]);
        $newer->created_at = Carbon::now()->subHours(1);
        $newer->save();

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('recentActivity.0.description', 'Newer expense')
                ->where('recentActivity.1.description', 'Older expense')
        );
    }

    public function test_dashboard_calculates_active_clients_correctly()
    {
        $this->actingAs($this->user);

        // Create additional clients
        $clientWithInvoice = Client::create([
            'company_name' => 'Active Client',
            'address' => 'Active Address',
            'vat_id' => 'CZ11111111'
        ]);

        $clientWithoutInvoice = Client::create([
            'company_name' => 'Inactive Client',
            'address' => 'Inactive Address',
            'vat_id' => 'CZ22222222'
        ]);

        // Create invoice for one client only
        Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $clientWithInvoice->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('stats.active_clients', 1) // Only client with invoice
                ->where('stats.total_clients', 3) // All clients
        );
    }

    public function test_dashboard_next_report_period_calculation()
    {
        $this->actingAs($this->user);

        // Mock current date to test different scenarios
        Carbon::setTestNow(Carbon::create(2024, 2, 20)); // 20th Feb (after 16th)

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('nextReportPeriod')
                ->whereType('nextReportPeriod.start', 'string')
                ->whereType('nextReportPeriod.end', 'string')
        );

        // Reset test time
        Carbon::setTestNow();
    }

    public function test_dashboard_handles_multiple_currencies_properly()
    {
        $this->actingAs($this->user);

        // Create expenses with different amounts
        Expense::create([
            'date' => Carbon::now(),
            'amount' => 1000.50, // Test decimal handling
            'category_id' => $this->category->id,
            'description' => 'Decimal expense',
            'vat_amount' => 210.11
        ]);

        Expense::create([
            'date' => Carbon::now(),
            'amount' => 999.99,
            'category_id' => $this->category->id,
            'description' => 'Another decimal expense',
            'vat_amount' => 209.99
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('stats.expenses_amount', 2000.49)
                ->where('stats.expenses_vat', 420.10)
        );
    }

    public function test_dashboard_expense_activity_includes_category_name()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => Carbon::now(),
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00
        ]);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('recentActivity.0.category', 'Test Category')
        );
    }

    public function test_dashboard_invoice_activity_shows_client_name()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('recentActivity.0.description', 'Invoice INV-TEST-001 - Test Client')
        );
    }

    public function test_dashboard_recent_activity_mixed_types_properly_sorted()
    {
        $this->actingAs($this->user);

        // Create items with specific timestamps to test sorting
        $expense = Expense::create([
            'date' => Carbon::now(),
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00
        ]);
        $expense->created_at = Carbon::now()->subMinutes(30);
        $expense->save();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $this->client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00
        ]);
        $invoice->created_at = Carbon::now()->subMinutes(15);
        $invoice->save();

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('recentActivity.0.type', 'invoice') // Most recent
                ->where('recentActivity.1.type', 'expense') // Older
        );
    }
}