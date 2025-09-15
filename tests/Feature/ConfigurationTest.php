<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\UploadedFile;

class ConfigurationTest extends TestCase
{
    public function test_s3_configuration_is_correct()
    {
        $this->assertEquals('s3', config('filesystems.default'));
        $this->assertNotEmpty(config('filesystems.disks.s3.key'));
        $this->assertNotEmpty(config('filesystems.disks.s3.secret'));
        $this->assertNotEmpty(config('filesystems.disks.s3.bucket'));
        $this->assertNotEmpty(config('filesystems.disks.s3.endpoint'));
        $this->assertEquals('eu-central-1', config('filesystems.disks.s3.region'));
    }

    public function test_ses_configuration_is_correct()
    {
        // In testing environment, mail is set to 'array' driver
        if (app()->environment('testing')) {
            $this->assertEquals('array', config('mail.default'));
        } else {
            $this->assertEquals('ses', config('mail.default'));
        }
        
        $this->assertNotEmpty(env('AWS_SES_KEY'));
        $this->assertNotEmpty(env('AWS_SES_SECRET'));
        $this->assertEquals('eu-west-2', env('AWS_SES_REGION'));
    }

    public function test_czech_specific_configuration()
    {
        // Test that accountant email is configured
        $this->assertNotEmpty(config('mail.accountant_email', env('ACCOUNTANT_EMAIL')));
        
        // Test that the application supports Czech locale
        $this->assertContains('cs', config('app.supported_locales', ['en', 'cs']));
    }

    public function test_s3_connection_works()
    {
        // Skip this test if we're not in a real environment with actual credentials
        if (app()->environment('testing')) {
            $this->markTestSkipped('S3 connection test skipped in testing environment');
        }

        try {
            $disk = Storage::disk('s3');
            
            // Create a test file
            $testContent = 'Test file for S3 connection';
            $testPath = 'test/connection-test.txt';
            
            // Upload test file
            $result = $disk->put($testPath, $testContent);
            $this->assertTrue($result);
            
            // Verify file exists
            $this->assertTrue($disk->exists($testPath));
            
            // Read file content
            $retrievedContent = $disk->get($testPath);
            $this->assertEquals($testContent, $retrievedContent);
            
            // Clean up
            $disk->delete($testPath);
            $this->assertFalse($disk->exists($testPath));
            
        } catch (\Exception $e) {
            $this->fail('S3 connection failed: ' . $e->getMessage());
        }
    }

    public function test_required_environment_variables_are_set()
    {
        $requiredEnvVars = [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_SES_KEY', 
            'AWS_SES_SECRET',
            'ACCOUNTANT_EMAIL',
            'MAIL_FROM_ADDRESS'
        ];

        foreach ($requiredEnvVars as $envVar) {
            $this->assertNotEmpty(env($envVar), "Environment variable {$envVar} is not set");
        }
    }

    public function test_database_tables_exist()
    {
        // Run migrations for the test
        $this->artisan('migrate:fresh');
        
        $tables = [
            'users',
            'expense_categories',
            'expenses',
            'clients',
            'invoices',
            'invoice_items',
            'monthly_reports'
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                \Schema::hasTable($table),
                "Table {$table} does not exist"
            );
        }
    }

    public function test_database_table_columns()
    {
        // Ensure migrations are run
        $this->artisan('migrate:fresh');
        
        // Test expense_categories table
        $this->assertTrue(\Schema::hasColumn('expense_categories', 'name'));
        
        // Test expenses table
        $expenseColumns = ['date', 'amount', 'category_id', 'description', 'vat_amount', 'receipt_path'];
        foreach ($expenseColumns as $column) {
            $this->assertTrue(\Schema::hasColumn('expenses', $column));
        }
        
        // Test clients table
        $clientColumns = ['company_name', 'contact_name', 'address', 'vat_id', 'company_id'];
        foreach ($clientColumns as $column) {
            $this->assertTrue(\Schema::hasColumn('clients', $column));
        }
        
        // Test invoices table
        $invoiceColumns = ['invoice_number', 'client_id', 'issue_date', 'due_date', 'status', 'subtotal', 'vat_amount', 'total'];
        foreach ($invoiceColumns as $column) {
            $this->assertTrue(\Schema::hasColumn('invoices', $column));
        }
    }
}