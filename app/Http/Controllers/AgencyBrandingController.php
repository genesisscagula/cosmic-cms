<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\PlanCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AgencyBrandingController extends Controller
{
    public function update(Request $request, Workspace $workspace, PlanCapabilityService $plans)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === (int) $user->id, 403, 'Only the workspace owner can manage agency branding.');

        $capabilities = $plans->forUser($user);
        $level = (string) data_get($capabilities, 'capabilities.white_label_level', 'none');
        abort_unless(($capabilities['plan_family'] ?? null) === 'agency' && $level !== 'none', 403, 'Agency branding is not available on the current plan.');

        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['nullable', 'email:rfc', 'max:190'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'logo_action' => ['nullable', Rule::in(['keep', 'remove'])],
            'remove_cosmic_branding' => ['nullable', 'boolean'],
            'portal_title' => ['nullable', 'string', 'max:120'],
            'portal_welcome' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = $workspace->settings ?? [];
        $branding = (array) data_get($settings, 'branding', []);

        if (($validated['logo_action'] ?? 'keep') === 'remove' && filled($branding['logo_path'] ?? null)) {
            Storage::disk('public')->delete($branding['logo_path']);
            $branding['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if (filled($branding['logo_path'] ?? null)) {
                Storage::disk('public')->delete($branding['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store("workspace-branding/{$workspace->id}", 'public');
        }

        $branding = array_merge($branding, [
            'agency_name' => $validated['agency_name'],
            'tagline' => $validated['tagline'] ?? null,
            'primary_color' => strtoupper($validated['primary_color']),
            'accent_color' => strtoupper($validated['accent_color']),
            'support_email' => $validated['support_email'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'remove_cosmic_branding' => $level !== 'none' && (bool) ($validated['remove_cosmic_branding'] ?? false),
            'portal_title' => $level === 'full' ? ($validated['portal_title'] ?? null) : ($branding['portal_title'] ?? null),
            'portal_welcome' => $level === 'full' ? ($validated['portal_welcome'] ?? null) : ($branding['portal_welcome'] ?? null),
            'updated_by' => $user->id,
            'updated_at' => now()->toIso8601String(),
        ]);

        $workspace->update(['settings' => array_merge($settings, ['branding' => $branding])]);

        return back()->with('success', 'Agency branding updated.');
    }

    public function reset(Request $request, Workspace $workspace, PlanCapabilityService $plans)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === (int) $user->id, 403, 'Only the workspace owner can reset agency branding.');

        $capabilities = $plans->forUser($user);
        abort_unless(($capabilities['plan_family'] ?? null) === 'agency', 403, 'Agency branding is not available on the current plan.');

        $settings = $workspace->settings ?? [];
        $branding = (array) data_get($settings, 'branding', []);
        if (filled($branding['logo_path'] ?? null)) {
            Storage::disk('public')->delete($branding['logo_path']);
        }

        unset($settings['branding']);
        $workspace->update(['settings' => $settings]);

        return back()->with('success', 'Agency branding reset to defaults.');
    }
}
