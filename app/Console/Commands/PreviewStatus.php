<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PreviewStatus extends Command
{
    protected $signature = 'cosmic:preview-status';

    protected $description = 'Show Cosmic preview deployment configuration and live-readiness checks.';

    public function handle(): int
    {
        $mode = (string) config('cosmic_preview.mode');
        $domain = trim((string) config('cosmic_preview.domain'));
        $scheme = (string) config('cosmic_preview.scheme', 'https');
        $diskName = (string) config('cosmic_preview.disk', 'local');
        $root = (string) config('cosmic_preview.root', 'cosmic-previews');

        $checks = [
            ['Mode', $mode, in_array($mode, ['local', 'subdomain'], true) ? 'OK' : 'INVALID'],
            ['Preview domain', $domain ?: '(empty)', $mode !== 'subdomain' || $domain !== '' ? 'OK' : 'MISSING'],
            ['Scheme', $scheme, in_array($scheme, ['http', 'https'], true) ? 'OK' : 'INVALID'],
            ['Storage disk', $diskName, array_key_exists($diskName, (array) config('filesystems.disks')) ? 'OK' : 'MISSING'],
            ['Preview root', $root, $root !== '' ? 'OK' : 'MISSING'],
        ];

        try {
            Storage::disk($diskName)->exists($root);
            $storageStatus = 'OK';
        } catch (\Throwable $exception) {
            $storageStatus = 'ERROR: '.$exception->getMessage();
        }

        $checks[] = ['Storage access', $diskName.':'.$root, $storageStatus];

        $this->table(['Check', 'Value', 'Status'], $checks);

        if ($mode === 'local') {
            $this->line('Local preview base: '.rtrim((string) config('cosmic_preview.base_url'), '/').'/{slug}');
        } else {
            $this->line('Wildcard preview pattern: '.$scheme.'://{slug}.'.$domain);
            $this->newLine();
            $this->warn('DNS/TLS are infrastructure checks and cannot be proven from Laravel config alone.');
            $this->line('Expected DNS: *.'.$domain.' -> this web server');
            $this->line('Expected TLS certificate: *.'.$domain.' (or equivalent wildcard coverage)');
        }

        $hasFailure = collect($checks)->contains(fn (array $row) => ! in_array($row[2], ['OK'], true));

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }
}
