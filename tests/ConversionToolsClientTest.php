<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use ConversionTools\Api\FilesApi;
use ConversionTools\Api\TasksApi;
use ConversionTools\ConversionToolsClient;
use ConversionTools\Exceptions\ValidationException;

class ConversionToolsClientTest extends TestCase
{
    private string $validToken = 'test-api-token-abc123';

    // ── Constructor ───────────────────────────────────────────────────────────

    public function testConstructsWithValidToken(): void
    {
        $client = new ConversionToolsClient(['api_token' => $this->validToken]);
        $this->assertInstanceOf(ConversionToolsClient::class, $client);
    }

    public function testThrowsOnEmptyToken(): void
    {
        $this->expectException(ValidationException::class);
        new ConversionToolsClient(['api_token' => '']);
    }

    public function testThrowsOnWhitespaceToken(): void
    {
        $this->expectException(ValidationException::class);
        new ConversionToolsClient(['api_token' => '   ']);
    }

    // ── Public API surface ────────────────────────────────────────────────────

    public function testExposesFilesApi(): void
    {
        $client = new ConversionToolsClient(['api_token' => $this->validToken]);
        $this->assertInstanceOf(FilesApi::class, $client->files);
    }

    public function testExposesTasksApi(): void
    {
        $client = new ConversionToolsClient(['api_token' => $this->validToken]);
        $this->assertInstanceOf(TasksApi::class, $client->tasks);
    }

    public function testVersionConstantIsDefined(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', ConversionToolsClient::VERSION);
    }

    // ── Config defaults ───────────────────────────────────────────────────────

    public function testAcceptsCustomConfig(): void
    {
        $client = new ConversionToolsClient([
            'api_token'        => $this->validToken,
            'timeout'          => 60_000,
            'retries'          => 5,
            'polling_interval' => 2_000,
            'user_agent'       => 'my-app/1.0',
        ]);

        // Constructor succeeded — config was accepted
        $this->assertInstanceOf(ConversionToolsClient::class, $client);
    }

    public function testAcceptsProgressCallbacks(): void
    {
        $client = new ConversionToolsClient([
            'api_token'              => $this->validToken,
            'on_upload_progress'     => static function (array $p): void {},
            'on_download_progress'   => static function (array $p): void {},
            'on_conversion_progress' => static function (array $p): void {},
        ]);

        $this->assertInstanceOf(ConversionToolsClient::class, $client);
    }

    public function testAcceptsWebhookUrl(): void
    {
        $client = new ConversionToolsClient([
            'api_token'   => $this->validToken,
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $this->assertInstanceOf(ConversionToolsClient::class, $client);
    }

    // ── getRateLimits() ───────────────────────────────────────────────────────

    public function testGetRateLimitsReturnsNullInitially(): void
    {
        $client = new ConversionToolsClient(['api_token' => $this->validToken]);
        $this->assertNull($client->getRateLimits());
    }
}
