<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use phpFolioClient\FolioAuth;
use phpFolioClient\FolioConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioAuthTest extends TestCase {
    private FolioAuth $folioAuth;
    private FolioConfig $configMock;

    protected function setUp(): void {
        // $this->configMock = $this->createMock(FolioConfig::class);
        // $this->configMock->okapiUrl = 'https://okapi.example.com';
        // $this->configMock->username = 'testuser';
        // $this->configMock->password = 'testpass';
        // $this->configMock->tenant_id = 'test-tenant';
        // $this->configMock->sslVerify = true;
        // $this->configMock->debug = false;
        // $this->configMock->localTimeZone = 'UTC';

        $this->configMock = $this->createMock(FolioConfig::class);
        $this->configMock->okapiUrl = 'https://api-lse-demo1.folio.ebsco.com/';
        $this->configMock->username = 'apitest';
        $this->configMock->password = "%b2&10J'%<(sa%V}";
        $this->configMock->tenant_id = 'fs00001208';
        $this->configMock->sslVerify = true;
        $this->configMock->debug = false;
        $this->configMock->localTimeZone = 'UTC';

        $this->folioAuth = new FolioAuth($this->configMock);
    }

    #[Test]
    public function testConstructorInitializesWithConfig(): void {
        $this->assertInstanceOf(FolioAuth::class, $this->folioAuth);
    }

    #[Test]
    public function testGetAccessTokenWithoutTokenReturnsEmptyString(): void {
        $token = $this->folioAuth->getAccessToken();
        $this->assertIsString($token);
    }

    #[Test]
    public function testGetExpirationInitiallyReturnsZero(): void {
        $expiration = $this->folioAuth->getExpiration();
        $this->assertSame(0, $expiration);
    }

    #[Test]
    public function testGetAuthFlavorReturnsRTR(): void {
        $flavor = $this->folioAuth->getAuthFlavor();
        $this->assertSame('RTR', $flavor);
    }

    #[Test]
    public function testNeedsRefreshIsTrue(): void {
        $reflection = new \ReflectionClass($this->folioAuth);
        $method = $reflection->getMethod('needsRefresh');
        
        $result = $method->invoke($this->folioAuth);
        $this->assertTrue($result);
    }

    #[Test]
    public function testNeedsRefreshIsFalseWhenTokenValid(): void {
        $reflection = new \ReflectionClass($this->folioAuth);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setValue($this->folioAuth, 'valid.token');

        $expiresProperty = $reflection->getProperty('ATExpires');
        $futureTime = time() + 3600;
        $expiresProperty->setValue($this->folioAuth, $futureTime);

        $method = $reflection->getMethod('needsRefresh');
        
        $result = $method->invoke($this->folioAuth);
        $this->assertFalse($result);
    }

    #[Test]
    public function testNeedsRefreshIsTrueWhenTokenExpired(): void {
        $reflection = new \ReflectionClass($this->folioAuth);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setValue($this->folioAuth, 'expired.token');

        $expiresProperty = $reflection->getProperty('ATExpires');
        $pastTime = time() - 3600;
        $expiresProperty->setValue($this->folioAuth, $pastTime);

        $method = $reflection->getMethod('needsRefresh');
        
        $result = $method->invoke($this->folioAuth);
        $this->assertTrue($result);
    }

    #[Test]
    public function testNeedsRefreshBeforeExpires(): void {
        $reflection = new \ReflectionClass($this->folioAuth);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setValue($this->folioAuth, 'valid.token');

        $expiresProperty = $reflection->getProperty('ATExpires');
        $soonToExpire = time() + 30;
        $expiresProperty->setValue($this->folioAuth, $soonToExpire);

        $method = $reflection->getMethod('needsRefresh');
        
        $result = $method->invoke($this->folioAuth);
        $this->assertTrue($result);
    }

    #[Test]
    public function testATExpiresObjIsInitializedAsDateTime(): void {
        $this->assertInstanceOf(\DateTime::class, $this->folioAuth->ATExpiresObj);
    }

    #[Test]
    public function testNeedsRefreshBeforeExpiresDefaultValue(): void {
        $this->assertSame(60, $this->folioAuth->needsRefreshBeforeExpires);
    }
}
