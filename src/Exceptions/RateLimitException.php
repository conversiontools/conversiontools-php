<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class RateLimitException extends ConversionToolsException
{
    public function __construct(
        string $message = 'Rate limit exceeded. Upgrade your plan at https://conversiontools.io/pricing',
        public readonly ?array $limits = null,
    ) {
        parent::__construct($message, 'RATE_LIMIT_EXCEEDED', 429);
    }
}
