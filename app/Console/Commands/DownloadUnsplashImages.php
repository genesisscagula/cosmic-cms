<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DownloadUnsplashImages extends Command
{
    protected $signature = 'unsplash:download
                            {query=yacht : Search phrase sent to Unsplash}
                            {--count=10 : Number of images to download (1-30)}
                            {--folder= : Destination folder name under cms-images/unsplash}
                            {--orientation=landscape : landscape, portrait, or squarish}';

    protected $description = 'Search Unsplash and download matching photos into local CMS image storage';

    public function handle(): int
    {
        $accessKey = trim((string) config('services.unsplash.access_key'));

        if ($accessKey === '') {
            $this->error('UNSPLASH_ACCESS_KEY is missing. Add it to .env, then run php artisan optimize:clear.');

            return self::FAILURE;
        }

        $query = trim((string) $this->argument('query'));
        $count = max(1, min(30, (int) $this->option('count')));
        $orientation = strtolower(trim((string) $this->option('orientation')));

        if ($query === '') {
            $this->error('The Unsplash search query cannot be empty.');

            return self::FAILURE;
        }

        if (! in_array($orientation, ['landscape', 'portrait', 'squarish'], true)) {
            $this->error('Invalid orientation. Use landscape, portrait, or squarish.');

            return self::FAILURE;
        }

        $folder = Str::slug((string) ($this->option('folder') ?: $query)) ?: 'unsplash-images';
        $relativeDirectory = 'cms-images/unsplash/' . $folder;
        $absoluteDirectory = storage_path('app/public/' . $relativeDirectory);

        File::ensureDirectoryExists($absoluteDirectory);

        $this->info("Searching Unsplash for: {$query}");
        $this->line("Requested images: {$count}");
        $this->line("Destination: {$absoluteDirectory}");
        $this->newLine();

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Client-ID ' . $accessKey,
                    'Accept-Version' => 'v1',
                ])
                ->connectTimeout(8)
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->get('https://api.unsplash.com/search/photos', [
                    'query' => $query,
                    'page' => 1,
                    'per_page' => $count,
                    'order_by' => 'latest',
                    'orientation' => $orientation,
                    'content_filter' => 'high',
                ]);

            if (! $response->successful()) {
                $this->error('Unsplash search failed with HTTP ' . $response->status() . '.');
                $this->line(Str::limit($response->body(), 500));

                return self::FAILURE;
            }

            $photos = $response->json('results', []);

            if (! is_array($photos) || $photos === []) {
                $this->warn("No Unsplash photos found for '{$query}'.");

                return self::SUCCESS;
            }

            $downloaded = 0;

            foreach (array_slice($photos, 0, $count) as $index => $photo) {
                if (! is_array($photo)) {
                    continue;
                }

                try {
                    $photoId = (string) data_get($photo, 'id', '');
                    $imageUrl = (string) (data_get($photo, 'urls.raw') ?: data_get($photo, 'urls.regular'));
                    $downloadLocation = data_get($photo, 'links.download_location');

                    if ($photoId === '' || $imageUrl === '') {
                        $this->warn('Skipping result ' . ($index + 1) . ': missing photo ID or URL.');
                        continue;
                    }

                    $imageResponse = Http::connectTimeout(10)
                        ->timeout(60)
                        ->retry(2, 500, throw: false)
                        ->get($imageUrl, [
                            'auto' => 'format',
                            'fit' => 'crop',
                            'crop' => 'entropy',
                            'w' => 1600,
                            'h' => 1000,
                            'q' => 84,
                        ]);

                    if (! $imageResponse->successful() || $imageResponse->body() === '') {
                        $this->warn("Failed to download photo {$photoId} (HTTP {$imageResponse->status()}).");
                        continue;
                    }

                    $contentType = strtolower((string) $imageResponse->header('Content-Type'));
                    $extension = match (true) {
                        str_contains($contentType, 'webp') => 'webp',
                        str_contains($contentType, 'png') => 'png',
                        default => 'jpg',
                    };

                    $number = str_pad((string) ($downloaded + 1), 2, '0', STR_PAD_LEFT);
                    $filename = sprintf('%s-%s-%s.%s', Str::slug($query), $number, $photoId, $extension);
                    $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

                    File::put($absolutePath, $imageResponse->body());

                    if (! File::exists($absolutePath) || File::size($absolutePath) === 0) {
                        $this->warn("Downloaded file was empty for photo {$photoId}.");
                        continue;
                    }

                    $downloaded++;
                    $photographer = (string) data_get($photo, 'user.name', 'Unknown photographer');
                    $this->info("[{$downloaded}/{$count}] {$filename} — {$photographer}");

                    $this->triggerDownloadTracking($downloadLocation, $accessKey);
                } catch (Throwable $exception) {
                    $this->warn('Skipped result ' . ($index + 1) . ': ' . $exception->getMessage());
                }
            }

            $this->newLine();

            if ($downloaded === 0) {
                $this->error('Unsplash returned results, but no files were downloaded.');

                return self::FAILURE;
            }

            $this->info("Finished. Downloaded {$downloaded} image(s).");
            $this->line('Storage folder: ' . $absoluteDirectory);
            $this->line('Public URL base: /storage/' . $relativeDirectory . '/');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Unexpected error: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function triggerDownloadTracking(mixed $downloadLocation, string $accessKey): void
    {
        if (! is_string($downloadLocation) || $downloadLocation === '') {
            return;
        }

        Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Client-ID ' . $accessKey,
                'Accept-Version' => 'v1',
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(1, 300, throw: false)
            ->get($downloadLocation);
    }
}
