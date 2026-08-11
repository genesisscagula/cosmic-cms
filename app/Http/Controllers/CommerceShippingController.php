<?php

namespace App\Http\Controllers;

use App\Models\CommerceShippingRate;
use App\Models\CommerceShippingZone;
use App\Models\Website;
use App\Services\CommerceCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceShippingController extends Controller
{
    public function storeZone(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $countryCodes = array_keys((array) config('cosmic-commerce.countries', []));
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'countries' => ['nullable','array','max:249'],
            'countries.*' => ['string', Rule::in($countryCodes)],
            'is_rest_of_world' => ['nullable','boolean'],
            'priority' => ['nullable','integer','min:0','max:10000'],
        ]);

        $isRest = (bool) ($data['is_rest_of_world'] ?? false);
        if ($isRest) {
            CommerceShippingZone::query()->where('website_id', $website->id)->where('is_rest_of_world', true)->update(['is_rest_of_world' => false]);
        }

        $website->commerceShippingZones()->create([
            'name' => trim($data['name']),
            'countries' => $isRest ? [] : array_values(array_unique(array_map('strtoupper', $data['countries'] ?? []))),
            'is_rest_of_world' => $isRest,
            'is_enabled' => true,
            'priority' => (int) ($data['priority'] ?? 100),
        ]);

        return redirect()->back(303)->with('success', 'Shipping zone created.');
    }

    public function updateZone(Request $request, Website $website, CommerceShippingZone $zone, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertZoneWebsite($website, $zone);

        $countryCodes = array_keys((array) config('cosmic-commerce.countries', []));
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'countries' => ['nullable','array','max:249'],
            'countries.*' => ['string', Rule::in($countryCodes)],
            'is_rest_of_world' => ['required','boolean'],
            'is_enabled' => ['required','boolean'],
            'priority' => ['required','integer','min:0','max:10000'],
        ]);

        if ($data['is_rest_of_world']) {
            CommerceShippingZone::query()->where('website_id', $website->id)->where('id', '!=', $zone->id)->where('is_rest_of_world', true)->update(['is_rest_of_world' => false]);
        }

        $zone->update([
            'name' => trim($data['name']),
            'countries' => $data['is_rest_of_world'] ? [] : array_values(array_unique(array_map('strtoupper', $data['countries'] ?? []))),
            'is_rest_of_world' => (bool) $data['is_rest_of_world'],
            'is_enabled' => (bool) $data['is_enabled'],
            'priority' => (int) $data['priority'],
        ]);

        return redirect()->back(303)->with('success', 'Shipping zone updated.');
    }

    public function destroyZone(Request $request, Website $website, CommerceShippingZone $zone, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertZoneWebsite($website, $zone);
        $zone->delete();
        return redirect()->back(303)->with('success', 'Shipping zone deleted.');
    }

    public function storeRate(Request $request, Website $website, CommerceShippingZone $zone, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertZoneWebsite($website, $zone);

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'rate' => ['required','numeric','min:0','max:99999999'],
            'free_above' => ['nullable','numeric','min:0','max:99999999'],
        ]);

        $zone->rates()->create([
            'name' => trim($data['name']),
            'rate_minor' => $this->moneyToMinor($data['rate'], $website, $commerce),
            'free_above_minor' => $data['free_above'] === null || $data['free_above'] === '' ? null : $this->moneyToMinor($data['free_above'], $website, $commerce),
            'is_enabled' => true,
            'sort_order' => ((int) $zone->rates()->max('sort_order')) + 1,
        ]);

        return redirect()->back(303)->with('success', 'Shipping rate added.');
    }

    public function updateRate(Request $request, Website $website, CommerceShippingZone $zone, CommerceShippingRate $rate, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertZoneWebsite($website, $zone);
        abort_unless((int) $rate->shipping_zone_id === (int) $zone->id, 404);

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'rate' => ['required','numeric','min:0','max:99999999'],
            'free_above' => ['nullable','numeric','min:0','max:99999999'],
            'is_enabled' => ['required','boolean'],
            'sort_order' => ['nullable','integer','min:0','max:10000'],
        ]);

        $rate->update([
            'name' => trim($data['name']),
            'rate_minor' => $this->moneyToMinor($data['rate'], $website, $commerce),
            'free_above_minor' => $data['free_above'] === null || $data['free_above'] === '' ? null : $this->moneyToMinor($data['free_above'], $website, $commerce),
            'is_enabled' => (bool) $data['is_enabled'],
            'sort_order' => (int) ($data['sort_order'] ?? $rate->sort_order),
        ]);

        return redirect()->back(303)->with('success', 'Shipping rate updated.');
    }

    public function destroyRate(Request $request, Website $website, CommerceShippingZone $zone, CommerceShippingRate $rate, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertZoneWebsite($website, $zone);
        abort_unless((int) $rate->shipping_zone_id === (int) $zone->id, 404);
        $rate->delete();
        return redirect()->back(303)->with('success', 'Shipping rate deleted.');
    }

    private function assertZoneWebsite(Website $website, CommerceShippingZone $zone): void
    {
        abort_unless((int) $zone->website_id === (int) $website->id, 404);
    }

    private function moneyToMinor(mixed $amount, Website $website, CommerceCapabilityService $commerce): int
    {
        $currency = strtoupper((string) $commerce->settingsFor($website)->currency);
        $decimals = (int) config('cosmic-commerce.currencies.'.$currency.'.decimals', 2);
        return (int) round(((float) $amount) * (10 ** $decimals));
    }
}
