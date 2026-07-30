<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct("Not enough Cosmic Credits. Required: {$required}; available: {$available}.");
    }

    public function render(Request $request)
    {
        $payload = [
            'message' => $this->getMessage(),
            'required_credits' => $this->required,
            'available_credits' => $this->available,
        ];

        return $request->expectsJson()
            ? response()->json($payload, 422)
            : back()->withErrors(['credits' => $this->getMessage()]);
    }
}
