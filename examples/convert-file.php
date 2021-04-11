<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \ConversionTools\ConversionClient;

// put token here from your Profile page at https://conversiontools.io/profile
$token = '';

$fileOrUrlInput = 'test.xml';
$fileOutput = 'test.csv';

$options = ['delimiter' => 'tabulation'];

$client = new ConversionClient($token);
try {
    $client->convert('convert.xml_to_csv', $fileOrUrlInput, $fileOutput, $options);
} catch (Exception $e) {
    print 'Exception: ' . $e->getMessage() . "\n";
}