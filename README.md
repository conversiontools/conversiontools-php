# [Conversion Tools](https://conversiontools.io) API PHP Client

[Conversion Tools](https://conversiontools.io) is an online service that offers a fast and easy way to convert documents between different formats, like XML, Excel, PDF, Word, Text, CSV and others.

This client allows to integrate the conversion of the files into PHP applications.

To convert the files PHP Client uses the public [Conversion Tools REST API](https://conversiontools.io/api-documentation).

## Installation

### Installation using Composer

```bash
composer require conversiontools/conversiontools-php
```

or without composer:

```php
require_once('conversiontools-php/src/autoload.php');
```

## Examples

To use REST API - get API Token from the Profile page at https://conversiontools.io/profile.

See example `test.php` in the `./examples/` folder.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use \ConversionTools\ConversionClient;

// put token here from your Profile page at https://conversiontools.io/profile
$token = '';

$fileInput = 'test.xml';
$fileOutput = 'test.csv';
$options = ['delimiter' => 'tabulation'];

$client = new ConversionClient($token);
try {
    $client->convert('convert.xml_to_csv', $fileInput, $fileOutput, $options);
} catch (Exception $e) {
    print 'Exception: ' . $e->getMessage() . "\n";
}
```

## API

### Create `ConversionClient` instance with a token.
```php
use \ConversionTools\ConversionClient;

$client = new ConversionClient('<token>');
```

Where `<token>` is API token from the account's Profile page https://conversiontools.io/profile.

### Convert input file and download the result
```php
$client = new ConversionClient($token);
try {
    $client->convert('<conversion type>', $fileInput, $fileOutput, $options);
} catch (Exception $e) {
    print 'Exception: ' . $e->getMessage() . "\n";
}
```

Where
- `<conversion type>` is a specific type of conversion, from [API Documentation](https://conversiontools.io/api-documentation).
- `$fileInput` is the filename of the input file
- `$fileOutput` is the filename of the output file
- `$options` is a PHP array with options for a corresponding converter, for example:
```php
$options = ['delimiter' => 'tabulation'];
```

## Requirements

PHP version 5.4.0 or later.

## Documentation

List of available Conversion Types and corresponding conversion options can be found on the [Conversion Tools API Documentation](https://conversiontools.io/api-documentation) page.

## License

Licensed under [MIT](./LICENSE).

Copyright (c) 2020 [Conversion Tools](https://conversiontools.io)
