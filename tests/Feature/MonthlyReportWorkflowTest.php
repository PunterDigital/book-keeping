<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MonthlyReport;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Jobs\SendMonthlyReport;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class MonthlyReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->client = Client::create([
            'company_name' => 'Test Client s.r.o.',
            'contact_name' => 'Jan Novák',
            'address' => 'Test Address 123, Praha',
            'vat_id' => 'CZ12345678'
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Office Supplies'
        ]);

        Mail::fake();
        Queue::fake();
    }

    public function test_monthly_reports_index_displays_current_period_and_data()
    {
        $this->actingAs($this->user);

        // Create data for current period
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 10000.00,
            'vat_amount' => 2100.00,
            'total' => 12100.00,
            'status' => 'sent'
        ]);

        $expense = Expense::create([
            'date' => now(),
            'amount' => 1500.00,
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'vat_amount' => 315.00
        ]);

        $response = $this->get('/monthly-reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('MonthlyReports/Index')
                ->has('currentPeriod.start')
                ->has('currentPeriod.end')
                ->has('currentInvoices', 1)
                ->has('currentExpenses', 1)
        );
    }

    public function test_can_generate_monthly_report()
    {
        $this->actingAs($this->user);

        $periodStart = Carbon::create(2024, 6, 16);
        $periodEnd = Carbon::create(2024, 7, 15);

        $response = $this->post('/monthly-reports/generate', [
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d')
        ]);

        $response->assertRedirect('/monthly-reports');
        $response->assertSessionHas('success', 'Monthly report generated successfully.');

        $this->assertDatabaseHas('monthly_reports', [
            'period_start' => $periodStart->format('Y-m-d') . ' 00:00:00',
            'period_end' => $periodEnd->format('Y-m-d') . ' 00:00:00',
            'email_status' => 'pending'
        ]);
    }

    public function test_cannot_generate_duplicate_monthly_report()
    {
        $this->actingAs($this->user);

        $periodStart = Carbon::create(2024, 6, 16);
        $periodEnd = Carbon::create(2024, 7, 15);

        // Create existing report
        MonthlyReport::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_at' => now(),
            'email_status' => 'sent'
        ]);

        $response = $this->post('/monthly-reports/generate', [
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d')
        ]);

        $response->assertRedirect('/monthly-reports');
        $response->assertSessionHas('error', 'Report for this period already exists.');
    }

    public function test_can_queue_monthly_report_for_sending()
    {
        $this->actingAs($this->user);

        $report = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 7, 15),
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        $response = $this->post('/monthly-reports/send', [
            'report_id' => $report->id
        ]);

        $response->assertRedirect('/monthly-reports');
        $response->assertSessionHas('success', 'Monthly report is being prepared and will be sent to the accountant shortly.');

        // Verify job was dispatched
        Queue::assertPushed(SendMonthlyReport::class, function ($job) use ($report) {
            return $job->report->id === $report->id;
        });
    }

    public function test_can_send_monthly_report_immediately()
    {
        $this->actingAs($this->user);

        // Mock EmailService
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('generateAndSendMonthlyReport')
                    ->once()
                    ->andReturn(true);

        $report = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 7, 15),
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        $response = $this->post('/monthly-reports/send-now', [
            'report_id' => $report->id
        ]);

        $response->assertRedirect('/monthly-reports');
        $response->assertSessionHas('success', 'Monthly report sent to accountant successfully.');

        // Refresh report
        $report->refresh();
        $this->assertEquals('sent', $report->email_status);
        $this->assertNotNull($report->sent_at);
    }

    public function test_handles_immediate_send_failure()
    {
        $this->actingAs($this->user);

        // Mock EmailService to fail
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('generateAndSendMonthlyReport')
                    ->once()
                    ->andReturn(false);

        $report = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 7, 15),
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        $response = $this->post('/monthly-reports/send-now', [
            'report_id' => $report->id
        ]);

        $response->assertRedirect('/monthly-reports');
        $response->assertSessionHas('error', 'Failed to send monthly report.');

        // Refresh report
        $report->refresh();
        $this->assertEquals('failed', $report->email_status);
    }

    public function test_validates_report_generation_input()
    {
        $this->actingAs($this->user);

        // Test missing period_start
        $response = $this->post('/monthly-reports/generate', [
            'period_end' => '2024-07-15'
        ]);

        $response->assertSessionHasErrors(['period_start']);

        // Test missing period_end
        $response = $this->post('/monthly-reports/generate', [
            'period_start' => '2024-06-16'
        ]);

        $response->assertSessionHasErrors(['period_end']);

        // Test end date before start date
        $response = $this->post('/monthly-reports/generate', [
            'period_start' => '2024-07-15',
            'period_end' => '2024-06-16'
        ]);

        $response->assertSessionHasErrors(['period_end']);
    }

    public function test_validates_report_sending_input()
    {
        $this->actingAs($this->user);

        // Test missing report_id
        $response = $this->post('/monthly-reports/send', []);
        $response->assertSessionHasErrors(['report_id']);

        // Test invalid report_id
        $response = $this->post('/monthly-reports/send', [
            'report_id' => 99999
        ]);
        $response->assertSessionHasErrors(['report_id']);
    }

    public function test_complete_monthly_report_workflow()
    {
        $this->actingAs($this->user);

        $periodStart = Carbon::create(2024, 6, 16);
        $periodEnd = Carbon::create(2024, 7, 15);

        // Create test data for the period
        $invoice = Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $this->client->id,
            'issue_date' => $periodStart->copy()->addDays(5),
            'due_date' => $periodStart->copy()->addDays(35),
            'subtotal' => 10000.00,
            'vat_amount' => 2100.00,
            'total' => 12100.00,
            'status' => 'sent'
        ]);

        $expense = Expense::create([
            'date' => $periodStart->copy()->addDays(10),
            'amount' => 1500.00,
            'category_id' => $this->category->id,
            'description' => 'Test Business Expense',
            'vat_amount' => 315.00
        ]);

        // Step 1: Generate report
        $response = $this->post('/monthly-reports/generate', [
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d')
        ]);

        $response->assertRedirect('/monthly-reports');
        
        $report = MonthlyReport::first();
        $this->assertEquals('pending', $report->email_status);

        // Step 2: Send report via queue
        $response = $this->post('/monthly-reports/send', [
            'report_id' => $report->id
        ]);

        $response->assertRedirect('/monthly-reports');
        Queue::assertPushed(SendMonthlyReport::class);

        // Step 3: Verify report can be viewed in index
        $response = $this->get('/monthly-reports');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('MonthlyReports/Index')
                ->has('reports', 1)
        );
    }

    public function test_monthly_reports_require_authentication()
    {
        $routes = [
            'GET /monthly-reports',
            'POST /monthly-reports/generate',
            'POST /monthly-reports/send',
            'POST /monthly-reports/send-now'
        ];

        foreach ($routes as $route) {
            $parts = explode(' ', $route);
            $method = strtolower($parts[0]);
            $url = $parts[1];

            $response = $this->$method($url);
            $response->assertRedirect('/login');
        }
    }
}