<?php

namespace App\Services;

use App\Models\Website;
use App\Support\HeaderFooterVariantContract;
use Illuminate\Support\Str;

/**
 * Bounded, zero-credit mutations for the existing global header/footer shell.
 * The Builder remains authoritative for persistence; this service only returns
 * validated shell snapshots using fields already consumed by Builder + exporter.
 */
final class LunaHeaderFooterActionService
{
    public function apply(?Website $website, string $prompt, array $intent, array $header = [], array $footer = [], array $blocks = []): ?array
    {
        if (($intent['intent'] ?? '') !== 'action') return null;

        $scope = (string) data_get($intent, 'routing.menu_scope', '');
        $action = (string) data_get($intent, 'routing.scope_action', '');
        if (! in_array($scope, ['header', 'footer'], true)) return null;

        return $scope === 'header'
            ? $this->applyHeader($website, $prompt, $action, $header, $blocks)
            : $this->applyFooter($website, $prompt, $action, $footer, $blocks);
    }

    /**
     * Trial Builder uses the same deterministic shell executor as the signed-in
     * Builder. Trial state is supplied in the request, so no persisted Website
     * model is required for the mutation to be resolved safely.
     */
    public function applyTrial(string $prompt, array $intent, array $header = [], array $footer = [], array $blocks = []): ?array
    {
        return $this->apply(null, $prompt, $intent, $header, $footer, $blocks);
    }

    /**
     * Strong natural-language variant vocabulary used to keep both routers on
     * the same seven-card Header/Footer contract.
     *
     * @return array{scope:string,action:string,variant:string,name:string}|null
     */
    public function explicitVariantIntent(string $prompt): ?array
    {
        if (($variant = HeaderFooterVariantContract::detectHeaderVariant($prompt)) !== null) {
            return [
                'scope' => 'header',
                'action' => 'change_header',
                'variant' => $variant,
                'name' => HeaderFooterVariantContract::headerVariantName($variant),
            ];
        }
        if (($variant = HeaderFooterVariantContract::detectFooterVariant($prompt)) !== null) {
            return [
                'scope' => 'footer',
                'action' => 'change_footer',
                'variant' => $variant,
                'name' => HeaderFooterVariantContract::footerVariantName($variant),
            ];
        }
        return null;
    }

    /**
     * Keep a compact, renderer-safe memory of the current website shell. This
     * is deliberately bounded: no full menu/footer payloads are copied into
     * Luna memory, only the facts required to resolve follow-up shell commands.
     */
    public function rememberShellContext(
        array $siteMemory,
        array $header = [],
        array $footer = [],
        ?string $lastScope = null,
        ?string $source = null,
    ): array {
        $header = HeaderFooterVariantContract::normalizeHeader($header) ?? [];
        $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? [];
        $headerVariant = (string) ($header['type'] ?? 'classic_header');
        $footerVariant = (string) data_get($footer, 'mega_footer.variant', 'classic');
        $existing = is_array($siteMemory['shell_context'] ?? null) ? $siteMemory['shell_context'] : [];

        $context = array_merge($existing, [
            'header_variant' => $headerVariant,
            'header_name' => HeaderFooterVariantContract::headerVariantName($headerVariant),
            'header_has_cta' => $this->headerVariantHasCta($headerVariant),
            'header_overlay' => in_array($headerVariant, ['overlay_hero_header', 'overlay_centered_header'], true),
            'header_allow_light_logo_filter' => (bool) ($header['allow_light_logo_filter'] ?? true),
            'header_has_light_logo' => $this->hasLightLogo($header),
            'footer_variant' => $footerVariant,
            'footer_name' => HeaderFooterVariantContract::footerVariantName($footerVariant),
            'footer_has_cta' => $this->footerVariantHasCta($footerVariant),
            'footer_allow_light_logo_filter' => (bool) ($footer['allow_light_logo_filter'] ?? true),
            'footer_has_light_logo' => $this->hasLightLogo($footer),
            'contract_version' => 3,
        ]);
        if (in_array($lastScope, ['header', 'footer'], true)) {
            $context['last_scope'] = $lastScope;
            $context['last_variant'] = $lastScope === 'header' ? $headerVariant : $footerVariant;
            $context['last_action_at'] = now()->toIso8601String();
            if ($source !== null && $source !== '') $context['last_source'] = $source;
            $history = is_array($existing['history'] ?? null) ? array_values($existing['history']) : [];
            $history[] = [
                'scope' => $lastScope,
                'variant' => $context['last_variant'],
                'at' => $context['last_action_at'],
            ];
            $context['history'] = array_slice($history, -6);
        }

        $siteMemory['shell_context'] = $context;
        return $siteMemory;
    }

