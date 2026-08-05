<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

class ProductionCloseoutCheck extends Command
{
    protected $signature = 'cosmic:production-closeout
        {--strict : Treat warnings as failures}
        {--json : Print machine-readable JSON}
        {--skip-deep : Verify the release package without running deep operational audits}';

    protected $description = 'Run the final non-destructive Cosmic CMS production-readiness closeout.';

    /** @var array<int,array{severity:string,code:string,message:string,context:array}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->checkReleaseArtifacts();
        $this->checkOperationalCommands();
        $this->checkConfigurationContracts();
        $this->checkSchedulerContract();

        if (! $this->option('skip-deep')) {
            $this->runLaunchAudit();
        }

        if ($this->findings === []) {
            $this->add('ok', 'production_closeout_complete', 'All production closeout checks passed.', []);
        }

        return $this->finish();
    }

    private function checkReleaseArtifacts(): void
    {
        foreach ([
            'COSMIC-V1-PRODUCTION-RUNBOOK.md',
            'COSMIC-V1-RELEASE-MANIFEST.json',
            'V1-PATCH-1.0.62-RELEASE-NOTES.md',
        ] as $file) {
            if (! File::exists(base_path($file))) {
                $this->add('error', 'release_artifact_missing', 'A required release artifact is missing.', ['file' => $file]);
            }
        }

        $manifestPath = base_path('COSMIC-V1-RELEASE-MANIFEST.json');
        if (File::exists($manifestPath)) {
            $manifest = json_decode((string) File::get($manifestPath), true);
            if (! is_array($manifest) || ($manifest['release'] ?? null) !== '1.0.62') {
                $this->add('error', 'release_manifest_invalid', 'The release manifest is invalid or has the wrong version.', []);
            }
        }
    }

    private function checkOperationalCommands(): void
    {
        $commands = [
            'cosmic:queue-health',
            'cosmic:backup',
            'cosmic:backup-health',
            'cosmic:monitoring-health',
            'cosmic:prune-logs',
            'cosmic:billing-qa',
            'cosmic:onboarding-qa',
            'cosmic:launch-readiness',
        ];

        $registered = Artisan::all();
        foreach ($commands as $command) {
            if (! array_key_exists($command, $registered)) {
                $this->add('error', 'operational_command_missing', 'A required production command is not registered.', ['command' => $command]);
            }
        }
    }

    private function checkConfigurationContracts(): void
    {
        foreach ([
            'cosmic-rate-limits.php',
            'cosmic-queue.php',
            'cosmic-backup.php',
            'cosmic-monitoring.php',
            'cosmic-legal.php',
            'cosmic-billing-qa.php',
            'cosmic-onboarding-qa.php',
            'cosmic-launch.php',
        ] as $file) {
            if (! File::exists(config_path($file))) {
                $this->add('error', 'production_config_missing', 'A required production configuration file is missing.', ['file' => $file]);
            }
        }
    }

    private function checkSchedulerContract(): void
    {
        $consoleRoutes = base_path('routes/console.php');
        if (! File::exists($consoleRoutes)) {
            $this->add('error', 'scheduler_contract_missing', 'routes/console.php is missing.', []);
            return;
        }

        $contents = (string) File::get($consoleRoutes);
        foreach ([
            'cosmic:queue-health',
            'cosmic:backup --prune',
            'cosmic:monitoring-health',
            'cosmic:billing-qa --strict',
            'cosmic:onboarding-qa --strict',
            'cosmic:launch-readiness --strict',
        ] as $needle) {
            if (! str_contains($contents, $needle)) {
                $this->add('error', 'scheduled_check_missing', 'A required scheduled production check is missing.', ['command' => $needle]);
            }
        }
    }

    private function runLaunchAudit(): void
    {
        try {
            $exit = Artisan::call('cosmic:launch-readiness', ['--deep' => true, '--strict' => true]);
            $this->add(
                $exit === self::SUCCESS ? 'ok' : 'error',
                'strict_launch_audit',
                'The strict deep launch-readiness audit completed.',
                ['exit_code' => $exit]
            );
        } catch (Throwable $e) {
            $this->add('error', 'strict_launch_audit_failed', 'The strict deep launch audit could not run.', ['exception' => $e::class]);
        }
    }

    private function add(string $severity, string $code, string $message, array $context): void
    {
        $this->findings[] = compact('severity', 'code', 'message', 'context');
    }

    private function finish(): int
    {
        $errors = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'error'));
        $warnings = count(array_filter($this->findings, fn (array $finding) => $finding['severity'] === 'warning'));
        $ready = $errors === 0 && (! $this->option('strict') || $warnings === 0);

        if ($this->option('json')) {
            $this->line(json_encode([
                'release' => '1.0.62',
                'ready' => $ready,
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
            $this->line("Production closeout summary: {$errors} error(s), {$warnings} warning(s).");
            if ($ready) {
                $this->info('Cosmic CMS V1 is ready for controlled production launch after manual staging sign-off.');
            }
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
