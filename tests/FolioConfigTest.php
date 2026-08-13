<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use InvalidArgumentException;
use Exception;
use phpFolioClient\FolioConfig;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioConfigTest extends TestCase {
    private array $validConfig;

    protected function setUp(): void {
        $this->validConfig = [
            'okapiUrl' => 'https://folio.example.com',
            'tenant_id' => 'tenant1',
            'username' => 'user@example.com',
            'password' => 'secret123',
        ];
    }

    #[Test]
    public function testConstructorWithValidArrayConfig(): void {
        $config = new FolioConfig($this->validConfig);
        
        $this->assertEquals('https://folio.example.com', $config->okapiUrl);
        $this->assertEquals('tenant1', $config->tenant_id);
        $this->assertEquals('user@example.com', $config->username);
        $this->assertEquals('secret123', $config->password);
    }

    #[Test]
    public function testConstructorWithValidObjectConfig(): void {
        $configObj = (object) $this->validConfig;
        $config = new FolioConfig($configObj);
        
        $this->assertEquals('https://folio.example.com', $config->okapiUrl);
        $this->assertEquals('tenant1', $config->tenant_id);
    }

    #[Test]
    public function testConstructorWithOptionalProperties(): void {
        $config_data = array_merge($this->validConfig, [
            'central_tenant_id' => 'central_tenant',
            'sslVerify' => false,
            'debug' => true,
        ]);
        
        $config = new FolioConfig($config_data);
        
        $this->assertEquals('central_tenant', $config->central_tenant_id);
        $this->assertFalse($config->sslVerify);
        $this->assertTrue($config->debug);
    }

    #[Test]
    public function testConstructorThrowsExceptionOnMissingOkapiUrl(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required config key: okapiUrl');
        
        $config_data = $this->validConfig;
        unset($config_data['okapiUrl']);
        new FolioConfig($config_data);
    }

    #[Test]
    public function testConstructorThrowsExceptionOnMissingTenantId(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required config key: tenant_id');
        
        $config_data = $this->validConfig;
        unset($config_data['tenant_id']);
        new FolioConfig($config_data);
    }

    #[Test]
    public function testConstructorThrowsExceptionOnMissingUsername(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required config key: username');
        
        $config_data = $this->validConfig;
        unset($config_data['username']);
        new FolioConfig($config_data);
    }

    #[Test]
    public function testConstructorThrowsExceptionOnMissingPassword(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required config key: password');
        
        $config_data = $this->validConfig;
        unset($config_data['password']);
        new FolioConfig($config_data);
    }

    #[Test]
    #[DataProvider('provideInvalidConfigInputs')]
    public function testConstructorWithInvalidInputs(mixed $config, string $expectedException): void {
        $this->expectException($expectedException);
        new FolioConfig($config);
    }

    public static function provideInvalidConfigInputs(): iterable {
        yield 'non-existent ini file' => ['/path/to/nonexistent/file.ini', InvalidArgumentException::class];
    }

    #[Test]
    public function testGetApiUrl(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertEquals('https://folio.example.com', $config->getApiUrl());
    }

    #[Test]
    public function testGetTenantId(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertEquals('tenant1', $config->getTenantId());
    }

    #[Test]
    public function testGetCentralTenantId(): void {
        $config_data = array_merge($this->validConfig, ['central_tenant_id' => 'central1']);
        $config = new FolioConfig($config_data);
        $this->assertEquals('central1', $config->getCentralTenantId());
    }

    #[Test]
    public function testGetUsername(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertEquals('user@example.com', $config->getUsername());
    }

    #[Test]
    public function testDefaultTimeoutValue(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertEquals(30, $config->timeout);
    }

    #[Test]
    public function testDefaultSslVerifyValue(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertTrue($config->sslVerify);
    }

    #[Test]
    public function testDefaultDebugValue(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertFalse($config->debug);
    }

    #[Test]
    public function testDefaultLocalTimeZone(): void {
        $config = new FolioConfig($this->validConfig);
        $this->assertEquals('America/Chicago', $config->localTimeZone);
    }

    #[Test]
    public function testEmptyStringValuesInRequiredFields(): void {
        $config_data = array_merge($this->validConfig, ['okapiUrl' => '']);
        $config = new FolioConfig($config_data);
        $this->assertEquals('', $config->okapiUrl);
    }
}
