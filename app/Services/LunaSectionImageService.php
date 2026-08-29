<?php

namespace App\Services;

use App\AI\Images\Providers\UnsplashProvider;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves image assets for Luna's selected-section popup.
 *
 * Generic image insertion requests use contextual Unsplash photography.
 * Explicit generation/custom-art requests use the configured OpenAI image model.
 * The service only returns/stores an asset; credit charging stays with the caller.
 */
final class LunaSectionImageService
{
    public function __construct(
        private readonly UnsplashProvider $unsplash,
        private readonly MediaLibraryRegistry $media,
    ) {}

    public function isInsertionIntent(string $prompt): bool
    {
        $p = Str::lower(trim($prompt));
        if ($p === '') return false;

        $hasImage = (bool) preg_match('/\b(?:image|photo|picture|photograph|visual)\b/i', $p);
        $hasInsert = (bool) preg_match('/\b(?:add|insert|put|place|include|show|attach)\b/i', $p);
        $hasPlacement = (bool) preg_match('/\b(?:left|right|after|before|below|under|above|over|beside|next\s+to|inside|between)\b/i', $p);
        $hasComposition = (bool) preg_match('/\b(?:with|featuring|using)\s+(?:an?\s+)?(?:image|photo|picture|visual)\b/i', $p);

        return $hasImage && ($hasInsert || $hasPlacement || $hasComposition);
    }

    public function mode(string $prompt): string
    {
        $p = Str::lower(trim($prompt));

        // "add an image" remains stock-photo behavior. Generation requires a
        // clearly explicit synthetic/custom-art instruction or a described scene.
        $generate = (bool) preg_match(
            '/\b(?:generate|ai[- ]generated|illustrate|illustration|render|draw|custom\s+(?:image|photo|art|illustration)|create\s+(?:a|an|the)?\s*(?:custom\s+)?(?:image|illustration|render)\s+(?:of|showing|depicting|with))\b/i',
            $p,
        );

        return $generate ? 'generate' : 'unsplash';
    }

    /** @return array{ok:bool,mode:string,url?:string,query?:string,error?:string,metadata?:array} */
    public function resolve(Website $website, ?User $user, string $prompt, array $block): array
    {
        $mode = $this->mode($prompt);
        $query = $this->contextQuery($prompt, $block, $website);

        if ($mode === 'generate') {
            return $this->generate($website, $user, $prompt, $query, $block);
        }

        return $this->stock($website, $user, $query, $block);
    }

    public function hasNewImageSlot(array $before, array $after): bool
    {
        $known = $this->imageNodeIds($before);
        $afterIds = $this->imageNodeIds($after);
        foreach ($afterIds as $id => $_) {
            if (! isset($known[$id])) return true;
        }

        return trim((string) ($before['image_url'] ?? '')) === ''
            && array_key_exists('image_url', $after)
            && trim((string) ($after['image_url'] ?? '')) === '';
    }

    public function newImageNeedsSource(array $before, array $after): bool
    {
        $known = $this->imageNodeIds($before);

        foreach ((array) ($after['field_extras'] ?? []) as $target => $slots) {
            if (! is_array($slots)) continue;
            foreach (['before','after'] as $placement) {
                foreach ((array) ($slots[$placement] ?? []) as $index => $item) {
                    if (! is_array($item) || Str::lower((string) ($item['type'] ?? '')) !== 'image') continue;
                    $id = trim((string) ($item['id'] ?? "field:{$target}:{$placement}:{$index}"));
                    if (! isset($known[$id]) && trim((string) data_get($item, 'data.src', '')) === '') return true;
                }
            }
        }

        $found = false;
        $walk = function (array $nodes, string $path = 'elements') use (&$walk, &$found, $known): void {
            foreach ($nodes as $index => $node) {
                if ($found || ! is_array($node)) continue;
                $nodePath = $path.'.'.$index;
                if (Str::lower((string) ($node['type'] ?? '')) === 'image') {
                    $id = trim((string) ($node['_cosmic_id'] ?? $node['id'] ?? $nodePath));
                    if (! isset($known[$id]) && trim((string) ($node['src'] ?? '')) === '') { $found = true; return; }
                }
                if (is_array($node['children'] ?? null)) $walk($node['children'], $nodePath.'.children');
            }
        };
        if (is_array($after['elements'] ?? null)) $walk($after['elements']);
        if ($found) return true;

        return trim((string) ($before['image_url'] ?? '')) === ''
            && array_key_exists('image_url', $after)
            && trim((string) ($after['image_url'] ?? '')) === '';
    }

