<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Http\Controllers\ExpenseController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->category = ExpenseCategory::create(['name' => 'Test Category']);
        
        // Mock S3 storage
        Storage::fake('s3');
    }

    public function test_index_returns_expenses_with_categories()
    {
        $this->actingAs($this->user);

        // Create test expenses
        $expense1 = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense 1',
            'vat_amount' => 210.00
        ]);

        $expense2 = Expense::create([
            'date' => '2024-01-10',
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense 2',
            'vat_amount' => 105.00
        ]);

        $response = $this->get('/expenses');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Index')
                ->has('expenses', 2)
                ->has('categories', 1)
                ->where('expenses.0.description', 'Test expense 1') // Most recent first
                ->where('expenses.1.description', 'Test expense 2')
        );
    }

    public function test_create_displays_create_form_with_categories()
    {
        $this->actingAs($this->user);

        $response = $this->get('/expenses/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Create')
                ->has('categories', 1)
        );
    }

    public function test_store_creates_new_expense_without_receipt()
    {
        $this->actingAs($this->user);

        $expenseData = [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00
        ];

        $response = $this->post('/expenses', $expenseData);

        $response->assertRedirect('/expenses');
        $this->assertDatabaseHas('expenses', [
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00,
            'receipt_path' => null
        ]);
    }

    public function test_store_creates_new_expense_with_receipt()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $expenseData = [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00,
            'receipt' => $file
        ];

        $response = $this->post('/expenses', $expenseData);

        $response->assertRedirect('/expenses');
        
        $expense = Expense::first();
        $this->assertNotNull($expense->receipt_path);
        $this->assertStringStartsWith('receipts/', $expense->receipt_path);
        
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expenses', []);

        $response->assertSessionHasErrors([
            'date', 'amount', 'category_id', 'description', 'vat_amount'
        ]);
        $this->assertEquals(0, Expense::count());
    }

    public function test_store_validates_numeric_amounts()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 'not-a-number',
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 'also-not-a-number'
        ]);

        $response->assertSessionHasErrors(['amount', 'vat_amount']);
    }

    public function test_store_validates_category_exists()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => 999, // Non-existent category
            'description' => 'Test expense',
            'vat_amount' => 210.00
        ]);

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_store_validates_file_upload()
    {
        $this->actingAs($this->user);

        // Test file too large
        $largefile = UploadedFile::fake()->create('receipt.pdf', 11000); // 11MB > 10MB limit

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt' => $largefile
        ]);

        $response->assertSessionHasErrors(['receipt']);

        // Test wrong file type
        $wrongType = UploadedFile::fake()->create('receipt.txt', 100, 'text/plain');

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt' => $wrongType
        ]);

        $response->assertSessionHasErrors(['receipt']);
    }

    public function test_show_displays_expense_details()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00
        ]);

        $response = $this->get("/expenses/{$expense->id}");

        $response->assertStatus(200);
        // Note: Show component not implemented yet, just test route works
    }

    public function test_edit_displays_edit_form_with_data()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00
        ]);

        $response = $this->get("/expenses/{$expense->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Expenses/Edit')
                ->where('expense.description', 'Business lunch')
                ->has('categories', 1)
        );
    }

    public function test_update_modifies_existing_expense()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00
        ]);

        $updateData = [
            'date' => '2024-01-16',
            'amount' => 1200.00,
            'category_id' => $this->category->id,
            'description' => 'Updated business lunch',
            'vat_amount' => 252.00
        ];

        $response = $this->put("/expenses/{$expense->id}", $updateData);

        $response->assertRedirect('/expenses');
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'Updated business lunch',
            'amount' => 1200.00
        ]);
    }

    public function test_update_replaces_receipt_file()
    {
        $this->actingAs($this->user);

        $oldFile = UploadedFile::fake()->create('old_receipt.pdf', 100, 'application/pdf');
        
        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00,
            'receipt_path' => 'receipts/old_receipt.pdf'
        ]);

        // Store old file
        Storage::disk('s3')->put('receipts/old_receipt.pdf', 'old content');

        $newFile = UploadedFile::fake()->create('new_receipt.pdf', 100, 'application/pdf');

        $updateData = [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00,
            'receipt' => $newFile
        ];

        $response = $this->put("/expenses/{$expense->id}", $updateData);

        $response->assertRedirect('/expenses');
        
        $updatedExpense = Expense::find($expense->id);
        $this->assertNotEquals('receipts/old_receipt.pdf', $updatedExpense->receipt_path);
        
        // Old file should be deleted
        Storage::disk('s3')->assertMissing('receipts/old_receipt.pdf');
        // New file should exist
        Storage::disk('s3')->assertExists($updatedExpense->receipt_path);
    }

    public function test_destroy_deletes_expense_and_receipt()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'vat_amount' => 210.00,
            'receipt_path' => 'receipts/test_receipt.pdf'
        ]);

        // Store receipt file
        Storage::disk('s3')->put('receipts/test_receipt.pdf', 'test content');

        $response = $this->delete("/expenses/{$expense->id}");

        $response->assertRedirect('/expenses');
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        Storage::disk('s3')->assertMissing('receipts/test_receipt.pdf');
    }

    public function test_expenses_ordered_by_date_descending()
    {
        $this->actingAs($this->user);

        // Create expenses with different dates
        $expense1 = Expense::create([
            'date' => '2024-01-10',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Older expense',
            'vat_amount' => 210.00
        ]);

        $expense2 = Expense::create([
            'date' => '2024-01-20',
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'Newer expense',
            'vat_amount' => 105.00
        ]);

        $response = $this->get('/expenses');

        $response->assertInertia(fn ($page) =>
            $page->where('expenses.0.description', 'Newer expense')
                ->where('expenses.1.description', 'Older expense')
        );
    }

    public function test_minimum_amount_validation()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => -100.00, // Negative amount
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => -21.00
        ]);

        $response->assertSessionHasErrors(['amount', 'vat_amount']);
    }
}