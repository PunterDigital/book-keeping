<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->category = ExpenseCategory::create([
            'name' => 'Office Supplies',
            'description' => 'Office equipment and supplies'
        ]);
    }

    public function test_expense_has_correct_fillable_attributes()
    {
        $expense = new Expense();
        $fillable = $expense->getFillable();

        $expectedFillable = [
            'date',
            'amount',
            'category_id',
            'description',
            'vat_amount',
            'receipt_path',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_expense_casts_types_correctly()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'amount' => '1500.50',        // String that should be cast to float
            'vat_amount' => '315.00',     // String that should be cast to float
            'date' => '2024-06-15'        // String that should be cast to date
        ]);

        $this->assertIsFloat($expense->amount);
        $this->assertIsFloat($expense->vat_amount);
        $this->assertInstanceOf(Carbon::class, $expense->date);
        
        $this->assertEquals(1500.50, $expense->amount);
        $this->assertEquals(315.0, $expense->vat_amount);
        $this->assertEquals('2024-06-15', $expense->date->format('Y-m-d'));
    }

    public function test_expense_can_be_created_with_all_fields()
    {
        $expenseData = [
            'category_id' => $this->category->id,
            'description' => 'Professional software license',
            'amount' => 2500.75,
            'vat_amount' => 525.16,
            'date' => Carbon::create(2024, 6, 15),
            'receipt_path' => 'receipts/2024/06/receipt.pdf'
        ];

        $expense = Expense::create($expenseData);

        $this->assertDatabaseHas('expenses', [
            'category_id' => $this->category->id,
            'description' => 'Professional software license',
            'amount' => 2500.75,
            'vat_amount' => 525.16,
            'receipt_path' => 'receipts/2024/06/receipt.pdf'
        ]);

        $this->assertEquals('Professional software license', $expense->description);
        $this->assertEquals(2500.75, $expense->amount);
        $this->assertEquals(21.0, $expense->vat_rate);
        $this->assertEquals('receipts/2024/06/receipt.pdf', $expense->receipt_path);
    }

    public function test_expense_can_be_created_with_minimal_fields()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Basic Expense',
            'amount' => 100.00,
            'vat_amount' => 21.0,
            'date' => Carbon::now()
        ]);

        $this->assertNotNull($expense->id);
        $this->assertEquals('Basic Expense', $expense->description);
        $this->assertEquals(100.00, $expense->amount);
        $this->assertNull($expense->receipt_path);
        $this->assertNull($expense->receipt_path);
    }

    public function test_expense_belongs_to_category()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'amount' => 500.00,
            'vat_amount' => 105.0,
            'date' => Carbon::now()
        ]);

        $this->assertInstanceOf(ExpenseCategory::class, $expense->category);
        $this->assertEquals($this->category->id, $expense->category->id);
        $this->assertEquals($this->category->name, $expense->category->name);
    }

    public function test_expense_requires_category()
    {
        // Test that category_id is required
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Expense::create([
            'category_id' => null,
            'description' => 'Uncategorized Expense',
            'amount' => 300.00,
            'vat_amount' => 0.0,
            'date' => Carbon::now()
        ]);
    }

    public function test_expense_with_different_vat_rates()
    {
        // Test with 0% VAT
        $expense0 = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'VAT Exempt Service',
            'amount' => 1000.00,
            'vat_amount' => 0.0,
            'date' => Carbon::now()
        ]);

        // Test with 12% VAT
        $expense12 = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Reduced VAT Service',
            'amount' => 800.00,
            'vat_amount' => 96.0,
            'date' => Carbon::now()
        ]);

        // Test with 21% VAT
        $expense21 = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Standard VAT Service',
            'amount' => 600.00,
            'vat_amount' => 126.0,
            'date' => Carbon::now()
        ]);

        $this->assertEquals(0.0, $expense0->vat_amount);
        $this->assertEquals(96.0, $expense12->vat_amount);
        $this->assertEquals(126.0, $expense21->vat_amount);
    }

    public function test_expense_date_handling()
    {
        $testDate = Carbon::create(2024, 3, 15, 14, 30, 0);
        
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Date Test Expense',
            'amount' => 250.00,
            'vat_amount' => 52.50,
            'date' => $testDate
        ]);

        $this->assertEquals('2024-03-15', $expense->date->format('Y-m-d'));
        $this->assertEquals('15.03.2024', $expense->date->format('d.m.Y'));
    }

    public function test_expense_updates_correctly()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Original Description',
            'amount' => 100.00,
            'vat_amount' => 21.0,
            'date' => Carbon::now()
        ]);

        $newCategory = ExpenseCategory::create([
            'name' => 'Travel',
            'description' => 'Travel expenses'
        ]);

        $expense->update([
            'category_id' => $newCategory->id,
            'description' => 'Updated Description',
            'amount' => 250.75,
            'vat_amount' => 30.09
        ]);

        $expense->refresh();

        $this->assertEquals($newCategory->id, $expense->category_id);
        $this->assertEquals('Updated Description', $expense->description);
        $this->assertEquals(250.75, $expense->amount);
        $this->assertEquals(30.09, $expense->vat_amount);
    }

    public function test_expense_deletion()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'amount' => 100.00,
            'vat_amount' => 21.0,
            'date' => Carbon::now()
        ]);

        $expenseId = $expense->id;
        $expense->delete();

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_expense_with_receipt_path()
    {
        $receiptPath = 'receipts/2024/06/business_lunch_20240615.pdf';
        
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Business lunch',
            'amount' => 1200.00,
            'vat_amount' => 252.0,
            'date' => Carbon::now(),
            'receipt_path' => $receiptPath
        ]);

        $this->assertEquals($receiptPath, $expense->receipt_path);
    }

    public function test_expense_amounts_precision()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Precision Test',
            'amount' => 1234.56,
            'vat_amount' => 265.43,
            'date' => Carbon::now()
        ]);

        $this->assertEquals(1234.56, $expense->amount);
        $this->assertEquals(265.43, $expense->vat_amount);
    }

    public function test_expense_with_zero_amount()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Free Service',
            'amount' => 0.00,
            'vat_amount' => 0.0,
            'date' => Carbon::now()
        ]);

        $this->assertEquals(0.0, $expense->amount);
    }

    public function test_expense_with_large_amount()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Major Equipment Purchase',
            'amount' => 999999.99,
            'vat_amount' => 210000.0,
            'date' => Carbon::now()
        ]);

        $this->assertEquals(999999.99, $expense->amount);
    }

    public function test_expense_relationship_configuration()
    {
        $expense = new Expense();
        
        // Test category relationship
        $categoryRelation = $expense->category();
        $this->assertEquals(ExpenseCategory::class, $categoryRelation->getRelated()::class);
        $this->assertEquals('category_id', $categoryRelation->getForeignKeyName());
        $this->assertEquals('id', $categoryRelation->getOwnerKeyName());
    }

    public function test_expense_with_czech_description()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Nákup kancelářských potřeb včetně papíru a tiskových kazet',
            'amount' => 2500.00,
            'vat_amount' => 525.0,
            'date' => Carbon::now()
        ]);

        $this->assertEquals('Nákup kancelářských potřeb včetně papíru a tiskových kazet', $expense->description);
        $this->assertEquals(2500.0, $expense->amount);
    }

    public function test_expense_date_queries()
    {
        // Create expenses in different months
        $june2024 = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'June Expense',
            'amount' => 100.00,
            'vat_amount' => 21.0,
            'date' => Carbon::create(2024, 6, 15)
        ]);

        $july2024 = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'July Expense',
            'amount' => 200.00,
            'vat_amount' => 42.0,
            'date' => Carbon::create(2024, 7, 15)
        ]);

        // Test querying by date
        $juneExpenses = Expense::whereMonth('date', 6)
                              ->whereYear('date', 2024)
                              ->get();

        $this->assertCount(1, $juneExpenses);
        $this->assertEquals('June Expense', $juneExpenses->first()->description);

        $julyExpenses = Expense::whereMonth('date', 7)
                              ->whereYear('date', 2024)
                              ->get();

        $this->assertCount(1, $julyExpenses);
        $this->assertEquals('July Expense', $julyExpenses->first()->description);
    }

    public function test_expense_with_complex_notes()
    {
        $expense = Expense::create([
            'category_id' => $this->category->id,
            'description' => 'IT Equipment Purchase with detailed Czech description',
            'amount' => 45000.00,
            'vat_amount' => 9450.0,
            'date' => Carbon::now()
        ]);

        $this->assertEquals('IT Equipment Purchase with detailed Czech description', $expense->description);
        $this->assertEquals(45000.0, $expense->amount);
        $this->assertEquals(9450.0, $expense->vat_amount);
    }

    public function test_expense_scoping_by_category()
    {
        $travelCategory = ExpenseCategory::create([
            'name' => 'Travel',
            'description' => 'Business travel expenses'
        ]);

        // Create expenses in different categories
        Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Office Supply',
            'amount' => 100.00,
            'vat_amount' => 21.0,
            'date' => Carbon::now()
        ]);

        Expense::create([
            'category_id' => $travelCategory->id,
            'description' => 'Business Trip',
            'amount' => 500.00,
            'vat_amount' => 60.0,
            'date' => Carbon::now()
        ]);

        // Test filtering by category
        $officeExpenses = Expense::where('category_id', $this->category->id)->get();
        $travelExpenses = Expense::where('category_id', $travelCategory->id)->get();

        $this->assertCount(1, $officeExpenses);
        $this->assertCount(1, $travelExpenses);
        
        $this->assertEquals('Office Supply', $officeExpenses->first()->description);
        $this->assertEquals('Business Trip', $travelExpenses->first()->description);
    }
}