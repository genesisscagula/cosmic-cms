<?php

namespace App\Services;

use App\Models\CommerceShippingZone;
use App\Models\Website;
use Illuminate\Support\Collection;

final class CommerceShippingService
{
    public function zoneFor(Website $website, ?string $countryCode): ?CommerceShippingZone
    {
        $country = strtoupper(trim((string) $countryCode));
        if ($country === '') return null;

        $zones = CommerceShippingZone::query()
            ->where('website_id', $website->id)
            ->where('is_enabled', true)
            ->with(['rates' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $explicit = $zones->first(function (CommerceShippingZone $zone) use ($country) {
            if ($zone->is_rest_of_world) return false;
            return in_array($country, array_map('strtoupper', (array) $zone->countries), true);
        });

        return $explicit ?: $zones->firstWhere('is_rest_of_world', true);
    }

    /** @return array{available:bool,country:string,zone:?CommerceShippingZone,rates:Collection,selected:?array} */
    public function quote(Website $website, ?string $countryCode, int $subtotalMinor, ?int $rateId = null): array
    {
        $country = strtoupper(trim((string) $countryCode));
        $zone = $this->zoneFor($website, $country);
        $rates = collect();

        if ($zone) {
            $rates = $zone->rates->map(function ($rate) use ($subtotalMinor) {
                $amount = ($rate->free_above_minor !== null && $subtotalMinor >= $rate->free_above_minor)
                    ? 0
                    : (int) $rate->rate_minor;
                return [
                    'id' => (int) $rate->id,
                    'name' => $rate->name,
                    'amount_minor' => $amount,
                    'base_amount_minor' => (int) $rate->rate_minor,
                    'free_above_minor' => $rate->free_above_minor === null ? null : (int) $rate->free_above_minor,
                ];
            })->values();
        }

        $selected = $rateId ? $rates->firstWhere('id', $rateId) : $rates->first();

        return [
            'available' => $rates->isNotEmpty(),
            'country' => $country,
            'zone' => $zone,
            'rates' => $rates,
            'selected' => $selected,
        ];
    }
}
