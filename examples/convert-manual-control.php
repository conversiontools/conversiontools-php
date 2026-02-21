<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ConversionTools\ConversionToolsClient;
use ConversionTools\Exceptions\ConversionException;
use ConversionTools\Exceptions\ConversionToolsException;

// Get your API token from https://conversiontools.io/profile
$token = getenv('CT_API_TOKEN') ?: 'your-api-token-here';

$client = new ConversionToolsClient([
    'api_token'        => $token,
    'polling_interval' => 3000,
]);

try {
    // 1. Upload the input file
    $fileId = $client->files->upload('input.pdf');
    echo "Uploaded. File ID: {$fileId}\n";

    // 2. Create the task
    $task = $client->createTask('convert.pdf_to_word', ['file_id' => $fileId]);
    echo "Task created. ID: {$task->id}\n";

    // 3. Wait with a progress callback
    $task->wait([
        'on_progress' => function (array $status): void {
            echo "  [{$status['status']}] {$status['conversionProgress']}%\n";
        },
    ]);

    // 4. Download the result
    $outputPath = $task->downloadTo('output.docx');
    echo "Done! Saved to: {$outputPath}\n";
} catch (ConversionException $e) {
    echo "Conversion failed for task {$e->taskId}: {$e->taskError}\n";
} catch (ConversionToolsException $e) {
    echo "Error [{$e->errorCode}]: {$e->getMessage()}\n";
}
