<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use App\Models\MonthlyReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class TestEmailService extends Command
{
    protected $signature = 'email:test {--dry-run : Only test configuration without sending}';
    protected $description = 'Test email service configuration and SES connectivity';

    public function handle(EmailService $emailService)
    {
        $this->info('Testing Email Service Configuration...');
        
        // Test basic configuration
        $mailer = config('mail.default');
        $accountantEmail = config('mail.accountant_email', env('ACCOUNTANT_EMAIL'));
        
        $this->line("Current mailer: {$mailer}");
        $this->line("Accountant email: {$accountantEmail}");
        
        if (!$accountantEmail) {
            $this->error('❌ ACCOUNTANT_EMAIL is not configured');
            return 1;
        } else {
            $this->info('✅ Accountant email is configured');
        }
        
        // Test SES configuration if using SES
        if ($mailer === 'ses') {
            $this->testSESConfiguration();
        }
        
        if ($this->option('dry-run')) {
            $this->info('✅ Configuration test completed (dry-run mode)');
            return 0;
        }
        
        // Test with a simple email
        try {
            $this->info('Sending test email...');
            
            Mail::raw('This is a test email from the bookkeeping system.', function ($message) use ($accountantEmail) {
                $message->to($accountantEmail)
                        ->subject('Test Email - ' . now()->format('Y-m-d H:i:s'));
            });
            
            $this->info('✅ Test email sent successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test email: ' . $e->getMessage());
            
            // Try fallback in local environment
            if (app()->environment(['local', 'testing'])) {
                $this->warn('Attempting fallback to log driver...');
                try {
                    config(['mail.default' => 'log']);
                    Mail::raw('This is a test email (logged) from the bookkeeping system.', function ($message) use ($accountantEmail) {
                        $message->to($accountantEmail)
                                ->subject('Test Email (Logged) - ' . now()->format('Y-m-d H:i:s'));
                    });
                    
                    $this->info('✅ Fallback to log driver successful');
                } catch (\Exception $fallbackError) {
                    $this->error('❌ Fallback also failed: ' . $fallbackError->getMessage());
                    return 1;
                }
            }
        }
        
        return 0;
    }
    
    private function testSESConfiguration(): void
    {
        $this->line('Testing SES Configuration...');
        
        $awsKey = env('AWS_SES_KEY') ?: env('AWS_ACCESS_KEY_ID');
        $awsSecret = env('AWS_SES_SECRET') ?: env('AWS_SECRET_ACCESS_KEY');
        $awsRegion = env('AWS_SES_REGION') ?: env('AWS_DEFAULT_REGION');
        
        if ($awsKey) {
            $this->info('✅ AWS Key is configured');
        } else {
            $this->error('❌ AWS Key is missing (AWS_SES_KEY or AWS_ACCESS_KEY_ID)');
        }
        
        if ($awsSecret) {
            $this->info('✅ AWS Secret is configured');
        } else {
            $this->error('❌ AWS Secret is missing (AWS_SES_SECRET or AWS_SECRET_ACCESS_KEY)');
        }
        
        if ($awsRegion) {
            $this->info('✅ AWS Region is configured: ' . $awsRegion);
        } else {
            $this->error('❌ AWS Region is missing (AWS_SES_REGION or AWS_DEFAULT_REGION)');
        }
        
        if (!$awsKey || !$awsSecret || !$awsRegion) {
            $this->warn('⚠️  Incomplete SES configuration may cause email failures');
        }
    }
}