    /**
     * Fill only image nodes that were newly created by the structural edit.
     * Existing user-selected images and existing empty placeholders are untouched.
     */
    public function hydrateNewImages(array $before, array $after, string $url, string $alt = ''): array
    {
        $known = $this->imageNodeIds($before);
        $changed = 0;

        $extras = is_array($after['field_extras'] ?? null) ? $after['field_extras'] : [];
        foreach ($extras as $target => &$slots) {
            if (! is_array($slots)) continue;
            foreach (['before', 'after'] as $placement) {
                if (! is_array($slots[$placement] ?? null)) continue;
                foreach ($slots[$placement] as $index => &$item) {
                    if (! is_array($item) || Str::lower((string) ($item['type'] ?? '')) !== 'image') continue;
                    $id = trim((string) ($item['id'] ?? "field:{$target}:{$placement}:{$index}"));
                    if (isset($known[$id])) continue;
                    $item['data'] = is_array($item['data'] ?? null) ? $item['data'] : [];
                    if (trim((string) ($item['data']['src'] ?? '')) !== '') continue;
                    $item['data']['src'] = $url;
                    if (trim((string) ($item['data']['alt'] ?? '')) === '') $item['data']['alt'] = $alt;
                    $changed++;
                }
                unset($item);
            }
        }
        unset($slots);
        $after['field_extras'] = $extras;

        if (is_array($after['elements'] ?? null)) {
            $walk = function (array &$nodes, string $path = 'elements') use (&$walk, $known, $url, $alt, &$changed): void {
                foreach ($nodes as $index => &$node) {
                    if (! is_array($node)) continue;
                    $nodePath = $path.'.'.$index;
                    if (Str::lower((string) ($node['type'] ?? '')) === 'image') {
                        $id = trim((string) ($node['_cosmic_id'] ?? $node['id'] ?? $nodePath));
                        if (! isset($known[$id]) && trim((string) ($node['src'] ?? '')) === '') {
                            $node['src'] = $url;
                            if (trim((string) ($node['alt'] ?? '')) === '') $node['alt'] = $alt;
                            $changed++;
                        }
                    }
                    if (is_array($node['children'] ?? null)) $walk($node['children'], $nodePath.'.children');
                }
                unset($node);
            };
            $walk($after['elements']);
        }

        // Legacy/AI Flex top-level image_url may be introduced by a schema edit.
        $beforeTop = trim((string) ($before['image_url'] ?? ''));
        $afterTop = trim((string) ($after['image_url'] ?? ''));
        if ($beforeTop === '' && array_key_exists('image_url', $after) && $afterTop === '') {
            $after['image_url'] = $url;
            $changed++;
        }

        return ['block' => $after, 'changed' => $changed > 0, 'hydrated_count' => $changed];
    }

    private function stock(Website $website, ?User $user, string $query, array $block): array
    {
        if (! $this->unsplash->isEnabled()) {
            return ['ok'=>false, 'mode'=>'unsplash', 'query'=>$query, 'error'=>'Unsplash is not configured.'];
        }

        try {
            $industry = trim((string) ($website->industry ?: ''));
            $queries = array_values(array_unique(array_filter([
                trim($query),
                $industry !== '' ? $industry.' premium editorial business photography' : null,
                'modern professional office workspace team editorial photography',
            ])));
            $result = null;
            $resolvedQuery = $query;
            foreach ($queries as $candidateQuery) {
                $result = $this->unsplash->search($candidateQuery, ['orientation' => 'landscape']);
                if ($result) {
                    $resolvedQuery = $candidateQuery;
                    break;
                }
            }
            if (! $result) return ['ok'=>false, 'mode'=>'unsplash', 'query'=>$query, 'error'=>'No relevant Unsplash image was found.'];

            $url = $this->withDownloadParameters($result->url, $result->downloadParameters);
            $asset = $this->media->importRemoteImage(
                $website,
                $url,
                'unsplash',
                (string) ($block['type'] ?? 'builder-section'),
                $user?->id,
                [
                    'query' => $resolvedQuery,
                    'source_url' => $url,
                    'photographer' => $result->photographer,
                    'source_page' => $result->sourceUrl,
                    'unsplash_id' => $result->meta['id'] ?? null,
                ],
            );
            if (! $asset) return ['ok'=>false, 'mode'=>'unsplash', 'query'=>$query, 'error'=>'The Unsplash image could not be saved to the Media Library.'];

            $this->unsplash->trackDownload($result);
            return [
                'ok'=>true,
                'mode'=>'unsplash',
                'query'=>$resolvedQuery,
                'url'=>$this->media->url($asset),
                'metadata'=>['photographer'=>$result->photographer, 'source_page'=>$result->sourceUrl],
            ];
        } catch (Throwable $e) {
            report($e);
            return ['ok'=>false, 'mode'=>'unsplash', 'query'=>$query, 'error'=>'Unsplash is temporarily unavailable.'];
        }
    }

    private function generate(Website $website, ?User $user, string $prompt, string $query, array $block): array
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') return ['ok'=>false, 'mode'=>'generate', 'query'=>$query, 'error'=>'Luna image generation is not configured.'];

