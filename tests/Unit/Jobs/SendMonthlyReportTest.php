<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\MonthlyReport;
use App\Jobs\SendMonthlyReport;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected MonthlyReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->report = MonthlyReport::create([
            'period_start' => Carbon::create(2024, 6, 16),
            'period_end' => Carbon::create(2024, 7, 15),
            'generated_at' => now(),
            'email_status' => 'pending'
        ]);

        Mail::fake();
    }

    public function test_job_handles_successful_email_sending()
    {
        // Mock EmailService to return success
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('generateAndSendMonthlyReport')
                    ->once()
                    ->with($this->report)
                    ->andReturn(true);

        $job = new SendMonthlyReport($this->report);
        $job->handle($emailService);

        // Refresh report from database
        $this->report->refresh();

        $this->assertEquals('sent', $this->report->email_status);
        $this->assertNotNull($this->report->sent_at);
    }

    public function test_job_handles_failed_email_sending()
    {
        // Mock EmailService to return failure
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('generateAndSendMonthlyReport')
                    ->once()
                    ->with($this->report)
                    ->andReturn(false);

        $job = new SendMonthlyReport($this->report);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email service returned false');

        $job->handle($emailService);

        // Refresh report from database
        $this->report->refresh();

        $this->assertEquals('failed', $this->report->email_status);
    }

    public function test_job_handles_exception_during_email_sending()
    {
        // Mock EmailService to throw exception
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('generateAndSendMonthlyReport')
                    ->once()
                    ->with($this->report)
                    ->andThrow(new \Exception('Test exception'));

        $job = new SendMonthlyReport($this->report);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $job->handle($emailService);

        // Refresh report from database
        $this->report->refresh();

        $this->assertEquals('failed', $this->report->email_status);
    }

    public function test_job_failed_method_updates_report_status()
    {
        $job = new SendMonthlyReport($this->report);
        
        $exception = new \Exception('Test permanent failure');
        $job->failed($exception);

        // Refresh report from database
        $this->report->refresh();

        $this->assertEquals('failed', $this->report->email_status);
    }

    public function test_job_has_correct_configuration()
    {
        $job = new SendMonthlyReport($this->report);

        $this->assertEquals(300, $job->timeout); // 5 minutes
        $this->assertEquals(3, $job->tries);
        $this->assertEquals($this->report->id, $job->report->id);
    }

    public function test_job_can_be_serialized_and_unserialized()
    {
        $job = new SendMonthlyReport($this->report);
        
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(SendMonthlyReport::class, $unserialized);
        $this->assertEquals($this->report->id, $unserialized->report->id);
    }
}