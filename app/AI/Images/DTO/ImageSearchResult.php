<?php

namespace App\AI\Images\DTO;

final class ImageSearchResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $url,
        public readonly string $description = '',
        public readonly string $photographer = '',
        public readonly string $sourceUrl = '',
        public readonly int $width = 0,
        public readonly int $height = 0,
        public readonly array $downloadParameters = [],
        public readonly array $meta = [],
    ) {
    }
}
