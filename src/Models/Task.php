<?php

declare(strict_types=1);

namespace ConversionTools\Models;

use Psr\Http\Message\StreamInterface;
use ConversionTools\Api\FilesApi;
use ConversionTools\Api\TasksApi;
use ConversionTools\Exceptions\ConversionException;
use ConversionTools\Utils\Polling;

class Task
{
    private string $status;
    private ?string $fileId;
    private ?string $error;
    private int $conversionProgress;

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        private readonly TasksApi $tasksApi,
        private readonly FilesApi $filesApi,
        string $status = 'PENDING',
        ?string $fileId = null,
        ?string $error = null,
        int $conversionProgress = 0,
        private readonly array $defaultPolling = [
            'interval'     => 5000,
            'max_interval' => 30000,
            'backoff'      => 1.5,
        ],
    ) {
        $this->status             = $status;
        $this->fileId             = $fileId;
        $this->error              = $error;
        $this->conversionProgress = $conversionProgress;
    }

    /**
     * Fetch the latest task status from the API, update internal state, and return the full response.
     */
    public function getStatus(): array
    {
        $response = $this->tasksApi->getStatus($this->id);
        $this->updateFromResponse($response);
        return $response;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getConversionProgress(): int
    {
        return $this->conversionProgress;
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['SUCCESS', 'ERROR'], true);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }

    public function isError(): bool
    {
        return $this->status === 'ERROR';
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['PENDING', 'RUNNING'], true);
    }

    /**
     * Refresh the task status from the API.
     */
    public function refresh(): void
    {
        $this->getStatus();
    }

    /**
     * Wait for the task to reach a terminal state (SUCCESS or ERROR).
     *
     * @param  array{
     *     polling_interval?:     float,
     *     max_polling_interval?: float,
     *     timeout?:              float,
     *     on_progress?:          callable(array): void,
     * } $options
     */
    public function wait(array $options = []): void
    {
        $interval    = $options['polling_interval']     ?? $this->defaultPolling['interval'];
        $maxInterval = $options['max_polling_interval'] ?? $this->defaultPolling['max_interval'];
        $timeout     = $options['timeout']              ?? 0;
        $onProgress  = $options['on_progress']          ?? null;
        $backoff     = $this->defaultPolling['backoff'];

        $finalStatus = Polling::pollTaskStatus(
            fn(): array => $this->getStatus(),
            $interval,
            $maxInterval,
            $backoff,
            $timeout,
            $onProgress,
        );

        $this->updateFromResponse($finalStatus);

        if ($this->status === 'ERROR') {
            throw new ConversionException(
                $this->error ?? 'Conversion failed',
                $this->id,
                $this->error,
            );
        }
    }

    /**
     * Download the result file as a lazy PSR-7 stream (no full memory buffer).
     * Useful for large files. Caller must consume or close the stream.
     */
    public function downloadStream(): StreamInterface
    {
        if ($this->fileId === null) {
            throw new ConversionException('No result file available. Task may not be complete.', $this->id);
        }

        return $this->filesApi->downloadStream($this->fileId);
    }

    /**
     * Download the result file and return its contents.
     */
    public function downloadBytes(): string
    {
        if ($this->fileId === null) {
            throw new ConversionException('No result file available. Task may not be complete.', $this->id);
        }

        return $this->filesApi->downloadBytes($this->fileId);
    }

    /**
     * Download the result file to a path and return the resolved path.
     */
    public function downloadTo(?string $outputPath = null, ?callable $onProgress = null): string
    {
        if ($this->fileId === null) {
            throw new ConversionException('No result file available. Task may not be complete.', $this->id);
        }

        return $this->filesApi->downloadTo($this->fileId, $outputPath, $onProgress);
    }

    /**
     * Return the task state as an array.
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'status'              => $this->status,
            'file_id'             => $this->fileId,
            'error'               => $this->error,
            'conversion_progress' => $this->conversionProgress,
        ];
    }

    private function updateFromResponse(array $response): void
    {
        $this->status             = $response['status'];
        $this->fileId             = $response['file_id'] ?? null;
        $this->error              = $response['error'] ?? null;
        $this->conversionProgress = $response['conversionProgress'] ?? 0;
    }
}
