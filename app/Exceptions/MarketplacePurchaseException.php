<?php

namespace App\Exceptions;

use RuntimeException;

final class MarketplacePurchaseException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $context = [],
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
