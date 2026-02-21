<?php

declare(strict_types=1);

namespace ConversionTools\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ConversionTools\Exceptions\ValidationException;
use ConversionTools\Http\HttpClient;
use ConversionTools\Utils\Validation;

class FilesApi
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Upload a file and return its file ID.
     *
     * $input can be:
     *   - string                                    → file path
     *   - ['path'     => string]                    → explicit file path
     *   - ['buffer'   => string, 'filename'? => string] → raw bytes
     *   - ['resource' => resource, 'filename'? => string] → open stream
     *
     * $options may include:
     *   - 'on_progress' => callable(['loaded', 'total', 'percent'])
     */
    public function upload(string|array $input, array $options = []): string
    {
        $onProgress = $options['on_progress'] ?? null;

        if (is_string($input)) {
            $input = ['path' => $input];
        }

        if (isset($input['path'])) {
            $filePath = $input['path'];

            if (!file_exists($filePath)) {
                throw new ValidationException("File not found: {$filePath}");
            }

            if (!is_file($filePath)) {
                throw new ValidationException("Not a file: {$filePath}");
            }

            $filename        = basename($filePath);
            $contentsFactory = fn(): mixed => fopen($filePath, 'rb');
        } elseif (isset($input['buffer'])) {
            if (!is_string($input['buffer'])) {
                throw new ValidationException('"buffer" must be a string');
            }

            $buffer          = $input['buffer'];
            $filename        = $input['filename'] ?? 'upload';
            $contentsFactory = fn(): string => $buffer;
        } elseif (isset($input['resource'])) {
            if (!is_resource($input['resource'])) {
                throw new ValidationException('"resource" must be a PHP resource');
            }

            $resource = $input['resource'];
            $filename = $input['filename'] ?? 'upload';
            $contentsFactory = function () use ($resource): mixed {
                if (stream_get_meta_data($resource)['seekable']) {
                    rewind($resource);
                }
                return $resource;
            };
        } else {
            throw new ValidationException(
                'Invalid upload input. Expected: string (file path) or array with "path", "buffer", or "resource" key'
            );
        }

        $result = $this->http->upload('/files', $contentsFactory, $filename, $onProgress);

        return $result['file_id'];
    }

    /**
     * Get metadata for an uploaded file.
     */
    public function getInfo(string $fileId): array
    {
        Validation::validateFileId($fileId);
        return $this->http->get('/files/' . rawurlencode($fileId) . '/info');
    }

    /**
     * Download a file as a lazy PSR-7 stream (no full memory buffer).
     * Useful for large files. Caller must consume or close the stream.
     */
    public function downloadStream(string $fileId): StreamInterface
    {
        Validation::validateFileId($fileId);
        $response = $this->http->getStream('/files/' . rawurlencode($fileId));
        return $response->getBody();
    }

    /**
     * Download a file and return its contents as a string.
     */
    public function downloadBytes(string $fileId): string
    {
        Validation::validateFileId($fileId);
        return $this->http->getRaw('/files/' . rawurlencode($fileId));
    }

    /**
     * Download a file to the given path and return the resolved path.
     * If no path is given, the filename is taken from the Content-Disposition header.
     */
    public function downloadTo(string $fileId, ?string $outputPath = null, ?callable $onProgress = null): string
    {
        Validation::validateFileId($fileId);

        $apiPath = '/files/' . rawurlencode($fileId);

        if ($outputPath !== null) {
            $this->http->downloadToFile($apiPath, $outputPath, $onProgress);
            return $outputPath;
        }

        // Download to a temp file first, then rename using the Content-Disposition filename.
        $tmpPath  = tempnam(sys_get_temp_dir(), 'ct_dl_');
        $response = $this->http->downloadToFile($apiPath, $tmpPath, $onProgress);

        $filename   = $this->extractFilename($response);
        $outputPath = getcwd() . DIRECTORY_SEPARATOR . $filename;

        if (@rename($tmpPath, $outputPath) === false) {
            copy($tmpPath, $outputPath);
            @unlink($tmpPath);
        }

        return $outputPath;
    }

    private function extractFilename(ResponseInterface $response): string
    {
        $header = $response->getHeaderLine('Content-Disposition');

        if ($header === '') {
            return 'result';
        }

        // Try filename*= first (RFC 5987, takes precedence over filename=)
        if (preg_match('/filename\*\s*=\s*(?:[\w-]+\'\')?([^\s;]+)/i', $header, $matches)) {
            return urldecode($matches[1]);
        }

        // Fall back to filename= (quoted or unquoted)
        if (preg_match('/filename\s*=\s*"([^"]+)"/i', $header, $matches)) {
            return $matches[1];
        }

        if (preg_match('/filename\s*=\s*([^\s;]+)/i', $header, $matches)) {
            return $matches[1];
        }

        return 'result';
    }
}
