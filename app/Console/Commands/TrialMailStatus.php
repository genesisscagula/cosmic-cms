<?php

namespace App\Console\Commands;

use App\Models\TrialGeneration;
use Illuminate\Console\Command;

class TrialMailStatus extends Command
{
    protected $signature = 'trial-mail:status {trial? : Trial ID or token}';
    protected $description = 'Show recent Cosmic CMS trial welcome-email delivery status.';

    public function handle(): int
    {
        $needle = $this->argument('trial');

        $query = TrialGeneration::query()
            ->whereNotNull('email')
            ->latest('email_captured_at');

        if ($needle) {
            $query->where(function ($query) use ($needle) {
                if (ctype_digit((string) $needle)) {
                    $query->orWhere('id', (int) $needle);
                }
                $query->orWhere('token', (string) $needle);
            });
        }

        $rows = $query->limit($needle ? 1 : 12)->get();

        if ($rows->isEmpty()) {
            $this->warn('No matching trial email records found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Trial', 'Email', 'Attempts', 'Sent', 'Last attempt', 'Last error'],
            $rows->map(fn ($trial) => [
                $trial->id,
                $trial->email,
                (int) $trial->welcome_email_attempts,
                optional($trial->welcome_email_sent_at)?->toDateTimeString() ?: '—',
                optional($trial->welcome_email_last_attempt_at)?->toDateTimeString() ?: '—',
                $trial->welcome_email_last_error ? mb_strimwidth($trial->welcome_email_last_error, 0, 80, '…') : '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
