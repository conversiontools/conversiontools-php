<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class NetworkException extends ConversionToolsException
{
    public function __construct(
        string $message,
        public readonly ?\Throwable $originalError = null,
    ) {
        parent::__construct($message, 'NETWORK_ERROR');
    }
}
