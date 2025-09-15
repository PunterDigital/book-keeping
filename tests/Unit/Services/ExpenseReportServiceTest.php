<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ExpenseReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseReportService $service;
    protected ExpenseCategory $category1;
    protected ExpenseCategory $category2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ExpenseReportService();
        
        $this->category1 = ExpenseCategory::create([
            'name' => 'Office Supplies',
            'description' => 'Office equipment and supplies'
        ]);

        $this->category2 = ExpenseCategory::create([
            'name' => 'Travel',
            'description' => 'Business travel expenses'
        ]);

        // Create test expenses
        $this->createTestExpenses();
    }

    private function createTestExpenses(): void
    {
        // June 2024 expenses
        Expense::create([
            'category_id' => $this->category1->id,
            'description' => 'Laptop purchase',
            'amount' => 50000.00,
            'vat_amount' => 10500.0,
            'date' => Carbon::create(2024, 6, 10)
        ]);

        Expense::create([
            'category_id' => $this->category1->id,
            'description' => 'Office chairs',
            'amount' => 15000.00,
            'vat_amount' => 3150.0,
            'date' => Carbon::create(2024, 6, 15)
        ]);

        Expense::create([
            'category_id' => $this->category2->id,
            'description' => 'Business trip to Brno',
            'amount' => 5000.00,
            'vat_amount' => 600.0,
            'date' => Carbon::create(2024, 6, 20)
        ]);

        // July 2024 expenses
        Expense::create([
            'category_id' => $this->category2->id,
            'description' => 'Conference attendance',
            'amount' => 8000.00,
            'vat_amount' => 0.0,
            'date' => Carbon::create(2024, 7, 5)
        ]);

        Expense::create([
            'category_id' => $this->category1->id,
            'description' => 'Software license',
            'amount' => 12000.00,
            'vat_amount' => 2520.0,
            'date' => Carbon::create(2024, 7, 10)
        ]);
    }

    public function test_generate_monthly_report_returns_correct_structure()
    {
        $report = $this->service->generateMonthlyReport(2024, 6);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('expenses', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('category_breakdown', $report);
        $this->assertArrayHasKey('vat_breakdown', $report);

        // Check period formatting
        $this->assertEquals('01.06.2024', $report['period']['start']);
        $this->assertEquals('30.06.2024', $report['period']['end']);
        $this->assertEquals('06/2024', $report['period']['month_year']);
    }

    public function test_generate_monthly_report_filters_expenses_correctly()
    {
        $report = $this->service->generateMonthlyReport(2024, 6);

        // Should only include June expenses (3 total)
        $this->assertCount(3, $report['expenses']);
        
        foreach ($report['expenses'] as $expense) {
            $expenseDate = Carbon::parse($expense->date);
            $this->assertEquals(6, $expenseDate->month);
            $this->assertEquals(2024, $expenseDate->year);
        }
    }

    public function test_generate_yearly_report_returns_correct_structure()
    {
        $report = $this->service->generateYearlyReport(2024);

        $this->assertArrayHasKey('monthly_breakdown', $report);
        $this->assertCount(5, $report['expenses']); // All expenses

        // Check year period
        $this->assertEquals('01.01.2024', $report['period']['start']);
        $this->assertEquals('31.12.2024', $report['period']['end']);
        $this->assertEquals(2024, $report['period']['year']);
    }

    public function test_generate_custom_report_filters_by_date_range()
    {
        $startDate = Carbon::create(2024, 6, 15);
        $endDate = Carbon::create(2024, 7, 8);
        
        $report = $this->service->generateCustomReport($startDate, $endDate);

        // Should include: Office chairs (15th), Business trip (20th), Conference (5th)
        $this->assertCount(3, $report['expenses']);
        
        foreach ($report['expenses'] as $expense) {
            $expenseDate = Carbon::parse($expense->date);
            $this->assertTrue($expenseDate->between($startDate, $endDate));
        }
    }

    public function test_calculate_summary_computes_correct_totals()
    {
        $expenses = Expense::whereYear('date', 2024)
                          ->whereMonth('date', 6)
                          ->get();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateSummary');
        $method->setAccessible(true);
        
        $summary = $method->invoke($this->service, $expenses);

        // Expected: 50000 + 15000 + 5000 = 70000
        $this->assertEquals(70000.00, $summary['total_amount']);
        
        // Expected VAT: (50000 + 15000) * 0.21 + 5000 * 0.12 = 13650 + 600 = 14250
        $this->assertEquals(14250.00, $summary['total_vat']);
        
        // Expected total with VAT: 70000 + 14250 = 84250
        $this->assertEquals(84250.00, $summary['total_with_vat']);
        
        // Expected count
        $this->assertEquals(3, $summary['total_expenses']);
        
        // Expected average: 70000 / 3 = 23333.33...
        $this->assertEquals(70000.00 / 3, $summary['average_amount']);
    }

    public function test_calculate_category_breakdown_groups_correctly()
    {
        $expenses = Expense::with('category')->get();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateCategoryBreakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->service, $expenses);

        $this->assertArrayHasKey('Office Supplies', $breakdown);
        $this->assertArrayHasKey('Travel', $breakdown);

        // Office Supplies: 50000 + 15000 + 12000 = 77000
        $this->assertEquals(77000.00, $breakdown['Office Supplies']['total_amount']);
        $this->assertEquals(3, $breakdown['Office Supplies']['count']);

        // Travel: 5000 + 8000 = 13000
        $this->assertEquals(13000.00, $breakdown['Travel']['total_amount']);
        $this->assertEquals(2, $breakdown['Travel']['count']);
    }

    public function test_calculate_vat_breakdown_groups_by_rate()
    {
        $expenses = Expense::all();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVatBreakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->service, $expenses);

        $this->assertArrayHasKey('21', $breakdown);
        $this->assertArrayHasKey('12', $breakdown);
        $this->assertArrayHasKey('0', $breakdown);

        // 21% VAT: 50000 + 15000 + 12000 = 77000
        $this->assertEquals(77000.00, $breakdown['21']['total_amount']);
        $this->assertEquals(16170.00, $breakdown['21']['total_vat']); // 77000 * 0.21
        $this->assertEquals(3, $breakdown['21']['count']);

        // 12% VAT: 5000
        $this->assertEquals(5000.00, $breakdown['12']['total_amount']);
        $this->assertEquals(600.00, $breakdown['12']['total_vat']); // 5000 * 0.12
        $this->assertEquals(1, $breakdown['12']['count']);

        // 0% VAT: 8000
        $this->assertEquals(8000.00, $breakdown['0']['total_amount']);
        $this->assertEquals(0.00, $breakdown['0']['total_vat']);
        $this->assertEquals(1, $breakdown['0']['count']);
    }

    public function test_calculate_monthly_breakdown_initializes_all_months()
    {
        $expenses = Expense::whereYear('date', 2024)->get();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateMonthlyBreakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->service, $expenses, 2024);

        // Should have data for all 12 months
        $this->assertCount(12, $breakdown);

        // Check June has data
        $june = collect($breakdown)->where('month', 6)->first();
        $this->assertEquals(3, $june['count']);
        $this->assertEquals(70000.00, $june['total_amount']);

        // Check July has data
        $july = collect($breakdown)->where('month', 7)->first();
        $this->assertEquals(2, $july['count']);
        $this->assertEquals(20000.00, $july['total_amount']);

        // Check empty month (e.g., January)
        $january = collect($breakdown)->where('month', 1)->first();
        $this->assertEquals(0, $january['count']);
        $this->assertEquals(0, $january['total_amount']);
    }

    public function test_generate_expense_report_filename()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateExpenseReportFilename');
        $method->setAccessible(true);

        // Test yearly filename
        $yearlyPeriod = ['year' => 2024];
        $filename = $method->invoke($this->service, $yearlyPeriod);
        $this->assertEquals('expense_report_2024.pdf', $filename);

        // Test monthly filename
        $monthlyPeriod = ['month_year' => '06/2024'];
        $filename = $method->invoke($this->service, $monthlyPeriod);
        $this->assertEquals('expense_report_06-2024.pdf', $filename);

        // Test custom period filename
        $customPeriod = ['start' => '01.06.2024', 'end' => '30.06.2024'];
        $filename = $method->invoke($this->service, $customPeriod);
        $this->assertEquals('expense_report_01062024_30062024.pdf', $filename);
    }

    public function test_download_expenses_pdf_returns_response()
    {
        $reportData = $this->service->generateMonthlyReport(2024, 6);
        $response = $this->service->downloadExpensesPdf($reportData);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_download_expenses_pdf_with_custom_filename()
    {
        $reportData = $this->service->generateMonthlyReport(2024, 6);
        $customFilename = 'custom_expense_report.pdf';
        
        $response = $this->service->downloadExpensesPdf($reportData, $customFilename);

        $this->assertStringContainsString($customFilename, $response->headers->get('Content-Disposition'));
    }

    public function test_generate_expenses_pdf_returns_pdf_content()
    {
        $reportData = $this->service->generateMonthlyReport(2024, 6);
        $pdfContent = $this->service->generateExpensesPdf($reportData);

        $this->assertIsString($pdfContent);
        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_empty_expenses_collection_handling()
    {
        // Test with no expenses
        $emptyCollection = new Collection();
        
        $reflection = new \ReflectionClass($this->service);
        $summaryMethod = $reflection->getMethod('calculateSummary');
        $summaryMethod->setAccessible(true);
        
        $summary = $summaryMethod->invoke($this->service, $emptyCollection);

        $this->assertEquals(0, $summary['total_expenses']);
        $this->assertEquals(0.0, $summary['total_amount']);
        $this->assertEquals(0.0, $summary['total_vat']);
        $this->assertEquals(0.0, $summary['total_with_vat']);
        $this->assertEquals(0.0, $summary['average_amount']);
    }

    public function test_report_with_future_date()
    {
        $futureDate = Carbon::create(2025, 12, 1);
        $report = $this->service->generateMonthlyReport($futureDate->year, $futureDate->month);

        $this->assertCount(0, $report['expenses']);
        $this->assertEquals(0.0, $report['summary']['total_amount']);
    }

    public function test_category_breakdown_sorting()
    {
        $expenses = Expense::with('category')->get();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateCategoryBreakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->service, $expenses);
        
        // Should be sorted by total_amount descending
        $keys = array_keys($breakdown);
        $this->assertEquals('Office Supplies', $keys[0]); // Higher amount
        $this->assertEquals('Travel', $keys[1]); // Lower amount
    }

    public function test_vat_breakdown_sorting()
    {
        $expenses = Expense::all();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVatBreakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->service, $expenses);
        
        // Should be sorted by total_amount descending
        $keys = array_keys($breakdown);
        $this->assertEquals('21', $keys[0]); // Highest amount (77000)
        $this->assertEquals('0', $keys[1]);  // Middle amount (8000)  
        $this->assertEquals('12', $keys[2]); // Lowest amount (5000)
    }
}