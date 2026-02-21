<?php

declare(strict_types=1);

namespace ConversionTools\Exceptions;

class TaskNotFoundException extends ConversionToolsException
{
    public function __construct(
        string $message = 'Task not found',
        public readonly ?string $taskId = null,
    ) {
        parent::__construct($message, 'TASK_NOT_FOUND', 404);
    }
}
