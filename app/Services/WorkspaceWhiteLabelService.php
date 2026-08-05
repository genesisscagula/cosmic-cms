<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

class WorkspaceWhiteLabelService
{
    public const DEFAULT_PRIMARY = '#7C3AED';
    public const DEFAULT_ACCENT = '#22D3EE';

    public function forWorkspace(?Workspace $workspace, string $level = 'none'): array
    {
        $branding = (array) data_get($workspace?->settings, 'branding', []);
        $removable = $level !== 'none';
        $removed = $removable && (bool) ($branding['remove_cosmic_branding'] ?? false);
        $logoPath = $branding['logo_path'] ?? null;
        $logoExists = filled($logoPath) && Storage::disk('public')->exists($logoPath);

        return [
            'level' => $level,
            'cosmic_branding_removed' => $removed,
            'agency_name' => trim((string) ($branding['agency_name'] ?? $workspace?->name ?? 'Your Agency')) ?: 'Your Agency',
            'tagline' => filled($branding['tagline'] ?? null) ? trim((string) $branding['tagline']) : null,
            'primary_color' => $this->validColor($branding['primary_color'] ?? null, self::DEFAULT_PRIMARY),
            'accent_color' => $this->validColor($branding['accent_color'] ?? null, self::DEFAULT_ACCENT),
            'support_email' => filter_var($branding['support_email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
            'website_url' => $this->validHttpUrl($branding['website_url'] ?? null),
            'logo_url' => $logoExists ? Storage::disk('public')->url($logoPath) : null,
            'logo_missing' => filled($logoPath) && ! $logoExists,
            'portal_title' => filled($branding['portal_title'] ?? null)
                ? trim((string) $branding['portal_title'])
                : 'Welcome to your client portal',
            'portal_welcome' => filled($branding['portal_welcome'] ?? null)
                ? trim((string) $branding['portal_welcome'])
                : 'Review your websites, check publication status, and open secure previews from one place.',
            'attribution_label' => $removed ? null : 'Built with Cosmic CMS',
            'updated_at' => $branding['updated_at'] ?? null,
        ];
    }

    public function completeness(?Workspace $workspace, string $level = 'none'): array
    {
        $brand = $this->forWorkspace($workspace, $level);
        $checks = [
            'agency_name' => filled($brand['agency_name']) && $brand['agency_name'] !== 'Your Agency',
            'logo' => filled($brand['logo_url']),
            'support_email' => filled($brand['support_email']),
            'website_url' => filled($brand['website_url']),
            'portal_copy' => $level !== 'full' || (filled($brand['portal_title']) && filled($brand['portal_welcome'])),
        ];

        $completed = collect($checks)->filter()->count();
        $total = count($checks);

        return [
            'checks' => $checks,
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 100,
            'ready' => $completed === $total,
        ];
    }

    private function validColor(mixed $value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
    }

    private function validHttpUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (! filter_var($value, FILTER_VALIDATE_URL)) return null;
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}
