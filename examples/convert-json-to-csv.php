<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ConversionTools\ConversionToolsClient;
use ConversionTools\Exceptions\ConversionToolsException;

// Get your API token from https://conversiontools.io/profile
$token = getenv('CT_API_TOKEN') ?: 'your-api-token-here';

$client = new ConversionToolsClient([
    'api_token' => $token,
]);

try {
    $outputPath = $client->convert(
        conversionType: 'convert.json_to_csv',
        input:          __DIR__ . '/test.json',
        output:         __DIR__ . '/test.csv',
    );

    echo "Done! Saved to: {$outputPath}\n";
} catch (ConversionToolsException $e) {
    echo "Error [{$e->errorCode}]: {$e->getMessage()}\n";
}
