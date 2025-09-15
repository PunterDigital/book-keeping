<?php

namespace App\Mail;

use App\Models\MonthlyReport as MonthlyReportModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonthlyReport extends Mailable
{
    use Queueable, SerializesModels;

    public MonthlyReportModel $report;
    public string $zipPath;

    public function __construct(MonthlyReportModel $report, string $zipPath)
    {
        $this->report = $report;
        $this->zipPath = $zipPath;
    }

    public function build()
    {
        $periodStart = $this->report->period_start->format('d.m.Y');
        $periodEnd = $this->report->period_end->format('d.m.Y');
        
        $subject = "Měsíční přehled účetnictví {$periodStart} - {$periodEnd}";
        
        return $this->view('emails.monthly-report')
                    ->subject($subject)
                    ->with([
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'generated_at' => $this->report->generated_at->format('d.m.Y H:i')
                    ])
                    ->attach($this->zipPath, [
                        'as' => 'mesicni_prehled_' . $this->report->period_start->format('Y_m') . '.zip',
                        'mime' => 'application/zip'
                    ]);
    }
}