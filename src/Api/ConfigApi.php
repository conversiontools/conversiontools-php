<?php

declare(strict_types=1);

namespace ConversionTools\Api;

use ConversionTools\Http\HttpClient;

class ConfigApi
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Get authenticated user information.
     */
    public function getUserInfo(): array
    {
        return $this->http->get('/auth');
    }

    /**
     * Get API configuration (available conversion types).
     */
    public function getConfig(): array
    {
        return $this->http->get('/config');
    }
}
