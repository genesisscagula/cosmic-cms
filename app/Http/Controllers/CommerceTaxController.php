<?php

namespace App\Http\Controllers;

use App\Models\CommerceTaxRule;
use App\Models\Website;
use App\Services\CommerceCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceTaxController extends Controller
{
    public function updateSettings(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());

        $data = $request->validate([
            'tax_enabled' => ['required','boolean'],
            'prices_include_tax' => ['required','boolean'],
        ]);

        $settings = $commerce->settingsFor($website);
        $settings->update([
            'tax_enabled' => (bool) $data['tax_enabled'],
            'prices_include_tax' => (bool) $data['prices_include_tax'],
            'tax_strategy' => 'manual',
        ]);

        return redirect()->back(303)->with('success', 'Tax settings updated.');
    }

    public function storeRule(Request $request, Website $website, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $data = $this->validatedRule($request);

        $website->commerceTaxRules()->create($this->normalize($data));
        return redirect()->back(303)->with('success', 'Tax rule created.');
    }

    public function updateRule(Request $request, Website $website, CommerceTaxRule $rule, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertWebsite($website, $rule);
        $rule->update($this->normalize($this->validatedRule($request)));
        return redirect()->back(303)->with('success', 'Tax rule updated.');
    }

    public function destroyRule(Request $request, Website $website, CommerceTaxRule $rule, CommerceCapabilityService $commerce)
    {
        $this->authorize('update', $website);
        $commerce->assertPlanAllowsCommerce($request->user());
        $this->assertWebsite($website, $rule);
        $rule->delete();
        return redirect()->back(303)->with('success', 'Tax rule deleted.');
    }

    private function validatedRule(Request $request): array
    {
        $countries = array_keys((array) config('cosmic-commerce.countries', []));
        return $request->validate([
            'name' => ['required','string','max:120'],
            'country_code' => ['nullable','string','size:2', Rule::in($countries)],
            'region_code' => ['nullable','string','max:120'],
            'tax_class' => ['nullable','string','max:120'],
            'rate_percent' => ['required','numeric','min:0','max:100'],
            'tax_shipping' => ['required','boolean'],
            'is_enabled' => ['required','boolean'],
            'priority' => ['required','integer','min:0','max:10000'],
        ]);
    }

    private function normalize(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'country_code' => filled($data['country_code'] ?? null) ? strtoupper(trim($data['country_code'])) : null,
            'region_code' => filled($data['region_code'] ?? null) ? strtoupper(trim($data['region_code'])) : null,
            'tax_class' => filled($data['tax_class'] ?? null) ? trim($data['tax_class']) : null,
            'rate_basis_points' => (int) round(((float) $data['rate_percent']) * 100),
            'tax_shipping' => (bool) $data['tax_shipping'],
            'is_enabled' => (bool) $data['is_enabled'],
            'priority' => (int) $data['priority'],
        ];
    }

    private function assertWebsite(Website $website, CommerceTaxRule $rule): void
    {
        abort_unless((int) $rule->website_id === (int) $website->id, 404);
    }
}
