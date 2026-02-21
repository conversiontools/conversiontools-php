<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use ConversionTools\Exceptions\TimeoutException;
use ConversionTools\Utils\Polling;

class PollingTest extends TestCase
{
    // ── pollTaskStatus ────────────────────────────────────────────────────────

    public function testReturnsImmediatelyOnSuccess(): void
    {
        $calls = 0;

        $result = Polling::pollTaskStatus(
            function () use (&$calls): array {
                $calls++;
                return ['status' => 'SUCCESS', 'file_id' => 'abc', 'conversionProgress' => 100];
            },
            intervalMs: 1,
            maxIntervalMs: 1,
            backoff: 1.0,
        );

        $this->assertSame('SUCCESS', $result['status']);
        $this->assertSame(1, $calls);
    }

    public function testReturnsImmediatelyOnError(): void
    {
        $result = Polling::pollTaskStatus(
            fn(): array => ['status' => 'ERROR', 'file_id' => null, 'conversionProgress' => 0],
            intervalMs: 1,
            maxIntervalMs: 1,
            backoff: 1.0,
        );

        $this->assertSame('ERROR', $result['status']);
    }

    public function testPollsUntilTerminalState(): void
    {
        $calls = 0;

        $result = Polling::pollTaskStatus(
            function () use (&$calls): array {
                $calls++;
                return [
                    'status'             => $calls >= 3 ? 'SUCCESS' : 'PENDING',
                    'file_id'            => null,
                    'conversionProgress' => 0,
                ];
            },
            intervalMs: 1,
            maxIntervalMs: 100,
            backoff: 1.0,
        );

        $this->assertSame('SUCCESS', $result['status']);
        $this->assertSame(3, $calls);
    }

    public function testPollsWhileRunning(): void
    {
        $calls = 0;

        Polling::pollTaskStatus(
            function () use (&$calls): array {
                $calls++;
                $status = match ($calls) {
                    1 => 'PENDING',
                    2 => 'RUNNING',
                    default => 'SUCCESS',
                };
                return ['status' => $status, 'file_id' => null, 'conversionProgress' => 0];
            },
            intervalMs: 1,
            maxIntervalMs: 100,
            backoff: 1.0,
        );

        $this->assertSame(3, $calls);
    }

    public function testOnProgressIsCalledOnEachNonTerminalPoll(): void
    {
        $progressCalls = [];
        $calls         = 0;

        Polling::pollTaskStatus(
            function () use (&$calls): array {
                $calls++;
                return [
                    'status'             => $calls >= 3 ? 'SUCCESS' : 'PENDING',
                    'file_id'            => null,
                    'conversionProgress' => $calls * 10,
                ];
            },
            intervalMs: 1,
            maxIntervalMs: 100,
            backoff: 1.0,
            onProgress: function (array $status) use (&$progressCalls): void {
                $progressCalls[] = $status['conversionProgress'];
            },
        );

        // onProgress is called for non-terminal results (calls 1 and 2)
        $this->assertCount(2, $progressCalls);
        $this->assertSame(10, $progressCalls[0]);
        $this->assertSame(20, $progressCalls[1]);
    }

    public function testThrowsTimeoutException(): void
    {
        $this->expectException(TimeoutException::class);

        Polling::pollTaskStatus(
            fn(): array => ['status' => 'PENDING', 'file_id' => null, 'conversionProgress' => 0],
            intervalMs: 1,
            maxIntervalMs: 100,
            backoff: 1.0,
            timeoutMs: 5, // 5ms — will time out before status changes
        );
    }

    // ── poll (generic) ────────────────────────────────────────────────────────

    public function testGenericPollStopsWhenConditionFalse(): void
    {
        $calls = 0;

        $result = Polling::poll(
            function () use (&$calls): array {
                $calls++;
                return ['count' => $calls];
            },
            shouldContinue: fn(array $v): bool => $v['count'] < 3,
            intervalMs: 1,
            maxIntervalMs: 100,
            backoff: 1.0,
        );

        $this->assertSame(3, $result['count']);
        $this->assertSame(3, $calls);
    }
}
