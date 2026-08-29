<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Str;

/**
 * Bounded, zero-credit navigation mutations.
 *
 * Navigation remains stored in global_header.menu for backward compatibility.
 * Internal CMS targets keep the existing `url` field for current renderers while
 * also carrying target_type/page_id/page_slug metadata for future-safe resolution.
 */
final class LunaNavigationActionService
{
    public function apply(Website $website, string $prompt, array $intent, array $header = []): ?array
    {
        if (($intent['intent'] ?? '') !== 'action') return null;
        if ((string) data_get($intent, 'routing.menu_scope', '') !== 'navigation') return null;

        $action = (string) data_get($intent, 'routing.scope_action', '');
        if ($action === '') return null;

        $header = $header !== [] ? $header : (is_array($website->global_header) ? $website->global_header : []);
        $menu = $this->normalizeMenu((array) ($header['menu'] ?? []));
        $result = match ($action) {
            'add_menu_item' => $this->addMenuItem($website, $menu, $prompt),
            'edit_menu_item' => $this->editMenuItem($website, $menu, $prompt),
            'remove_menu_item' => $this->removeMenuItem($menu, $prompt),
            'reorder_menu' => $this->reorderMenu($menu, $prompt),
            'add_submenu_item' => $this->addSubmenuItem($website, $menu, $prompt),
            'remove_submenu_item' => $this->removeSubmenuItem($menu, $prompt),
            'change_menu_link' => $this->changeMenuLink($website, $menu, $prompt),
            'rename_menu_item' => $this->renameMenuItem($menu, $prompt),
            'create_menu' => $this->createMenu($website, $menu, $prompt),
            default => null,
        };

        if (! is_array($result)) return null;
        if (! ($result['success'] ?? false)) {
            return [
                'handled' => true,
                'success' => false,
                'domain' => 'navigation',
                'scope_action' => $action,
                'message' => $result['message'] ?? 'The navigation request could not be resolved safely.',
                'operations' => [],
            ];
        }

        $header['menu'] = $result['menu'];
        return [
            'handled' => true,
            'success' => true,
            'domain' => 'navigation',
            'scope_action' => $action,
            'header' => $header,
            'operations' => $result['operations'] ?? [],
        ];
    }

    private function addMenuItem(Website $website, array $menu, string $prompt): array
    {
        $label = null;
        if (preg_match('/\badd\s+["“]?(.+?)["”]?\s+(?:to|into)\s+(?:(?:the|my)\s+)?(?:main\s+)?(?:menu|navigation|nav)\b/iu', $prompt, $m)) {
            $label = $this->cleanLabel($m[1]);
        } elseif (preg_match('/\badd\s+(?:menu\s+(?:item|link)\s+)?["“]?(.+?)["”]?(?:\s+(?:menu\s+(?:item|link)|nav\s+link))?(?:\s+(?:with|linking|pointing).*)?$/iu', trim($prompt), $m)) {
            $label = $this->cleanLabel($m[1]);
        }
        if (! $label) return $this->fail('Tell Luna which menu item to add.');
        if ($this->findPathByLabel($menu, $label) !== null) return $this->fail("{$label} is already in the navigation.");

        $target = $this->targetForLabelOrPrompt($website, $label, $prompt);
        $menu[] = array_merge(['label' => $label], $target);
        return $this->ok($menu, [$this->op('add', 'header.menu', end($menu))]);
    }

    private function addSubmenuItem(Website $website, array $menu, string $prompt): array
    {
        $child = $parent = null;
        if (preg_match('/\b(?:put|add|move)\s+["“]?(.+?)["”]?\s+(?:under|inside|into)\s+["“]?(.+?)["”]?(?:\s+(?:menu|submenu|sub-menu))?\s*$/iu', trim($prompt), $m)) {
            $child = $this->cleanLabel($m[1]);
            $parent = $this->cleanLabel($m[2]);
        }
        if (! $child || ! $parent) return $this->fail('Specify both the submenu item and its parent, for example “Put Web Design under Services”.');

        $parentPath = $this->findPathByLabel($menu, $parent);
        if ($parentPath === null) return $this->fail("I couldn't find {$parent} in the current navigation.");
        if (count($parentPath) >= 3) return $this->fail('Navigation nesting is limited to three levels.');

        // If the child already exists elsewhere, move it rather than duplicate it.
        $childPath = $this->findPathByLabel($menu, $child);
        $entry = null;
        if ($childPath !== null) {
            [$menu, $entry] = $this->removeAtPath($menu, $childPath);
            $parentPath = $this->findPathByLabel($menu, $parent); // indexes may shift
        }
        if (! is_array($entry)) $entry = array_merge(['label' => $child], $this->targetForLabelOrPrompt($website, $child, $prompt));

        $menu = $this->appendChild($menu, $parentPath, $entry);
        return $this->ok($menu, [$this->op('add', 'header.menu.'.implode('.children.', $parentPath).'.children', $entry)]);
    }

