<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LaunchReadinessCheck extends Command
{
    protected $signature = 'cosmic:launch-readiness
        {--strict : Treat warnings as failures}
        {--json : Print machine-readable JSON}
        {--deep : Also run billing, onboarding, queue, backup, and monitoring health commands}';

    protected $description = 'Run non-destructive production launch readiness checks for Cosmic CMS.';

    /** @var array<int,array{severity:string,code:string,message:string,context:array}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->checkRuntime();
        $this->checkEnvironment();
        $this->checkFilesystem();
        $this->checkDatabase();
        $this->checkBuildArtifacts();
        $this->checkProductionServices();

        if ($this->option('deep')) {
            $this->runDeepChecks();
        }

        if ($this->findings === []) {
            $this->add('ok', 'launch_ready', 'No launch-blocking configuration problems were detected.', []);
        }

        return $this->finish();
    }

    private function checkRuntime(): void
    {
        $minimum = (string) config('cosmic-launch.minimum_php_version', '8.2.0');
        if (version_compare(PHP_VERSION, $minimum, '<')) {
            $this->add('error', 'php_version', "PHP {$minimum} or newer is required.", ['current' => PHP_VERSION]);
        }

        foreach ((array) config('cosmic-launch.required_extensions', []) as $extension) {
            if (! extension_loaded((string) $extension)) {
                $this->add('error', 'missing_extension', 'A required PHP extension is missing.', ['extension' => $extension]);
            }
        }
    }

    private function checkEnvironment(): void
    {
        foreach ((array) config('cosmic-launch.required_env', []) as $key) {
            $value = env((string) $key);
            if ($value === null || trim((string) $value) === '') {
                $this->add('error', 'missing_env', 'A required environment value is missing.', ['key' => $key]);
            }
        }

        if (! app()->environment('production')) {
            $this->add('warning', 'not_production_environment', 'APP_ENV is not production.', ['environment' => app()->environment()]);
        }

        foreach ((array) config('cosmic-launch.production_forbidden', []) as $key => $forbidden) {
            $value = strtolower(trim((string) env($key, '')));
            if (in_array($value, array_map('strtolower', (array) $forbidden), true)) {
                $this->add('error', 'unsafe_production_setting', 'An unsafe production environment setting was detected.', ['key' => $key, 'value' => $value]);
            }
        }

        $url = (string) config('app.url');
        if ((bool) config('cosmic-launch.require_https', true) && ! str_starts_with(strtolower($url), 'https://')) {
            $this->add('error', 'https_required', 'APP_URL must use HTTPS for production.', ['url' => $url]);
        }

        if ((string) config('app.key') === '') {
            $this->add('error', 'missing_app_key', 'APP_KEY is not configured.', []);
        }
    }

    private function checkFilesystem(): void
    {
        foreach ((array) config('cosmic-launch.required_writable_paths', []) as $path) {
            if (! is_dir($path)) {
                $this->add('error', 'missing_directory', 'A required application directory does not exist.', ['path' => $path]);
            } elseif (! is_writable($path)) {
                $this->add('error', 'not_writable', 'A required application directory is not writable.', ['path' => $path]);
            }
        }

        $free = @disk_free_space(storage_path());
        $minimum = max(128, (int) config('cosmic-launch.minimum_free_disk_mb', 1024));
        if ($free !== false && $free < $minimum * 1024 * 1024) {
            $this->add('error', 'low_disk_space', 'Available disk space is below the launch threshold.', [
                'free_mb' => round($free / 1024 / 1024, 1),
                'minimum_mb' => $minimum,
            ]);
        }

        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->add('warning', 'storage_link_missing', 'The public storage link is missing.', ['command' => 'php artisan storage:link']);
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->add('error', 'database_unreachable', 'The configured database connection failed.', ['exception' => $e::class]);
            return;
        }

        foreach (['users', 'workspaces', 'websites', 'payment_orders'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->add('error', 'missing_core_table', 'A core database table is missing.', ['table' => $table]);
            }
        }

        try {
            $pending = DB::table('migrations')->count();
            if ($pending === 0) {
                $this->add('warning', 'migration_history_empty', 'Migration history is empty or unavailable.', []);
            }
        } catch (Throwable) {
            $this->add('error', 'migrations_table_missing', 'The migrations table is missing.', []);
        }
    }

    private function checkBuildArtifacts(): void
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            $this->add('error', 'frontend_build_missing', 'Production frontend assets have not been built.', ['command' => 'npm run build']);
        }

        if (is_file(base_path('public/hot'))) {
            $this->add('error', 'vite_hot_file_present', 'Vite development hot-reload file is present in production.', ['path' => 'public/hot']);
        }
    }

    private function checkProductionServices(): void
    {
        if ((bool) config('cosmic-launch.require_queue', true) && config('queue.default') === 'sync') {
            $this->add('error', 'queue_sync', 'QUEUE_CONNECTION cannot be sync in production.', []);
        }

        if ((bool) config('cosmic-launch.require_mail', true) && in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->add('error', 'mail_not_deliverable', 'MAIL_MAILER is not configured for real delivery.', ['mailer' => config('mail.default')]);
        }

        if ((bool) config('cosmic-launch.require_paypal', true)) {
            foreach (['services.paypal.client_id', 'services.paypal.client_secret'] as $key) {
                if (! filled(config($key))) {
                    $this->add('error', 'paypal_configuration_missing', 'A required PayPal production credential is missing.', ['config' => $key]);
                }
            }
            if ((string) config('services.paypal.mode', 'sandbox') !== 'live') {
                $this->add('error', 'paypal_not_live', 'PayPal is not configured in live mode.', ['mode' => config('services.paypal.mode')]);
            }
        }
    }

    private function runDeepChecks(): void
    {
        foreach ([
            'cosmic:queue-health',
            'cosmic:backup-health',
            'cosmic:monitoring-health',
            'cosmic:billing-qa' => ['--strict' => true],
            'cosmic:onboarding-qa' => ['--strict' => true],
        ] as $command => $arguments) {
            if (is_int($command)) {
                $command = $arguments;
                $arguments = [];
            }

            try {
                $exit = Artisan::call($command, $arguments);
                $severity = $exit === self::SUCCESS ? 'ok' : 'error';
                $this->add($severity, 'deep_check', "Deep check {$command} completed.", ['exit_code' => $exit]);
            } catch (Throwable $e) {
                $this->add('error', 'deep_check_failed', "Deep check {$command} could not run.", ['exception' => $e::class]);
            }
        }
    }

    private function add(string $severity, string $code, string $message, array $context): void
    {
        $this->findings[] = compact('severity', 'code', 'message', 'context');
    }

    private function finish(): int
    {
        $errors = count(array_filter($this->findings, fn (array $f) => $f['severity'] === 'error'));
        $warnings = count(array_filter($this->findings, fn (array $f) => $f['severity'] === 'warning'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ready' => $errors === 0 && (! $this->option('strict') || $warnings === 0),
                'summary' => ['errors' => $errors, 'warnings' => $warnings],
                'findings' => $this->findings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($this->findings as $finding) {
                $line = '['.strtoupper($finding['severity']).'] '.$finding['code'].': '.$finding['message'];
                match ($finding['severity']) {
                    'error' => $this->error($line),
                    'warning' => $this->warn($line),
                    default => $this->info($line),
                };
                if ($finding['context'] !== []) {
                    $this->line('  '.json_encode($finding['context'], JSON_UNESCAPED_SLASHES));
                }
            }
            $this->newLine();
            $this->line("Launch readiness summary: {$errors} error(s), {$warnings} warning(s).");
        }

        return ($errors > 0 || ($this->option('strict') && $warnings > 0)) ? self::FAILURE : self::SUCCESS;
    }
}
