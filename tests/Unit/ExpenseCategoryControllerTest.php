<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Http\Controllers\ExpenseCategoryController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ExpenseCategoryController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->controller = new ExpenseCategoryController();
    }

    public function test_index_returns_categories_with_expense_count()
    {
        $this->actingAs($this->user);

        // Create test categories
        $category1 = ExpenseCategory::create(['name' => 'Office Supplies']);
        $category2 = ExpenseCategory::create(['name' => 'Travel']);

        // Create expenses for category1 only
        Expense::factory()->create(['category_id' => $category1->id]);
        Expense::factory()->create(['category_id' => $category1->id]);

        $response = $this->get('/expense-categories');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('ExpenseCategories/Index')
                ->has('categories', 2)
                ->where('categories.0.expenses_count', 2)
                ->where('categories.1.expenses_count', 0)
        );
    }

    public function test_create_displays_create_form()
    {
        $this->actingAs($this->user);

        $response = $this->get('/expense-categories/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('ExpenseCategories/Create')
        );
    }

    public function test_store_creates_new_category()
    {
        $this->actingAs($this->user);

        $categoryData = [
            'name' => 'Marketing & Advertising'
        ];

        $response = $this->post('/expense-categories', $categoryData);

        $response->assertRedirect('/expense-categories');
        $this->assertDatabaseHas('expense_categories', $categoryData);
    }

    public function test_store_validates_required_name()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expense-categories', [
            'name' => ''
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertEquals(0, ExpenseCategory::count());
    }

    public function test_store_validates_unique_name()
    {
        $this->actingAs($this->user);

        ExpenseCategory::create(['name' => 'Office Supplies']);

        $response = $this->post('/expense-categories', [
            'name' => 'Office Supplies'
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertEquals(1, ExpenseCategory::count());
    }

    public function test_show_displays_category_details()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);

        $response = $this->get("/expense-categories/{$category->id}");

        $response->assertStatus(200);
        // Note: Show component not implemented yet, just test route works
    }

    public function test_edit_displays_edit_form()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);

        $response = $this->get("/expense-categories/{$category->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('ExpenseCategories/Edit')
                ->where('category.name', 'Travel')
        );
    }

    public function test_update_modifies_existing_category()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);

        $updateData = [
            'name' => 'Travel & Transportation'
        ];

        $response = $this->put("/expense-categories/{$category->id}", $updateData);

        $response->assertRedirect('/expense-categories');
        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Travel & Transportation'
        ]);
    }

    public function test_update_validates_unique_name_excluding_current()
    {
        $this->actingAs($this->user);

        $category1 = ExpenseCategory::create(['name' => 'Travel']);
        $category2 = ExpenseCategory::create(['name' => 'Office']);

        // Should allow updating to same name
        $response = $this->put("/expense-categories/{$category1->id}", [
            'name' => 'Travel'
        ]);
        $response->assertRedirect('/expense-categories');

        // Should not allow updating to existing different category name
        $response = $this->put("/expense-categories/{$category1->id}", [
            'name' => 'Office'
        ]);
        $response->assertSessionHasErrors(['name']);
    }

    public function test_destroy_deletes_category_without_expenses()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);

        $response = $this->delete("/expense-categories/{$category->id}");

        $response->assertRedirect('/expense-categories');
        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id
        ]);
    }

    public function test_destroy_prevents_deletion_of_category_with_expenses()
    {
        $this->actingAs($this->user);

        $category = ExpenseCategory::create(['name' => 'Travel']);
        Expense::factory()->create(['category_id' => $category->id]);

        $response = $this->delete("/expense-categories/{$category->id}");

        $response->assertRedirect('/expense-categories');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id
        ]);
    }

    public function test_category_name_max_length_validation()
    {
        $this->actingAs($this->user);

        $response = $this->post('/expense-categories', [
            'name' => str_repeat('a', 256) // Over 255 character limit
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}