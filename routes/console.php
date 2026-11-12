<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server cron calls "php artisan schedule:run" every minute. The evaluation command decides by itself
// whether the hour in the settings has come (PRD F16), and old notifications are removed once a day.
Schedule::command('evaluation:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:prune')->dailyAt('01:00');
