<?php

declare(strict_types=1);

namespace ConversionTools\Api;

use ConversionTools\Http\HttpClient;
use ConversionTools\Utils\Validation;

class TasksApi
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Create a new conversion task.
     *
     * @param  array{type: string, options: array, callbackUrl?: string} $request
     */
    public function create(array $request): array
    {
        Validation::validateConversionType($request['type']);

        $body   = json_encode($request, JSON_THROW_ON_ERROR);
        $result = $this->http->post('/tasks', $body);

        return $result;
    }

    /**
     * Get the status of a task.
     */
    public function getStatus(string $taskId): array
    {
        Validation::validateTaskId($taskId);
        return $this->http->get('/tasks/' . rawurlencode($taskId));
    }

    /**
     * List all tasks, optionally filtered by status.
     *
     * @param  'PENDING'|'RUNNING'|'SUCCESS'|'ERROR'|null $status
     */
    public function list(?string $status = null): array
    {
        $path = '/tasks';

        if ($status !== null) {
            $path .= '?status=' . rawurlencode($status);
        }

        $result = $this->http->get($path);

        return $result['data'] ?? [];
    }
}
