<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Repair missed/delayed PayPal lifecycle events without granting credits.
Schedule::command('payments:reconcile-paypal --limit=500')
    ->everySixHours()
    ->withoutOverlapping(30)
    ->onOneServer();

// Recover failed or stale workspace provisioning without duplicating resources.
Schedule::command('provisioning:recover --limit=100')
    ->everyTenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer();