    /**
     * Resolve short follow-ups such as "center it", "make it primary" and
     * "same layout but no CTA" only when the shell referent is unambiguous.
     *
     * @return array{scope:string,action:string,variant:string,name:string,prompt:string,source:string}|null
     */
    public function resolveFollowUpIntent(
        string $prompt,
        array $siteMemory,
        array $header = [],
        array $footer = [],
        ?string $preferredScope = null,
    ): ?array {
        if (($explicit = $this->explicitVariantIntent($prompt)) !== null) {
            return $explicit + ['prompt' => $prompt, 'source' => 'explicit'];
        }

        $text = trim($prompt);
        if ($text === '') return null;
        $lower = Str::lower($text);
        $shellContext = is_array($siteMemory['shell_context'] ?? null) ? $siteMemory['shell_context'] : [];

        $scope = in_array($preferredScope, ['header', 'footer'], true) ? $preferredScope : null;
        if ($scope === null) {
            if (preg_match('/\bheader\b/i', $text)) $scope = 'header';
            elseif (preg_match('/\b(?:mega\s+)?footer\b/i', $text)) $scope = 'footer';
        }
        if ($scope === null) {
            $lastScope = (string) ($shellContext['last_scope'] ?? '');
            $lastAt = strtotime((string) ($shellContext['last_action_at'] ?? '')) ?: 0;
            // Pronoun-only shell follow-ups are intentionally short-lived so a
            // stale shell edit cannot hijack unrelated "center it" requests.
            if (in_array($lastScope, ['header', 'footer'], true) && $lastAt > 0 && (time() - $lastAt) <= 1800) {
                $scope = $lastScope;
            }
        }
        if ($scope === null) return null;

        // Only a narrow follow-up vocabulary is allowed to inherit a referent.
        $isRelative = (bool) preg_match('/^(?:please\s+)?(?:make\s+)?(?:it\s+)?(?:same(?:\s+layout)?\s+but\s+)?(?:center(?:ed)?|centre(?:d)?|primary|secondary|white|classic|default|overlay|transparent|split|editorial|brand|minimal|with\s+cta|add\s+(?:the\s+)?cta|without\s+cta|no\s+cta|remove\s+(?:the\s+)?cta)(?:\s+(?:it|this|that))?(?:\s+(?:please|instead|now))?[.!?]*$/i', $text)
            || (bool) preg_match('/\b(?:same(?:\s+layout)?\s+but\s+|keep\s+(?:the\s+)?(?:same\s+)?layout\s+but\s+)(?:without|no)\s+cta\b/i', $text)
            || (bool) preg_match('/\b(?:same(?:\s+layout)?\s+but\s+|keep\s+(?:the\s+)?(?:same\s+)?layout\s+but\s+)(?:with|add)\s+(?:the\s+)?cta\b/i', $text)
            || (bool) preg_match('/\b(?:make|change|switch)\s+(?:it|this|that)\s+(?:to\s+)?(?:center(?:ed)?|primary|secondary|white|classic|overlay|transparent|split|editorial|brand|minimal)\b/i', $text);
        if (! $isRelative && ! preg_match('/\b(?:header|footer)\b/i', $text)) return null;

        $header = HeaderFooterVariantContract::normalizeHeader($header) ?? [];
        $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? [];
        $currentHeader = (string) ($header['type'] ?? $shellContext['header_variant'] ?? 'classic_header');
        $currentFooter = (string) data_get($footer, 'mega_footer.variant', $shellContext['footer_variant'] ?? 'classic');
        $variant = null;

        $removeCta = (bool) preg_match('/\b(?:without|no|remove)\s+(?:the\s+)?cta\b|\bcta\s+(?:off|removed)\b/i', $text);
        $addCta = (bool) preg_match('/\b(?:with|add)\s+(?:the\s+)?cta\b|\bcta\s+on\b/i', $text);
        $center = (bool) preg_match('/\bcent(?:er|re)(?:ed)?\b/i', $text);
        $primary = (bool) preg_match('/\bprimary\b/i', $text);
        $secondary = (bool) preg_match('/\bsecondary\b/i', $text);
        $classic = (bool) preg_match('/\b(?:white|classic|default)\b/i', $text);
        $overlay = (bool) preg_match('/\b(?:overlay|transparent)\b/i', $text);
        $split = (bool) preg_match('/\b(?:split|editorial)\b/i', $text);
        $brand = (bool) preg_match('/\bbrand\b/i', $text);
        $minimal = (bool) preg_match('/\bminimal\b/i', $text);

        if ($scope === 'header') {
            if ($removeCta) $variant = in_array($currentHeader, ['overlay_hero_header', 'overlay_centered_header'], true) ? 'overlay_centered_header' : 'centered_header';
            elseif ($addCta) $variant = $currentHeader === 'overlay_centered_header' ? 'overlay_hero_header' : 'split_navigation_header';
            elseif ($overlay) $variant = in_array($currentHeader, ['centered_header', 'split_navigation_header', 'overlay_centered_header'], true) ? 'overlay_centered_header' : 'overlay_hero_header';
            elseif ($center) $variant = in_array($currentHeader, ['overlay_hero_header', 'overlay_centered_header'], true)
                ? 'overlay_centered_header'
                : ($this->headerVariantHasCta($currentHeader) ? 'split_navigation_header' : 'centered_header');
            elseif ($primary) $variant = 'primary_header';
            elseif ($secondary) $variant = 'secondary_header';
            elseif ($classic || $minimal) $variant = 'classic_header';
            elseif ($split) $variant = 'split_navigation_header';
        } else {
            if ($removeCta || $minimal) $variant = 'centered';
            elseif ($addCta) $variant = 'centered_cta';
            elseif ($center) $variant = $this->footerVariantHasCta($currentFooter) ? 'centered_cta' : 'centered';
            elseif ($primary) $variant = 'primary';
            elseif ($secondary) $variant = 'secondary';
            elseif ($classic) $variant = 'classic';
            elseif ($split) $variant = 'split';
            elseif ($brand) $variant = 'brand';
        }
        if ($variant === null) return null;

        $name = $scope === 'header'
            ? HeaderFooterVariantContract::headerVariantName($variant)
            : HeaderFooterVariantContract::footerVariantName($variant);
        return [
            'scope' => $scope,
            'action' => $scope === 'header' ? 'change_header' : 'change_footer',
            'variant' => $variant,
            'name' => $name,
            'prompt' => 'Use '.$name.' '.($scope === 'header' ? 'header' : 'footer').'.',
            'source' => 'shell_follow_up',
        ];
    }

