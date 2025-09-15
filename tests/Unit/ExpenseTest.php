<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_belongs_to_category()
    {
        $category = ExpenseCategory::create(['name' => 'Office Supplies']);
        $expense = Expense::create([
            'date' => now(),
            'amount' => 500.00,
            'category_id' => $category->id,
            'description' => 'Test expense',
            'vat_amount' => 105.00
        ]);

        $this->assertInstanceOf(ExpenseCategory::class, $expense->category);
        $this->assertEquals('Office Supplies', $expense->category->name);
    }

    public function test_expense_casts_amounts_to_decimal()
    {
        $expense = Expense::create([
            'date' => now(),
            'amount' => '500.00',
            'category_id' => ExpenseCategory::create(['name' => 'Test'])->id,
            'description' => 'Test expense',
            'vat_amount' => '105.00'
        ]);

        $this->assertIsFloat($expense->amount);
        $this->assertIsFloat($expense->vat_amount);
        $this->assertEquals(500.00, $expense->amount);
        $this->assertEquals(105.00, $expense->vat_amount);
    }

    public function test_expense_date_is_cast_to_carbon()
    {
        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 500.00,
            'category_id' => ExpenseCategory::create(['name' => 'Test'])->id,
            'description' => 'Test expense',
            'vat_amount' => 105.00
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $expense->date);
    }
}