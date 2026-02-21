<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class ConversionToolsException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'UNKNOWN_ERROR',
        public readonly ?int $httpStatus = null,
        public readonly mixed $response = null,
    ) {
        parent::__construct($message);
    }
}
