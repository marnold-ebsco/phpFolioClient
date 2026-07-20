<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use PHPUnit\Framework\TestCase;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioInformationTest extends TestCase {
    private FolioInformation $folioInformation;
    private FolioConfig $mockConfig;
    private FolioAuth $mockAuth;

    protected function setUp(): void {
        $this->mockConfig = $this->createMock(FolioConfig::class);
        $this->mockAuth = $this->createMock(FolioAuth::class);
        $this->folioInformation = new FolioInformation($this->mockConfig, $this->mockAuth);
    }

    #[Test]
    public function testGetAuthFlavorReturnsAuthFlavorFromAuth(): void {
        $this->mockAuth->method('getAuthFlavor')->willReturn('oauth2');
        
        $result = $this->folioInformation->getAuthFlavor();
        
        $this->assertEquals('oauth2', $result);
    }

    #[Test]
    public function testGetUrlReturnsApiUrlFromConfig(): void {
        $this->mockConfig->method('getApiUrl')->willReturn('https://api.example.com');
        
        $result = $this->folioInformation->getUrl();
        
        $this->assertEquals('https://api.example.com', $result);
    }

    #[Test]
    public function testGetTenantIdReturnsTenantIdFromConfig(): void {
        $this->mockConfig->tenant_id = 'tenant-123';
        
        $result = $this->folioInformation->getTenantId();
        
        $this->assertEquals('tenant-123', $result);
    }

    #[Test]
    public function testGetCentralTenantIdReturnsCentralTenantIdWhenSet(): void {
        $this->mockConfig->central_tenant_id = 'central-tenant-456';
        
        $result = $this->folioInformation->getCentralTenantId();
        
        $this->assertEquals('central-tenant-456', $result);
    }

    #[Test]
    public function testGetCentralTenantIdReturnsEmptyStringWhenNotSet(): void {
        $this->mockConfig->central_tenant_id = null;
        
        $result = $this->folioInformation->getCentralTenantId();
        
        $this->assertEquals('', $result);
    }

    #[Test]
    #[DataProvider('hostnameProvider')]
    public function testGetHostnameExtractsSubdomainCorrectly(string $url, string $expected): void {
        $this->mockConfig->method('getApiUrl')->willReturn($url);
        
        $result = $this->folioInformation->getHostname();
        
        $this->assertEquals($expected, $result);
    }

    public static function hostnameProvider(): array {
        return [
            'happy path - standard subdomain' => [
                'https://demo.okapi.example.com/okapi',
                'demo'
            ],
            'subdomain prefix to remove' => [
                'https://subdomain-demo.example.com',
                'demo'
            ],
            'okapi prefix to remove' => [
                'https://okapi-demo.example.com',
                'demo'
            ],
            'api prefix to remove' => [
                'https://api-demo.example.com',
                'demo'
            ],
            'kong prefix to remove' => [
                'https://kong-demo.example.com',
                'demo'
            ],
            'okapi suffix to remove' => [
                'https://demo-okapi.example.com',
                'demo'
            ],
            'single word hostname' => [
                'https://localhost',
                'localhost'
            ],
        ];
    }

    #[Test]
    public function testGetUsernameReturnsUsernameFromConfig(): void {
        $this->mockConfig->username = 'testuser';
        
        $result = $this->folioInformation->getUsername();
        
        $this->assertEquals('testuser', $result);
    }
}
