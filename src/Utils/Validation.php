<?php

declare(strict_types=1);

namespace ConversionTools\Utils;

use ConversionTools\Exceptions\ValidationException;

class Validation
{
    public static function validateApiToken(string $token): void
    {
        if (trim($token) === '') {
            throw new ValidationException('API token cannot be empty');
        }
    }

    public static function validateConversionType(string $type): void
    {
        if (!str_starts_with($type, 'convert.')) {
            throw new ValidationException(
                "Invalid conversion type format: \"{$type}\". Expected format: \"convert.source_to_target\""
            );
        }
    }

    public static function validateFileId(string $fileId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $fileId)) {
            throw new ValidationException(
                "Invalid file ID format: \"{$fileId}\". Expected 32-character hexadecimal string"
            );
        }
    }

    public static function validateTaskId(string $taskId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $taskId)) {
            throw new ValidationException(
                "Invalid task ID format: \"{$taskId}\". Expected 32-character hexadecimal string"
            );
        }
    }

    public static function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);
        return isset($parsed['scheme'], $parsed['host']);
    }

    /**
     * Validate and normalize conversion input.
     *
     * Accepts:
     *   - string                                         → file path
     *   - ['url'      => string]                         → URL-based conversion
     *   - ['file_id'  => string]                         → pre-uploaded file
     *   - ['path'     => string]                         → explicit file path
     *   - ['buffer'   => string,   'filename'? => string] → raw bytes
     *   - ['resource' => resource, 'filename'? => string] → open stream
     *
     * @param  string|array $input
     * @return array{type: string, value: mixed, filename?: string|null}
     */
    public static function validateConversionInput(string|array $input): array
    {
        if (is_string($input)) {
            return ['type' => 'path', 'value' => $input];
        }

        if (isset($input['url'])) {
            if (!self::isValidUrl($input['url'])) {
                throw new ValidationException("Invalid URL: {$input['url']}");
            }
            return ['type' => 'url', 'value' => $input['url']];
        }

        if (isset($input['file_id'])) {
            return ['type' => 'file_id', 'value' => $input['file_id']];
        }

        if (isset($input['path'])) {
            return ['type' => 'path', 'value' => $input['path']];
        }

        if (isset($input['buffer'])) {
            if (!is_string($input['buffer'])) {
                throw new ValidationException('"buffer" must be a string');
            }
            return ['type' => 'buffer', 'value' => $input['buffer'], 'filename' => $input['filename'] ?? null];
        }

        if (isset($input['resource'])) {
            if (!is_resource($input['resource'])) {
                throw new ValidationException('"resource" must be a PHP resource');
            }
            return ['type' => 'resource', 'value' => $input['resource'], 'filename' => $input['filename'] ?? null];
        }

        throw new ValidationException(
            'Invalid input format. Expected: string (file path) or array with "url", "file_id", "path", "buffer", or "resource" key'
        );
    }
}