    private function applyHeader(?Website $website, string $prompt, string $action, array $header, array $blocks = []): ?array
    {
        $websiteHeader = $website && is_array($website->global_header) ? $website->global_header : [];
        $header = HeaderFooterVariantContract::normalizeHeader($header !== [] ? $header : $websiteHeader) ?? [];
        $operations = [];
        $explicitVariant = HeaderFooterVariantContract::detectHeaderVariant($prompt);

        // A named card/variant always wins over the sub-action chosen by a
        // probabilistic router. This makes commands such as "use Center Logo +
        // CTA" deterministic in both Trial and authenticated Builders.
        if ($explicitVariant !== null) {
            if (in_array($explicitVariant, ['overlay_hero_header', 'overlay_centered_header'], true) && $blocks !== [] && ! $this->supportsOverlayHeader($blocks)) {
                return $this->failure('header', 'change_header', 'Overlay headers require a compatible hero or banner as the first section. Choose a non-overlay header or add a hero/banner first.');
            }
            if ($this->headerVariantNeedsLightLogo($explicitVariant) && ! $this->lightLogoTreatmentAvailable($header)) {
                return $this->failure('header', 'change_header', 'This header needs a light logo. Upload a Light Logo or allow automatic white logo filtering first.');
            }
            $header['type'] = $explicitVariant;
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $header['type']);
        }

