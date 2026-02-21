<?php

declare(strict_types=1);

namespace ConversionTools\Utils;

use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\NetworkException;
use ConversionTools\Exceptions\TimeoutException;

class Retry
{
    /**
     * Execute a callable with retry logic and exponential backoff.
     *
     * @param  callable(): mixed $fn
     * @param  list<int>         $retryableStatuses
     */
    public static function withRetry(
        callable $fn,
        int $retries,
        float $retryDelayMs,
        array $retryableStatuses,
    ): mixed {
        $lastError = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                return $fn();
            } catch (\Throwable $error) {
                $lastError = $error;

                if ($attempt >= $retries) {
                    break;
                }

                if (!self::shouldRetry($error, $retryableStatuses)) {
                    throw $error;
                }

                $delayMs = $retryDelayMs * (2 ** $attempt);
                usleep((int) ($delayMs * 1000));
            }
        }

        throw $lastError;
    }

    private static function shouldRetry(\Throwable $error, array $retryableStatuses): bool
    {
        if ($error instanceof NetworkException) {
            return true;
        }

        if ($error instanceof ConversionToolsException && $error->httpStatus !== null) {
            return in_array($error->httpStatus, $retryableStatuses, true);
        }

        return false;
    }
}
