<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use InvalidArgumentException;
use phpFolioClient\FolioConfig;
use PHPUnit\Framework\TestCase;

final class FolioConfigTest extends TestCase {
    private const REQUIRED = [
        'okapiUrl' => 'https://okapi.example.edu',
        'tenant_id' => 'diku',
        'username' => 'diku_admin',
        'password' => 'secret',
    ];

    public function testConstructFromArraySetsRequiredProperties(): void {
        $config = new FolioConfig(self::REQUIRED);

        $this->assertSame('https://okapi.example.edu', $config->getApiUrl());
        $this->assertSame('diku', $config->getTenantId());
        $this->assertSame('diku_admin', $config->getUsername());
        $this->assertSame('secret', $config->password);
    }

    public function testConstructFromObject(): void {
        $config = new FolioConfig((object) self::REQUIRED);

        $this->assertSame('diku', $config->getTenantId());
    }

    public function testConstructThrowsOnMissingRequiredKey(): void {
        $incomplete = self::REQUIRED;
        unset($incomplete['password']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('password');
        new FolioConfig($incomplete);
    }

    public function testConstructFromIniFile(): void {
        $iniPath = tempnam(sys_get_temp_dir(), 'folio_config_');
        file_put_contents($iniPath, <<<INI
        okapiUrl = "https://okapi.example.edu"
        tenant_id = "diku"
        username = "diku_admin"
        password = "secret"
        debug = true
        timeout = 60
        sslVerify = false
        INI);

        try {
            $config = new FolioConfig($iniPath);

            // B12: debug/timeout must parse as real bool/int, not strings,
            // or this would have thrown a TypeError before we got here.
            $this->assertTrue($config->debug);
            $this->assertSame(60, $config->timeout);
            // B14: sslVerify=false in an INI file must become a real bool.
            $this->assertFalse($config->sslVerify);
        } finally {
            unlink($iniPath);
        }
    }

    public function testConstructThrowsWhenIniFileMissing(): void {
        $this->expectException(InvalidArgumentException::class);
        new FolioConfig(sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.ini');
    }

    public function testOptionalPropertiesHaveSensibleDefaults(): void {
        $config = new FolioConfig(self::REQUIRED);

        $this->assertTrue($config->sslVerify);
        $this->assertFalse($config->debug);
        $this->assertSame(30, $config->timeout);
        $this->assertSame('America/Chicago', $config->localTimeZone);
        $this->assertNull($config->getCentralTenantId());
        $this->assertSame('', $config->name);
    }

    public function testNamePropertyCanBeSetWithoutError(): void {
        // B13: 'name' is a declared property now, not a dynamic one.
        $config = new FolioConfig(self::REQUIRED + ['name' => 'primary']);

        $this->assertSame('primary', $config->name);
    }

    /**
     * @dataProvider booleanLikeStringProvider
     */
    public function testSslVerifyNormalizesRecognizedBooleanStrings(string $input, bool $expected): void {
        $config = new FolioConfig(self::REQUIRED + ['sslVerify' => $input]);

        $this->assertSame($expected, $config->sslVerify);
    }

    public static function booleanLikeStringProvider(): array {
        return [
            ['true', true],
            ['TRUE', true],
            ['false', false],
            ['FALSE', false],
            ['yes', true],
            ['no', false],
            ['on', true],
            ['off', false],
            ['1', true],
            ['0', false],
            ['', false],
        ];
    }

    public function testSslVerifyLeavesCaBundlePathStringUntouched(): void {
        // B14: a real CA-bundle path isn't one of the recognized
        // boolean-like strings, so it must be passed through as-is.
        $config = new FolioConfig(self::REQUIRED + ['sslVerify' => '/etc/ssl/certs/ca-bundle.crt']);

        $this->assertSame('/etc/ssl/certs/ca-bundle.crt', $config->sslVerify);
    }

    public function testDebugInfoRedactsPassword(): void {
        $config = new FolioConfig(self::REQUIRED);

        $vars = $config->__debugInfo();

        $this->assertSame('***REDACTED***', $vars['password']);
        $this->assertSame('diku_admin', $vars['username']);
    }

    public function testCentralTenantIdCanBeSetExplicitly(): void {
        $config = new FolioConfig(self::REQUIRED + ['central_tenant_id' => 'consortium']);

        $this->assertSame('consortium', $config->getCentralTenantId());
    }
}
