<?php

namespace App\Services;

use App\Cosmic\Pricing\CreditPackageRegistry;
use App\Models\MarketplaceTemplate;
use RuntimeException;

final class MarketplaceCreditTopUpService
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    public function quote(string $planKey, MarketplaceTemplate $template, ?string $selectedKey = null): array
    {
        $plan = $this->plans->find($planKey);

        if (! is_array($plan) || (string) ($plan['family'] ?? '') !== 'agency') {
            throw new RuntimeException('Marketplace credit top-ups require an Agency plan.');
        }

        $requiredCredits = max(0, (int) $template->credit_price);
        $includedCredits = max(0, (int) ($plan['credits'] ?? 0));
        $shortfall = max(0, $requiredCredits - $includedCredits);
        $planPriceMinor = (int) round(((float) ($plan['price_usd'] ?? 0)) * 100);

        if ($shortfall === 0) {
            return [
                'required_template_credits' => $requiredCredits,
                'included_plan_credits' => $includedCredits,
                'shortfall_credits' => 0,
                'requires_topup' => false,
                'options' => [],
                'default_key' => null,
                'selected_key' => null,
                'selected' => null,
                'plan_price_usd' => $planPriceMinor / 100,
            ];
        }

        $options = [];
        $recommended = $this->bestBundleAtLeast($shortfall);
        $options[$this->optionKey($recommended['credits'], $recommended['price_minor'])] = $this->normalizeOption(
            $recommended,
            'Minimum recommended',
            true,
            $includedCredits,
            $requiredCredits,
            $planPriceMinor,
        );

        foreach (CreditPackageRegistry::all() as $key => $package) {
            $credits = (int) ($package['credits'] ?? 0);
            $priceMinor = (int) round(((float) ($package['price_usd'] ?? 0)) * 100);

            if ($credits < $shortfall || $credits <= 0 || $priceMinor <= 0) {
                continue;
            }

            $optionKey = $this->optionKey($credits, $priceMinor);
            $candidate = $this->normalizeOption([
                'credits' => $credits,
                'price_minor' => $priceMinor,
                'bundle' => [$key => 1],
            ], (string) ($package['label'] ?? ucfirst($key)), false, $includedCredits, $requiredCredits, $planPriceMinor);

            if (! isset($options[$optionKey]) || $candidate['price_minor'] < $options[$optionKey]['price_minor']) {
                $options[$optionKey] = $candidate;
            }
        }

        $options = array_values($options);
        usort($options, static function (array $a, array $b): int {
            if ($a['recommended'] !== $b['recommended']) {
                return $a['recommended'] ? -1 : 1;
            }

            return [$a['price_minor'], $a['credits']] <=> [$b['price_minor'], $b['credits']];
        });

        $defaultKey = (string) ($options[0]['key'] ?? '');
        $selected = collect($options)->firstWhere('key', $selectedKey ?: $defaultKey);

        if (! $selected) {
            $selected = $options[0];
        }

        return [
            'required_template_credits' => $requiredCredits,
            'included_plan_credits' => $includedCredits,
            'shortfall_credits' => $shortfall,
            'requires_topup' => true,
            'options' => $options,
            'default_key' => $defaultKey,
            'selected_key' => $selected['key'],
            'selected' => $selected,
            'plan_price_usd' => $planPriceMinor / 100,
        ];
    }

    public function selected(string $planKey, MarketplaceTemplate $template, ?string $selectedKey): ?array
    {
        $quote = $this->quote($planKey, $template, $selectedKey);

        if (! $quote['requires_topup']) {
            return null;
        }

        if ($selectedKey !== null && $selectedKey !== '' && (string) $quote['selected']['key'] !== $selectedKey) {
            throw new RuntimeException('The selected Cosmic Credit top-up is no longer available.');
        }

        return $quote['selected'];
    }

    private function bestBundleAtLeast(int $minimumCredits): array
    {
        $packages = [];
        foreach (CreditPackageRegistry::all() as $key => $package) {
            $credits = (int) ($package['credits'] ?? 0);
            $priceMinor = (int) round(((float) ($package['price_usd'] ?? 0)) * 100);
            if ($credits > 0 && $priceMinor > 0) {
                $packages[$key] = compact('credits', 'priceMinor');
            }
        }

        if ($packages === []) {
            throw new RuntimeException('No Cosmic Credit packages are configured.');
        }

        $maxPackCredits = max(array_column($packages, 'credits'));
        $limit = $minimumCredits + $maxPackCredits;
        $dp = array_fill(0, $limit + 1, null);
        $dp[0] = ['price_minor' => 0, 'bundle' => []];

        for ($credits = 0; $credits <= $limit; $credits++) {
            if ($dp[$credits] === null) {
                continue;
            }

            foreach ($packages as $key => $package) {
                $nextCredits = $credits + $package['credits'];
                if ($nextCredits > $limit) {
                    continue;
                }

                $nextPrice = $dp[$credits]['price_minor'] + $package['priceMinor'];
                $current = $dp[$nextCredits];
                if ($current !== null && $current['price_minor'] <= $nextPrice) {
                    continue;
                }

                $bundle = $dp[$credits]['bundle'];
                $bundle[$key] = (int) ($bundle[$key] ?? 0) + 1;
                $dp[$nextCredits] = ['price_minor' => $nextPrice, 'bundle' => $bundle];
            }
        }

        $best = null;
        for ($credits = $minimumCredits; $credits <= $limit; $credits++) {
            if ($dp[$credits] === null) {
                continue;
            }

            $candidate = [
                'credits' => $credits,
                'price_minor' => $dp[$credits]['price_minor'],
                'bundle' => $dp[$credits]['bundle'],
            ];

            if ($best === null
                || $candidate['price_minor'] < $best['price_minor']
                || ($candidate['price_minor'] === $best['price_minor'] && $candidate['credits'] < $best['credits'])) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            throw new RuntimeException('Unable to price the required Cosmic Credit top-up.');
        }

        return $best;
    }

    private function normalizeOption(
        array $option,
        string $label,
        bool $recommended,
        int $includedCredits,
        int $requiredCredits,
        int $planPriceMinor,
    ): array {
        $credits = (int) $option['credits'];
        $priceMinor = (int) $option['price_minor'];

        return [
            'key' => $this->optionKey($credits, $priceMinor),
            'label' => $label,
            'credits' => $credits,
            'price_minor' => $priceMinor,
            'price_usd' => $priceMinor / 100,
            'recommended' => $recommended,
            'bundle' => $option['bundle'] ?? [],
            'balance_after_install' => max(0, $includedCredits + $credits - $requiredCredits),
            'initial_checkout_usd' => ($planPriceMinor + $priceMinor) / 100,
        ];
    }

    private function optionKey(int $credits, int $priceMinor): string
    {
        return 'topup_'.$credits.'_'.$priceMinor;
    }
}
