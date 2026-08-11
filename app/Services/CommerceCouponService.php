<?php

namespace App\Services;

use App\Models\CommerceCoupon;
use App\Models\CommerceCouponUsage;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CommerceCouponService
{
    public function quote(Website $website, array $cart, ?string $rawCode, ?string $customerEmail = null, bool $throw = false): array
    {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '') return $this->emptyQuote($cart);

        $coupon = CommerceCoupon::query()
            ->where('website_id', $website->id)
            ->where('code', $code)
            ->first();

        $error = $this->invalidReason($coupon, $cart, $customerEmail);
        if ($error) {
            if ($throw) throw ValidationException::withMessages(['coupon' => $error]);
            return [...$this->emptyQuote($cart), 'code' => $code, 'error' => $error];
        }

        $eligible = collect($cart['items'] ?? [])->filter(fn ($line) => $this->lineEligible($line, $coupon));
        $eligibleSubtotal = (int) $eligible->sum('line_total_minor');
        if ($eligibleSubtotal <= 0) {
            $message = 'This coupon does not apply to the items in your cart.';
            if ($throw) throw ValidationException::withMessages(['coupon' => $message]);
            return [...$this->emptyQuote($cart), 'code' => $code, 'error' => $message];
        }

        $discount = $coupon->discount_type === 'fixed'
            ? min($eligibleSubtotal, (int) ($coupon->fixed_amount_minor ?? 0))
            : min($eligibleSubtotal, (int) round($eligibleSubtotal * min(10000, (int) ($coupon->percent_basis_points ?? 0)) / 10000));

        $allocations = $this->allocate($eligible, $discount, $eligibleSubtotal);
        $discountedItems = collect($cart['items'] ?? [])->map(function ($line) use ($allocations) {
            $copy = $line;
            $lineDiscount = (int) ($allocations[$line['key']] ?? 0);
            $copy['discount_minor'] = $lineDiscount;
            $copy['line_total_minor'] = max(0, (int) $line['line_total_minor'] - $lineDiscount);
            return $copy;
        });

        $discountedSubtotal = max(0, (int) ($cart['subtotal_minor'] ?? 0) - $discount);

        return [
            'applied' => true,
            'coupon' => $coupon,
            'code' => $coupon->code,
            'label' => $coupon->name ?: $coupon->code,
            'discount_minor' => $discount,
            'eligible_subtotal_minor' => $eligibleSubtotal,
            'subtotal_after_discount_minor' => $discountedSubtotal,
            'allocations' => $allocations,
            'cart' => [...$cart, 'items' => $discountedItems, 'subtotal_minor' => $discountedSubtotal],
            'error' => null,
        ];
    }

    private function invalidReason(?CommerceCoupon $coupon, array $cart, ?string $email): ?string
    {
        if (! $coupon || ! $coupon->is_enabled) return 'That coupon code is not valid.';
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) return 'This coupon is not active yet.';
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) return 'This coupon has expired.';
        if ($coupon->minimum_spend_minor !== null && (int) ($cart['subtotal_minor'] ?? 0) < (int) $coupon->minimum_spend_minor) return 'Your cart does not meet the minimum spend for this coupon.';
        if ($coupon->usage_limit !== null && $coupon->usages()->count() >= (int) $coupon->usage_limit) return 'This coupon has reached its usage limit.';
        if ($email && $coupon->usage_limit_per_email !== null && $coupon->usages()->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($email))])->count() >= (int) $coupon->usage_limit_per_email) return 'This coupon has already been used the maximum number of times for this email.';
        return null;
    }

    private function lineEligible(array $line, CommerceCoupon $coupon): bool
    {
        $productIds = array_values(array_filter(array_map('intval', $coupon->product_ids ?? [])));
        $categoryIds = array_values(array_filter(array_map('intval', $coupon->category_ids ?? [])));
        if ($productIds === [] && $categoryIds === []) return true;

        $product = $line['product'];
        if (in_array((int) $product->id, $productIds, true)) return true;
        if ($categoryIds !== []) {
            $ids = $product->relationLoaded('categories') ? $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all() : $product->categories()->pluck('commerce_product_categories.id')->map(fn ($id) => (int) $id)->all();
            return count(array_intersect($ids, $categoryIds)) > 0;
        }
        return false;
    }

    private function allocate(Collection $eligible, int $discount, int $eligibleSubtotal): array
    {
        if ($discount <= 0 || $eligibleSubtotal <= 0) return [];
        $allocations = [];
        $remaining = $discount;
        $lines = $eligible->values();
        foreach ($lines as $index => $line) {
            $isLast = $index === $lines->count() - 1;
            $amount = $isLast ? $remaining : min($remaining, (int) floor($discount * (int) $line['line_total_minor'] / $eligibleSubtotal));
            $amount = min($amount, (int) $line['line_total_minor']);
            $allocations[$line['key']] = $amount;
            $remaining -= $amount;
        }
        return $allocations;
    }

    private function emptyQuote(array $cart): array
    {
        return [
            'applied' => false, 'coupon' => null, 'code' => '', 'label' => null,
            'discount_minor' => 0, 'eligible_subtotal_minor' => 0,
            'subtotal_after_discount_minor' => (int) ($cart['subtotal_minor'] ?? 0),
            'allocations' => [], 'cart' => $cart, 'error' => null,
        ];
    }
}
