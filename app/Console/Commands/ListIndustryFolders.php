<?php

namespace App\Console\Commands;

use App\Services\IndustryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListIndustryFolders extends Command
{
    protected $signature = 'cosmic:industries:list';

    protected $description = 'List the curated Cosmic CMS industry folders and local image counts';

    public function handle(IndustryResolver $industries): int
    {
        $rows = [];

        foreach ($industries->catalog() as $key => $definition) {
            if ($key === 'default') {
                continue;
            }

            $directory = storage_path('app/public/cms-images/'.$key);
            $count = File::isDirectory($directory)
                ? collect(File::files($directory))->filter(fn ($file) => preg_match('/\.(avif|webp|png|jpe?g)$/i', $file->getFilename()) === 1)->count()
                : 0;

            $rows[] = [
                $key,
                (string) ($definition['label'] ?? $key),
                $count,
                $count > 0 ? 'ready' : 'empty',
            ];
        }

        $this->table(['Industry key', 'Label', 'Images', 'Status'], $rows);
        $this->newLine();
        $this->line('Folder root: '.storage_path('app/public/cms-images'));
        $this->line('Fill one folder: php artisan cosmic:industries:pull <industry-key> --count=10');

        return self::SUCCESS;
    }
}
