<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ConversionTools\Api\FilesApi;
use ConversionTools\Exceptions\ValidationException;
use ConversionTools\Http\HttpClient;

class FilesApiTest extends TestCase
{
    private FilesApi $files;
    /** @var HttpClient&\PHPUnit\Framework\MockObject\MockObject */
    private HttpClient $http;

    private string $validFileId;

    protected function setUp(): void
    {
        $this->http       = $this->createMock(HttpClient::class);
        $this->files      = new FilesApi($this->http);
        $this->validFileId = str_repeat('a', 32);
    }

    // ── upload() ──────────────────────────────────────────────────────────────

    public function testUploadWithNonExistentFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/File not found/');
        $this->files->upload('/nonexistent/path/file.pdf');
    }

    public function testUploadWithDirectory(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->upload(sys_get_temp_dir()); // exists but is not a file
    }

    public function testUploadWithBuffer(): void
    {
        $this->http
            ->expects($this->once())
            ->method('upload')
            ->willReturn(['file_id' => $this->validFileId]);

        $fileId = $this->files->upload(['buffer' => 'hello world', 'filename' => 'test.txt']);
        $this->assertSame($this->validFileId, $fileId);
    }

    public function testUploadWithBufferNoFilename(): void
    {
        $this->http->method('upload')->willReturn(['file_id' => $this->validFileId]);
        $fileId = $this->files->upload(['buffer' => 'data']);
        $this->assertSame($this->validFileId, $fileId);
    }

    public function testUploadBufferNotString(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->upload(['buffer' => 12345]);
    }

    public function testUploadWithResource(): void
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, 'test content');
        rewind($stream);

        $this->http
            ->expects($this->once())
            ->method('upload')
            ->willReturn(['file_id' => $this->validFileId]);

        $fileId = $this->files->upload(['resource' => $stream, 'filename' => 'data.bin']);
        $this->assertSame($this->validFileId, $fileId);

        fclose($stream);
    }

    public function testUploadResourceNotAResource(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->upload(['resource' => 'not-a-resource']);
    }

    public function testUploadWithInvalidInput(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->upload([]);
    }

    public function testUploadWithFilePath(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ct_test_');
        file_put_contents($tmp, 'content');

        try {
            $this->http->method('upload')->willReturn(['file_id' => $this->validFileId]);
            $fileId = $this->files->upload($tmp);
            $this->assertSame($this->validFileId, $fileId);
        } finally {
            unlink($tmp);
        }
    }

    public function testUploadPassesOnProgressCallback(): void
    {
        $onProgress = static function (array $p): void {};
        $capturedOnProgress = null;

        $this->http
            ->expects($this->once())
            ->method('upload')
            ->willReturnCallback(
                function (string $path, \Closure $factory, string $filename, ?callable $progressCb) use (&$capturedOnProgress): array {
                    $capturedOnProgress = $progressCb;
                    return ['file_id' => $this->validFileId];
                }
            );

        $this->files->upload(['buffer' => 'data'], ['on_progress' => $onProgress]);
        $this->assertSame($onProgress, $capturedOnProgress);
    }

    // ── getInfo() ─────────────────────────────────────────────────────────────

    public function testGetInfoCallsCorrectEndpoint(): void
    {
        $info = ['name' => 'output.docx', 'size' => 1024];
        $this->http
            ->expects($this->once())
            ->method('get')
            ->with('/files/' . $this->validFileId . '/info')
            ->willReturn($info);

        $result = $this->files->getInfo($this->validFileId);
        $this->assertSame($info, $result);
    }

    public function testGetInfoInvalidFileId(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->getInfo('short');
    }

    // ── downloadBytes() ───────────────────────────────────────────────────────

    public function testDownloadBytesCallsGetRaw(): void
    {
        $this->http
            ->expects($this->once())
            ->method('getRaw')
            ->with('/files/' . $this->validFileId)
            ->willReturn('binary content');

        $result = $this->files->downloadBytes($this->validFileId);
        $this->assertSame('binary content', $result);
    }

    public function testDownloadBytesInvalidFileId(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->downloadBytes('bad-id');
    }

    // ── downloadTo() ─────────────────────────────────────────────────────────

    public function testDownloadToWithExplicitPath(): void
    {
        $tmp = sys_get_temp_dir() . '/ct_test_output.txt';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getHeaderLine')->willReturn('');

        $this->http
            ->expects($this->once())
            ->method('downloadToFile')
            ->with('/files/' . $this->validFileId, $tmp, null)
            ->willReturn($mockResponse);

        $result = $this->files->downloadTo($this->validFileId, $tmp);
        $this->assertSame($tmp, $result);
    }

    public function testDownloadToInvalidFileId(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->downloadTo('invalid');
    }

    public function testDownloadToExtractsFilenameFromContentDisposition(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getHeaderLine')
            ->with('Content-Disposition')
            ->willReturn('attachment; filename="output.docx"');

        // Simulate downloading to a real temp file
        $tmpPath = tempnam(sys_get_temp_dir(), 'ct_dl_');

        $this->http
            ->method('downloadToFile')
            ->willReturnCallback(function (string $path, string $dest) use ($tmpPath, $mockResponse): ResponseInterface {
                // Simulate that Guzzle wrote to the temp file
                file_put_contents($dest, 'fake content');
                return $mockResponse;
            });

        $result = $this->files->downloadTo($this->validFileId);

        $this->assertStringEndsWith('output.docx', $result);

        // Cleanup
        if (file_exists($result)) {
            unlink($result);
        }
    }

    public function testDownloadToFallsBackWhenNoContentDisposition(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getHeaderLine')->willReturn('');

        $tmpPath = tempnam(sys_get_temp_dir(), 'ct_dl_');

        $this->http
            ->method('downloadToFile')
            ->willReturnCallback(function (string $path, string $dest) use ($mockResponse): ResponseInterface {
                file_put_contents($dest, 'content');
                return $mockResponse;
            });

        $result = $this->files->downloadTo($this->validFileId);
        $this->assertStringEndsWith('result', $result);

        if (file_exists($result)) {
            unlink($result);
        }
    }

    // ── downloadStream() ──────────────────────────────────────────────────────

    public function testDownloadStreamReturnsStreamInterface(): void
    {
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($mockStream);

        $this->http
            ->expects($this->once())
            ->method('getStream')
            ->with('/files/' . $this->validFileId)
            ->willReturn($mockResponse);

        $result = $this->files->downloadStream($this->validFileId);
        $this->assertInstanceOf(StreamInterface::class, $result);
    }

    public function testDownloadStreamInvalidFileId(): void
    {
        $this->expectException(ValidationException::class);
        $this->files->downloadStream('bad-id');
    }
}
