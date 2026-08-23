<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class LunaCapabilityRegistry
{
    private array $registry;

    public function __construct()
    {
        $this->registry = config('luna_capabilities', []);
    }

    public function all(): array
    {
        return array_values($this->registry['capabilities'] ?? []);
    }

    public function find(string $id): ?array
    {
        return collect($this->all())->first(
            fn (array $capability) => ($capability['id'] ?? null) === $id
        );
    }

    public function byCategory(string $category): array
    {
        return collect($this->all())
            ->filter(fn (array $capability) => ($capability['category'] ?? null) === $category)
            ->values()
            ->all();
    }

    public function byStatus(string $status): array
    {
        return collect($this->all())
            ->filter(fn (array $capability) => ($capability['status'] ?? null) === $status)
            ->values()
            ->all();
    }

    public function searchableSummary(array $capability): array
    {
        return Arr::only($capability, [
            'id',
            'name',
            'category',
            'status',
            'scopes',
            'manual',
            'luna',
            'confirmation',
            'credit_behavior',
            'can_do',
            'cannot_do',
            'limits',
            'fallback',
            'verification',
            'tags',
            'aliases',
            'keywords',
            'doc_refs',
        ]);
    }

    public function summaries(?string $category = null): array
    {
        $items = $category ? $this->byCategory($category) : $this->all();

        return collect($items)
            ->map(fn (array $capability) => $this->searchableSummary($capability))
            ->values()
            ->all();
    }

    public function statuses(): array
    {
        return array_values($this->registry['statuses'] ?? []);
    }

    public function isExecutable(string $id): bool
    {
        $capability = $this->find($id);
        if (! $capability) return false;

        return ($capability['luna'] ?? false)
            && in_array(($capability['status'] ?? ''), ['supported', 'partially_supported'], true);
    }
}
