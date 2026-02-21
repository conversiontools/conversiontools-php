<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class TimeoutException extends ConversionToolsException
{
    public function __construct(
        string $message = 'Operation timed out',
        public readonly ?float $timeout = null,
    ) {
        parent::__construct($message, 'TIMEOUT_ERROR', 408);
    }
}
