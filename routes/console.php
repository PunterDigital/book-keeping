<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule monthly report generation on the 15th of each month at 9:00 AM Prague time
// This generates the report for the previous month (14th to 14th period)
Schedule::command('reports:generate-monthly --auto-send')
    ->monthlyOn(15, '09:00')
    ->timezone('Europe/Prague')
    ->description('Generate and send monthly accountant report')
    ->onSuccess(function () {
        info('Monthly report generated and sent successfully');
    })
    ->onFailure(function () {
        error('Monthly report generation failed');
    });
