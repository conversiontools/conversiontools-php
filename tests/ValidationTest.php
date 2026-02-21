<?php

declare(strict_types=1);

namespace ConversionTools\Tests;

use PHPUnit\Framework\TestCase;
use ConversionTools\Exceptions\ValidationException;
use ConversionTools\Utils\Validation;

class ValidationTest extends TestCase
{
    // ── validateApiToken ──────────────────────────────────────────────────────

    public function testValidApiToken(): void
    {
        Validation::validateApiToken('mytoken123');
        $this->addToAssertionCount(1);
    }

    public function testEmptyApiToken(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateApiToken('');
    }

    public function testWhitespaceOnlyApiToken(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateApiToken('   ');
    }

    // ── validateConversionType ────────────────────────────────────────────────

    public function testValidConversionType(): void
    {
        Validation::validateConversionType('convert.xml_to_excel');
        Validation::validateConversionType('convert.pdf_to_word');
        $this->addToAssertionCount(2);
    }

    public function testConversionTypeMissingPrefix(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Invalid conversion type format/');
        Validation::validateConversionType('xml_to_excel');
    }

    public function testConversionTypeEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateConversionType('');
    }

    // ── validateFileId ────────────────────────────────────────────────────────

    public function testValidFileId(): void
    {
        Validation::validateFileId(str_repeat('a', 32));
        Validation::validateFileId('1234567890abcdef1234567890abcdef');
        $this->addToAssertionCount(2);
    }

    public function testFileIdTooShort(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateFileId(str_repeat('a', 31));
    }

    public function testFileIdTooLong(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateFileId(str_repeat('a', 33));
    }

    public function testFileIdInvalidChars(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateFileId(str_repeat('g', 32)); // 'g' is not valid hex
    }

    public function testFileIdEmpty(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateFileId('');
    }

    // ── validateTaskId ────────────────────────────────────────────────────────

    public function testValidTaskId(): void
    {
        Validation::validateTaskId(str_repeat('f', 32));
        $this->addToAssertionCount(1);
    }

    public function testInvalidTaskId(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateTaskId('invalid');
    }

    // ── isValidUrl ────────────────────────────────────────────────────────────

    public function testValidUrls(): void
    {
        $this->assertTrue(Validation::isValidUrl('https://example.com/file.xml'));
        $this->assertTrue(Validation::isValidUrl('http://example.com'));
        $this->assertTrue(Validation::isValidUrl('https://www.w3schools.com/xml/books.xml'));
    }

    public function testInvalidUrls(): void
    {
        $this->assertFalse(Validation::isValidUrl('not-a-url'));
        $this->assertFalse(Validation::isValidUrl('/local/path'));
        $this->assertFalse(Validation::isValidUrl(''));
    }

    // ── validateConversionInput ───────────────────────────────────────────────

    public function testInputStringIsPath(): void
    {
        $result = Validation::validateConversionInput('/some/file.pdf');
        $this->assertSame('path', $result['type']);
        $this->assertSame('/some/file.pdf', $result['value']);
    }

    public function testInputExplicitPath(): void
    {
        $result = Validation::validateConversionInput(['path' => '/some/file.pdf']);
        $this->assertSame('path', $result['type']);
        $this->assertSame('/some/file.pdf', $result['value']);
    }

    public function testInputUrl(): void
    {
        $result = Validation::validateConversionInput(['url' => 'https://example.com/file.xml']);
        $this->assertSame('url', $result['type']);
        $this->assertSame('https://example.com/file.xml', $result['value']);
    }

    public function testInputUrlInvalid(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateConversionInput(['url' => 'not-a-url']);
    }

    public function testInputFileId(): void
    {
        $result = Validation::validateConversionInput(['file_id' => str_repeat('a', 32)]);
        $this->assertSame('file_id', $result['type']);
        $this->assertSame(str_repeat('a', 32), $result['value']);
    }

    public function testInputBuffer(): void
    {
        $result = Validation::validateConversionInput(['buffer' => 'raw bytes', 'filename' => 'data.csv']);
        $this->assertSame('buffer', $result['type']);
        $this->assertSame('raw bytes', $result['value']);
        $this->assertSame('data.csv', $result['filename']);
    }

    public function testInputBufferWithoutFilename(): void
    {
        $result = Validation::validateConversionInput(['buffer' => 'raw bytes']);
        $this->assertSame('buffer', $result['type']);
        $this->assertNull($result['filename']);
    }

    public function testInputBufferNotString(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateConversionInput(['buffer' => 123]);
    }

    public function testInputResource(): void
    {
        $stream = fopen('php://memory', 'rb');
        $result = Validation::validateConversionInput(['resource' => $stream, 'filename' => 'data.bin']);
        $this->assertSame('resource', $result['type']);
        $this->assertSame('data.bin', $result['filename']);
        fclose($stream);
    }

    public function testInputResourceNotAResource(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateConversionInput(['resource' => 'not-a-resource']);
    }

    public function testInputEmptyArray(): void
    {
        $this->expectException(ValidationException::class);
        Validation::validateConversionInput([]);
    }
}
