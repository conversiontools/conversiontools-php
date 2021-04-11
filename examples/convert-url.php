<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \ConversionTools\ConversionClient;

// put token here from your Profile page at https://conversiontools.io/profile
$token = '';

$fileOrUrlInput = 'https://google.com';
$fileOutput = 'result.pdf';

$options = [];

$client = new ConversionClient($token);
try {
    $client->convert('convert.website_to_pdf', $fileOrUrlInput, $fileOutput, $options);
} catch (Exception $e) {
    print 'Exception: ' . $e->getMessage() . "\n";
}