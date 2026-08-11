<?php

namespace App\Services;

use App\Models\CommerceTaxRule;
use App\Models\Website;
use Illuminate\Support\Collection;

final class CommerceTaxService
{
    /**
     * Server-side tax quote. Taxable line subtotals come from the canonical cart,
     * never from browser-supplied prices.
     *
     * @return array{enabled:bool,country:string,region:string,prices_include_tax:bool,tax_minor:int,net_subtotal_minor:int,shipping_tax_minor:int,display_subtotal_minor:int,total_minor:int,lines:Collection,rule_names:array}
     */
    public function quote(Website $website, array $cart, ?string $countryCode, ?string $regionCode, int $shippingMinor = 0): array
    {
        $settings = $website->commerceSetting;
        $country = strtoupper(trim((string) $countryCode));
        $region = strtoupper(trim((string) $regionCode));
        $inclusive = (bool) ($settings?->prices_include_tax ?? false);
        $enabled = (bool) ($settings?->tax_enabled ?? false) && $country !== '';

        $taxMinor = 0;
        $netSubtotal = 0;
        $shippingTaxMinor = 0;
        $ruleNames = [];
        $lines = collect();

        foreach (($cart['items'] ?? collect()) as $line) {
            $gross = (int) ($line['line_total_minor'] ?? 0);
            $product = $line['product'];
            $rule = ($enabled && $product->taxable)
                ? $this->ruleFor($website, $country, $region, $product->tax_class)
                : null;
            $bps = (int) ($rule?->rate_basis_points ?? 0);
            $lineTax = $this->taxAmount($gross, $bps, $inclusive);
            $lineNet = $inclusive ? max(0, $gross - $lineTax) : $gross;

            $taxMinor += $lineTax;
            $netSubtotal += $lineNet;
            if ($rule) $ruleNames[$rule->id] = $rule->name;

            $lines->push([
                'key' => $line['key'],
                'taxable' => (bool) $product->taxable,
                'tax_class' => $product->tax_class ?: 'standard',
                'rate_basis_points' => $bps,
                'tax_minor' => $lineTax,
                'net_minor' => $lineNet,
                'gross_minor' => $inclusive ? $gross : $gross + $lineTax,
                'rule' => $rule,
            ]);
        }

        if ($enabled && $shippingMinor > 0) {
            // Shipping uses the standard class. It is taxed only when the matched
            // standard rule explicitly enables tax_shipping.
            $shippingRule = $this->ruleFor($website, $country, $region, null);
            if ($shippingRule?->tax_shipping) {
                $shippingTaxMinor = $this->taxAmount($shippingMinor, (int) $shippingRule->rate_basis_points, false);
                $taxMinor += $shippingTaxMinor;
                $ruleNames[$shippingRule->id] = $shippingRule->name;
            }
        }

        $subtotal = (int) ($cart['subtotal_minor'] ?? 0);
        $total = $inclusive
            ? $subtotal + $shippingMinor + $shippingTaxMinor
            : $subtotal + $shippingMinor + $taxMinor;

        return [
            'enabled' => $enabled,
            'country' => $country,
            'region' => $region,
            'prices_include_tax' => $inclusive,
            'tax_minor' => $taxMinor,
            'net_subtotal_minor' => $netSubtotal,
            'shipping_tax_minor' => $shippingTaxMinor,
            'display_subtotal_minor' => $subtotal,
            'total_minor' => $total,
            'lines' => $lines,
            'rule_names' => array_values($ruleNames),
        ];
    }

    public function ruleFor(Website $website, string $countryCode, string $regionCode = '', ?string $taxClass = null): ?CommerceTaxRule
    {
        $country = strtoupper(trim($countryCode));
        $region = strtoupper(trim($regionCode));
        $taxClass = trim((string) $taxClass) ?: null;

        $rules = CommerceTaxRule::query()
            ->where('website_id', $website->id)
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        // Most specific wins: country + region + class, country + region + standard,
        // country + class, country + standard, then rest-of-world equivalents.
        $scopes = [
            [$country, $region ?: null, $taxClass],
            [$country, $region ?: null, null],
            [$country, null, $taxClass],
            [$country, null, null],
            [null, $region ?: null, $taxClass],
            [null, $region ?: null, null],
            [null, null, $taxClass],
            [null, null, null],
        ];

        foreach ($scopes as [$ruleCountry, $ruleRegion, $ruleClass]) {
            $match = $rules->first(function (CommerceTaxRule $rule) use ($ruleCountry, $ruleRegion, $ruleClass) {
                return ($rule->country_code ? strtoupper($rule->country_code) : null) === $ruleCountry
                    && ($rule->region_code ? strtoupper($rule->region_code) : null) === $ruleRegion
                    && ($rule->tax_class ?: null) === $ruleClass;
            });
            if ($match) return $match;
        }

        return null;
    }

    private function taxAmount(int $amountMinor, int $rateBasisPoints, bool $inclusive): int
    {
        if ($amountMinor <= 0 || $rateBasisPoints <= 0) return 0;
        $rateBasisPoints = min(10000, $rateBasisPoints);

        if ($inclusive) {
            return (int) round($amountMinor - ($amountMinor * 10000 / (10000 + $rateBasisPoints)));
        }

        return (int) round($amountMinor * $rateBasisPoints / 10000);
    }
}
