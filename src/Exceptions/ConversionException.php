<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class ConversionException extends ConversionToolsException
{
    public function __construct(
        string $message,
        public readonly ?string $taskId = null,
        public readonly ?string $taskError = null,
    ) {
        parent::__construct($message, 'CONVERSION_ERROR');
    }
}
