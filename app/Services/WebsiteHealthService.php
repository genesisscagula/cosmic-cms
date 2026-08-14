<?php

namespace App\Services;

use App\Models\CommerceProduct;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic, read-only customer website health scanner.
 *
 * V1 deliberately avoids external HTTP crawling and never mutates website data.
 * The UI layer can consume the returned `fix` descriptors in a later patch.
 */
final class WebsiteHealthService
{
    /** @return array<string,mixed> */
    public function scan(Website $website, array $context = []): array
    {
        $website->loadMissing(['user', 'pages', 'contentEntries.contentType', 'mediaAssets', 'commerceSetting']);

        $findings = collect()
            ->merge($this->publishingFindings($website, $context))
            ->merge($this->formFindings($website))
            ->merge($this->linkFindings($website))
            ->merge($this->seoFindings($website))
            ->merge($this->mediaFindings($website))
            ->merge($this->commerceFindings($website))
            ->values();

        $penalties = (array) config('cosmic-health.severity_penalties', []);
        $score = max(0, 100 - (int) $findings
            ->whereIn('status', ['critical', 'warning', 'info'])
            ->sum(fn (array $finding): int => (int) ($penalties[$finding['status']] ?? 0)));

        $criticalCount = $findings->where('status', 'critical')->count();
        $warningCount = $findings->where('status', 'warning')->count();
        $infoCount = $findings->where('status', 'info')->count();

        $categoryOrder = ['content', 'links', 'seo', 'forms', 'media', 'commerce', 'publishing'];
        $categories = collect($categoryOrder)->mapWithKeys(function (string $category) use ($findings): array {
            $items = $findings->where('category', $category)->values();

            return [$category => [
                'critical' => $items->where('status', 'critical')->count(),
                'warning' => $items->where('status', 'warning')->count(),
                'info' => $items->where('status', 'info')->count(),
                'passed' => $items->where('status', 'passed')->count(),
                'findings' => $items->all(),
            ]];
        })->all();

        return [
            'version' => (int) config('cosmic-health.version', 1),
            'website_id' => $website->id,
            'scanned_at' => now()->toIso8601String(),
            'score' => $score,
            'ready_to_publish' => $criticalCount === 0,
            'status' => $criticalCount > 0 ? 'needs_attention' : ($warningCount > 0 ? 'ready_with_warnings' : 'ready'),
            'summary' => [
                'critical' => $criticalCount,
                'warning' => $warningCount,
                'info' => $infoCount,
                'passed' => $findings->where('status', 'passed')->count(),
            ],
            'categories' => $categories,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function publishingFindings(Website $website, array $context = []): array
    {
        $items = [];
        $publishingPageId = (int) ($context['publishing_page_id'] ?? 0);

        if (filled($website->deployment_error)) {
            $items[] = $this->finding('publishing.live_deployment_failed', 'publishing', 'critical', 'Live deployment needs attention', (string) $website->deployment_error, $this->fix('publish'));
        }

        if (filled($website->preview_deployment_error)) {
            $items[] = $this->finding('publishing.preview_deployment_failed', 'publishing', 'critical', 'Preview deployment failed', (string) $website->preview_deployment_error, $this->fix('publish'));
        }

        $failedPages = $website->pages->filter(fn ($page) => filled($page->publish_error) && (int) $page->id !== $publishingPageId);
        foreach ($failedPages as $page) {
            $items[] = $this->finding(
                'publishing.page_failed.'.$page->id,
                'publishing',
                'critical',
                'Page publish failed: '.$page->title,
                (string) $page->publish_error,
                $this->fix('builder', ['page_id' => $page->id])
            );
        }

        // Draft pages are intentionally excluded from publishing. Their presence is not
        // a launch defect, so do not penalize health or interrupt the publish flow.
        $drafts = $website->pages->filter(fn ($page) => strtolower((string) $page->status) !== 'published' && (int) $page->id !== $publishingPageId);
        if ($drafts->isNotEmpty()) {
            $items[] = $this->finding(
                'publishing.draft_pages',
                'publishing',
                'passed',
                $drafts->count().' draft page'.($drafts->count() === 1 ? ' is' : 's are').' safely excluded',
                'Draft pages stay private until you publish them.',
                null,
                ['count' => $drafts->count(), 'page_ids' => $drafts->pluck('id')->values()->all()]
            );
        }

        if ($website->pages->isNotEmpty() && $failedPages->isEmpty() && blank($website->deployment_error) && blank($website->preview_deployment_error)) {
            $items[] = $this->finding('publishing.no_known_errors', 'publishing', 'passed', 'No known publish errors', 'Cosmic has no recorded page, preview, or live deployment errors.');
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function formFindings(Website $website): array
    {
        $formPages = $website->pages->filter(fn ($page) => $this->pageContainsForm((array) $page->blocks, (string) $page->published_html));

        if ($formPages->isEmpty()) {
            return [$this->finding('forms.none', 'forms', 'passed', 'No forms require configuration', 'No form or inquiry sections were detected on this website.')];
        }

        $recipient = trim((string) ($website->contact_email ?: $website->user?->email));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [$this->finding(
                'forms.recipient_missing',
                'forms',
                'critical',
                'Inquiry recipient email is missing',
                'This website contains a form, but Cosmic cannot resolve a valid recipient email.',
                $this->fix('settings'),
                ['page_ids' => $formPages->pluck('id')->values()->all()]
            )];
        }

        return [$this->finding(
            'forms.recipient_ready',
            'forms',
            'passed',
            'Form recipient is configured',
            'Form submissions can be routed to '.$recipient.'.',
            null,
            ['page_count' => $formPages->count()]
        )];
    }

    /** @return array<int,array<string,mixed>> */
    private function linkFindings(Website $website): array
    {
        $placeholders = [];
        foreach ($website->pages as $page) {
            $count = $this->countPlaceholderLinks((array) $page->blocks);
            if ($count > 0) {
                $placeholders[] = ['page_id' => $page->id, 'title' => $page->title, 'count' => $count];
            }
        }

        foreach ($website->contentEntries as $entry) {
            $count = $this->countPlaceholderLinks((array) ($entry->custom_fields ?? []));
            if ($count > 0) {
                $placeholders[] = ['content_entry_id' => $entry->id, 'title' => $entry->title, 'count' => $count];
            }
        }

        $total = collect($placeholders)->sum('count');
        if ($total > 0) {
            return [$this->finding(
                'links.placeholder_targets',
                'links',
                'warning',
                $total.' placeholder link'.($total === 1 ? '' : 's').' still use #',
                'Replace placeholder CTA/link destinations before launch.',
                $this->fix('builder'),
                ['locations' => $placeholders]
            )];
        }

        return [$this->finding('links.no_placeholders', 'links', 'passed', 'No placeholder CTA links found', 'No editable page or content-entry link fields are still set to #.')];
    }

    /** @return array<int,array<string,mixed>> */
    private function seoFindings(Website $website): array
    {
        $items = [];
        $publishedEntries = $website->contentEntries->filter(fn (ContentEntry $entry) => strtolower((string) $entry->status) === 'published');
        $missingEntries = $publishedEntries->filter(fn (ContentEntry $entry) => (blank($entry->seo_title) && blank($entry->title)) || (blank($entry->seo_description) && blank($entry->excerpt) && blank(strip_tags((string) $entry->content))));

        if ($missingEntries->isNotEmpty()) {
            $items[] = $this->finding(
                'seo.content_entries_missing',
                'seo',
                'warning',
                $missingEntries->count().' published content entr'.($missingEntries->count() === 1 ? 'y is' : 'ies are').' missing SEO metadata',
                'Add both an SEO title and meta description to published Posts/Updates.',
                $this->fix('content'),
                ['entry_ids' => $missingEntries->pluck('id')->values()->all()]
            );
        } elseif ($publishedEntries->isNotEmpty()) {
            $items[] = $this->finding('seo.content_entries_ready', 'seo', 'passed', 'Published content has SEO metadata', 'All published Posts/Updates have an SEO title and meta description.');
        }

        $publishedProducts = $website->commerceProducts()->published()->get();
        $missingProducts = $publishedProducts->filter(fn (CommerceProduct $product) => blank($product->seo_title) || blank($product->seo_description));
        if ($missingProducts->isNotEmpty()) {
            $items[] = $this->finding(
                'seo.products_missing',
                'seo',
                'warning',
                $missingProducts->count().' published product'.($missingProducts->count() === 1 ? ' is' : 's are').' missing SEO metadata',
                'Add SEO titles and descriptions to published store products.',
                $this->fix('commerce'),
                ['product_ids' => $missingProducts->pluck('id')->values()->all()]
            );
        } elseif ($publishedProducts->isNotEmpty()) {
            $items[] = $this->finding('seo.products_ready', 'seo', 'passed', 'Published products have SEO metadata', 'All published products have an SEO title and meta description.');
        }

        if ($items === []) {
            $items[] = $this->finding('seo.no_dynamic_content', 'seo', 'passed', 'No published dynamic content needs SEO review', 'There are no published Posts/Updates or products to audit yet.');
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function mediaFindings(Website $website): array
    {
        $images = $website->mediaAssets->filter(fn (MediaAsset $asset) => str_starts_with(strtolower((string) $asset->mime_type), 'image/'));
        if ($images->isEmpty()) {
            return [$this->finding('media.none', 'media', 'passed', 'No uploaded media needs review', 'The website Media Library does not contain uploaded images yet.')];
        }

        $items = [];
        $missingAlt = $images->filter(function (MediaAsset $asset) {
            $kind = strtolower((string) $asset->kind);
            $decorative = $kind === 'logo' || str_contains($kind, 'background') || str_contains($kind, 'banner');
            return ! $decorative && blank($asset->alt_text);
        });
        if ($missingAlt->isNotEmpty()) {
            $items[] = $this->finding(
                'media.alt_missing',
                'media',
                'warning',
                $missingAlt->count().' image'.($missingAlt->count() === 1 ? ' is' : 's are').' missing alt text',
                'Add descriptive alt text where the image communicates useful content.',
                $this->fix('media'),
                ['asset_ids' => $missingAlt->pluck('uuid')->values()->all()]
            );
        }

        $threshold = max(1, (int) config('cosmic-health.large_image_bytes', 2 * 1024 * 1024));
        $large = $images->filter(fn (MediaAsset $asset) => (int) $asset->size_bytes > $threshold);
        if ($large->isNotEmpty()) {
            $items[] = $this->finding(
                'media.large_images',
                'media',
                'warning',
                $large->count().' image'.($large->count() === 1 ? ' is' : 's are').' larger than '.number_format($threshold / 1048576, 1).' MB',
                'Large source images can slow first-page load on mobile connections.',
                $this->fix('media'),
                ['asset_ids' => $large->pluck('uuid')->values()->all(), 'threshold_bytes' => $threshold]
            );
        }

        if ($items === []) {
            $items[] = $this->finding('media.ready', 'media', 'passed', 'Media basics look good', 'Uploaded images have alt text and are below the large-image warning threshold.');
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function commerceFindings(Website $website): array
    {
        $settings = $website->commerceSetting;
        if (! $settings || ! $settings->enabled) {
            return [$this->finding('commerce.disabled', 'commerce', 'passed', 'Commerce is not enabled', 'Store-specific checks are skipped until Commerce is enabled.')];
        }

        $items = [];
        $receiver = strtolower(trim((string) data_get($settings->settings, 'paypal_receiver_email', '')));
        $fallbackReceiver = strtolower(trim((string) config('cosmic-commerce.default_paypal_receiver_email', config('cosmic.platform_owner_email'))));
        $effectiveReceiver = filter_var($receiver, FILTER_VALIDATE_EMAIL) ? $receiver : (filter_var($fallbackReceiver, FILTER_VALIDATE_EMAIL) ? $fallbackReceiver : '');

        if ($effectiveReceiver === '') {
            $items[] = $this->finding('commerce.payment_receiver_missing', 'commerce', 'critical', 'Commerce payment receiver is missing', 'PayPal checkout has no valid merchant receiver email.', $this->fix('commerce_settings'));
        } elseif ($receiver === '') {
            $items[] = $this->finding('commerce.payment_receiver_fallback', 'commerce', 'warning', 'Commerce is using the platform fallback PayPal receiver', 'Set a website-specific PayPal receiver before accepting real customer payments.', $this->fix('commerce_settings'));
        } else {
            $items[] = $this->finding('commerce.payment_receiver_ready', 'commerce', 'passed', 'Commerce payment receiver is configured', 'A valid website-specific PayPal receiver is configured.');
        }

        $products = $website->commerceProducts()->with('variants')->get();
        $published = $products->where('status', CommerceProduct::STATUS_PUBLISHED);
        if ($published->isEmpty()) {
            $items[] = $this->finding('commerce.no_published_products', 'commerce', 'warning', 'Store has no published products', 'Publish at least one product before promoting the storefront.', $this->fix('commerce'));
        }

        $brokenVariables = $published->filter(fn (CommerceProduct $product) => $product->isVariable() && $product->variants->where('is_enabled', true)->isEmpty());
        if ($brokenVariables->isNotEmpty()) {
            $items[] = $this->finding(
                'commerce.variable_products_no_variants',
                'commerce',
                'critical',
                $brokenVariables->count().' variable product'.($brokenVariables->count() === 1 ? ' has' : 's have').' no enabled variants',
                'Customers cannot purchase a variable product without an enabled variant.',
                $this->fix('commerce'),
                ['product_ids' => $brokenVariables->pluck('id')->values()->all()]
            );
        }

        $missingImages = $published->filter(fn (CommerceProduct $product) => blank($product->featured_image_url));
        if ($missingImages->isNotEmpty()) {
            $items[] = $this->finding('commerce.product_images_missing', 'commerce', 'warning', $missingImages->count().' published product'.($missingImages->count() === 1 ? ' is' : 's are').' missing a featured image', 'Add product imagery before launch for a complete storefront.', $this->fix('commerce'), ['product_ids' => $missingImages->pluck('id')->values()->all()]);
        }

        $physical = $published->filter(fn (CommerceProduct $product) => $product->isPhysical());
        if ($physical->isNotEmpty()) {
            $enabledRates = \App\Models\CommerceShippingRate::query()
                ->whereHas('zone', fn ($query) => $query->where('website_id', $website->id)->where('is_enabled', true))
                ->where('is_enabled', true)
                ->count();

            if ($enabledRates === 0) {
                $items[] = $this->finding('commerce.shipping_missing', 'commerce', 'warning', 'Physical products have no enabled shipping rate', 'Review shipping zones/rates before accepting physical-product orders.', $this->fix('commerce_settings'));
            }
        }

        if ($published->isNotEmpty() && $brokenVariables->isEmpty()) {
            $items[] = $this->finding('commerce.catalog_ready', 'commerce', 'passed', 'Published catalog is purchasable at a basic level', 'Published products passed the basic product/variant availability checks.', null, ['published_products' => $published->count()]);
        }

        return $items;
    }

    private function pageContainsForm(array $blocks, string $publishedHtml): bool
    {
        if (stripos($publishedHtml, '<form') !== false) {
            return true;
        }

        $found = false;
        $this->walk($blocks, function ($value, ?string $key) use (&$found): void {
            if ($found || ! is_string($value)) return;
            if (in_array(strtolower((string) $key), ['type', 'block_type', 'spark_type', 'name'], true)
                && preg_match('/(?:form|contact|inquiry|enquiry)/i', $value)) {
                $found = true;
            }
        });

        return $found;
    }

    private function countPlaceholderLinks(array $data): int
    {
        $count = 0;
        $this->walk($data, function ($value, ?string $key) use (&$count): void {
            if (! is_string($value) || trim($value) !== '#' || ! $this->isDestinationKey((string) $key)) return;
            $count++;
        });

        return $count;
    }

    private function isDestinationKey(string $key): bool
    {
        $key = strtolower($key);
        if ($key === '') return false;
        if (preg_match('/(?:image|video|media|background|poster|avatar|logo|icon|src)/', $key)) return false;

        return $key === 'href'
            || $key === 'url'
            || $key === 'link'
            || str_ends_with($key, '_url')
            || str_ends_with($key, '_href')
            || str_ends_with($key, '_link');
    }

    /** @param callable(mixed,?string):void $callback */
    private function walk(mixed $value, callable $callback, ?string $key = null): void
    {
        $callback($value, $key);
        if (! is_array($value)) return;

        foreach ($value as $childKey => $child) {
            $this->walk($child, $callback, is_string($childKey) ? $childKey : $key);
        }
    }

    /** @return array<string,mixed> */
    private function finding(string $id, string $category, string $status, string $title, string $message, ?array $fix = null, array $meta = []): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'fix' => $fix,
            'meta' => $meta,
        ];
    }

    /** @return array<string,mixed> */
    private function fix(string $area, array $params = []): array
    {
        return ['area' => $area, 'params' => $params];
    }
}
