<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use ConversionTools\Exceptions\AuthenticationException;
use ConversionTools\Exceptions\ConversionException;
use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\FileNotFoundException;
use ConversionTools\Exceptions\NetworkException;
use ConversionTools\Exceptions\RateLimitException;
use ConversionTools\Exceptions\TaskNotFoundException;
use ConversionTools\Exceptions\TimeoutException;
use ConversionTools\Exceptions\ValidationException;

class ExceptionsTest extends TestCase
{
    // ── ConversionToolsException (base) ──────────────────────────────────────

    public function testBaseExceptionMessage(): void
    {
        $e = new ConversionToolsException('Something went wrong');
        $this->assertSame('Something went wrong', $e->getMessage());
    }

    public function testBaseExceptionDefaults(): void
    {
        $e = new ConversionToolsException('msg');
        $this->assertSame('UNKNOWN_ERROR', $e->errorCode);
        $this->assertNull($e->httpStatus);
        $this->assertNull($e->response);
    }

    public function testBaseExceptionWithAllFields(): void
    {
        $data = ['error' => 'bad'];
        $e    = new ConversionToolsException('msg', 'MY_CODE', 422, $data);
        $this->assertSame('MY_CODE', $e->errorCode);
        $this->assertSame(422, $e->httpStatus);
        $this->assertSame($data, $e->response);
    }

    public function testBaseExceptionIsRuntimeException(): void
    {
        $e = new ConversionToolsException('msg');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    // ── AuthenticationException ───────────────────────────────────────────────

    public function testAuthenticationException(): void
    {
        $e = new AuthenticationException('Invalid token');
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('Invalid token', $e->getMessage());
        $this->assertSame('AUTHENTICATION_ERROR', $e->errorCode);
        $this->assertSame(401, $e->httpStatus);
    }

    // ── ValidationException ───────────────────────────────────────────────────

    public function testValidationException(): void
    {
        $e = new ValidationException('Bad input');
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('Bad input', $e->getMessage());
        $this->assertSame('VALIDATION_ERROR', $e->errorCode);
        $this->assertSame(400, $e->httpStatus);
    }

    public function testValidationExceptionWithData(): void
    {
        $data = ['field' => 'file_id', 'issue' => 'missing'];
        $e    = new ValidationException('Bad input', $data);
        $this->assertSame($data, $e->response);
    }

    // ── RateLimitException ────────────────────────────────────────────────────

    public function testRateLimitExceptionDefaults(): void
    {
        $e = new RateLimitException();
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $e->errorCode);
        $this->assertSame(429, $e->httpStatus);
        $this->assertNull($e->limits);
    }

    public function testRateLimitExceptionWithLimits(): void
    {
        $limits = ['daily' => ['limit' => 100, 'remaining' => 0]];
        $e      = new RateLimitException('Rate limit exceeded', $limits);
        $this->assertSame($limits, $e->limits);
        $this->assertSame(0, $e->limits['daily']['remaining']);
    }

    // ── FileNotFoundException ─────────────────────────────────────────────────

    public function testFileNotFoundException(): void
    {
        $e = new FileNotFoundException('File not found');
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame(404, $e->httpStatus);
        $this->assertSame('FILE_NOT_FOUND', $e->errorCode);
    }

    public function testFileNotFoundExceptionWithFileId(): void
    {
        $e = new FileNotFoundException('Not found', str_repeat('a', 32));
        $this->assertSame(str_repeat('a', 32), $e->fileId);
    }

    // ── TaskNotFoundException ─────────────────────────────────────────────────

    public function testTaskNotFoundException(): void
    {
        $e = new TaskNotFoundException('Task not found');
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame(404, $e->httpStatus);
        $this->assertSame('TASK_NOT_FOUND', $e->errorCode);
    }

    public function testTaskNotFoundExceptionWithTaskId(): void
    {
        $e = new TaskNotFoundException('Not found', str_repeat('b', 32));
        $this->assertSame(str_repeat('b', 32), $e->taskId);
    }

    // ── ConversionException ───────────────────────────────────────────────────

    public function testConversionException(): void
    {
        $taskId = str_repeat('c', 32);
        $e      = new ConversionException('Conversion failed', $taskId, 'Invalid format');
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('CONVERSION_ERROR', $e->errorCode);
        $this->assertSame($taskId, $e->taskId);
        $this->assertSame('Invalid format', $e->taskError);
    }

    public function testConversionExceptionNullFields(): void
    {
        $e = new ConversionException('Failed');
        $this->assertNull($e->taskId);
        $this->assertNull($e->taskError);
    }

    // ── TimeoutException ──────────────────────────────────────────────────────

    public function testTimeoutException(): void
    {
        $e = new TimeoutException('Timed out', 30000);
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('TIMEOUT_ERROR', $e->errorCode);
        $this->assertSame(408, $e->httpStatus);
        $this->assertSame(30000.0, $e->timeout);
    }

    // ── NetworkException ──────────────────────────────────────────────────────

    public function testNetworkException(): void
    {
        $cause = new \RuntimeException('connection refused');
        $e     = new NetworkException('Network error', $cause);
        $this->assertInstanceOf(ConversionToolsException::class, $e);
        $this->assertSame('NETWORK_ERROR', $e->errorCode);
        $this->assertSame($cause, $e->originalError);
    }

    public function testNetworkExceptionWithoutCause(): void
    {
        $e = new NetworkException('Network error');
        $this->assertNull($e->originalError);
    }

    // ── Hierarchy ─────────────────────────────────────────────────────────────

    public function testAllExceptionsExtendBase(): void
    {
        $exceptions = [
            new AuthenticationException('msg'),
            new ValidationException('msg'),
            new RateLimitException(),
            new FileNotFoundException('msg'),
            new TaskNotFoundException('msg'),
            new ConversionException('msg'),
            new TimeoutException('msg'),
            new NetworkException('msg'),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(ConversionToolsException::class, $e);
            $this->assertInstanceOf(\RuntimeException::class, $e);
        }
    }
}
