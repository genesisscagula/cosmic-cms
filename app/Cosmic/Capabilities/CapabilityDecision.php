<?php

namespace App\Cosmic\Capabilities;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class CapabilityDecision implements Arrayable, JsonSerializable
{
    public function __construct(
        public bool $allowed,
        public string $capability,
        public mixed $value = null,
        public mixed $required = true,
        public ?string $reason = null,
        public ?string $upgradeMessage = null,
    ) {
    }

    public static function allow(string $capability, mixed $value = null, mixed $required = true): self
    {
        return new self(true, $capability, $value, $required);
    }

    public static function deny(
        string $capability,
        mixed $value = null,
        mixed $required = true,
        ?string $reason = null,
        ?string $upgradeMessage = null,
    ): self {
        return new self(false, $capability, $value, $required, $reason, $upgradeMessage);
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'capability' => $this->capability,
            'value' => $this->value,
            'required' => $this->required,
            'reason' => $this->reason,
            'upgrade_message' => $this->upgradeMessage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
