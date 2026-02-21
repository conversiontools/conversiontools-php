<?php

declare(strict_types=1);

namespace ConversionTools\Utils;

use ConversionTools\Exceptions\TimeoutException;

class Polling
{
    /**
     * Poll a callable until a condition is met, with exponential backoff.
     *
     * @param  callable(): array      $getStatus
     * @param  callable(array): bool  $shouldContinue  Return true to keep polling
     * @param  callable(array): void|null $onProgress
     */
    public static function poll(
        callable $getStatus,
        callable $shouldContinue,
        float $intervalMs,
        float $maxIntervalMs,
        float $backoff,
        float $timeoutMs = 0,
        ?callable $onProgress = null,
    ): array {
        $startMs         = microtime(true) * 1000;
        $currentInterval = $intervalMs;

        while (true) {
            $result = $getStatus();

            if (!$shouldContinue($result)) {
                return $result;
            }

            if ($timeoutMs > 0 && (microtime(true) * 1000 - $startMs) >= $timeoutMs) {
                throw new TimeoutException("Polling timed out after {$timeoutMs}ms", $timeoutMs);
            }

            if ($onProgress !== null) {
                $onProgress($result);
            }

            usleep((int) ($currentInterval * 1000));

            $currentInterval = min($currentInterval * $backoff, $maxIntervalMs);
        }
    }

    /**
     * Poll a task status endpoint until the task reaches a terminal state.
     *
     * @param  callable(): array      $getStatus
     * @param  callable(array): void|null $onProgress
     */
    public static function pollTaskStatus(
        callable $getStatus,
        float $intervalMs,
        float $maxIntervalMs,
        float $backoff,
        float $timeoutMs = 0,
        ?callable $onProgress = null,
    ): array {
        return self::poll(
            $getStatus,
            fn(array $status): bool => in_array($status['status'], ['PENDING', 'RUNNING'], true),
            $intervalMs,
            $maxIntervalMs,
            $backoff,
            $timeoutMs,
            $onProgress,
        );
    }
}
