<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExpenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Storage::fake('s3');
    }

    public function test_complete_expense_category_workflow()
    {
        $this->actingAs($this->user);

        // 1. Create new expense category
        $response = $this->get('/expense-categories/create');
        $response->assertStatus(200);

        $response = $this->post('/expense-categories', [
            'name' => 'Office Supplies'
        ]);
        $response->assertRedirect('/expense-categories');

        // 2. View categories list
        $response = $this->get('/expense-categories');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('categories', 1)
                ->where('categories.0.name', 'Office Supplies')
                ->where('categories.0.expenses_count', 0)
        );

        // 3. Edit the category
        $category = ExpenseCategory::first();
        $response = $this->get("/expense-categories/{$category->id}/edit");
        $response->assertStatus(200);

        $response = $this->put("/expense-categories/{$category->id}", [
            'name' => 'Office Equipment & Supplies'
        ]);
        $response->assertRedirect('/expense-categories');

        // 4. Verify the update
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Office Equipment & Supplies'
        ]);
    }

    public function test_complete_expense_creation_and_management_workflow()
    {
        $this->actingAs($this->user);

        // Setup: Create category
        $category = ExpenseCategory::create(['name' => 'Travel']);

        // 1. Navigate to create expense page
        $response = $this->get('/expenses/create');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Create')
                ->has('categories', 1)
        );

        // 2. Create expense with receipt
        $receipt = UploadedFile::fake()->create('business_receipt.pdf', 100, 'application/pdf');

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => '850.00',
            'category_id' => $category->id,
            'description' => 'Business trip to Prague - train tickets and accommodation',
            'vat_amount' => '178.50',
            'receipt' => $receipt
        ]);

        $response->assertRedirect('/expenses');

        // 3. Verify expense was created
        $expense = Expense::first();
        $this->assertNotNull($expense);
        $this->assertEquals('Business trip to Prague - train tickets and accommodation', $expense->description);
        $this->assertEquals(850.00, $expense->amount);
        $this->assertEquals(178.50, $expense->vat_amount);
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('s3')->assertExists($expense->receipt_path);

        // 4. View expenses list
        $response = $this->get('/expenses');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Index')
                ->has('expenses', 1)
                ->where('expenses.0.description', 'Business trip to Prague - train tickets and accommodation')
        );

        // 5. Edit the expense
        $response = $this->get("/expenses/{$expense->id}/edit");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Edit')
                ->where('expense.description', 'Business trip to Prague - train tickets and accommodation')
        );

        // 6. Update the expense
        $response = $this->put("/expenses/{$expense->id}", [
            'date' => '2024-01-16',
            'amount' => '900.00',
            'category_id' => $category->id,
            'description' => 'Business trip to Prague - updated with additional costs',
            'vat_amount' => '189.00'
        ]);

        $response->assertRedirect('/expenses');

        // 7. Verify the update
        $expense->refresh();
        $this->assertEquals('Business trip to Prague - updated with additional costs', $expense->description);
        $this->assertEquals(900.00, $expense->amount);
        $this->assertEquals(189.00, $expense->vat_amount);
    }

    public function test_expense_filtering_and_search_workflow()
    {
        $this->actingAs($this->user);

        // Setup: Create multiple categories and expenses
        $officeCategory = ExpenseCategory::create(['name' => 'Office Supplies']);
        $travelCategory = ExpenseCategory::create(['name' => 'Travel']);
        $softwareCategory = ExpenseCategory::create(['name' => 'Software']);

        // Create expenses in different months and categories
        Expense::create([
            'date' => '2024-01-15',
            'amount' => 500.00,
            'category_id' => $officeCategory->id,
            'description' => 'Office desk and chair',
            'vat_amount' => 105.00
        ]);

        Expense::create([
            'date' => '2024-01-20',
            'amount' => 1200.00,
            'category_id' => $travelCategory->id,
            'description' => 'Flight to Berlin conference',
            'vat_amount' => 252.00
        ]);

        Expense::create([
            'date' => '2024-02-10',
            'amount' => 890.00,
            'category_id' => $softwareCategory->id,
            'description' => 'Adobe Creative Suite license',
            'vat_amount' => 186.90
        ]);

        // 1. View all expenses
        $response = $this->get('/expenses');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('expenses', 3)
                ->has('categories', 3)
        );

        // Note: Filtering and search functionality is handled client-side in Vue.js
        // The controller provides all data and categories for filtering
    }

    public function test_expense_receipt_replacement_workflow()
    {
        $this->actingAs($this->user);

        // Setup
        $category = ExpenseCategory::create(['name' => 'Travel']);
        $originalReceipt = UploadedFile::fake()->create('original_receipt.pdf', 100, 'application/pdf');

        // 1. Create expense with receipt
        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => '500.00',
            'category_id' => $category->id,
            'description' => 'Business lunch',
            'vat_amount' => '105.00',
            'receipt' => $originalReceipt
        ]);

        $expense = Expense::first();
        $originalPath = $expense->receipt_path;
        Storage::disk('s3')->assertExists($originalPath);

        // 2. Replace receipt with new file
        $newReceipt = UploadedFile::fake()->create('updated_receipt.pdf', 100, 'application/pdf');

        $response = $this->put("/expenses/{$expense->id}", [
            'date' => '2024-01-15',
            'amount' => '500.00',
            'category_id' => $category->id,
            'description' => 'Business lunch',
            'vat_amount' => '105.00',
            'receipt' => $newReceipt
        ]);

        // 3. Verify receipt was replaced
        $expense->refresh();
        $this->assertNotEquals($originalPath, $expense->receipt_path);
        Storage::disk('s3')->assertMissing($originalPath);
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_expense_deletion_workflow()
    {
        $this->actingAs($this->user);

        // Setup
        $category = ExpenseCategory::create(['name' => 'Travel']);
        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 500.00,
            'category_id' => $category->id,
            'description' => 'Business lunch',
            'vat_amount' => 105.00,
            'receipt_path' => 'receipts/test_receipt.pdf'
        ]);

        Storage::disk('s3')->put('receipts/test_receipt.pdf', 'test content');

        // 1. Delete expense
        $response = $this->delete("/expenses/{$expense->id}");
        $response->assertRedirect('/expenses');

        // 2. Verify expense and receipt are deleted
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        Storage::disk('s3')->assertMissing('receipts/test_receipt.pdf');

        // 3. Verify category still exists
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_category_protection_when_has_expenses()
    {
        $this->actingAs($this->user);

        // Setup
        $category = ExpenseCategory::create(['name' => 'Office']);
        Expense::create([
            'date' => '2024-01-15',
            'amount' => 500.00,
            'category_id' => $category->id,
            'description' => 'Office supplies',
            'vat_amount' => 105.00
        ]);

        // 1. Attempt to delete category with expenses
        $response = $this->delete("/expense-categories/{$category->id}");
        $response->assertRedirect('/expense-categories');

        // 2. Verify category was NOT deleted
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);

        // 3. Delete the expense first
        $expense = Expense::first();
        $response = $this->delete("/expenses/{$expense->id}");
        $response->assertRedirect('/expenses');

        // 4. Now category can be deleted
        $response = $this->delete("/expense-categories/{$category->id}");
        $response->assertRedirect('/expense-categories');
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_dashboard_updates_with_expense_data()
    {
        $this->actingAs($this->user);

        // Setup
        $category = ExpenseCategory::create(['name' => 'Travel']);

        // 1. Check dashboard with no expenses
        $response = $this->get('/dashboard');
        $response->assertInertia(fn ($page) =>
            $page->where('stats.expenses_this_month', 0)
                ->where('stats.expenses_amount', 0)
        );

        // 2. Create current month expense
        Expense::create([
            'date' => Carbon::now()->format('Y-m-d'),
            'amount' => 1000.00,
            'category_id' => $category->id,
            'description' => 'Current month expense',
            'vat_amount' => 210.00
        ]);

        // 3. Check dashboard updates
        $response = $this->get('/dashboard');
        $response->assertInertia(fn ($page) =>
            $page->where('stats.expenses_this_month', 1)
                ->where('stats.expenses_amount', 1000)
                ->has('recentActivity', 1)
                ->where('recentActivity.0.type', 'expense')
        );

        // 4. Create another expense
        Expense::create([
            'date' => Carbon::now()->format('Y-m-d'),
            'amount' => 500.00,
            'category_id' => $category->id,
            'description' => 'Another current month expense',
            'vat_amount' => 105.00
        ]);

        // 5. Verify totals update
        $response = $this->get('/dashboard');
        $response->assertInertia(fn ($page) =>
            $page->where('stats.expenses_this_month', 2)
                ->where('stats.expenses_amount', 1500)
                ->where('stats.expenses_vat', 315)
        );
    }

    public function test_vat_calculation_scenarios()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Test']);

        // Test standard 21% VAT
        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => '1000.00',
            'category_id' => $category->id,
            'description' => 'Standard VAT expense',
            'vat_amount' => '210.00' // 21% of 1000
        ]);

        // Test reduced 12% VAT
        $response = $this->post('/expenses', [
            'date' => '2024-01-16',
            'amount' => '1000.00',
            'category_id' => $category->id,
            'description' => 'Reduced VAT expense',
            'vat_amount' => '120.00' // 12% of 1000
        ]);

        // Test 0% VAT
        $response = $this->post('/expenses', [
            'date' => '2024-01-17',
            'amount' => '1000.00',
            'category_id' => $category->id,
            'description' => 'VAT exempt expense',
            'vat_amount' => '0.00' // 0% of 1000
        ]);

        // Verify all expenses created with correct VAT amounts
        $expenses = Expense::orderBy('date')->get();
        $this->assertEquals(210.00, $expenses[0]->vat_amount);
        $this->assertEquals(120.00, $expenses[1]->vat_amount);
        $this->assertEquals(0.00, $expenses[2]->vat_amount);
    }

    public function test_expense_date_range_behavior()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Test']);

        // Create expenses across different months
        $expenses = [
            ['date' => '2024-01-15', 'desc' => 'January expense'],
            ['date' => '2024-02-15', 'desc' => 'February expense'],
            ['date' => '2024-03-15', 'desc' => 'March expense'],
        ];

        foreach ($expenses as $expenseData) {
            Expense::create([
                'date' => $expenseData['date'],
                'amount' => 1000.00,
                'category_id' => $category->id,
                'description' => $expenseData['desc'],
                'vat_amount' => 210.00
            ]);
        }

        // Verify expenses are ordered by date descending
        $response = $this->get('/expenses');
        $response->assertInertia(fn ($page) =>
            $page->where('expenses.0.description', 'March expense')
                ->where('expenses.1.description', 'February expense')
                ->where('expenses.2.description', 'January expense')
        );
    }
}