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

// The API downloads the file from the URL and converts it directly —
// no local download needed.
try {
    $outputPath = $client->convert(
        conversionType: 'convert.xml_to_excel',
        input:          ['url' => 'https://www.w3schools.com/xml/books.xml'],
        output:         __DIR__ . '/books.xlsx',
    );

    echo "Done! Saved to: {$outputPath}\n";
} catch (ConversionToolsException $e) {
    echo "Error [{$e->errorCode}]: {$e->getMessage()}\n";
}