    private function removeMenuItem(array $menu, string $prompt): array
    {
        $label = null;
        if (preg_match('/\b(?:remove|delete)\s+["“]?(.+?)["”]?\s+(?:from\s+)?(?:(?:the|my)\s+)?(?:main\s+)?(?:menu|navigation|nav)\b/iu', $prompt, $m)) $label = $this->cleanLabel($m[1]);
        elseif (preg_match('/\b(?:remove|delete)\s+(?:menu\s+(?:item|link)\s+)?["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) $label = $this->cleanLabel($m[1]);
        if (! $label) return $this->fail('Tell Luna which menu item to remove.');
        $path = $this->findPathByLabel($menu, $label);
        if ($path === null) return $this->fail("I couldn't find {$label} in the current navigation.");
        [$menu, $removed] = $this->removeAtPath($menu, $path);
        return $this->ok($menu, [$this->op('remove', 'header.menu.'.implode('.', $path), $removed)]);
    }

    private function removeSubmenuItem(array $menu, string $prompt): array
    {
        $child = null;
        if (preg_match('/\b(?:remove|delete)\s+["“]?(.+?)["”]?\s+(?:from\s+under|under|from)\s+["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) $child = $this->cleanLabel($m[1]);
        elseif (preg_match('/\b(?:remove|delete)\s+(?:the\s+)?(?:submenu|sub-menu)\s+(?:item\s+)?["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) $child = $this->cleanLabel($m[1]);
        if (! $child) return $this->fail('Tell Luna which submenu item to remove.');
        $path = $this->findPathByLabel($menu, $child);
        if ($path === null || count($path) < 2) return $this->fail("I couldn't find {$child} as a submenu item.");
        [$menu, $removed] = $this->removeAtPath($menu, $path);
        return $this->ok($menu, [$this->op('remove', 'header.menu.'.implode('.', $path), $removed)]);
    }

    private function renameMenuItem(array $menu, string $prompt): array
    {
        $from = $to = null;
        if (preg_match('/\brename\s+["“]?(.+?)["”]?\s+to\s+["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) {
            $from = $this->cleanLabel($m[1]); $to = $this->cleanLabel($m[2]);
        } elseif (preg_match('/\bchange\s+["“]?(.+?)["”]?\s+(?:menu\s+)?label\s+to\s+["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) {
            $from = $this->cleanLabel($m[1]); $to = $this->cleanLabel($m[2]);
        }
        if (! $from || ! $to) return $this->fail('Use a clear rename such as “Rename About to Our Company”.');
        $path = $this->findPathByLabel($menu, $from);
        if ($path === null) return $this->fail("I couldn't find {$from} in the current navigation.");
        $menu = $this->updateAtPath($menu, $path, ['label' => $to]);
        return $this->ok($menu, [$this->op('update', 'header.menu.'.implode('.', $path).'.label', $to)]);
    }

    private function changeMenuLink(Website $website, array $menu, string $prompt): array
    {
        $label = $destination = null;
        if (preg_match('/\b(?:change|set|update)\s+["“]?(.+?)["”]?\s+(?:menu\s+)?(?:url|link|destination)\s+(?:to|as)\s+["“]?([^"”]+)["”]?\s*$/iu', trim($prompt), $m)) {
            $label = $this->cleanLabel($m[1]); $destination = trim($m[2]);
        } elseif (preg_match('/\b(?:change|set|update)\s+(?:the\s+)?(?:url|link|destination)\s+(?:for|of)\s+["“]?(.+?)["”]?\s+(?:to|as)\s+["“]?([^"”]+)["”]?\s*$/iu', trim($prompt), $m)) {
            $label = $this->cleanLabel($m[1]); $destination = trim($m[2]);
        }
        if (! $label || ! $destination) return $this->fail('Specify the menu item and its destination.');
        $path = $this->findPathByLabel($menu, $label);
        if ($path === null) return $this->fail("I couldn't find {$label} in the current navigation.");
        $target = $this->resolveDestination($website, $destination);
        if ($target === null) return $this->fail('That menu destination is not a valid internal page, path, anchor, email, phone, or URL.');
        $menu = $this->updateAtPath($menu, $path, $target);
        return $this->ok($menu, [$this->op('update', 'header.menu.'.implode('.', $path).'.destination', $target)]);
    }

    private function editMenuItem(Website $website, array $menu, string $prompt): ?array
    {
        // Keep ambiguous edits on the existing Luna planner rather than guessing.
        return null;
    }

    private function reorderMenu(array $menu, string $prompt): array
    {
        $label = $anchor = null; $mode = null;
        if (preg_match('/\bmove\s+["“]?(.+?)["”]?\s+(?:to\s+)?(?:the\s+)?(?:end|last)\b/iu', $prompt, $m)) { $label=$this->cleanLabel($m[1]); $mode='last'; }
        elseif (preg_match('/\bmove\s+["“]?(.+?)["”]?\s+(?:to\s+)?(?:the\s+)?(?:start|first|beginning)\b/iu', $prompt, $m)) { $label=$this->cleanLabel($m[1]); $mode='first'; }
        elseif (preg_match('/\bmove\s+["“]?(.+?)["”]?\s+(before|after)\s+["“]?(.+?)["”]?\s*$/iu', trim($prompt), $m)) { $label=$this->cleanLabel($m[1]); $mode=Str::lower($m[2]); $anchor=$this->cleanLabel($m[3]); }
        if (! $label || ! $mode) return $this->fail('Use a clear reorder such as “Move Contact to the end” or “Move Pricing before Contact”.');

        $path = $this->findPathByLabel($menu, $label);
        if ($path === null) return $this->fail("I couldn't find {$label} in the current navigation.");
        if (count($path) !== 1) return $this->fail('For safety, bounded reordering currently moves top-level menu items only.');
        $from = $path[0]; $entry = $menu[$from]; array_splice($menu, $from, 1);
        if ($mode === 'first') $to = 0;
        elseif ($mode === 'last') $to = count($menu);
        else {
            $anchorPath = $this->findPathByLabel($menu, $anchor ?? '');
            if ($anchorPath === null || count($anchorPath) !== 1) return $this->fail("I couldn't find {$anchor} as a top-level menu item.");
            $to = $anchorPath[0] + ($mode === 'after' ? 1 : 0);
        }
        array_splice($menu, $to, 0, [$entry]);
        return $this->ok($menu, [$this->op('reorder', 'header.menu', ['label'=>$entry['label'] ?? $label, 'position'=>$to])]);
    }

    private function createMenu(Website $website, array $menu, string $prompt): array
    {
        if (! preg_match('/\b(?:menu|navigation)\s+(?:with|containing|using)\s+(.+)$/iu', trim($prompt), $m)) {
            return $this->fail('Specify the menu items, for example “Create a menu with Home, About, Services, Contact”.');
        }
        $raw = preg_split('/\s*,\s*|\s+and\s+/iu', trim($m[1])) ?: [];
        $labels = array_values(array_filter(array_map(fn($v) => $this->cleanLabel($v), $raw)));
        if (count($labels) < 1 || count($labels) > 12) return $this->fail('A menu can be created with 1 to 12 clearly named top-level items.');
        $next = [];
        foreach ($labels as $label) $next[] = array_merge(['label'=>$label], $this->targetForLabelOrPrompt($website, $label, ''));
        return $this->ok($next, [$this->op('replace', 'header.menu', $next)]);
    }

    private function targetForLabelOrPrompt(Website $website, string $label, string $prompt): array
    {
        if ($prompt !== '' && preg_match('/(?:https?:\/\/[^\s"”]+|mailto:[^\s"”]+|tel:[^\s"”]+|\/[a-z0-9_\-\/]+|#[a-z0-9_\-]+)/iu', $prompt, $m)) {
            $resolved = $this->resolveDestination($website, $m[0]);
            if ($resolved !== null) return $resolved;
        }
        return $this->resolvePage($website, $label) ?? ['url' => '#'];
    }

    private function resolveDestination(Website $website, string $destination): ?array
    {
        $destination = trim($destination, " \t\n\r\0\x0B\"'“”.,;");
        if ($destination === '') return null;

        if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $destination)) {
            if (strlen($destination) > 500) return null;
            return ['url' => $destination, 'target_type' => 'external'];
        }
        if (str_starts_with($destination, '#')) return ['url' => $destination, 'target_type' => 'anchor'];

        $pageLookup = trim($destination, '/');
        $page = $website->pages()->where(function ($query) use ($pageLookup) {
            $query->whereRaw('LOWER(slug) = ?', [Str::lower($pageLookup)])
                ->orWhereRaw('LOWER(title) = ?', [Str::lower(str_replace('-', ' ', $pageLookup))]);
        })->first();
        if ($page) return $this->pageTarget($page);

        if (str_starts_with($destination, '/')) return ['url' => '/'.trim($destination, '/'), 'target_type' => 'path'];
        return null;
    }

    private function resolvePage(Website $website, string $label): ?array
    {
        $slug = Str::slug($label);
        $lower = Str::lower(trim($label));
        $page = $website->pages()->where(function ($query) use ($slug, $lower) {
            $query->whereRaw('LOWER(slug) = ?', [$slug])->orWhereRaw('LOWER(title) = ?', [$lower]);
        })->first();
        return $page ? $this->pageTarget($page) : null;
    }

    private function pageTarget(Page $page): array
    {
        $slug = trim((string) $page->slug, '/');
        return ['url' => $slug === '' || $slug === 'home' ? '/' : '/'.$slug, 'target_type'=>'page', 'page_id'=>(int)$page->id, 'page_slug'=>$slug];
    }

    private function normalizeMenu(array $items, int $depth = 0): array
    {
        if ($depth >= 3) return [];
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $label = $this->cleanLabel((string) ($item['label'] ?? ''));
            if (! $label) continue;
            $entry = $item;
            $entry['label'] = $label;
            $entry['url'] = is_string($item['url'] ?? null) ? trim($item['url']) : '#';
            if (isset($item['children']) && is_array($item['children'])) $entry['children'] = $this->normalizeMenu($item['children'], $depth + 1);
            $out[] = $entry;
        }
        return $out;
    }

    private function findPathByLabel(array $menu, string $label, array $prefix = []): ?array
    {
        $needle = Str::lower(trim($label));
        foreach ($menu as $i => $item) {
            if (Str::lower(trim((string) ($item['label'] ?? ''))) === $needle) return [...$prefix, $i];
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $found = $this->findPathByLabel($children, $label, [...$prefix, $i]);
                if ($found !== null) return $found;
            }
        }
        return null;
    }

    private function updateAtPath(array $menu, array $path, array $changes): array
    {
        $i = array_shift($path);
        if ($i === null || ! isset($menu[$i])) return $menu;
        if ($path === []) $menu[$i] = array_merge($menu[$i], $changes);
        else {
            $children = is_array($menu[$i]['children'] ?? null) ? $menu[$i]['children'] : [];
            $menu[$i]['children'] = $this->updateAtPath($children, $path, $changes);
        }
        return $menu;
    }

    private function appendChild(array $menu, array $path, array $entry): array
    {
        $i = array_shift($path);
        if ($i === null || ! isset($menu[$i])) return $menu;
        if ($path === []) {
            $children = is_array($menu[$i]['children'] ?? null) ? $menu[$i]['children'] : [];
            $children[] = $entry; $menu[$i]['children'] = $children;
        } else {
            $children = is_array($menu[$i]['children'] ?? null) ? $menu[$i]['children'] : [];
            $menu[$i]['children'] = $this->appendChild($children, $path, $entry);
        }
        return $menu;
    }

    private function removeAtPath(array $menu, array $path): array
    {
        $i = array_shift($path);
        if ($i === null || ! isset($menu[$i])) return [$menu, null];
        if ($path === []) {
            $removed = $menu[$i]; array_splice($menu, $i, 1); return [$menu, $removed];
        }
        $children = is_array($menu[$i]['children'] ?? null) ? $menu[$i]['children'] : [];
        [$children, $removed] = $this->removeAtPath($children, $path);
        $menu[$i]['children'] = $children;
        return [$menu, $removed];
    }

    private function cleanLabel(string $value): ?string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'“”.,;");
        $value = preg_replace('/\s+(?:menu|navigation|nav)\s+(?:item|link)$/iu', '', $value) ?? $value;
        if ($value === '' || mb_strlen($value) > 80) return null;
        return $value;
    }

    private function ok(array $menu, array $operations): array { return ['success'=>true,'menu'=>$menu,'operations'=>$operations]; }
    private function fail(string $message): array { return ['success'=>false,'message'=>$message]; }
    private function op(string $action, string $target, mixed $value): array { return ['action'=>$action,'target'=>$target,'value'=>$value,'verified'=>true]; }
}
