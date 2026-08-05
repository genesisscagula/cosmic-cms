<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile-paypal --limit=500')
    ->everySixHours()->withoutOverlapping(30)->onOneServer()->runInBackground();

Schedule::command('provisioning:recover --limit=100')
    ->everyTenMinutes()->withoutOverlapping(20)->onOneServer()->runInBackground();

Schedule::command('cosmic:prune-expired-access')
    ->hourly()->withoutOverlapping(10)->onOneServer()->runInBackground();

Schedule::command('cosmic:queue-health')
    ->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('queue:prune-failed --hours='.(int) config('cosmic-queue.retention.failed_job_hours', 336))
    ->dailyAt('03:20')->withoutOverlapping(30)->onOneServer()->runInBackground();

Schedule::command('cosmic:backup --prune')
    ->dailyAt('02:30')->withoutOverlapping(120)->onOneServer()->runInBackground();

Schedule::command('cosmic:backup-health')
    ->dailyAt('08:00')->withoutOverlapping(10)->onOneServer();


Schedule::command('cosmic:monitoring-health')
    ->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('cosmic:prune-logs')
    ->dailyAt('03:40')->withoutOverlapping(15)->onOneServer()->runInBackground();

Schedule::command('cosmic:billing-qa --strict')
    ->dailyAt((string) config('cosmic-billing-qa.schedule_daily_at', '09:15'))
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('cosmic:onboarding-qa --strict')
    ->dailyAt((string) config('cosmic-onboarding-qa.schedule_daily_at', '09:35'))
    ->withoutOverlapping(30)
    ->onOneServer();


Schedule::command('cosmic:launch-readiness --strict')
    ->dailyAt('10:00')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('cosmic:production-closeout --skip-deep --strict')
    ->weeklyOn(1, '10:20')
    ->withoutOverlapping(30)
    ->onOneServer();
