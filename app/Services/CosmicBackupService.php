<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class CosmicBackupService
{
    public function create(): array
    {
        if (! config('cosmic-backup.enabled')) {
            throw new RuntimeException('Cosmic backups are disabled.');
        }

        $stamp = now()->utc()->format('Y-m-d_H-i-s');
        $name = "cosmic-{$stamp}";
        $working = storage_path("app/cosmic-backup-tmp/{$name}");
        File::ensureDirectoryExists($working);

        try {
            $parts = [];
            if (config('cosmic-backup.database.enabled')) {
                $parts['database'] = $this->dumpDatabase($working);
            }
            if (config('cosmic-backup.files.enabled')) {
                $parts['files'] = $this->archiveFiles($working);
            }

            $manifest = [
                'name' => $name,
                'created_at' => now()->utc()->toIso8601String(),
                'application' => config('app.name'),
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'parts' => [],
            ];

            foreach ($parts as $key => $file) {
                $manifest['parts'][$key] = [
                    'file' => basename($file),
                    'bytes' => File::size($file),
                    'sha256' => hash_file('sha256', $file),
                ];
            }

            File::put($working.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $archive = storage_path("app/cosmic-backup-tmp/{$name}.zip");
            $this->zipDirectory($working, $archive);

            $disk = $this->disk();
            $remote = $this->basePath().'/'.basename($archive);
            $stream = fopen($archive, 'rb');
            if ($stream === false || ! $disk->put($remote, $stream)) {
                throw new RuntimeException('Unable to write backup archive to the configured disk.');
            }
            if (is_resource($stream)) fclose($stream);

            return [
                'path' => $remote,
                'bytes' => File::size($archive),
                'sha256' => hash_file('sha256', $archive),
                'created_at' => $manifest['created_at'],
            ];
        } finally {
            File::deleteDirectory($working);
            if (isset($archive)) File::delete($archive);
        }
    }

    public function list(): array
    {
        $disk = $this->disk();
        return collect($disk->files($this->basePath()))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'path' => $path,
                'bytes' => $disk->size($path),
                'modified_at' => CarbonImmutable::createFromTimestampUTC($disk->lastModified($path)),
            ])
            ->sortByDesc('modified_at')
            ->values()->all();
    }

    public function prune(): int
    {
        $backups = $this->list();
        $keep = [];
        $now = CarbonImmutable::now('UTC');
        $daily = (int) config('cosmic-backup.retention.daily_days', 7);
        $weekly = (int) config('cosmic-backup.retention.weekly_weeks', 4);
        $monthly = (int) config('cosmic-backup.retention.monthly_months', 6);

        foreach ($backups as $backup) {
            $date = $backup['modified_at'];
            $ageDays = $date->diffInDays($now);
            if ($ageDays < $daily) $keep['daily:'.$backup['path']] = $backup['path'];
            if ($ageDays < ($weekly * 7)) $keep['weekly:'.$date->format('o-W')] ??= $backup['path'];
            if ($ageDays < ($monthly * 31)) $keep['monthly:'.$date->format('Y-m')] ??= $backup['path'];
        }

        $pathsToKeep = array_values($keep);
        $deleted = 0;
        foreach ($backups as $backup) {
            if (! in_array($backup['path'], $pathsToKeep, true)) {
                $this->disk()->delete($backup['path']);
                $deleted++;
            }
        }
        return $deleted;
    }

    public function health(): array
    {
        $latest = $this->list()[0] ?? null;
        if (! $latest) return ['healthy' => false, 'message' => 'No backup archives found.'];

        $maxAge = (int) config('cosmic-backup.health.maximum_age_hours', 30);
        $minBytes = (int) config('cosmic-backup.health.minimum_bytes', 1024);
        $age = $latest['modified_at']->diffInHours(CarbonImmutable::now('UTC'));
        $healthy = $age <= $maxAge && $latest['bytes'] >= $minBytes;

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Latest backup is healthy.' : 'Latest backup is stale or unexpectedly small.',
            'latest' => $latest,
            'age_hours' => $age,
        ];
    }

    private function dumpDatabase(string $working): string
    {
        $connection = config('cosmic-backup.database.connection');
        $db = config("database.connections.{$connection}");
        if (! is_array($db)) throw new RuntimeException("Unknown database connection [{$connection}].");

        $driver = $db['driver'] ?? null;
        $output = $working.'/database.sql';
        if ($driver === 'sqlite') {
            $source = $db['database'];
            if (! is_file($source)) throw new RuntimeException('SQLite database file was not found.');
            File::copy($source, $working.'/database.sqlite');
            return $working.'/database.sqlite';
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $args = [config('cosmic-backup.database.mysqldump_binary'), '--single-transaction', '--quick', '--routines', '--triggers', '--host='.$db['host'], '--port='.(string) $db['port'], '--user='.$db['username'], '--result-file='.$output, $db['database']];
            $env = ['MYSQL_PWD' => (string) ($db['password'] ?? '')];
        } elseif ($driver === 'pgsql') {
            $args = [config('cosmic-backup.database.pg_dump_binary'), '--format=plain', '--no-owner', '--no-acl', '--host='.$db['host'], '--port='.(string) $db['port'], '--username='.$db['username'], '--file='.$output, $db['database']];
            $env = ['PGPASSWORD' => (string) ($db['password'] ?? '')];
        } else {
            throw new RuntimeException("Unsupported backup database driver [{$driver}].");
        }

        $process = new Process($args, base_path(), $env, null, 600);
        $process->mustRun();
        if (! File::exists($output) || File::size($output) === 0) throw new RuntimeException('Database dump was empty.');
        return $output;
    }

    private function archiveFiles(string $working): string
    {
        $archive = $working.'/files.zip';
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create file archive.');
        foreach ((array) config('cosmic-backup.files.paths', []) as $path) {
            if (! is_dir($path)) continue;
            $prefix = basename($path);
            foreach (File::allFiles($path) as $file) {
                $zip->addFile($file->getPathname(), $prefix.'/'.$file->getRelativePathname());
            }
        }
        $zip->close();
        return $archive;
    }

    private function zipDirectory(string $directory, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create backup archive.');
        foreach (File::allFiles($directory) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('cosmic-backup.disk', 'local'));
    }

    private function basePath(): string
    {
        return trim((string) config('cosmic-backup.path', 'backups/cosmic'), '/');
    }
}
