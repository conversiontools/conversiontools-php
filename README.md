# [Conversion Tools](https://conversiontools.io) PHP Client

[Conversion Tools](https://conversiontools.io) is an online service for converting files between formats — XML, Excel, PDF, Word, CSV, JSON, images, audio, video, and more.

This library integrates file conversion into PHP applications via the [Conversion Tools REST API](https://conversiontools.io/api-documentation).

## Requirements

- PHP 8.1 or later
- ext-curl, ext-json (enabled by default in standard PHP installs)

## Installation

```bash
composer require conversiontools/conversiontools-php
```

## Quick Start

Get your API token from [conversiontools.io/profile](https://conversiontools.io/profile).

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ConversionTools\ConversionToolsClient;

$client = new ConversionToolsClient([
    'api_token' => 'your-api-token',
]);

$client->convert('convert.pdf_to_word', 'input.pdf', 'output.docx');
```

## Examples

### Convert a file

```php
$client->convert('convert.xml_to_csv', 'data.xml', 'data.csv', [
    'delimiter' => 'tabulation',
]);
```

### Convert a URL

```php
$client->convert('convert.website_to_pdf', ['url' => 'https://example.com'], 'result.pdf');
```

### Use a pre-uploaded file

```php
$fileId = $client->files->upload('input.pdf');

$client->convert('convert.pdf_to_word', ['file_id' => $fileId], 'output.docx');
```

### Manual control (upload → task → wait → download)

```php
$fileId = $client->files->upload('input.pdf');

$task = $client->createTask('convert.pdf_to_word', ['file_id' => $fileId]);

$task->wait([
    'on_progress' => function (array $status): void {
        echo "{$status['conversionProgress']}% [{$status['status']}]\n";
    },
]);

$task->downloadTo('output.docx');
```

### Fire and forget (webhook)

```php
$taskId = $client->convert(
    conversionType: 'convert.pdf_to_word',
    input:          'input.pdf',
    wait:           false,
    callbackUrl:    'https://your-app.com/webhook',
);
```

### Error handling

```php
use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\RateLimitException;
use ConversionTools\Exceptions\ConversionException;

try {
    $client->convert('convert.pdf_to_word', 'input.pdf', 'output.docx');
} catch (RateLimitException $e) {
    echo "Quota exceeded. Daily remaining: {$e->limits['daily']['remaining']}\n";
} catch (ConversionException $e) {
    echo "Conversion failed for task {$e->taskId}: {$e->taskError}\n";
} catch (ConversionToolsException $e) {
    echo "Error [{$e->errorCode}]: {$e->getMessage()}\n";
}
```

## API

### `new ConversionToolsClient(array $config)`

| Key | Type | Default | Description |
|---|---|---|---|
| `api_token` | string | **required** | API token from your profile |
| `base_url` | string | `https://api.conversiontools.io/v1` | API base URL |
| `timeout` | float (ms) | `300000` | Request timeout |
| `retries` | int | `3` | Retry attempts on transient errors |
| `retry_delay` | float (ms) | `1000` | Initial retry delay (doubles each attempt) |
| `polling_interval` | float (ms) | `5000` | How often to poll task status |
| `max_polling_interval` | float (ms) | `30000` | Max polling interval (with backoff) |
| `polling_backoff` | float | `1.5` | Polling backoff multiplier |
| `webhook_url` | string | `null` | Default webhook URL for all tasks |
| `on_conversion_progress` | callable | `null` | Called on each poll with progress info |

### `convert(string $conversionType, string|array $input, ?string $output, array $options, bool $wait, ?string $callbackUrl): string`

One-call conversion. Returns the output file path (if `$wait=true`) or task ID (if `$wait=false`).

**Input formats:**
- `'path/to/file.pdf'` — local file path
- `['url' => 'https://...']` — URL-based conversion
- `['file_id' => '...']` — pre-uploaded file

### `createTask(string $conversionType, array $options, ?string $callbackUrl): Task`

Create a task without waiting. Returns a `Task` object.

### `getTask(string $taskId): Task`

Retrieve an existing task by ID.

### `getRateLimits(): ?array`

Returns rate limit info from the last API response.

```php
$limits = $client->getRateLimits();
// ['daily' => ['limit' => 30, 'remaining' => 25], 'monthly' => [...]]
```

### `Task`

| Method | Description |
|---|---|
| `wait(array $options = [])` | Poll until complete. Accepts `polling_interval`, `max_polling_interval`, `timeout`, `on_progress` |
| `downloadTo(?string $path)` | Download result to file, returns resolved path |
| `downloadBytes()` | Download result as string |
| `refresh()` | Re-fetch status from API |
| `getStatus()` | Returns current status string |
| `isSuccess()` / `isError()` / `isRunning()` / `isComplete()` | Status helpers |
| `toArray()` | Serialize task state |

### `$client->files`

| Method | Description |
|---|---|
| `upload(string $filePath): string` | Upload file, returns `file_id` |
| `getInfo(string $fileId): array` | Get file metadata |
| `downloadBytes(string $fileId): string` | Download as string |
| `downloadTo(string $fileId, ?string $outputPath): string` | Download to file |

### `$client->tasks`

| Method | Description |
|---|---|
| `create(array $request): array` | Create task (low-level) |
| `getStatus(string $taskId): array` | Get task status |
| `list(?string $status): array` | List tasks, optionally filtered by status |

### Exceptions

All exceptions extend `ConversionToolsException` and expose `$errorCode` and `$httpStatus`.

| Exception | Trigger |
|---|---|
| `AuthenticationException` | Invalid or missing API token |
| `ValidationException` | Bad request parameters |
| `RateLimitException` | Quota exceeded — has `$limits` property |
| `FileNotFoundException` | File ID not found |
| `TaskNotFoundException` | Task ID not found |
| `ConversionException` | Task failed — has `$taskId` and `$taskError` |
| `TimeoutException` | Request or polling timed out |
| `NetworkException` | Connection error |

## Documentation

Full list of conversion types and options: [conversiontools.io/api-documentation](https://conversiontools.io/api-documentation)

## License

Licensed under [MIT](./LICENSE). Copyright (c) [Conversion Tools](https://conversiontools.io)
