<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use phpFolioClient\FolioUtils;
use PHPUnit\Framework\TestCase;

final class FolioUtilsTest extends TestCase {
    private FolioUtils $utils;

    protected function setUp(): void {
        $this->utils = new FolioUtils();
    }

    public function testIsValidUuidAcceptsV4(): void {
        $this->assertTrue($this->utils->isValidUuid('e4a1c3d0-1234-4abc-89ab-1234567890ab'));
    }

    public function testIsValidUuidAcceptsV5(): void {
        $this->assertTrue($this->utils->isValidUuid('e4a1c3d0-1234-5abc-9abc-1234567890ab'));
    }

    /**
     * B26: deliberately kept strict — a well-formed v1 UUID is rejected
     * because this validator is FOLIO-specific (v4/v5 only), not general-purpose.
     */
    public function testIsValidUuidRejectsV1(): void {
        $this->assertFalse($this->utils->isValidUuid('e4a1c3d0-1234-1abc-89ab-1234567890ab'));
    }

    public function testIsValidUuidRejectsWrongVariantNibble(): void {
        // Variant nibble must be 8/9/a/b; 'c' is out of range for this check.
        $this->assertFalse($this->utils->isValidUuid('e4a1c3d0-1234-4abc-cabc-1234567890ab'));
    }

    public function testIsValidUuidRejectsMalformedString(): void {
        $this->assertFalse($this->utils->isValidUuid('not-a-uuid'));
    }

    public function testIsValidUuidRejectsEmptyString(): void {
        $this->assertFalse($this->utils->isValidUuid(''));
    }

    public function testIsJsonAcceptsObjectString(): void {
        $this->assertTrue($this->utils->isJson('{"a":1}'));
    }

    public function testIsJsonAcceptsArrayString(): void {
        $this->assertTrue($this->utils->isJson('[1,2,3]'));
    }

    /**
     * B27: "0" is falsy as a PHP string but is valid JSON (decodes to int 0).
     */
    public function testIsJsonAcceptsTheStringZero(): void {
        $this->assertTrue($this->utils->isJson('0'));
    }

    public function testIsJsonAcceptsLiteralFalse(): void {
        $this->assertTrue($this->utils->isJson('false'));
    }

    public function testIsJsonAcceptsLiteralNull(): void {
        $this->assertTrue($this->utils->isJson('null'));
    }

    public function testIsJsonRejectsEmptyString(): void {
        $this->assertFalse($this->utils->isJson(''));
    }

    public function testIsJsonRejectsNull(): void {
        $this->assertFalse($this->utils->isJson(null));
    }

    public function testIsJsonRejectsMalformedJson(): void {
        $this->assertFalse($this->utils->isJson('{not valid json'));
    }

    public function testIsJsonRejectsPlainWord(): void {
        $this->assertFalse($this->utils->isJson('hello'));
    }
}
