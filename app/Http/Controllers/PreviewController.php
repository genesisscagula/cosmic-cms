<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\CommerceCartService;
use App\Services\CommerceStorefrontService;
use App\Services\PreviewDeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PreviewController extends Controller
{
    public function local(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, Request $request, string $slug, ?string $path = null): Response
    {
        return $this->serve($previews, $commerce, $request, $slug, $path);
    }

    public function subdomain(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, Request $request, string $preview, ?string $path = null): Response
    {
        return $this->serve($previews, $commerce, $request, $preview, $path);
    }

    public function localCartAdd(Request $request, CommerceCartService $cart, string $slug): RedirectResponse
    {
        return $this->cartAdd($request, $cart, $slug);
    }

    public function subdomainCartAdd(Request $request, CommerceCartService $cart, string $preview): RedirectResponse
    {
        return $this->cartAdd($request, $cart, $preview);
    }

    public function localCartUpdate(Request $request, CommerceCartService $cart, string $slug): RedirectResponse
    {
        return $this->cartUpdate($request, $cart, $slug);
    }

    public function subdomainCartUpdate(Request $request, CommerceCartService $cart, string $preview): RedirectResponse
    {
        return $this->cartUpdate($request, $cart, $preview);
    }

    public function localCartRemove(Request $request, CommerceCartService $cart, string $slug): RedirectResponse
    {
        return $this->cartRemove($request, $cart, $slug);
    }

    public function subdomainCartRemove(Request $request, CommerceCartService $cart, string $preview): RedirectResponse
    {
        return $this->cartRemove($request, $cart, $preview);
    }

    private function serve(PreviewDeploymentService $previews, CommerceStorefrontService $commerce, Request $request, string $slug, ?string $path): Response
    {
        $commerceResponse = $commerce->renderIfCommercePath($slug, $path, $request);
        if ($commerceResponse) {
            return $commerceResponse;
        }

        $html = $previews->resolveFile($slug, $path);
        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function cartAdd(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate([
            'product' => ['required','uuid'],
            'variant' => ['nullable','uuid'],
            'quantity' => ['nullable','integer','min:1','max:99'],
        ]);
        $cart->add($website, $data['product'], $data['variant'] ?? null, (int) ($data['quantity'] ?? 1));

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Added to cart.');
    }

    private function cartUpdate(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate([
            'line' => ['required','string','max:100'],
            'quantity' => ['required','integer','min:0','max:99'],
        ]);
        $cart->update($website, $data['line'], (int) $data['quantity']);

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Cart updated.');
    }

    private function cartRemove(Request $request, CommerceCartService $cart, string $previewSlug): RedirectResponse
    {
        $website = $this->commerceWebsite($previewSlug);
        $data = $request->validate(['line' => ['required','string','max:100']]);
        $cart->remove($website, $data['line']);

        return redirect($this->commerceUrl($previewSlug, '/cart'))->with('commerce_success', 'Item removed.');
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