        if (in_array($action, ['custom_header', 'mobile_header'], true)) return null;

        if ($action === 'overlay_header' || $action === 'transparent_header') {
            $enabled = ! preg_match('/\b(disable|off|remove|turn off|not transparent|non[- ]?transparent)\b/i', $prompt);
            if ($enabled && $blocks !== [] && ! $this->supportsOverlayHeader($blocks)) {
                return $this->failure('header', $action, 'Overlay headers require a compatible hero or banner as the first section.');
            }
            if ($enabled && ! $this->lightLogoTreatmentAvailable($header)) {
                return $this->failure('header', $action, 'This header needs a light logo. Upload a Light Logo or allow automatic white logo filtering first.');
            }
            $currentType = (string) ($header['type'] ?? 'classic_header');
            $nextType = $enabled
                ? (in_array($currentType, ['centered_header','overlay_centered_header'], true) ? 'overlay_centered_header' : 'overlay_hero_header')
                : ($currentType === 'overlay_centered_header' ? 'centered_header' : 'classic_header');
            $header['type'] = $nextType;
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $nextType);
        } elseif ($action === 'sticky_header') {
            $enabled = ! preg_match('/\b(disable|off|remove|turn off|not sticky)\b/i', $prompt);
            if (! $enabled) return null;
            $currentType = (string) ($header['type'] ?? 'classic_header');
            if ($currentType === 'overlay_centered_header') $header['type'] = 'centered_header';
            elseif ($currentType === 'overlay_hero_header') $header['type'] = 'classic_header';
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $header['type']);
        }

        if ($action === 'change_header' && $explicitVariant === null) {
            $type = null;
            if (preg_match('/\b(?:dark cyan|dark-cyan|cyan)\b/i', $prompt)) $type = 'dark_cyan_header';
            if (preg_match('/\b(?:glass|glassmorphism|glass morphism)\b/i', $prompt)) $type = 'glassmorphism_header';
            if (preg_match('/\b(?:classic|white)(?: header)?\b/i', $prompt)) $type = 'classic_header';
            if (preg_match('/\bprimary(?: contrast| color| background)?(?: header)?\b/i', $prompt)) $type = 'primary_header';
            if (preg_match('/\b(?:center(?:ed)? logo|split navigation|split nav)(?: with cta)?(?: header)?\b/i', $prompt)) $type = 'split_navigation_header';
            if (preg_match('/\b(?:center(?:ed)? logo|centered)(?: without cta| no cta)?(?: header)?\b/i', $prompt) && ! preg_match('/\bwith cta\b/i', $prompt)) $type = 'centered_header';
            if (preg_match('/\boverlay(?: hero)?(?: header)?\b/i', $prompt)) $type = 'overlay_hero_header';
            if (preg_match('/\boverlay\s+(?:center(?:ed)?|center logo)(?: header)?\b/i', $prompt)) $type = 'overlay_centered_header';
            if (preg_match('/\bsecondary(?: surface| background)?(?: header)?\b/i', $prompt)) $type = 'secondary_header';
            if (preg_match('/\b(?:floating(?: glass)?|minimal)(?: header)?\b/i', $prompt)) $type = 'classic_header';
            if ($type === null) return null;
            $header['type'] = $type;
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $header['type']);
        }

        if (in_array($action, ['logo', 'edit_header'], true)) {
            if (preg_match('/\blogo\b.*?\b(?:height|size)\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)
                || preg_match('/\b(?:height|size)\b.*?\blogo\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)) {
                $height = max(44, min(60, (int) $m[1]));
                $header['logo_height'] = $height;
                $operations[] = $this->op('header.logo_height', $height);
            }
            if (preg_match('/\blogo\b.*?\b(?:max[- ]?width|width)\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)) {
                $width = max(180, min(300, (int) $m[1]));
                $header['logo_max_width'] = $width;
                $operations[] = $this->op('header.logo_max_width', $width);
            }
            if (preg_match('/\b(?:logo text|brand name)\b\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?$/iu', trim($prompt), $m)) {
                $text = trim($m[1]);
                if ($text !== '') {
                    $header['logo_text'] = Str::limit($text, 80, '');
                    $operations[] = $this->op('header.logo_text', $header['logo_text']);
                }
            }
        }

        if (in_array($action, ['header_cta', 'edit_header'], true)) {
            if (preg_match('/\b(?:cta|button)\s+(?:label|text)\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?/iu', $prompt, $m)) {
                $label = trim(preg_replace('/\s+(?:and|with)\s+(?:url|link).*$/iu', '', $m[1]) ?? $m[1]);
                if ($label !== '') {
                    $header['cta_label'] = Str::limit($label, 60, '');
                    $operations[] = $this->op('header.cta_label', $header['cta_label']);
                }
            }
            if (preg_match('/\b(?:cta|button)\s+(?:url|link)\s*(?:to|as|:)\s*["“]?([^\s"”]+)["”]?/iu', $prompt, $m)) {
                $url = $this->safeUrl($m[1]);
                if ($url !== null) {
                    $header['cta_url'] = $url;
                    $operations[] = $this->op('header.cta_url', $url);
                }
            }
        }

        if ($action === 'header_spacing') {
            // Existing header variants do not expose generic shell padding tokens.
            // Custom-shell settings own exact height/padding, so only mutate them
            // when the current header already opted into that renderer contract.
            if (! ($header['custom_shell_mode'] ?? false)) return null;
            $style = is_array($header['custom_style'] ?? null) ? $header['custom_style'] : [];
            if (preg_match('/\bheader\s+height\b.*?(\d{2,3})\s*px\b/i', $prompt, $m)) {
                $style['height'] = max(48, min(180, (int) $m[1]));
                $operations[] = $this->op('header.custom_style.height', $style['height']);
            }
            if (preg_match('/\b(?:horizontal\s+)?padding\b.*?(\d{1,3})\s*px\b/i', $prompt, $m)) {
                $style['padding_x'] = max(16, min(160, (int) $m[1]));
                $operations[] = $this->op('header.custom_style.padding_x', $style['padding_x']);
            }
            if ($operations !== []) $header['custom_style'] = $style;
        }

        return $operations === [] ? null : $this->success('header', $action ?: 'edit_header', ['header' => HeaderFooterVariantContract::normalizeHeader($header) ?? $header, 'operations' => $operations]);
    }

    private function applyFooter(?Website $website, string $prompt, string $action, array $footer, array $blocks = []): ?array
    {
        $websiteFooter = $website && is_array($website->global_footer) ? $website->global_footer : [];
        $footer = HeaderFooterVariantContract::normalizeFooter($footer !== [] ? $footer : $websiteFooter) ?? [];
        $operations = [];
        $mega = is_array($footer['mega_footer'] ?? null) ? $footer['mega_footer'] : [];
        $explicitVariant = HeaderFooterVariantContract::detectFooterVariant($prompt);

        // Variant names map directly to the same card IDs used by the Builder.
        // This also fixes "primary mega footer" being swallowed by the generic
        // mega_footer action before the requested variant could be read.
        if ($explicitVariant !== null) {
            if ($this->footerVariantNeedsLightLogo($explicitVariant) && ! $this->lightLogoTreatmentAvailable($footer)) {
                return $this->failure('footer', 'change_footer', 'This footer needs a light logo. Upload a Light Logo or allow automatic white logo filtering first.');
            }
            $mega['variant'] = $explicitVariant;
            $footer['mega_footer'] = $mega;
            $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
            $mega = $footer['mega_footer'];
            $operations[] = $this->op('footer.mega_footer.variant', $explicitVariant);
        }

        if ($action === 'custom_footer') return null;

        if ($action === 'mega_footer' && $explicitVariant === null) {
            // Mega Footer is no longer an independent on/off setting. A request
            // to simplify it selects the canonical white footer instead.
            $disableRequested = (bool) preg_match('/\b(disable|off|remove|turn off)\b/i', $prompt);
            $mega['variant'] = $disableRequested ? 'classic' : (string) ($mega['variant'] ?? 'classic');
            $footer['mega_footer'] = $mega;
            $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
            $mega = $footer['mega_footer'];
            $operations[] = $this->op('footer.mega_footer.variant', $mega['variant']);
        }
        if ($action === 'change_footer' && $explicitVariant === null) {
            $variant = null;
            if (preg_match('/\b(?:mega\s+)?(?:classic|white)(?:\s+footer)?\b/i', $prompt)) $variant = 'classic';
            if (preg_match('/\b(?:mega\s+)?primary(?:\s+footer)?\b/i', $prompt)) $variant = 'primary';
            if (preg_match('/\bcenter(?:ed)?(?:\s+footer)?\s+(?:with\s+)?cta\b|\bcentered[_ -]?cta\b/i', $prompt)) $variant = 'centered_cta';
            if (preg_match('/\b(?:mega\s+)?centered(?: minimal)?(?:\s+footer)?\b/i', $prompt) && ! preg_match('/\bcta\b/i', $prompt)) $variant = 'centered';
            if (preg_match('/\b(?:split|editorial)(?:\s+footer)?\b/i', $prompt)) $variant = 'split';
            if (preg_match('/\b(?:mega\s+)?brand(?:\s+footer)?\b/i', $prompt)) $variant = 'brand';
            if (preg_match('/\bsecondary(?: surface| background)?(?:\s+footer)?\b/i', $prompt)) $variant = 'secondary';
            // Legacy names migrate to the closest current design instead of creating stale variants.
            if (preg_match('/\b(?:contact|newsletter|cta)(?:\s+footer)?\b/i', $prompt) && $variant === null) $variant = 'centered_cta';
            if ($variant !== null) {
                $footer['mega_enabled'] = true;
                $mega['enabled'] = true;
                $mega['variant'] = $variant;
                $footer['mega_footer'] = $mega;
                $operations[] = $this->op('footer.mega_footer.variant', $variant);
            }
        }

        if ($explicitVariant === null && in_array($action, ['simple_footer', 'change_footer'], true)) {
            if ($action === 'change_footer' && preg_match('/\bmega\b/i', $prompt)) {
                $footer['mega_enabled'] = true; $mega['enabled'] = true;
                $operations[] = $this->op('footer.mega_enabled', true);
            } elseif ($action === 'simple_footer' || preg_match('/\b(simple|minimal)\b/i', $prompt)) {
                $mega['variant'] = 'centered';
                $footer['mega_footer'] = $mega;
                $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
                $mega = $footer['mega_footer'];
                $operations[] = $this->op('footer.mega_footer.variant', 'centered');
            } elseif ($operations === []) return null;
            $footer['mega_footer'] = $mega;
        }

        if ($action === 'footer_columns') {
            $count = null;
            if (preg_match('/\b([1-4])\s+columns?\b/i', $prompt, $m)) $count = (int) $m[1];
            elseif (preg_match('/\bcolumns?\s*(?:to|:)?\s*([1-4])\b/i', $prompt, $m)) $count = (int) $m[1];
            if ($count === null) return null;
            $columns = is_array($mega['columns'] ?? null) ? array_values($mega['columns']) : [];
            $defaults = [
                ['title'=>'Company','items'=>[['label'=>'About us','url'=>'#about'],['label'=>'Contact','url'=>'#contact']]],
                ['title'=>'Services','items'=>[['label'=>'What we do','url'=>'#services'],['label'=>'Pricing','url'=>'#pricing']]],
                ['title'=>'Resources','items'=>[['label'=>'Insights','url'=>'#insights'],['label'=>'Updates','url'=>'#updates']]],
                ['title'=>'Connect','items'=>[['label'=>'Contact','url'=>'#contact']]],
            ];
            while (count($columns) < $count) $columns[] = $defaults[count($columns)] ?? end($defaults);
            $columns = array_slice($columns, 0, $count);
            $footer['mega_enabled'] = true; $mega['enabled'] = true; $mega['columns'] = $columns; $footer['mega_footer'] = $mega;
            $operations[] = $this->op('footer.mega_footer.columns', $columns);
        }

        if (in_array($action, ['footer_cta', 'edit_footer'], true)) {
            if (preg_match('/\b(?:footer\s+)?(?:cta|button)\s+(?:label|text)\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?/iu', $prompt, $m)) {
                $label = trim(preg_replace('/\s+(?:and|with)\s+(?:url|link).*$/iu', '', $m[1]) ?? $m[1]);
                if ($label !== '') { $mega['primary_label'] = Str::limit($label, 60, ''); $operations[]=$this->op('footer.mega_footer.primary_label',$mega['primary_label']); }
            }
            if (preg_match('/\b(?:footer\s+)?(?:cta|button)\s+(?:url|link)\s*(?:to|as|:)\s*["“]?([^\s"”]+)["”]?/iu', $prompt, $m)) {
                $url=$this->safeUrl($m[1]); if($url!==null){$mega['primary_url']=$url;$operations[]=$this->op('footer.mega_footer.primary_url',$url);}
            }
            if ($operations !== []) { $footer['mega_enabled']=true; $mega['enabled']=true; $footer['mega_footer']=$mega; }
        }

        if ($action === 'footer_background') {
            $theme = null;
            foreach (['primary','white','surface','secondary','auto'] as $candidate) if (preg_match('/\b'.preg_quote($candidate,'/').'\b/i',$prompt)) { $theme=$candidate; break; }
            if ($theme === null) return null;
            if ($theme !== 'auto') {
                $mega['variant'] = match ($theme) {
                    'primary' => 'primary',
                    'surface' => 'split',
                    'secondary' => 'secondary',
                    default => 'classic',
                };
            }
            $footer['mega_footer']=$mega;
            $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
            $mega = $footer['mega_footer'];
            $operations[]=$this->op('footer.mega_footer.variant',$mega['variant']);
        }

        if ($action === 'footer_brand') {
            if (preg_match('/\bcopyright\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?$/iu',trim($prompt),$m)) {
                $copyright=trim($m[1]);
                if($copyright!==''){$footer['copyright']=Str::limit($copyright,180,'');$operations[]=$this->op('footer.copyright',$footer['copyright']);}
            }
            if (preg_match('/\b(?:footer\s+)?logo\s+(?:height|size)\b.*?(\d{2,3})\s*(?:px)?\b/i',$prompt,$m)) {
                $height=max(24,min(80,(int)$m[1]));$footer['logo_height']=$height;$operations[]=$this->op('footer.logo_height',$height);
            }
        }

        if ($action === 'footer_socials') {
            $links=is_array($footer['social_links']??null)?array_values($footer['social_links']):[];
            $known=['facebook','instagram','linkedin','youtube','tiktok','twitter','x'];
            $added=false;
            foreach($known as $network){
                if(!preg_match('/\b'.preg_quote($network,'/').'\b/i',$prompt)) continue;
                $label=$network==='x'?'X':Str::title($network);
                if(preg_match('/\b'.preg_quote($network,'/').'\b.*?(https?:\/\/[^\s"”]+)/i',$prompt,$m)){
                    $url=$this->safeUrl($m[1]);
                    if($url!==null){
                        $existing=null;foreach($links as $i=>$item){if(Str::lower((string)($item['label']??''))===Str::lower($label)){$existing=$i;break;}}
                        $entry=['label'=>$label,'url'=>$url];
                        if($existing===null)$links[]=$entry;else $links[$existing]=$entry;
                        $added=true;
                    }
                }
            }
            if(!$added) return null; // Never invent social URLs.
            $footer['social_links']=array_slice($links,0,6);
            $operations[]=$this->op('footer.social_links',$footer['social_links']);
        }

        return $operations === [] ? null : $this->success('footer', $action ?: 'edit_footer', ['footer'=>HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer,'operations'=>$operations]);
    }

    private function headerVariantHasCta(string $variant): bool
    {
        return ! in_array($variant, ['centered_header', 'overlay_centered_header'], true);
    }

    private function footerVariantHasCta(string $variant): bool
    {
        return $variant !== 'centered';
    }

    private function headerVariantNeedsLightLogo(string $variant): bool
    {
        return in_array($variant, ['primary_header', 'overlay_hero_header', 'overlay_centered_header'], true);
    }

    private function footerVariantNeedsLightLogo(string $variant): bool
    {
        return in_array($variant, ['primary', 'brand'], true);
    }

    private function hasLightLogo(array $shell): bool
    {
        foreach (['logo_light_image_url', 'logo_image_url_light', 'light_logo_url'] as $key) {
            if (trim((string) ($shell[$key] ?? '')) !== '') return true;
        }
        return false;
    }

    private function lightLogoTreatmentAvailable(array $shell): bool
    {
        return (bool) ($shell['allow_light_logo_filter'] ?? true) || $this->hasLightLogo($shell);
    }

    /** @param array<int,array<string,mixed>> $blocks */
    private function supportsOverlayHeader(array $blocks): bool
    {
        $first = is_array($blocks[0] ?? null) ? $blocks[0] : [];
        $type = Str::lower((string) ($first['type'] ?? ''));
        $category = Str::lower((string) ($first['category'] ?? $first['semantic_type'] ?? ''));
        if ($category === 'hero') return true;
        return $type === 'hero' || Str::contains($type, ['hero', 'banner', 'masthead']);
    }

    private function failure(string $domain, string $action, string $message): array
    {
        return [
            'handled' => true,
            'success' => false,
            'domain' => $domain,
            'scope_action' => $action,
            'message' => $message,
            'operations' => [],
        ];
    }

    private function safeUrl(string $value): ?string
    {
        $url=trim($value," \t\n\r\0\x0B.,;\"");
        if($url==='' || strlen($url)>500) return null;
        if(str_starts_with($url,'/') || str_starts_with($url,'#')) return $url;
        return filter_var($url,FILTER_VALIDATE_URL) ? $url : null;
    }

    private function op(string $target, mixed $value): array
    {
        return ['action'=>'update','target'=>$target,'value'=>$value,'verified'=>true];
    }

    private function success(string $domain,string $action,array $payload): array
    {
        return array_replace(['handled'=>true,'success'=>true,'domain'=>$domain,'scope_action'=>$action,'operations'=>[]],$payload);
    }
}
