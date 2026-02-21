<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class ValidationException extends ConversionToolsException
{
    public function __construct(string $message, mixed $response = null)
    {
        parent::__construct($message, 'VALIDATION_ERROR', 400, $response);
    }
}
