<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaLibraryRegistry
{
    public function registerStoredPath(Website $website, string $path, string $source = 'upload', ?string $kind = null, ?int $userId = null, ?string $originalName = null, array $metadata = []): ?MediaAsset
    {
        $path = ltrim($path, '/');
        if ($path === '' || !Storage::disk('public')->exists($path)) return null;

        $existing = $website->mediaAssets()->where('disk', 'public')->where('path', $path)->first();
        if ($existing) return $existing;

        $absolute = Storage::disk('public')->path($path);
        $mime = @mime_content_type($absolute) ?: null;
        if ($mime && !str_starts_with($mime, 'image/')) return null;

        $size = (int) (Storage::disk('public')->size($path) ?: 0);
        [$width, $height] = $this->dimensions($absolute);
        $filename = basename($path);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $website->mediaAssets()->create([
            'uuid' => (string) Str::uuid(),
            'uploaded_by' => $userId,
            'source' => in_array($source, ['upload','ai','unsplash','import'], true) ? $source : 'import',
            'kind' => $kind,
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($originalName ?: $filename, 255, ''),
            'filename' => $filename,
            'mime_type' => $mime,
            'extension' => $extension ?: null,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'checksum_sha256' => @hash_file('sha256', $absolute) ?: null,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function importRemoteImage(Website $website, string $url, string $source = 'unsplash', ?string $kind = null, ?int $userId = null, array $metadata = []): ?MediaAsset
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) return null;

        $host = strtolower((string) $parts['host']);
        $allowed = ['images.unsplash.com', 'plus.unsplash.com', 'images.pexels.com'];
        if (!in_array($host, $allowed, true)) return null;

        $existing = $website->mediaAssets()->where('source', $source)
            ->where('metadata->source_url', $url)->first();
        if ($existing) return $existing;

        $response = Http::timeout(20)->retry(1, 250)->get($url);
        if (!$response->successful()) return null;
        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) > 12 * 1024 * 1024) return null;

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0] ?? ''));
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif'];
        if (!isset($extensions[$mime])) return null;

        $extension = $extensions[$mime];
        $filename = ($source === 'unsplash' ? 'unsplash-' : 'import-').Str::uuid().'.'.$extension;
        $path = "websites/{$website->id}/media-library/{$filename}";
        if (!Storage::disk('public')->put($path, $bytes)) return null;

        return $this->registerStoredPath($website, $path, $source, $kind, $userId, $filename, [
            ...$metadata,
            'source_url' => $url,
        ]);
    }

    public function url(MediaAsset $asset): string
    {
        return route('media-library.assets.show', ['website' => $asset->website_id, 'asset' => $asset->uuid], false);
    }

    private function dimensions(string $absolutePath): array
    {
        $info = @getimagesize($absolutePath);
        return [isset($info[0]) ? (int)$info[0] : null, isset($info[1]) ? (int)$info[1] : null];
    }
}