        $business = trim((string) ($website->name ?: 'this website'));
        $industry = trim((string) ($website->industry ?: 'business'));
        $blockType = trim((string) ($block['type'] ?? 'website section'));
        $generationPrompt = implode("\n", array_filter([
            'Create a premium website image for '.$business.'.',
            'Industry/context: '.$industry.'.',
            'Website section type: '.$blockType.'.',
            'Section context: '.$query.'.',
            'User request: '.trim($prompt),
            'Composition: landscape editorial image designed to crop cleanly in a responsive website section.',
            'Use realistic premium commercial art direction, clear subject separation, and useful negative space.',
            'Do not add text, typography, logos, watermarks, UI labels, borders, or mock captions unless explicitly requested.',
        ]));

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('openai.request_timeout', 180))
                ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/generations', [
                    'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
                    'prompt' => $generationPrompt,
                    'size' => '1536x1024',
                    'quality' => env('OPENAI_IMAGE_QUALITY', 'low'),
                    'n' => 1,
                ]);
            if ($response->failed()) {
                logger()->warning('[LunaPopupImage] Image generation failed.', ['status'=>$response->status(), 'body'=>Str::limit($response->body(), 500)]);
                return ['ok'=>false, 'mode'=>'generate', 'query'=>$query, 'error'=>'Luna could not generate the requested image.'];
            }

            $encoded = data_get($response->json(), 'data.0.b64_json');
            $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
            if ($bytes === false || strlen($bytes) < 100) return ['ok'=>false, 'mode'=>'generate', 'query'=>$query, 'error'=>'Luna returned an invalid generated image.'];

            $filename = 'luna-section-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.png';
            $path = "websites/{$website->id}/ai-images/{$filename}";
            if (! Storage::disk('public')->put($path, $bytes)) return ['ok'=>false, 'mode'=>'generate', 'query'=>$query, 'error'=>'The generated image could not be saved.'];

            $asset = $this->media->registerStoredPath(
                $website,
                $path,
                'ai',
                $blockType,
                $user?->id,
                $filename,
                ['prompt'=>Str::limit(trim($prompt), 500, ''), 'section_query'=>$query, 'size'=>'1536x1024'],
            );

            return [
                'ok'=>true,
                'mode'=>'generate',
                'query'=>$query,
                'url'=>$asset ? $this->media->url($asset) : '/storage/'.$path,
                'metadata'=>['size'=>'1536x1024'],
            ];
        } catch (Throwable $e) {
            report($e);
            return ['ok'=>false, 'mode'=>'generate', 'query'=>$query, 'error'=>'Luna image generation is temporarily unavailable.'];
        }
    }

    private function contextQuery(string $prompt, array $block, Website $website): string
    {
        $parts = [];
        foreach (['heading','heading_accent_text','eyebrow','title','text','description','semantic_type','category'] as $key) {
            $value = trim(strip_tags((string) ($block[$key] ?? '')));
            if ($value !== '') $parts[] = $value;
        }
        $parts[] = trim((string) ($website->industry ?: ''));
        $parts[] = trim((string) ($website->name ?: ''));

        $cleanPrompt = preg_replace('/\b(?:add|insert|put|place|include|show|attach|image|photo|picture|photograph|visual|after|before|below|under|above|over|the|this|that|title|heading|section|here|please)\b/i', ' ', $prompt) ?? $prompt;
        $cleanPrompt = preg_replace('/\s+/', ' ', trim($cleanPrompt)) ?? trim($cleanPrompt);
        if ($cleanPrompt !== '') array_unshift($parts, $cleanPrompt);

        $query = trim(preg_replace('/\s+/', ' ', implode(' ', array_slice(array_filter($parts), 0, 6))) ?? '');
        return Str::limit($query !== '' ? $query : 'premium business editorial photography', 220, '');
    }

    private function withDownloadParameters(string $url, array $parameters): string
    {
        if ($parameters === []) return $url;
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url.$separator.http_build_query($parameters);
    }

    /** @return array<string,true> */
    private function imageNodeIds(array $block): array
    {
        $ids = [];
        foreach ((array) ($block['field_extras'] ?? []) as $target => $slots) {
            if (! is_array($slots)) continue;
            foreach (['before','after'] as $placement) {
                foreach ((array) ($slots[$placement] ?? []) as $index => $item) {
                    if (! is_array($item) || Str::lower((string) ($item['type'] ?? '')) !== 'image') continue;
                    $id = trim((string) ($item['id'] ?? "field:{$target}:{$placement}:{$index}"));
                    $ids[$id] = true;
                }
            }
        }

        $walk = function (array $nodes, string $path = 'elements') use (&$walk, &$ids): void {
            foreach ($nodes as $index => $node) {
                if (! is_array($node)) continue;
                $nodePath = $path.'.'.$index;
                if (Str::lower((string) ($node['type'] ?? '')) === 'image') {
                    $id = trim((string) ($node['_cosmic_id'] ?? $node['id'] ?? $nodePath));
                    $ids[$id] = true;
                }
                if (is_array($node['children'] ?? null)) $walk($node['children'], $nodePath.'.children');
            }
        };
        if (is_array($block['elements'] ?? null)) $walk($block['elements']);

        return $ids;
    }
}
