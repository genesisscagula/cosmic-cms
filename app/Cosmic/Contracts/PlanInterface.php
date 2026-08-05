<?php

namespace App\Cosmic\Contracts;

interface PlanInterface
{
    public function key(): string;

    public function name(): string;

    public function family(): string;

    public function tier(): string;

    public function price(): int;

    public function credits(): int;

    public function can(string $capability, mixed $expected = true): bool;

    public function limit(string $capability, mixed $default = null): mixed;

    public function hasUnlimited(string $capability): bool;

    public function toArray(): array;
}
