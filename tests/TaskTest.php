<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use ConversionTools\Api\FilesApi;
use ConversionTools\Api\TasksApi;
use ConversionTools\Exceptions\ConversionException;
use ConversionTools\Models\Task;

class TaskTest extends TestCase
{
    private string $taskId;
    private string $fileId;

    /** @var TasksApi&\PHPUnit\Framework\MockObject\MockObject */
    private TasksApi $tasksApi;

    /** @var FilesApi&\PHPUnit\Framework\MockObject\MockObject */
    private FilesApi $filesApi;

    protected function setUp(): void
    {
        $this->taskId   = str_repeat('t', 32);
        $this->fileId   = str_repeat('f', 32);
        $this->tasksApi = $this->createMock(TasksApi::class);
        $this->filesApi = $this->createMock(FilesApi::class);
    }

    private function makeTask(
        string $status = 'PENDING',
        ?string $fileId = null,
        ?string $error = null,
        int $progress = 0,
    ): Task {
        return new Task(
            id:             $this->taskId,
            type:           'convert.xml_to_csv',
            tasksApi:       $this->tasksApi,
            filesApi:       $this->filesApi,
            status:         $status,
            fileId:         $fileId,
            error:          $error,
            conversionProgress: $progress,
            defaultPolling: ['interval' => 1, 'max_interval' => 1, 'backoff' => 1.0],
        );
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function testIsCompleteForSuccessAndError(): void
    {
        $this->assertTrue($this->makeTask('SUCCESS')->isComplete());
        $this->assertTrue($this->makeTask('ERROR')->isComplete());
        $this->assertFalse($this->makeTask('PENDING')->isComplete());
        $this->assertFalse($this->makeTask('RUNNING')->isComplete());
    }

    public function testIsSuccess(): void
    {
        $this->assertTrue($this->makeTask('SUCCESS')->isSuccess());
        $this->assertFalse($this->makeTask('ERROR')->isSuccess());
        $this->assertFalse($this->makeTask('PENDING')->isSuccess());
    }

    public function testIsError(): void
    {
        $this->assertTrue($this->makeTask('ERROR')->isError());
        $this->assertFalse($this->makeTask('SUCCESS')->isError());
    }

    public function testIsRunning(): void
    {
        $this->assertTrue($this->makeTask('PENDING')->isRunning());
        $this->assertTrue($this->makeTask('RUNNING')->isRunning());
        $this->assertFalse($this->makeTask('SUCCESS')->isRunning());
        $this->assertFalse($this->makeTask('ERROR')->isRunning());
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function testGetFileId(): void
    {
        $task = $this->makeTask('SUCCESS', $this->fileId);
        $this->assertSame($this->fileId, $task->getFileId());
    }

    public function testGetError(): void
    {
        $task = $this->makeTask('ERROR', null, 'Conversion failed');
        $this->assertSame('Conversion failed', $task->getError());
    }

    public function testGetConversionProgress(): void
    {
        $task = $this->makeTask('RUNNING', null, null, 42);
        $this->assertSame(42, $task->getConversionProgress());
    }

    // ── toArray() ─────────────────────────────────────────────────────────────

    public function testToArray(): void
    {
        $task   = $this->makeTask('SUCCESS', $this->fileId, null, 100);
        $result = $task->toArray();

        $this->assertSame($this->taskId, $result['id']);
        $this->assertSame('convert.xml_to_csv', $result['type']);
        $this->assertSame('SUCCESS', $result['status']);
        $this->assertSame($this->fileId, $result['file_id']);
        $this->assertNull($result['error']);
        $this->assertSame(100, $result['conversion_progress']);
    }

    // ── getStatus() ───────────────────────────────────────────────────────────

    public function testGetStatusCallsApiAndReturnsResponse(): void
    {
        $apiResponse = [
            'status'             => 'SUCCESS',
            'file_id'            => $this->fileId,
            'error'              => null,
            'conversionProgress' => 100,
        ];

        $this->tasksApi
            ->expects($this->once())
            ->method('getStatus')
            ->with($this->taskId)
            ->willReturn($apiResponse);

        $task   = $this->makeTask('PENDING');
        $result = $task->getStatus();

        $this->assertSame($apiResponse, $result);
        $this->assertTrue($task->isSuccess()); // internal state updated
        $this->assertSame($this->fileId, $task->getFileId());
    }

    // ── refresh() ─────────────────────────────────────────────────────────────

    public function testRefreshUpdatesInternalState(): void
    {
        $this->tasksApi
            ->method('getStatus')
            ->willReturn([
                'status'             => 'RUNNING',
                'file_id'            => null,
                'error'              => null,
                'conversionProgress' => 50,
            ]);

        $task = $this->makeTask('PENDING');
        $task->refresh();

        $this->assertTrue($task->isRunning());
        $this->assertSame(50, $task->getConversionProgress());
    }

    // ── wait() ────────────────────────────────────────────────────────────────

    public function testWaitSucceeds(): void
    {
        $this->tasksApi
            ->method('getStatus')
            ->willReturn([
                'status'             => 'SUCCESS',
                'file_id'            => $this->fileId,
                'error'              => null,
                'conversionProgress' => 100,
            ]);

        $task = $this->makeTask('PENDING');
        $task->wait(); // must not throw

        $this->assertTrue($task->isSuccess());
    }

    public function testWaitThrowsConversionExceptionOnError(): void
    {
        $this->tasksApi
            ->method('getStatus')
            ->willReturn([
                'status'             => 'ERROR',
                'file_id'            => null,
                'error'              => 'Unsupported file format',
                'conversionProgress' => 0,
            ]);

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Unsupported file format');

        $task = $this->makeTask('PENDING');
        $task->wait();
    }

    public function testWaitCallsOnProgressCallback(): void
    {
        $progressStatuses = [];
        $calls            = 0;

        $this->tasksApi
            ->method('getStatus')
            ->willReturnCallback(function () use (&$calls): array {
                $calls++;
                return [
                    'status'             => $calls >= 3 ? 'SUCCESS' : 'PENDING',
                    'file_id'            => $calls >= 3 ? $this->fileId : null,
                    'error'              => null,
                    'conversionProgress' => $calls * 30,
                ];
            });

        $task = $this->makeTask('PENDING');
        $task->wait([
            'on_progress' => function (array $status) use (&$progressStatuses): void {
                $progressStatuses[] = $status['conversionProgress'];
            },
        ]);

        $this->assertCount(2, $progressStatuses); // called for PENDING polls (calls 1, 2)
    }

    // ── downloadTo() ─────────────────────────────────────────────────────────

    public function testDownloadToDelegatesWithPath(): void
    {
        $this->filesApi
            ->expects($this->once())
            ->method('downloadTo')
            ->with($this->fileId, '/out/result.csv', null)
            ->willReturn('/out/result.csv');

        $task   = $this->makeTask('SUCCESS', $this->fileId);
        $result = $task->downloadTo('/out/result.csv');
        $this->assertSame('/out/result.csv', $result);
    }

    public function testDownloadToPassesProgressCallback(): void
    {
        $cb = static function (array $p): void {};

        $this->filesApi
            ->expects($this->once())
            ->method('downloadTo')
            ->with($this->fileId, null, $cb)
            ->willReturn('output.csv');

        $task = $this->makeTask('SUCCESS', $this->fileId);
        $task->downloadTo(null, $cb);
    }

    public function testDownloadToThrowsWhenNoFileId(): void
    {
        $this->expectException(ConversionException::class);
        $task = $this->makeTask('PENDING', null);
        $task->downloadTo();
    }

    // ── downloadStream() ──────────────────────────────────────────────────────

    public function testDownloadStreamDelegates(): void
    {
        $mockStream = $this->createMock(StreamInterface::class);

        $this->filesApi
            ->expects($this->once())
            ->method('downloadStream')
            ->with($this->fileId)
            ->willReturn($mockStream);

        $task   = $this->makeTask('SUCCESS', $this->fileId);
        $result = $task->downloadStream();
        $this->assertSame($mockStream, $result);
    }

    public function testDownloadStreamThrowsWhenNoFileId(): void
    {
        $this->expectException(ConversionException::class);
        $task = $this->makeTask('PENDING', null);
        $task->downloadStream();
    }

    // ── downloadBytes() ───────────────────────────────────────────────────────

    public function testDownloadBytesDelegates(): void
    {
        $this->filesApi
            ->expects($this->once())
            ->method('downloadBytes')
            ->with($this->fileId)
            ->willReturn('binary data');

        $task   = $this->makeTask('SUCCESS', $this->fileId);
        $result = $task->downloadBytes();
        $this->assertSame('binary data', $result);
    }

    public function testDownloadBytesThrowsWhenNoFileId(): void
    {
        $this->expectException(ConversionException::class);
        $task = $this->makeTask('PENDING', null);
        $task->downloadBytes();
    }
}
