<?php

namespace Tests\Unit;

use App\Services\CzechVatReportingService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CzechVatReportingServiceTest extends TestCase
{
    private CzechVatReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CzechVatReportingService();
    }

    public function test_czech_vat_rates_constants()
    {
        $this->assertEquals(21, CzechVatReportingService::CZECH_VAT_RATES['standard']);
        $this->assertEquals(12, CzechVatReportingService::CZECH_VAT_RATES['reduced']);
        $this->assertEquals(0, CzechVatReportingService::CZECH_VAT_RATES['zero']);
    }

    public function test_get_rate_name()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getRateName');
        $method->setAccessible(true);

        $this->assertEquals('standard', $method->invoke($this->service, 21));
        $this->assertEquals('reduced', $method->invoke($this->service, 12));
        $this->assertEquals('zero', $method->invoke($this->service, 0));
        $this->assertEquals('other', $method->invoke($this->service, 15));
    }

    public function test_quarterly_return_due_dates()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getQuarterlyReturnDueDate');
        $method->setAccessible(true);

        $this->assertEquals('2024-04-25', $method->invoke($this->service, 1, 2024));
        $this->assertEquals('2024-07-25', $method->invoke($this->service, 2, 2024));
        $this->assertEquals('2024-10-25', $method->invoke($this->service, 3, 2024));
        $this->assertEquals('2025-01-25', $method->invoke($this->service, 4, 2024));
    }

    public function test_reverse_charge_applicability()
    {
        // This test requires database access and proper model creation
        // Skipping in unit tests - should be tested in feature tests
        $this->markTestSkipped('Reverse charge test requires Invoice model with relationships - should be tested in feature tests');
    }

    public function test_compliance_check_structure()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('performComplianceCheck');
        $method->setAccessible(true);

        $vatSummary = [
            'output_vat' => [
                21 => ['vat_amount' => 1000],
                12 => ['vat_amount' => 500]
            ],
            'totals' => [
                'total_net_vat' => 1500
            ]
        ];

        $result = $method->invoke($this->service, $vatSummary);

        $this->assertArrayHasKey('vat_rates_valid', $result);
        $this->assertArrayHasKey('calculations_accurate', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertTrue($result['vat_rates_valid']);
    }

    public function test_compliance_check_with_invalid_rate()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('performComplianceCheck');
        $method->setAccessible(true);

        $vatSummary = [
            'output_vat' => [
                15 => ['vat_amount' => 1000], // Invalid rate
                21 => ['vat_amount' => 500]
            ],
            'totals' => [
                'total_net_vat' => 1500
            ]
        ];

        $result = $method->invoke($this->service, $vatSummary);

        $this->assertFalse($result['vat_rates_valid']);
        $this->assertContains('Nestandartní sazba DPH: 15%', $result['warnings']);
    }

    public function test_compliance_check_with_high_amount()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('performComplianceCheck');
        $method->setAccessible(true);

        $vatSummary = [
            'output_vat' => [
                21 => ['vat_amount' => 1000000] // Very high amount
            ],
            'totals' => [
                'total_net_vat' => 1000000
            ]
        ];

        $result = $method->invoke($this->service, $vatSummary);

        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Vysoká částka DPH', $result['warnings'][0]);
    }

    public function test_quarterly_return_validation()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateQuarterlyReturn');
        $method->setAccessible(true);

        // Valid VAT summary
        $validSummary = [
            'totals' => [
                'total_output_vat' => 1000,
                'total_input_vat' => 500
            ]
        ];

        $result = $method->invoke($this->service, $validSummary);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Invalid VAT summary with negative output VAT
        $invalidSummary = [
            'totals' => [
                'total_output_vat' => -1000,
                'total_input_vat' => 500
            ]
        ];

        $result = $method->invoke($this->service, $invalidSummary);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('DPH na výstupu nemůže být záporná', $result['errors']);
    }

    public function test_export_methods_exist()
    {
        $this->assertTrue(method_exists($this->service, 'exportForCzechForms'));

        $reflection = new \ReflectionClass($this->service);
        $this->assertTrue($reflection->hasMethod('generateXmlForCzechTaxOffice'));
        $this->assertTrue($reflection->hasMethod('generateCsvForAccountant'));
        $this->assertTrue($reflection->hasMethod('generateSummaryReport'));
    }

    public function test_service_constants_and_structure()
    {
        // Test that all required constants exist
        $this->assertIsArray(CzechVatReportingService::CZECH_VAT_RATES);
        $this->assertCount(3, CzechVatReportingService::CZECH_VAT_RATES);

        // Test that all required methods exist
        $requiredMethods = [
            'generateVatReport',
            'exportForCzechForms'
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($this->service, $method));
        }
    }
}