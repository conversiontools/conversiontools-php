<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class FileNotFoundException extends ConversionToolsException
{
    public function __construct(
        string $message = 'File not found',
        public readonly ?string $fileId = null,
    ) {
        parent::__construct($message, 'FILE_NOT_FOUND', 404);
    }
}
