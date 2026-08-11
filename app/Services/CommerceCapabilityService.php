<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCommerceSetting;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CommerceCapabilityService
{
    public function __construct(private readonly PlanCapabilityService $plans)
    {
    }

    public function settingsFor(Website $website): WebsiteCommerceSetting
    {
        return $website->commerceSetting()->firstOrCreate([], [
            'public_key' => (string) Str::uuid(),
            'enabled' => false,
            'currency' => strtoupper((string) config('cosmic-commerce.default_currency', 'USD')),
            'tax_enabled' => false,
            'prices_include_tax' => false,
            'tax_strategy' => 'manual',
            'settings' => [],
        ]);
    }

    public function planAllowsCommerce(User $user): bool
    {
        return $this->plans->allows($user, 'commerce_store');
    }

    public function connectorAvailable(User $user): bool
    {
        return $this->plans->allows($user, 'commerce_connector');
    }

    public function isActive(Website $website): bool
    {
        $owner = $website->user;
        if (! $owner || ! $this->planAllowsCommerce($owner)) {
            return false;
        }

        return (bool) $this->settingsFor($website)->enabled;
    }

    public function assertPlanAllowsCommerce(User $user): void
    {
        if (! $this->planAllowsCommerce($user)) {
            throw new InvalidArgumentException('E-commerce is available on Cosmic Growth and Pro plans.');
        }
    }

    public function manifest(Website $website): array
    {
        $settings = $this->settingsFor($website);
        $owner = $website->user;
        $planAvailable = $owner ? $this->planAllowsCommerce($owner) : false;

        return [
            'version' => (string) config('cosmic-commerce.connector_version', '1.0.0'),
            'website_id' => $website->id,
            'public_key' => $settings->public_key,
            'available' => $planAvailable,
            'enabled' => $planAvailable && (bool) $settings->enabled,
            'currency' => $settings->currency,
            'currency_config' => config('cosmic-commerce.currencies.' . $settings->currency),
            'tax' => [
                'enabled' => (bool) $settings->tax_enabled,
                'prices_include_tax' => (bool) $settings->prices_include_tax,
                'strategy' => $settings->tax_strategy,
            ],
            'features' => [
                'products' => true,
                'categories' => true,
                'variants' => true,
                'cart' => true,
                'shipping' => true,
                'checkout' => true,
                'orders' => false,
            ],
            'shipping' => [
                'country_selector' => true,
                'zone_count' => $website->commerceShippingZones()->where('is_enabled', true)->count(),
                'rate_count' => \App\Models\CommerceShippingRate::query()->whereHas('zone', fn ($q) => $q->where('website_id', $website->id)->where('is_enabled', true))->where('is_enabled', true)->count(),
                'supports_rest_of_world' => true,
                'supports_free_above' => true,
            ],
        ];
    }
}
