<?php

declare(strict_types=1);

namespace ConversionTools\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\ResponseInterface;
use ConversionTools\Exceptions\AuthenticationException;
use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\FileNotFoundException;
use ConversionTools\Exceptions\NetworkException;
use ConversionTools\Exceptions\RateLimitException;
use ConversionTools\Exceptions\TaskNotFoundException;
use ConversionTools\Exceptions\TimeoutException;
use ConversionTools\Exceptions\ValidationException;
use ConversionTools\Utils\Retry;

class HttpClient
{
    private readonly Client $client;
    private ?array $lastRateLimits = null;

    public function __construct(
        private readonly string $apiToken,
        private readonly string $baseUrl,
        private readonly float $timeoutMs,
        private readonly int $retries,
        private readonly float $retryDelayMs,
        private readonly array $retryableStatuses,
        private readonly string $userAgent,
    ) {
        $this->client = new Client([
            'timeout'     => $this->timeoutMs / 1000,
            'http_errors' => false,
        ]);
    }

    public function getLastRateLimits(): ?array
    {
        return $this->lastRateLimits;
    }

    private function baseHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'User-Agent'    => $this->userAgent,
        ];
    }

    private function extractRateLimits(ResponseInterface $response): void
    {
        $limits = [];

        $dailyLimit     = $response->getHeaderLine('x-ratelimit-limit-tasks');
        $dailyRemaining = $response->getHeaderLine('x-ratelimit-limit-tasks-remaining');
        if ($dailyLimit !== '' && $dailyRemaining !== '') {
            $limits['daily'] = ['limit' => (int) $dailyLimit, 'remaining' => (int) $dailyRemaining];
        }

        $monthlyLimit     = $response->getHeaderLine('x-ratelimit-limit-tasks-monthly');
        $monthlyRemaining = $response->getHeaderLine('x-ratelimit-limit-tasks-monthly-remaining');
        if ($monthlyLimit !== '' && $monthlyRemaining !== '') {
            $limits['monthly'] = ['limit' => (int) $monthlyLimit, 'remaining' => (int) $monthlyRemaining];
        }

        $fileSize = $response->getHeaderLine('x-ratelimit-limit-filesize');
        if ($fileSize !== '') {
            $limits['file_size'] = (int) $fileSize;
        }

        if (!empty($limits)) {
            $this->lastRateLimits = $limits;
        }
    }

    /** @return never */
    private function handleErrorResponse(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();

        $errorMessage = $response->getReasonPhrase();
        $errorData    = null;

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            if (!empty($decoded['error'])) {
                $errorMessage = $decoded['error'];
            }
            $errorData = $decoded;
        } catch (\JsonException) {
            // keep reason phrase
        }

        $errorLower = strtolower($errorMessage);

        match (true) {
            $status === 401 => throw new AuthenticationException($errorMessage),
            $status === 400 => throw new ValidationException($errorMessage, $errorData),
            $status === 404 && str_contains($errorLower, 'file') => throw new FileNotFoundException($errorMessage),
            $status === 404 && str_contains($errorLower, 'task') => throw new TaskNotFoundException($errorMessage),
            $status === 404 => throw new ConversionToolsException($errorMessage, 'NOT_FOUND', 404, $errorData),
            $status === 429 => throw new RateLimitException($errorMessage, $this->lastRateLimits),
            $status === 408 => throw new TimeoutException($errorMessage),
            default         => throw new ConversionToolsException($errorMessage, 'HTTP_ERROR', $status, $errorData),
        };
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function getRaw(string $path): string
    {
        return $this->request('GET', $path, raw: true);
    }

    /**
     * Download a file as a lazy stream (no buffering into memory).
     * Caller is responsible for consuming and closing the stream.
     */
    public function getStream(string $path): ResponseInterface
    {
        return Retry::withRetry(
            function () use ($path): ResponseInterface {
                try {
                    $response = $this->client->get($this->baseUrl . $path, [
                        'headers' => $this->baseHeaders(),
                        'stream'  => true,
                    ]);
                } catch (ConnectException $e) {
                    throw new NetworkException('Network request failed: ' . $e->getMessage(), $e);
                }

                $this->extractRateLimits($response);

                if ($response->getStatusCode() >= 400) {
                    $this->handleErrorResponse($response);
                }

                return $response;
            },
            $this->retries,
            $this->retryDelayMs,
            $this->retryableStatuses,
        );
    }

    /**
     * Download a file to $outputPath and return the response (for header access).
     */
    public function downloadToFile(string $path, string $outputPath, ?callable $onProgress = null): ResponseInterface
    {
        return Retry::withRetry(
            function () use ($path, $outputPath, $onProgress): ResponseInterface {
                $dir = dirname($outputPath);
                if ($dir !== '' && !is_dir($dir)) {
                    mkdir($dir, 0755, recursive: true);
                }

                $options = [
                    'headers' => $this->baseHeaders(),
                    'sink'    => $outputPath,
                ];

                if ($onProgress !== null) {
                    $options['progress'] = function (int $dlTotal, int $dlNow) use ($onProgress): void {
                        if ($dlTotal > 0) {
                            ($onProgress)([
                                'loaded'  => $dlNow,
                                'total'   => $dlTotal,
                                'percent' => (int) round($dlNow / $dlTotal * 100),
                            ]);
                        }
                    };
                }

                try {
                    $response = $this->client->get($this->baseUrl . $path, $options);
                } catch (ConnectException $e) {
                    throw new NetworkException('Network request failed: ' . $e->getMessage(), $e);
                }

                $this->extractRateLimits($response);

                if ($response->getStatusCode() >= 400) {
                    @unlink($outputPath);
                    $this->handleErrorResponse($response);
                }

                return $response;
            },
            $this->retries,
            $this->retryDelayMs,
            $this->retryableStatuses,
        );
    }

    public function post(string $path, string $jsonBody): array
    {
        return $this->request('POST', $path, jsonBody: $jsonBody);
    }

    /**
     * Upload a file using a closure factory so retries can reopen/rewind the stream.
     *
     * @param \Closure(): mixed $contentsFactory Returns fresh file contents on each call.
     */
    public function upload(string $path, \Closure $contentsFactory, string $filename, ?callable $onProgress = null): array
    {
        return Retry::withRetry(
            function () use ($path, $contentsFactory, $filename, $onProgress): array {
                $options = [
                    'headers'   => $this->baseHeaders(),
                    'multipart' => [
                        [
                            'name'     => 'file',
                            'contents' => $contentsFactory(),
                            'filename' => $filename,
                        ],
                    ],
                ];

                if ($onProgress !== null) {
                    $options['progress'] = function (int $dlTotal, int $dlNow, int $ulTotal, int $ulNow) use ($onProgress): void {
                        if ($ulTotal > 0) {
                            ($onProgress)([
                                'loaded'  => $ulNow,
                                'total'   => $ulTotal,
                                'percent' => (int) round($ulNow / $ulTotal * 100),
                            ]);
                        }
                    };
                }

                try {
                    $response = $this->client->post($this->baseUrl . $path, $options);
                } catch (ConnectException $e) {
                    throw new NetworkException('Network request failed: ' . $e->getMessage(), $e);
                }

                $this->extractRateLimits($response);

                if ($response->getStatusCode() >= 400) {
                    $this->handleErrorResponse($response);
                }

                return $this->parseJsonResponse($response);
            },
            $this->retries,
            $this->retryDelayMs,
            $this->retryableStatuses,
        );
    }

    private function request(string $method, string $path, ?string $jsonBody = null, bool $raw = false): mixed
    {
        return Retry::withRetry(
            function () use ($method, $path, $jsonBody, $raw): mixed {
                $headers = $this->baseHeaders();

                if ($jsonBody !== null) {
                    $headers['Content-Type'] = 'application/json';
                }

                $options = ['headers' => $headers];

                if ($jsonBody !== null) {
                    $options['body'] = $jsonBody;
                }

                try {
                    $response = $this->client->request($method, $this->baseUrl . $path, $options);
                } catch (ConnectException $e) {
                    throw new NetworkException('Network request failed: ' . $e->getMessage(), $e);
                }

                $this->extractRateLimits($response);

                if ($response->getStatusCode() >= 400) {
                    $this->handleErrorResponse($response);
                }

                if ($raw) {
                    return (string) $response->getBody();
                }

                return $this->parseJsonResponse($response);
            },
            $this->retries,
            $this->retryDelayMs,
            $this->retryableStatuses,
        );
    }

    private function parseJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConversionToolsException('Invalid JSON response: ' . $e->getMessage(), 'PARSE_ERROR');
        }

        if (isset($data['error']) && $data['error'] !== null) {
            throw new ConversionToolsException($data['error'], 'API_ERROR', $response->getStatusCode(), $data);
        }

        return $data;
    }
}
