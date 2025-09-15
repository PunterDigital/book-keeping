<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadTest extends TestCase
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

    public function test_pdf_file_upload_succeeds()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with PDF',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertRedirect('/expenses');
        
        $expense = Expense::first();
        $this->assertNotNull($expense->receipt_path);
        $this->assertStringStartsWith('receipts/', $expense->receipt_path);
        $this->assertStringEndsWith('.pdf', $expense->receipt_path);
        
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_jpg_file_upload_succeeds()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->image('receipt.jpg', 800, 600)->size(100);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with JPG',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertRedirect('/expenses');
        
        $expense = Expense::first();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_png_file_upload_succeeds()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->image('receipt.png', 800, 600)->size(100);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with PNG',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertRedirect('/expenses');
        
        $expense = Expense::first();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_jpeg_file_upload_succeeds()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->image('receipt.jpeg', 800, 600)->size(100);

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with JPEG',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertRedirect('/expenses');
        
        $expense = Expense::first();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('s3')->assertExists($expense->receipt_path);
    }

    public function test_invalid_file_type_rejected()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('receipt.txt', 100, 'text/plain');

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with invalid file',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertSessionHasErrors(['receipt']);
        $this->assertEquals(0, Expense::count());
    }

    public function test_file_too_large_rejected()
    {
        $this->actingAs($this->user);

        // Create file larger than 10MB limit
        $file = UploadedFile::fake()->create('large_receipt.pdf', 11000); // 11MB

        $response = $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense with large file',
            'vat_amount' => 210.00,
            'receipt' => $file
        ]);

        $response->assertSessionHasErrors(['receipt']);
        $this->assertEquals(0, Expense::count());
    }

    public function test_file_upload_creates_unique_filename()
    {
        $this->actingAs($this->user);

        $file1 = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        // Upload first expense with receipt
        $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'First expense',
            'vat_amount' => 210.00,
            'receipt' => $file1
        ]);

        // Upload second expense with same filename
        $this->post('/expenses', [
            'date' => '2024-01-16',
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'Second expense',
            'vat_amount' => 105.00,
            'receipt' => $file2
        ]);

        $expenses = Expense::all();
        $this->assertEquals(2, $expenses->count());
        
        // Receipt paths should be different despite same original filename
        $this->assertNotEquals($expenses[0]->receipt_path, $expenses[1]->receipt_path);
        
        // Both files should exist in storage
        Storage::disk('s3')->assertExists($expenses[0]->receipt_path);
        Storage::disk('s3')->assertExists($expenses[1]->receipt_path);
    }

    public function test_file_replacement_on_expense_update()
    {
        $this->actingAs($this->user);

        $originalFile = UploadedFile::fake()->create('original.pdf', 100, 'application/pdf');
        
        // Create expense with receipt
        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00
        ]);

        // Upload first file
        $response = $this->put("/expenses/{$expense->id}", [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt' => $originalFile
        ]);

        $expense->refresh();
        $originalPath = $expense->receipt_path;
        $this->assertNotNull($originalPath);
        Storage::disk('s3')->assertExists($originalPath);

        // Replace with new file
        $newFile = UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf');
        
        $response = $this->put("/expenses/{$expense->id}", [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt' => $newFile
        ]);

        $expense->refresh();
        $newPath = $expense->receipt_path;
        
        // Path should have changed
        $this->assertNotEquals($originalPath, $newPath);
        
        // Old file should be deleted, new file should exist
        Storage::disk('s3')->assertMissing($originalPath);
        Storage::disk('s3')->assertExists($newPath);
    }

    public function test_file_deletion_on_expense_deletion()
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        
        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt_path' => 'receipts/test-receipt.pdf'
        ]);

        // Manually store file to simulate uploaded receipt
        Storage::disk('s3')->put('receipts/test-receipt.pdf', 'test content');
        Storage::disk('s3')->assertExists('receipts/test-receipt.pdf');

        // Delete expense
        $response = $this->delete("/expenses/{$expense->id}");

        $response->assertRedirect('/expenses');
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        
        // File should also be deleted
        Storage::disk('s3')->assertMissing('receipts/test-receipt.pdf');
    }

    public function test_expense_update_without_new_file_keeps_existing_receipt()
    {
        $this->actingAs($this->user);

        $expense = Expense::create([
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'Test expense',
            'vat_amount' => 210.00,
            'receipt_path' => 'receipts/existing-receipt.pdf'
        ]);

        Storage::disk('s3')->put('receipts/existing-receipt.pdf', 'existing content');

        // Update expense without providing new receipt
        $response = $this->put("/expenses/{$expense->id}", [
            'date' => '2024-01-16',
            'amount' => 1200.00,
            'category_id' => $this->category->id,
            'description' => 'Updated expense',
            'vat_amount' => 252.00
        ]);

        $expense->refresh();
        
        // Receipt path should remain unchanged
        $this->assertEquals('receipts/existing-receipt.pdf', $expense->receipt_path);
        Storage::disk('s3')->assertExists('receipts/existing-receipt.pdf');
    }

    public function test_multiple_file_types_stored_in_receipts_directory()
    {
        $this->actingAs($this->user);

        $pdfFile = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');
        $jpgFile = UploadedFile::fake()->image('receipt.jpg')->size(100);

        // Upload PDF receipt
        $this->post('/expenses', [
            'date' => '2024-01-15',
            'amount' => 1000.00,
            'category_id' => $this->category->id,
            'description' => 'PDF expense',
            'vat_amount' => 210.00,
            'receipt' => $pdfFile
        ]);

        // Upload JPG receipt
        $this->post('/expenses', [
            'date' => '2024-01-16',
            'amount' => 500.00,
            'category_id' => $this->category->id,
            'description' => 'JPG expense',
            'vat_amount' => 105.00,
            'receipt' => $jpgFile
        ]);

        $expenses = Expense::all();
        
        // Both receipts should be in receipts/ directory
        $this->assertStringStartsWith('receipts/', $expenses[0]->receipt_path);
        $this->assertStringStartsWith('receipts/', $expenses[1]->receipt_path);
        
        Storage::disk('s3')->assertExists($expenses[0]->receipt_path);
        Storage::disk('s3')->assertExists($expenses[1]->receipt_path);
    }
}