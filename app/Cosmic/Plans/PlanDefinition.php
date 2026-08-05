<?php

namespace App\Cosmic\Plans;

use App\Cosmic\Contracts\PlanInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class PlanDefinition implements Arrayable, JsonSerializable, PlanInterface
{
    public function __construct(
        private string $planKey,
        private array $definition,
    ) {
    }

    public function key(): string
    {
        return $this->planKey;
    }

    public function name(): string
    {
        return (string) ($this->definition['label'] ?? str($this->planKey)->headline());
    }

    public function family(): string
    {
        return (string) ($this->definition['family'] ?? 'personal');
    }

    public function tier(): string
    {
        return (string) ($this->definition['tier'] ?? 'starter');
    }

    public function rank(): int
    {
        return (int) ($this->definition['rank'] ?? 0);
    }

    public function price(): int
    {
        return (int) ($this->definition['price_usd'] ?? 0);
    }

    public function credits(): int
    {
        return (int) ($this->definition['credits'] ?? 0);
    }

    public function description(): string
    {
        return (string) ($this->definition['description'] ?? '');
    }

    public function billing(): array
    {
        return (array) ($this->definition['billing'] ?? []);
    }

    public function billingValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->billing(), $key, $default);
    }

    public function capabilities(): array
    {
        return (array) ($this->definition['capabilities'] ?? []);
    }

    public function can(string $capability, mixed $expected = true): bool
    {
        return data_get($this->capabilities(), $capability) === $expected;
    }

    public function limit(string $capability, mixed $default = null): mixed
    {
        return data_get($this->capabilities(), $capability, $default);
    }

    public function hasUnlimited(string $capability): bool
    {
        $value = $this->limit($capability);

        return $value === null || $value === 'unlimited';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->name(),
            'family' => $this->family(),
            'tier' => $this->tier(),
            'rank' => $this->rank(),
            'price_usd' => $this->price(),
            'credits' => $this->credits(),
            'description' => $this->description(),
            'billing' => $this->billing(),
            'capabilities' => $this->capabilities(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
