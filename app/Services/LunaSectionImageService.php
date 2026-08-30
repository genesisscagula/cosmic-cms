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
        private readonly ImageSlotResolver $imageSlots,
        private readonly SmartImageService $smartImages,
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

    public function isReplacementIntent(string $prompt): bool
    {
        $p = Str::lower(trim($prompt));
        if ($p === '') return false;

        $hasVisual = (bool) preg_match('/\b(?:image|photo|picture|photograph|visual|background)\b/i', $p);
        $hasReplacement = (bool) preg_match('/\b(?:change|replace|swap|update|use|set|make)\b/i', $p);

        return $hasVisual && $hasReplacement;
    }

    /** @return array{block:array,changed:bool,path?:string,role?:string} */
    public function replacePrimaryImage(array $block, string $url): array
    {
        $url = trim($url);
        if ($url === '') return ['block' => $block, 'changed' => false];

        $slots = $this->imageSlots->slots($block);
        usort($slots, static function (array $left, array $right): int {
            $score = static function (array $slot): int {
                $path = Str::lower((string) ($slot['path'] ?? ''));
                $role = Str::lower((string) ($slot['role'] ?? ''));
                if ($path === 'universal_background_image_url') return 0;
                if ($path === 'image_url') return 1;
                if ($role === 'background') return 2;
                if ($role === 'hero') return 3;
                return 10 + substr_count($path, '.');
            };
            return $score($left) <=> $score($right);
        });

        foreach ($slots as $slot) {
            $path = trim((string) ($slot['path'] ?? ''));
            if ($path === '') continue;
            $before = trim((string) data_get($block, $path, ''));
            if ($before === $url) continue;
            data_set($block, $path, $url);
            return [
                'block' => $block,
                'changed' => true,
                'path' => $path,
                'role' => (string) ($slot['role'] ?? 'general'),
            ];
        }

        return ['block' => $block, 'changed' => false];
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

    /**
     * Hydrate every blank/generic image slot in a newly selected premade Spark.
     *
     * Start Blank with Luna is Spark-first now, so the generated registered Spark
     * can legitimately contain nested image grids/cards/slides whose image fields
     * are empty. This pass resolves those slots with contextual Unsplash photos
     * before the popup preview is returned. Existing real/user-selected images are
     * never replaced. When Unsplash cannot resolve a slot, a local industry/default
     * fallback is assigned so the preview never ships with a blank media hole.
     *
     * @return array{block:array,changed:bool,hydrated_count:int,fallback_count:int,failed_count:int,slots:array}
     */
    public function hydrateMissingImages(
        Website $website,
        ?User $user,
        string $prompt,
        array $block,
        int $maxSlots = 16,
    ): array {
        $slots = array_values(array_filter(
            $this->imageSlots->slots($block),
            fn (array $slot): bool => $this->shouldAutoHydrateValue($slot['value'] ?? null),
        ));

        if ($slots === []) {
            return [
                'block' => $block,
                'changed' => false,
                'hydrated_count' => 0,
                'fallback_count' => 0,
                'failed_count' => 0,
                'slots' => [],
            ];
        }

        $slots = array_slice($slots, 0, max(1, $maxSlots));
        $hydrated = 0;
        $fallbacks = 0;
        $failed = 0;
        $slotLog = [];
        $usedUrls = [];
        $industry = Str::slug((string) ($website->industry ?: 'default')) ?: 'default';

        foreach ($slots as $index => $slot) {
            $path = (string) ($slot['path'] ?? '');
            if ($path === '') continue;

            $query = $this->slotContextQuery($prompt, $block, $website, $slot, $index);
            $resolved = $this->stock($website, $user, $query, $block);
            $url = trim((string) ($resolved['url'] ?? ''));
            $source = 'unsplash';

            // Different image-grid slots should not silently collapse to the same
            // photograph. Give a duplicate result one more context variation.
            if ($url !== '' && isset($usedUrls[$url])) {
                $retryQuery = $this->slotContextQuery($prompt, $block, $website, $slot, $index + count($slots) + 3);
                $retry = $this->stock($website, $user, $retryQuery, $block);
                $retryUrl = trim((string) ($retry['url'] ?? ''));
                if ($retryUrl !== '' && ! isset($usedUrls[$retryUrl])) {
                    $resolved = $retry;
                    $url = $retryUrl;
                    $query = $retryQuery;
                }
            }

            if ($url === '') {
                $url = trim($this->smartImages->localFallback($industry));
                $source = 'fallback';
                $fallbacks++;
            }

            if ($url === '') {
                $failed++;
                $slotLog[] = ['path' => $path, 'role' => $slot['role'] ?? 'general', 'status' => 'failed'];
                continue;
            }

            data_set($block, $path, $url);
            $usedUrls[$url] = true;
            $hydrated++;
            $slotLog[] = [
                'path' => $path,
                'role' => $slot['role'] ?? 'general',
                'status' => $source,
                'query' => $query,
                'url' => $url,
            ];
        }

        return [
            'block' => $block,
            'changed' => $hydrated > 0,
            'hydrated_count' => $hydrated,
            'fallback_count' => $fallbacks,
            'failed_count' => $failed,
            'slots' => $slotLog,
        ];
    }

    private function shouldAutoHydrateValue(mixed $value): bool
    {
        if (! is_string($value)) return false;

        $url = trim($value);
        if ($url === '') return true;

        $normalized = Str::lower($url);
        return str_contains($normalized, '/cms-images/default/')
            || str_contains($normalized, '/cms-images/background/')
            || str_contains($normalized, '/cosmic-images/cosmic-fallback.svg');
    }

    /** @param array{path?:string,key?:string,role?:string,value?:mixed} $slot */
    private function slotContextQuery(string $prompt, array $block, Website $website, array $slot, int $index): string
    {
        $role = trim((string) ($slot['role'] ?? 'general')) ?: 'general';
        $path = trim((string) ($slot['path'] ?? ''));
        $parentPath = str_contains($path, '.') ? Str::beforeLast($path, '.') : '';
        $parent = $parentPath !== '' ? data_get($block, $parentPath) : $block;
        $parts = [];

        if (is_array($parent)) {
            foreach (['eyebrow','heading','title','name','label','text','description','service','category'] as $key) {
                $value = trim(strip_tags((string) ($parent[$key] ?? '')));
                if ($value !== '') $parts[] = $value;
            }
        }

        foreach (['eyebrow','heading','title','description','semantic_type','category'] as $key) {
            $value = trim(strip_tags((string) ($block[$key] ?? '')));
            if ($value !== '') $parts[] = $value;
        }

        $cleanPrompt = preg_replace('/\b(?:add|insert|put|place|include|show|attach|image|images|photo|photos|picture|pictures|visual|section|please)\b/i', ' ', $prompt) ?? $prompt;
        $cleanPrompt = preg_replace('/\s+/', ' ', trim($cleanPrompt)) ?? trim($cleanPrompt);
        $cleanPrompt = preg_replace('/\byatch\b/i', 'yacht', $cleanPrompt) ?? $cleanPrompt;
        if ($cleanPrompt !== '') array_unshift($parts, $cleanPrompt);

        $industry = trim((string) ($website->industry ?: ''));
        if ($industry !== '') $parts[] = $industry;

        $rolePhrase = match ($role) {
            'hero' => 'wide cinematic website hero photography',
            'services' => 'professional service work editorial photography',
            'people' => 'authentic professional people portrait photography',
            'gallery' => 'premium project portfolio editorial photography',
            'background' => 'wide atmospheric website background photography',
            default => 'premium commercial editorial photography',
        };
        $parts[] = $rolePhrase;

        $variants = ['craftsmanship','workspace','project detail','team at work','materials','finished result','environment','process','architecture','close-up detail','professional scene','editorial detail'];
        $parts[] = $variants[$index % count($variants)];

        $query = preg_replace('/\s+/', ' ', implode(' ', array_slice(array_values(array_unique(array_filter($parts))), 0, 7))) ?? '';
        return Str::limit(trim($query) ?: 'premium business editorial photography', 220, '');
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
        $cleanPrompt = preg_replace('/\byatch\b/i', 'yacht', $cleanPrompt) ?? $cleanPrompt;
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
