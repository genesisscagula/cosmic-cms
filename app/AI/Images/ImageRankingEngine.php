<?php

namespace App\AI\Images;

use App\AI\Images\DTO\ImageSearchResult;
use Illuminate\Support\Str;

final class ImageRankingEngine
{
    /** @param array<int, ImageSearchResult> $candidates */
    public function rank(array $candidates, string $query, array $options = []): array
    {
        $orientation = Str::lower((string) ($options['orientation'] ?? 'landscape'));
        $tokens = $this->tokens($query);
        $seen = [];

        return collect($candidates)
            ->filter(fn ($item) => $item instanceof ImageSearchResult)
            ->map(function (ImageSearchResult $result, int $index) use ($tokens, $orientation, &$seen) {
                $identity = $result->provider . ':' . ($result->meta['id'] ?? sha1($result->url));
                $duplicate = isset($seen[$identity]);
                $seen[$identity] = true;

                $metadata = Str::lower(trim(implode(' ', array_filter([
                    $result->description,
                    $result->photographer,
                    $result->sourceUrl,
                    (string) ($result->meta['tags'] ?? ''),
                    (string) ($result->meta['slug'] ?? ''),
                ]))));

                $matched = $tokens->filter(fn (string $token) => str_contains($metadata, $token))->count();
                $score = $matched * 4;
                $reasons = ['keyword_matches' => $matched];

                // Hard industry asset guard: reject obvious unrelated imagery.
                $hardBlocked = [
                    'construction', 'crane', 'hard hat', 'building site', 'excavator',
                    'road work', 'real estate development'
                ];
                foreach ($hardBlocked as $term) {
                    if (str_contains($metadata, $term) && ! str_contains($tokens->implode(' '), $term)) {
                        $score -= 100;
                        $reasons['hard_blocked_term'] = $term;
                        break;
                    }
                }

                if ($result->width > 0 && $result->height > 0) {
                    $ratio = $result->width / max(1, $result->height);
                    $orientationMatch = match ($orientation) {
                        'portrait' => $ratio < 0.9,
                        'square' => $ratio >= 0.85 && $ratio <= 1.18,
                        default => $ratio >= 1.2,
                    };
                    $score += $orientationMatch ? 3 : -5;
                    $reasons['orientation'] = $orientationMatch ? 'match' : 'mismatch';

                    $pixels = $result->width * $result->height;
                    if ($pixels >= 2_000_000) {
                        $score += 3;
                        $reasons['resolution'] = 'high';
                    } elseif ($pixels >= 900_000) {
                        $score += 1;
                        $reasons['resolution'] = 'medium';
                    } else {
                        $score -= 2;
                        $reasons['resolution'] = 'low';
                    }
                }

                foreach ($this->blockedTerms() as $term) {
                    if (str_contains($metadata, $term)) {
                        $score -= 20;
                        $reasons['blocked_term'] = $term;
                        break;
                    }
                }

                foreach ($this->mismatchTerms($tokens->all()) as $term) {
                    if (str_contains($metadata, $term)) {
                        $score -= 8;
                        $reasons['mismatch_term'] = $term;
                        break;
                    }
                }

                if ($duplicate) {
                    $score -= 25;
                    $reasons['duplicate'] = true;
                }

                // Stable tie-break: earlier provider results win only when quality is equal.
                $score -= min($index, 20) * 0.01;

                return ['result' => $result, 'score' => $score, 'reasons' => $reasons];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /** @param array<int, ImageSearchResult> $candidates */
    public function best(array $candidates, string $query, array $options = []): ?ImageSearchResult
    {
        $ranked = $this->rank($candidates, $query, $options);
        $best = $ranked[0] ?? null;
        $minimum = (float) config('services.smart_images.min_score', 2);

        if (! is_array($best) || ($best['score'] ?? -INF) < $minimum) {
            logger()->info('[SmartImageRanking] No candidate reached minimum score.', [
                'query' => $query,
                'minimum_score' => $minimum,
                'best_score' => $best['score'] ?? null,
                'candidate_count' => count($candidates),
            ]);
            return null;
        }

        /** @var ImageSearchResult $result */
        $result = $best['result'];
        logger()->info('[SmartImageRanking] Candidate selected.', [
            'provider' => $result->provider,
            'query' => $query,
            'score' => round((float) $best['score'], 2),
            'reasons' => $best['reasons'],
            'candidate_count' => count($candidates),
        ]);

        return $result;
    }

    private function tokens(string $query)
    {
        $stop = ['wide','cinematic','exterior','interior','lifestyle','professional','modern','premium','exclusive','based','company','website','photo','image','scene','people'];

        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($query)) ?: [])
            ->filter(fn ($token) => strlen($token) >= 3 && ! in_array($token, $stop, true))
            ->unique()
            ->values();
    }

    private function blockedTerms(): array
    {
        return ['logo','watermark','screenshot','mockup','template','website builder','brand identity','app interface','ui design','adobe','apple','canva','figma','google','microsoft','shopify','squarespace','webflow','wix','wordpress'];
    }

    /** @param array<int, string> $tokens */
    private function mismatchTerms(array $tokens): array
    {
        $query = implode(' ', $tokens);

        if (str_contains($query, 'yacht') || str_contains($query, 'cruise') || str_contains($query, 'sailing')) {
            return ['office','laptop','computer','meeting room','workspace','software'];
        }
        if (str_contains($query, 'restaurant') || str_contains($query, 'sushi') || str_contains($query, 'coffee')) {
            return ['office','construction','server room','software'];
        }
        if (str_contains($query, 'dentist') || str_contains($query, 'medical') || str_contains($query, 'clinic')) {
            return ['restaurant','construction','nightclub','factory'];
        }
        if (str_contains($query, 'automotive') || str_contains($query, 'vehicle') || str_contains($query, 'mechanic') || str_contains($query, 'car service')) {
            return ['mountain','snow','ski','resort','landscape','forest','beach','ocean','office','workspace','restaurant','construction site'];
        }

        return [];
    }
}
