<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GlobalMegaFooterService
{
    public function __construct(private readonly LunaAiFlexSparkService $aiFlex)
    {
    }

    /**
     * Compose one export-safe global Mega Footer. AI Flex supplies the wording
     * and grouping, while this service owns the strict navigation contract.
     *
     * @param  array<string, mixed>  $siteContext
     * @param  array<int, array<string, mixed>>  $menu
     * @param  array<string, mixed>  $currentFooter
     * @return array<string, mixed>
     */
    public function compose(string $prompt, array $siteContext, array $menu, array $currentFooter = []): array
    {
        $navigation = $this->navigation($menu);
        $raw = [];

        try {
            $raw = $this->aiFlex->generateMegaFooter(
                $prompt,
                $siteContext,
                $navigation,
                is_array($siteContext['theme'] ?? null) ? $siteContext['theme'] : [],
            );
        } catch (Throwable $exception) {
            Log::warning('[GlobalMegaFooter] AI Flex unavailable; using prompt-safe navigation fallback.', [
                'error' => $exception->getMessage(),
                'navigation_count' => count($navigation),
            ]);
        }

        $columns = $this->columns((array) ($raw['columns'] ?? []), $navigation);
        $contact = collect($navigation)->first(fn (array $item): bool => Str::contains(
            Str::lower($item['label'].' '.$item['url']),
            ['contact', 'booking', 'reservation', 'enquir', 'inquir'],
        ));
        $cta = $this->matchedNavigation((string) ($raw['primary_url'] ?? ''), '', $navigation)
            ?? $contact
            ?? end($navigation)
            ?: ['label' => 'Home', 'url' => 'home'];
        $businessName = trim((string) ($siteContext['business_name'] ?? $currentFooter['logo_text'] ?? 'Your Logo'));
        $fallbackTagline = trim((string) ($siteContext['business_description'] ?? $prompt));
        $tagline = $this->plain((string) ($raw['tagline'] ?? $fallbackTagline), 220);
        $primaryLabel = $this->plain((string) ($raw['primary_label'] ?? 'Visit '.$cta['label']), 56);
        $metadata = is_array($raw['ai_flex'] ?? null) ? $raw['ai_flex'] : [
            'version' => 1,
            'source' => 'deterministic_fallback',
            'intent' => 'prompt-aware global mega footer',
        ];

        return [
            ...$currentFooter,
            'type' => 'minimal_footer',
            'theme' => (string) ($currentFooter['theme'] ?? 'white'),
            'logo_text' => $businessName !== '' ? $businessName : 'Your Logo',
            'copyright' => (string) ($currentFooter['copyright'] ?? ('© '.now()->year.' '.($businessName ?: 'Your business').'. All rights reserved.')),
            'privacy_label' => (string) ($currentFooter['privacy_label'] ?? 'Privacy Policy'),
            'privacy_url' => (string) ($currentFooter['privacy_url'] ?? '/privacy-policy'),
            'terms_label' => (string) ($currentFooter['terms_label'] ?? 'Terms & Conditions'),
            'terms_url' => (string) ($currentFooter['terms_url'] ?? '/terms-and-conditions'),
            'mega_enabled' => true,
            'mega_footer' => [
                'enabled' => true,
                'variant' => 'classic',
                'theme' => 'white',
                'tagline' => $tagline !== '' ? $tagline : 'Explore the website and find the right next step.',
                'primary_label' => $primaryLabel !== '' ? $primaryLabel : 'Get in touch',
                'primary_url' => $cta['url'],
                'columns' => $columns,
                'ai_flex' => $metadata,
            ],
            'generated_by' => 'ai_flex_mega_footer',
        ];
    }

    /** @return array<int, array{label:string,url:string}> */
    private function navigation(array $menu): array
    {
        $seen = [];
        $items = [];
        foreach ($menu as $item) {
            if (! is_array($item)) continue;
            $label = $this->plain((string) ($item['label'] ?? $item['title'] ?? ''), 64);
            $url = trim((string) ($item['url'] ?? $item['slug'] ?? ''));
            if ($label === '' || $url === '') continue;
            $key = $this->navigationKey($url, $label);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $items[] = ['label' => $label, 'url' => $url];
            if (count($items) >= 12) break;
        }

        return $items ?: [['label' => 'Home', 'url' => 'home']];
    }

    /** @return array<int, array{title:string,items:array<int,array{label:string,url:string}>}> */
    private function columns(array $rawColumns, array $navigation): array
    {
        $columns = [];
        $used = [];
        foreach (array_slice($rawColumns, 0, 4) as $rawColumn) {
            if (! is_array($rawColumn)) continue;
            $items = [];
            foreach (array_slice((array) ($rawColumn['items'] ?? []), 0, 12) as $rawItem) {
                if (! is_array($rawItem)) continue;
                $match = $this->matchedNavigation(
                    (string) ($rawItem['url'] ?? ''),
                    (string) ($rawItem['label'] ?? ''),
                    $navigation,
                );
                if (! $match) continue;
                $key = $this->navigationKey($match['url'], $match['label']);
                if (isset($used[$key])) continue;
                $used[$key] = true;
                $items[] = $match;
            }
            if ($items === []) continue;
            $columns[] = [
                'title' => $this->plain((string) ($rawColumn['title'] ?? 'Explore'), 48) ?: 'Explore',
                'items' => $items,
            ];
        }

        foreach ($navigation as $item) {
            $key = $this->navigationKey($item['url'], $item['label']);
            if (isset($used[$key])) continue;
            $group = $this->groupTitle($item);
            $columnIndex = collect($columns)->search(fn (array $column): bool => Str::lower($column['title']) === Str::lower($group));
            if ($columnIndex === false) {
                if (count($columns) < 4) {
                    $columns[] = ['title' => $group, 'items' => []];
                    $columnIndex = array_key_last($columns);
                } else {
                    $columnIndex = count($columns) - 1;
                }
            }
            $columns[$columnIndex]['items'][] = $item;
            $used[$key] = true;
        }

        return array_values(array_filter($columns, fn (array $column): bool => $column['items'] !== []));
    }

    /** @return array{label:string,url:string}|null */
    private function matchedNavigation(string $url, string $label, array $navigation): ?array
    {
        $urlKey = $this->navigationKey($url, '');
        $labelKey = Str::lower(trim($label));
        foreach ($navigation as $item) {
            if ($urlKey !== '' && $urlKey === $this->navigationKey($item['url'], '')) return $item;
            if ($labelKey !== '' && $labelKey === Str::lower(trim($item['label']))) return $item;
        }
        return null;
    }

    /** @param array{label:string,url:string} $item */
    private function groupTitle(array $item): string
    {
        $value = Str::lower($item['label'].' '.$item['url']);
        return match (true) {
            Str::contains($value, ['service', 'menu', 'product', 'solution', 'program', 'course', 'room', 'property', 'practice']) => 'Offerings',
            Str::contains($value, ['project', 'gallery', 'portfolio', 'work', 'case stud']) => 'Our Work',
            Str::contains($value, ['about', 'team', 'story', 'career']) => 'Company',
            Str::contains($value, ['faq', 'blog', 'resource', 'insight', 'update']) => 'Resources',
            Str::contains($value, ['contact', 'booking', 'reservation', 'enquir', 'inquir']) => 'Connect',
            default => 'Explore',
        };
    }

    private function navigationKey(string $url, string $label): string
    {
        $url = Str::lower(trim($url));
        $url = trim($url, " \t\n\r\0\x0B/#");
        return $url !== '' ? $url : Str::lower(trim($label));
    }

    private function plain(string $value, int $limit): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? ''), $limit, '');
    }
}
