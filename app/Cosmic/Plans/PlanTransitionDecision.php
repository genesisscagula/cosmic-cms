<?php

namespace App\Cosmic\Plans;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class PlanTransitionDecision implements Arrayable, JsonSerializable
{
    public function __construct(
        public PlanDefinition $from,
        public PlanDefinition $to,
        public string $type,
        public bool $allowed,
        public bool $familyChange,
        public array $warnings = [],
        public array $requirements = [],
        public ?string $reason = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->key(),
            'to' => $this->to->key(),
            'type' => $this->type,
            'allowed' => $this->allowed,
            'family_change' => $this->familyChange,
            'warnings' => $this->warnings,
            'requirements' => $this->requirements,
            'reason' => $this->reason,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
