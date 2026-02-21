<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ConversionTools\ConversionToolsClient;
use ConversionTools\Exceptions\ConversionToolsException;
use ConversionTools\Exceptions\RateLimitException;

// Get your API token from https://conversiontools.io/profile
$token = getenv('CT_API_TOKEN') ?: 'your-api-token-here';

$client = new ConversionToolsClient([
    'api_token' => $token,
]);

try {
    $outputPath = $client->convert(
        conversionType: 'convert.pdf_to_word',
        input:          'input.pdf',
        output:         'output.docx',
    );

    echo "Done! Saved to: {$outputPath}\n";
} catch (RateLimitException $e) {
    echo "Rate limit exceeded.\n";
    if ($e->limits !== null) {
        echo "Daily remaining: {$e->limits['daily']['remaining']}\n";
    }
} catch (ConversionToolsException $e) {
    echo "Error [{$e->errorCode}]: {$e->getMessage()}\n";
}
