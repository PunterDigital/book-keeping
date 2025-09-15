<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->category = ExpenseCategory::create([
            'name' => 'Testovací kategorie',
            'description' => 'Kategorie pro testování'
        ]);

        // Create test expenses
        Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test expense 1',
            'amount' => 1000.00,
            'vat_amount' => 210.0,
            'date' => Carbon::create(2024, 6, 15)
        ]);

        Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test expense 2',
            'amount' => 500.00,
            'vat_amount' => 60.0,
            'date' => Carbon::create(2024, 6, 20)
        ]);

        Expense::create([
            'category_id' => $this->category->id,
            'description' => 'Test expense 3',
            'amount' => 800.00,
            'vat_amount' => 0.0,
            'date' => Carbon::create(2024, 7, 5)
        ]);
    }

    public function test_can_generate_monthly_expense_report()
    {
        $reportService = new ExpenseReportService();
        $report = $reportService->generateMonthlyReport(2024, 6);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('expenses', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('category_breakdown', $report);
        $this->assertArrayHasKey('vat_breakdown', $report);

        // Check expenses count (should be 2 for June)
        $this->assertCount(2, $report['expenses']);

        // Check summary calculations
        $this->assertEquals(1500.00, $report['summary']['total_amount']);
        $this->assertEquals(270.00, $report['summary']['total_vat']); // 1000*0.21 + 500*0.12
        $this->assertEquals(1770.00, $report['summary']['total_with_vat']);
    }

    public function test_can_generate_yearly_expense_report()
    {
        $reportService = new ExpenseReportService();
        $report = $reportService->generateYearlyReport(2024);

        $this->assertArrayHasKey('monthly_breakdown', $report);
        $this->assertCount(3, $report['expenses']); // All 3 expenses

        // Check summary calculations for whole year
        $this->assertEquals(2300.00, $report['summary']['total_amount']); // 1000 + 500 + 800
        $this->assertEquals(270.00, $report['summary']['total_vat']); // Only first two have VAT
    }

    public function test_can_generate_custom_expense_report()
    {
        $reportService = new ExpenseReportService();
        $startDate = Carbon::create(2024, 6, 1);
        $endDate = Carbon::create(2024, 6, 30);
        
        $report = $reportService->generateCustomReport($startDate, $endDate);

        $this->assertCount(2, $report['expenses']); // June expenses only
        $this->assertEquals(1500.00, $report['summary']['total_amount']);
    }

    public function test_vat_breakdown_calculations()
    {
        $reportService = new ExpenseReportService();
        $report = $reportService->generateYearlyReport(2024);

        $vatBreakdown = $report['vat_breakdown'];

        // Should have breakdowns for 0%, 12%, and 21%
        $this->assertArrayHasKey('0', $vatBreakdown);
        $this->assertArrayHasKey('12', $vatBreakdown);
        $this->assertArrayHasKey('21', $vatBreakdown);

        // Check 21% VAT breakdown
        $this->assertEquals(1000.00, $vatBreakdown['21']['total_amount']);
        $this->assertEquals(210.00, $vatBreakdown['21']['total_vat']);

        // Check 12% VAT breakdown
        $this->assertEquals(500.00, $vatBreakdown['12']['total_amount']);
        $this->assertEquals(60.00, $vatBreakdown['12']['total_vat']);

        // Check 0% VAT breakdown
        $this->assertEquals(800.00, $vatBreakdown['0']['total_amount']);
        $this->assertEquals(0.00, $vatBreakdown['0']['total_vat']);
    }

    public function test_category_breakdown_calculations()
    {
        $reportService = new ExpenseReportService();
        $report = $reportService->generateYearlyReport(2024);

        $categoryBreakdown = $report['category_breakdown'];
        
        $this->assertArrayHasKey('Testovací kategorie', $categoryBreakdown);
        
        $categoryData = $categoryBreakdown['Testovací kategorie'];
        $this->assertEquals(3, $categoryData['count']);
        $this->assertEquals(2300.00, $categoryData['total_amount']);
        $this->assertEquals(270.00, $categoryData['total_vat']);
        $this->assertEquals(2570.00, $categoryData['total_with_vat']);
    }

    public function test_can_access_monthly_expense_report_route()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('reports.expenses.monthly'), [
            'year' => 2024,
            'month' => 6,
            'format' => 'html'
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Reports/ExpensesReport')
                ->has('report_data')
                ->where('report_type', 'monthly')
        );
    }

    public function test_can_download_monthly_expense_report_pdf()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('reports.expenses.monthly'), [
            'year' => 2024,
            'month' => 6,
            'format' => 'pdf'
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_access_yearly_expense_report_route()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('reports.expenses.yearly'), [
            'year' => 2024,
            'format' => 'html'
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Reports/ExpensesReport')
                ->has('report_data')
                ->where('report_type', 'yearly')
        );
    }

    public function test_can_access_custom_expense_report_route()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('reports.expenses.custom'), [
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
            'format' => 'html'
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Reports/ExpensesReport')
                ->has('report_data')
                ->where('report_type', 'custom')
        );
    }

    public function test_expense_report_validation()
    {
        $this->actingAs($this->user);

        // Test invalid year
        $response = $this->post(route('reports.expenses.monthly'), [
            'year' => 2019, // too old
            'month' => 6,
            'format' => 'html'
        ]);
        $response->assertSessionHasErrors(['year']);

        // Test invalid month
        $response = $this->post(route('reports.expenses.monthly'), [
            'year' => 2024,
            'month' => 13, // invalid month
            'format' => 'html'
        ]);
        $response->assertSessionHasErrors(['month']);

        // Test invalid date range
        $response = $this->post(route('reports.expenses.custom'), [
            'start_date' => '2024-06-30',
            'end_date' => '2024-06-01', // end before start
            'format' => 'html'
        ]);
        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_expense_pdf_view_renders_correctly()
    {
        $reportService = new ExpenseReportService();
        $reportData = $reportService->generateMonthlyReport(2024, 6);

        $view = view('reports.expenses-pdf', $reportData);
        $html = $view->render();

        // Check for Czech text and formatting
        $this->assertStringContainsString('PŘEHLED VÝDAJŮ', $html);
        $this->assertStringContainsString('Souhrn', $html);
        $this->assertStringContainsString('Detail výdajů', $html);
        $this->assertStringContainsString('Přehled dle kategorií', $html);
        $this->assertStringContainsString('Přehled dle sazby DPH', $html);
        $this->assertStringContainsString('Kč', $html);

        // Check data is present
        $this->assertStringContainsString('Test expense 1', $html);
        $this->assertStringContainsString('Test expense 2', $html);
        $this->assertStringContainsString('Testovací kategorie', $html);
    }

    public function test_unauthenticated_user_cannot_access_expense_reports()
    {
        $response = $this->post(route('reports.expenses.monthly'), [
            'year' => 2024,
            'month' => 6,
            'format' => 'html'
        ]);
        $response->assertRedirect(route('login'));

        $response = $this->post(route('reports.expenses.yearly'), [
            'year' => 2024,
            'format' => 'html'
        ]);
        $response->assertRedirect(route('login'));

        $response = $this->post(route('reports.expenses.custom'), [
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
            'format' => 'html'
        ]);
        $response->assertRedirect(route('login'));
    }
}