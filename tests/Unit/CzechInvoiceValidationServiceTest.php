<?php

namespace Tests\Unit;

use App\Services\CzechInvoiceValidationService;
use PHPUnit\Framework\TestCase;

class CzechInvoiceValidationServiceTest extends TestCase
{
    private CzechInvoiceValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CzechInvoiceValidationService();
    }

    public function test_valid_invoice_number_format()
    {
        $result = $this->service->validateInvoiceNumber('2024-0001');

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_invoice_number_format()
    {
        $result = $this->service->validateInvoiceNumber('24-1');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('YYYY-NNNN', $result['message']);
    }

    public function test_invoice_number_with_invalid_year()
    {
        $result = $this->service->validateInvoiceNumber('2019-0001');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('mimo očekávaný rozsah', $result['message']);
    }

    public function test_invoice_number_with_zero_number()
    {
        $result = $this->service->validateInvoiceNumber('2024-0000');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('větší než 0', $result['message']);
    }

    public function test_recommended_invoice_number_generation()
    {
        // This test is skipped in unit tests because it requires database access
        // It should be tested in feature tests
        $this->markTestSkipped('Database access required - should be tested in feature tests');
    }

    public function test_validate_for_save_with_valid_data()
    {
        $validData = [
            'invoice_number' => '2024-0001',
            'client_id' => 1,
            'issue_date' => '2024-01-15',
            'due_date' => '2024-01-29',
            'items' => [
                [
                    'description' => 'Test service',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'vat_rate' => 21
                ]
            ]
        ];

        $result = $this->service->validateForSave($validData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_for_save_with_invalid_data()
    {
        $invalidData = [
            'invoice_number' => '24-1', // Invalid format
            'client_id' => '', // Missing
            'issue_date' => '', // Missing
            'due_date' => '', // Missing
            'items' => [] // Empty
        ];

        $result = $this->service->validateForSave($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertGreaterThan(4, count($result['errors'])); // Should have multiple errors
    }

    public function test_invoice_number_validation_method_exists()
    {
        // Test that the service has the validateInvoiceNumber method
        $this->assertTrue(method_exists($this->service, 'validateInvoiceNumber'));
        $this->assertTrue(method_exists($this->service, 'getRecommendedInvoiceNumber'));
        $this->assertTrue(method_exists($this->service, 'validateForSave'));
    }
}