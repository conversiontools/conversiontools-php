<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class AuthenticationException extends ConversionToolsException
{
    public function __construct(string $message = 'Not authorized - Invalid or missing API token')
    {
        parent::__construct($message, 'AUTHENTICATION_ERROR', 401);
    }
}
