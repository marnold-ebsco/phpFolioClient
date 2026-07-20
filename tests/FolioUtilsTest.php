<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use phpFolioClient\FolioUtils;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioUtilsTest extends TestCase {
    private FolioUtils $folioUtils;

    protected function setUp(): void {
        $this->folioUtils = new FolioUtils();
    }

    #[Test]
    public function testIsValidUuidWithValidUuid(): void {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertTrue($this->folioUtils->isValidUuid($validUuid));
    }

    #[Test]
    public function testIsValidUuidWithValidVersion5Uuid(): void {
        $validUuid = '550e8400-e29b-51d4-a716-446655440000';
        $this->assertTrue($this->folioUtils->isValidUuid($validUuid));
    }

    #[Test]
    public function testIsValidUuidWithInvalidFormat(): void {
        $invalidUuid = 'not-a-uuid';
        $this->assertFalse($this->folioUtils->isValidUuid($invalidUuid));
    }

    #[Test]
    public function testIsValidUuidWithInvalidVersion(): void {
        $invalidUuid = '550e8400-e29b-31d4-a716-446655440000';
        $this->assertFalse($this->folioUtils->isValidUuid($invalidUuid));
    }

    #[Test]
    public function testIsValidUuidWithInvalidVariant(): void {
        $invalidUuid = '550e8400-e29b-41d4-c716-446655440000';
        $this->assertFalse($this->folioUtils->isValidUuid($invalidUuid));
    }

    #[Test]
    public function testIsValidUuidWithEmptyString(): void {
        $this->assertFalse($this->folioUtils->isValidUuid(''));
    }

    #[Test]
    public function testIsValidUuidWithUppercaseCharacters(): void {
        $invalidUuid = '550E8400-E29B-41D4-A716-446655440000';
        $this->assertFalse($this->folioUtils->isValidUuid($invalidUuid));
    }

    #[Test]
    public function testIsJsonWithValidJson(): void {
        $validJson = '{"key":"value"}';
        $this->assertTrue($this->folioUtils->isJson($validJson));
    }

    #[Test]
    public function testIsJsonWithValidJsonArray(): void {
        $validJson = '[1,2,3]';
        $this->assertTrue($this->folioUtils->isJson($validJson));
    }

    #[Test]
    public function testIsJsonWithValidJsonBoolean(): void {
        $validJson = 'true';
        $this->assertTrue($this->folioUtils->isJson($validJson));
    }

    #[Test]
    public function testIsJsonWithValidJsonNumber(): void {
        $validJson = '42';
        $this->assertTrue($this->folioUtils->isJson($validJson));
    }

    #[Test]
    public function testIsJsonWithValidJsonNull(): void {
        $validJson = 'null';
        $this->assertTrue($this->folioUtils->isJson($validJson));
    }

    #[Test]
    public function testIsJsonWithInvalidJson(): void {
        $invalidJson = '{key:value}';
        $this->assertFalse($this->folioUtils->isJson($invalidJson));
    }

    #[Test]
    public function testIsJsonWithMalformedJson(): void {
        $malformedJson = '{"key":"value"';
        $this->assertFalse($this->folioUtils->isJson($malformedJson));
    }

    #[Test]
    public function testIsJsonWithEmptyString(): void {
        $this->assertFalse($this->folioUtils->isJson(''));
    }

    #[Test]
    public function testIsJsonWithNull(): void {
        $this->assertFalse($this->folioUtils->isJson(null));
    }

    #[Test]
    public function testIsJsonWithPlainText(): void {
        $plainText = 'just plain text';
        $this->assertFalse($this->folioUtils->isJson($plainText));
    }
}
