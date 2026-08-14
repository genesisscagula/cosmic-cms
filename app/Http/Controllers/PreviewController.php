<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\CommerceCartService;
use App\Services\CommerceStorefrontService;
use App\Services\ContentStorefrontService;
use App\Services\PreviewDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PreviewController extends Controller
{
    public function local(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, ContentStorefrontService $content, Request $request, string $slug, ?string $path = null): Response
    {
        return $this->serve($previews, $commerce, $content, $request, $slug, $path);
    }

    public function subdomain(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, ContentStorefrontService $content, Request $request, string $preview, ?string $path = null): Response
    {
        return $this->serve($previews, $commerce, $content, $request, $preview, $path);
    }

    public function localCartAdd(Request $request, CommerceCartService $cart, string $slug): RedirectResponse|JsonResponse
    {
        return $this->cartAdd($request, $cart, $slug);
    }

    public function subdomainCartAdd(Request $request, CommerceCartService $cart, string $preview): RedirectResponse|JsonResponse
    {
        return $this->cartAdd($request, $cart, $preview);
    }

    public function localCartUpdate(Request $request, CommerceCartService $cart, string $slug): RedirectResponse|JsonResponse
    {
        return $this->cartUpdate($request, $cart, $slug);
    }

    public function subdomainCartUpdate(Request $request, CommerceCartService $cart, string $preview): RedirectResponse|JsonResponse
    {
        return $this->cartUpdate($request, $cart, $preview);
    }

    public function localCartRemove(Request $request, CommerceCartService $cart, string $slug): RedirectResponse|JsonResponse
    {
        return $this->cartRemove($request, $cart, $slug);
    }

    public function subdomainCartRemove(Request $request, CommerceCartService $cart, string $preview): RedirectResponse|JsonResponse
    {
        return $this->cartRemove($request, $cart, $preview);
    }


    public function localCartSummary(CommerceCartService $cart, string $slug): JsonResponse
    {
        return $this->cartSummary($cart, $slug);
    }

    public function subdomainCartSummary(CommerceCartService $cart, string $preview): JsonResponse
    {
        return $this->cartSummary($cart, $preview);
    }

    private function serve(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, ContentStorefrontService $content, Request $request, string $slug, ?string $path): Response
    {
        $commerceResponse = $commerce->renderIfCommercePath($slug, $path, $request);
        if ($commerceResponse) {
            return $commerceResponse;
        }

        $contentResponse = $content->renderIfContentPath($slug, $path, $request);
        if ($contentResponse) {
            return $contentResponse;
        }

        $html = $previews->resolveFile($slug, $path);
        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function cartAdd(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse|JsonResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate([
            'product' => ['required','uuid'],
            'variant' => ['nullable','uuid'],
            'quantity' => ['nullable','integer','min:1','max:99'],
        ]);
        $summary = $cart->add($website, $data['product'], $data['variant'] ?? null, (int) ($data['quantity'] ?? 1));

        if ($this->wantsCartJson($request)) {
            return response()->json($this->cartPayload($website, $summary, $previewSlug) + ['message' => 'Added to cart.']);
        }

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Added to cart.');
    }

    private function cartUpdate(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse|JsonResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate([
            'line' => ['required','string','max:100'],
            'quantity' => ['required','integer','min:0','max:99'],
        ]);
        $summary = $cart->update($website, $data['line'], (int) $data['quantity']);

        if ($this->wantsCartJson($request)) {
            return response()->json($this->cartPayload($website, $summary, $previewSlug) + ['message' => 'Cart updated.']);
        }

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Cart updated.');
    }

    private function cartRemove(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse|JsonResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate(['line' => ['required','string','max:100']]);
        $summary = $cart->remove($website, $data['line']);

        if ($this->wantsCartJson($request)) {
            return response()->json($this->cartPayload($website, $summary, $previewSlug) + ['message' => 'Item removed.']);
        }

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Item removed.');
    }


    private function cartSummary(CommerceCartService $cart, string $previewSlug): JsonResponse
    {
        $website = $this->commerceWebsite($previewSlug);

        return response()->json($this->cartPayload($website, $cart->summary($website), $previewSlug));
    }

    private function wantsCartJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax() || str_contains((string) $request->header('Accept'), 'application/json');
    }

    private function cartPayload(Website $website, array $summary, string $previewSlug): array
    {
        $currency = strtoupper((string) ($website->commerceSetting?->currency ?: config('cosmic-commerce.default_currency', 'USD')));
        $decimals = (int) data_get(config('cosmic-commerce.currencies.'.$currency, []), 'decimals', 2);

        return [
            'count' => (int) ($summary['count'] ?? 0),
            'subtotal_minor' => (int) ($summary['subtotal_minor'] ?? 0),
            'currency' => $currency,
            'decimals' => $decimals,
            'cart_url' => $this->commerceUrl($previewSlug, '/cart'),
            'checkout_url' => $this->commerceUrl($previewSlug, '/checkout'),
            'update_url' => $this->commerceUrl($previewSlug, '/cart/update'),
            'remove_url' => $this->commerceUrl($previewSlug, '/cart/remove'),
            'items' => collect($summary['items'] ?? [])->map(fn ($line) => [
                'line' => (string) ($line['key'] ?? ''),
                'title' => (string) ($line['product']?->title ?? $line['title'] ?? 'Item'),
                'option_label' => (string) ($line['option_label'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'max_quantity' => (int) ($line['max_quantity'] ?? 99),
                'line_total_minor' => (int) ($line['line_total_minor'] ?? 0),
                'product_url' => $line['product'] ? $this->commerceUrl($previewSlug, '/product/'.rawurlencode((string) $line['product']->slug)) : null,
            ])->values()->all(),
        ];
    }

    private function commerceWebsite(string $previewSlug): Website
    {
        $website = Website::query()->where('preview_slug', $previewSlug)->with('commerceSetting')->firstOrFail();
        abort_unless((bool) $website->commerceSetting?->enabled, 404);
        return $website;
    }

    private function commerceUrl(string $previewSlug, string $path): string
    {
        return config('cosmic_preview.mode') === 'local'
            ? '/preview/'.rawurlencode($previewSlug).'/'.ltrim($path, '/')
            : '/'.ltrim($path, '/');
    }
}
