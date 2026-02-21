<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\NetworkException;
use ConversionTools\Exceptions\ValidationException;
use ConversionTools\Utils\Retry;

class RetryTest extends TestCase
{
    public function testSuccessOnFirstAttempt(): void
    {
        $calls  = 0;
        $result = Retry::withRetry(
            function () use (&$calls): string {
                $calls++;
                return 'ok';
            },
            retries: 3,
            retryDelayMs: 0,
            retryableStatuses: [],
        );

        $this->assertSame('ok', $result);
        $this->assertSame(1, $calls);
    }

    public function testReturnsValueFromCallable(): void
    {
        $result = Retry::withRetry(fn(): int => 42, 1, 0, []);
        $this->assertSame(42, $result);
    }

    public function testRetriesOnNetworkException(): void
    {
        $calls = 0;

        $result = Retry::withRetry(
            function () use (&$calls): string {
                $calls++;
                if ($calls < 3) {
                    throw new NetworkException('connection refused');
                }
                return 'recovered';
            },
            retries: 3,
            retryDelayMs: 0,
            retryableStatuses: [],
        );

        $this->assertSame('recovered', $result);
        $this->assertSame(3, $calls);
    }

    public function testRetriesOnRetryableStatusCode(): void
    {
        $calls = 0;

        $result = Retry::withRetry(
            function () use (&$calls): string {
                $calls++;
                if ($calls < 2) {
                    throw new ConversionToolsException('Service unavailable', 'HTTP_ERROR', 503);
                }
                return 'ok';
            },
            retries: 3,
            retryDelayMs: 0,
            retryableStatuses: [503],
        );

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function testDoesNotRetryOnNonRetryableStatusCode(): void
    {
        $calls = 0;

        $this->expectException(ConversionToolsException::class);

        Retry::withRetry(
            function () use (&$calls): never {
                $calls++;
                throw new ConversionToolsException('Bad request', 'HTTP_ERROR', 400);
            },
            retries: 3,
            retryDelayMs: 0,
            retryableStatuses: [503],
        );

        $this->assertSame(1, $calls);
    }

    public function testDoesNotRetryValidationException(): void
    {
        $calls = 0;

        $this->expectException(ValidationException::class);

        Retry::withRetry(
            function () use (&$calls): never {
                $calls++;
                throw new ValidationException('Invalid input');
            },
            retries: 3,
            retryDelayMs: 0,
            retryableStatuses: [503],
        );

        $this->assertSame(1, $calls);
    }

    public function testThrowsAfterMaxRetries(): void
    {
        $calls = 0;

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('always fails');

        Retry::withRetry(
            function () use (&$calls): never {
                $calls++;
                throw new NetworkException('always fails');
            },
            retries: 2,
            retryDelayMs: 0,
            retryableStatuses: [],
        );

        $this->assertSame(3, $calls); // initial + 2 retries
    }

    public function testZeroRetriesMeansOneAttempt(): void
    {
        $calls = 0;

        $this->expectException(NetworkException::class);

        Retry::withRetry(
            function () use (&$calls): never {
                $calls++;
                throw new NetworkException('fail');
            },
            retries: 0,
            retryDelayMs: 0,
            retryableStatuses: [],
        );

        $this->assertSame(1, $calls);
    }

    public function testReturnsNullFromCallable(): void
    {
        $result = Retry::withRetry(fn(): mixed => null, 1, 0, []);
        $this->assertNull($result);
    }
}
