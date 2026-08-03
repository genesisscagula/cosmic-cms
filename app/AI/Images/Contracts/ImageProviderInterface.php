<?php

namespace App\AI\Images\Contracts;

use App\AI\Images\DTO\ImageSearchResult;

interface ImageProviderInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    /** @return array<int, ImageSearchResult> */
    public function searchMany(string $query, array $options = []): array;

    public function search(string $query, array $options = []): ?ImageSearchResult;

    public function trackDownload(ImageSearchResult $result): void;
}
