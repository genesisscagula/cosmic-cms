<?php

namespace App\Console\Commands;

use App\Services\PayPalService;
use Illuminate\Console\Command;

class PaymentHealthCheck extends Command
{
    protected $signature = 'payments:health-check {--live : Require live PayPal mode}';

    protected $description = 'Validate payment configuration and PayPal API connectivity.';

    public function handle(PayPalService $paypal): int
    {
        $errors = [];
        $warnings = [];

        if (! config('payments.paypal.enabled')) {
            $errors[] = 'PAYPAL_ENABLED must be true.';
        }

        foreach (['client_id', 'client_secret', 'webhook_id'] as $key) {
            if ((string) config("payments.paypal.{$key}") === '') {
                $errors[] = 'Missing PayPal setting: '.$key.'.';
            }
        }

        foreach (array_keys(config('payments.plans', [])) as $planKey) {
            if ((string) config("payments.paypal.plan_ids.{$planKey}") === '') {
                $errors[] = 'Missing PayPal plan ID for '.$planKey.'.';
            }
        }

        $mode = (string) config('payments.paypal.mode');

        if ($this->option('live') && $mode !== 'live') {
            $errors[] = 'PAYPAL_MODE must be live for this check.';
        }

        if (app()->environment('production') && $mode !== 'live') {
            $warnings[] = 'Production is currently using PayPal sandbox mode.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://') && app()->environment('production')) {
            $errors[] = 'APP_URL must use HTTPS in production.';
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        try {
            $paypal->client();
            $this->info('PayPal authentication successful.');
        } catch (\Throwable $exception) {
            $this->error('PayPal API connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Payment configuration is ready for '.$mode.' mode.');

        return self::SUCCESS;
    }
}
