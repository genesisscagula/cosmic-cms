<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Str;

class LunaSiteAdminActionService
{
    /**
     * Deterministic Batch 4 executor for explicit Forms/SEO/Site Settings requests.
     * Returns null when the request should continue to the normal Builder planner.
     */
    public function apply(Website $website, ?Page $page, string $prompt, array $intent): ?array
    {
        if (($intent['intent'] ?? '') !== 'action' || ($intent['action'] ?? '') !== 'update') {
            return null;
        }

        $domain = Str::lower((string) ($intent['domain'] ?? ''));
        if (! in_array($domain, ['seo', 'settings', 'form'], true)) {
            return null;
        }

        if ($domain === 'seo') {
            return $this->applySeo($website, $page, $prompt);
        }

        if ($domain === 'settings') {
            return $this->applySettings($website, $prompt);
        }

        // Form delivery recipient is a site-level deterministic setting. Structural
        // field/layout edits remain in the Builder planner where block schemas are known.
        if ($domain === 'form' && preg_match('/\b(?:recipient|send(?:\s+submissions?)?\s+to|notification email)\b/i', $prompt)) {
            if (preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $prompt, $m)) {
                $website->forceFill(['contact_email' => $m[0]])->save();
                return $this->success('form', [['action' => 'update', 'target' => 'form.recipient', 'value' => $m[0], 'verified' => true]]);
            }
        }

        return null;
    }

    private function applySeo(Website $website, ?Page $page, string $prompt): ?array
    {
        if (! $page) {
            return [
                'handled' => true,
                'success' => false,
                'domain' => 'seo',
                'message' => 'A current page is required for this SEO change.',
                'operations' => [],
            ];
        }

        $updates = [];
        $ops = [];

        if (preg_match('/\b(?:seo title|meta title|page title)\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i', trim($prompt), $m)) {
            $value = trim($m[1], " \t\n\r\0\x0B\"'“”");
            if ($value !== '') {
                $updates['seo_title'] = Str::limit($value, 255, '');
                $ops[] = ['action' => 'update', 'target' => 'seo.title', 'value' => $updates['seo_title'], 'verified' => true];
            }
        }

        if (preg_match('/\b(?:meta description|seo description)\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i', trim($prompt), $m)) {
            $value = trim($m[1], " \t\n\r\0\x0B\"'“”");
            if ($value !== '') {
                $updates['meta_description'] = Str::limit($value, 500, '');
                $ops[] = ['action' => 'update', 'target' => 'seo.meta_description', 'value' => $updates['meta_description'], 'verified' => true];
            }
        }

        if (preg_match('/\b(?:og image|open graph image)\b\s*(?:to|as|is|:)\s*(https?:\/\/\S+)/i', $prompt, $m)) {
            $updates['og_image_url'] = rtrim($m[1], '.,)');
            $ops[] = ['action' => 'update', 'target' => 'seo.og_image', 'value' => $updates['og_image_url'], 'verified' => true];
        }

        if (preg_match('/\b(?:canonical|canonical url)\b\s*(?:to|as|is|:)\s*(https?:\/\/\S+)/i', $prompt, $m)) {
            $updates['canonical_url'] = rtrim($m[1], '.,)');
            $ops[] = ['action' => 'update', 'target' => 'seo.canonical_url', 'value' => $updates['canonical_url'], 'verified' => true];
        }

        if (preg_match('/\b(?:noindex|do not index|don\'t index)\b/i', $prompt)) {
            $updates['is_indexable'] = false;
            $ops[] = ['action' => 'update', 'target' => 'seo.indexing', 'value' => false, 'verified' => true];
        } elseif (preg_match('/\b(?:index this page|make (?:this )?page indexable|allow indexing)\b/i', $prompt)) {
            $updates['is_indexable'] = true;
            $ops[] = ['action' => 'update', 'target' => 'seo.indexing', 'value' => true, 'verified' => true];
        }

        if (preg_match('/\b(?:change|set|update)\s+(?:the\s+)?slug\s+(?:to|as)\s+["“]?([a-z0-9\-\/]+)["”]?/i', $prompt, $m)) {
            $slug = Str::slug(trim($m[1], '/'));
            if ($slug !== '') {
                $updates['slug'] = $slug;
                $ops[] = ['action' => 'update', 'target' => 'page.slug', 'value' => $slug, 'verified' => true];
            }
        }

        if ($updates === []) return null;

        $page->forceFill($updates)->save();
        return $this->success('seo', $ops);
    }

    private function applySettings(Website $website, string $prompt): ?array
    {
        $updates = [];
        $settings = (array) ($website->settings ?? []);
        $ops = [];

        $patterns = [
            'name' => '/\b(?:website name|site name)\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i',
            'industry' => '/\bindustry\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i',
            'location' => '/\b(?:business location|location)\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i',
            'contact_phone' => '/\b(?:contact phone|phone number|phone)\b\s*(?:to|as|is|:)\s*["“]?([+0-9()\-\s]{7,30})["”]?/i',
        ];
        foreach ($patterns as $field => $regex) {
            if (preg_match($regex, trim($prompt), $m)) {
                $value = trim($m[1], " \t\n\r\0\x0B\"'“”");
                if ($value !== '') {
                    $updates[$field] = Str::limit($value, $field === 'name' ? 255 : 500, '');
                    $ops[] = ['action' => 'update', 'target' => 'settings.'.$field, 'value' => $updates[$field], 'verified' => true];
                }
            }
        }

        if (preg_match('/\b(?:contact email|business email|email)\b\s*(?:to|as|is|:)\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $prompt, $m)) {
            $updates['contact_email'] = $m[1];
            $ops[] = ['action' => 'update', 'target' => 'settings.contact_email', 'value' => $m[1], 'verified' => true];
        }
        if (preg_match('/\b(?:business name|company name)\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i', trim($prompt), $m)) {
            $value = trim($m[1], " \t\n\r\0\x0B\"'“”");
            if ($value !== '') {
                $settings['business_name'] = Str::limit($value, 255, '');
                $ops[] = ['action' => 'update', 'target' => 'settings.business_name', 'value' => $settings['business_name'], 'verified' => true];
            }
        }
        if (preg_match('/\baddress\b\s*(?:to|as|is|:)\s*["“]?(.+?)["”]?\s*$/i', trim($prompt), $m)) {
            $settings['address'] = Str::limit(trim($m[1], " \t\n\r\0\x0B\"'“”"), 500, '');
            $ops[] = ['action' => 'update', 'target' => 'settings.address', 'value' => $settings['address'], 'verified' => true];
        }

        if ($updates === [] && $ops === []) return null;
        $updates['settings'] = $settings;
        $website->forceFill($updates)->save();
        return $this->success('settings', $ops);
    }

    private function success(string $domain, array $ops): array
    {
        return ['handled' => true, 'success' => true, 'domain' => $domain, 'operations' => $ops];
    }
}
