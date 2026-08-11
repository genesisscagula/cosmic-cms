<?php

namespace App\Http\Controllers;

use App\Models\CommerceCoupon;
use App\Models\Website;
use App\Services\CommerceCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceCouponController extends Controller
{
    public function store(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $data = $this->validated($request, $website);
        $data['code'] = strtoupper(trim($data['code']));
        $data = $this->moneyAndPercent($data, $website);
        $website->commerceCoupons()->create($data);
        return redirect()->back(303)->with('success', 'Coupon created.');
    }

    public function update(Request $request, Website $website, CommerceCoupon $coupon, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        abort_unless((int) $coupon->website_id === (int) $website->id, 404);
        $data = $this->validated($request, $website, $coupon);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon->update($this->moneyAndPercent($data, $website));
        return redirect()->back(303)->with('success', 'Coupon saved.');
    }

    public function destroy(Request $request, Website $website, CommerceCoupon $coupon, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        abort_unless((int) $coupon->website_id === (int) $website->id, 404);
        $coupon->delete();
        return redirect()->back(303)->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, Website $website, ?CommerceCoupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:80','regex:/^[A-Za-z0-9_-]+$/', Rule::unique('commerce_coupons','code')->where(fn($q)=>$q->where('website_id',$website->id))->ignore($coupon?->id)],
            'name' => ['nullable','string','max:160'],
            'discount_type' => ['required', Rule::in(['percent','fixed'])],
            'percent' => ['nullable','numeric','min:0.01','max:100'],
            'fixed_amount' => ['nullable','numeric','min:0.01','max:999999999'],
            'minimum_spend' => ['nullable','numeric','min:0','max:999999999'],
            'usage_limit' => ['nullable','integer','min:1','max:4294967295'],
            'usage_limit_per_email' => ['nullable','integer','min:1','max:4294967295'],
            'starts_at' => ['nullable','date'],
            'expires_at' => ['nullable','date','after:starts_at'],
            'is_enabled' => ['boolean'],
            'product_ids' => ['array','max:100'],
            'product_ids.*' => ['integer', Rule::exists('commerce_products','id')->where(fn($q)=>$q->where('website_id',$website->id))],
            'category_ids' => ['array','max:100'],
            'category_ids.*' => ['integer', Rule::exists('commerce_product_categories','id')->where(fn($q)=>$q->where('website_id',$website->id))],
        ]);
    }

    private function moneyAndPercent(array $data, Website $website): array
    {
        $currency = strtoupper((string) ($website->commerceSetting?->currency ?: config('cosmic-commerce.default_currency','USD')));
        $decimals = (int) config("cosmic-commerce.currencies.$currency.decimals", 2);
        $scale = 10 ** max(0, $decimals);
        $type = $data['discount_type'];
        return [
            'code' => $data['code'], 'name' => $data['name'] ?? null, 'discount_type' => $type,
            'percent_basis_points' => $type === 'percent' ? (int) round(((float) ($data['percent'] ?? 0)) * 100) : null,
            'fixed_amount_minor' => $type === 'fixed' ? (int) round(((float) ($data['fixed_amount'] ?? 0)) * $scale) : null,
            'minimum_spend_minor' => isset($data['minimum_spend']) && $data['minimum_spend'] !== null && $data['minimum_spend'] !== '' ? (int) round(((float) $data['minimum_spend']) * $scale) : null,
            'usage_limit' => $data['usage_limit'] ?? null, 'usage_limit_per_email' => $data['usage_limit_per_email'] ?? null,
            'starts_at' => $data['starts_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false), 'product_ids' => array_values($data['product_ids'] ?? []), 'category_ids' => array_values($data['category_ids'] ?? []),
        ];
    }
}
