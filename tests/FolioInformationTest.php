<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use phpFolioClient\FolioConfig;
use phpFolioClient\FolioInformation;
use phpFolioClient\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

final class FolioInformationTest extends TestCase {
    private function makeConfig(array $overrides = []): FolioConfig {
        return new FolioConfig(array_merge([
            'okapiUrl' => 'https://okapi.example.edu',
            'tenant_id' => 'diku',
            'username' => 'diku_admin',
            'password' => 'secret',
        ], $overrides));
    }

    public function testGetAuthFlavorDelegatesToAuth(): void {
        $info = new FolioInformation($this->makeConfig(), new StubAuth());

        $this->assertSame('RTR', $info->getAuthFlavor());
    }

    public function testGetUrlDelegatesToConfig(): void {
        $info = new FolioInformation($this->makeConfig(), new StubAuth());

        $this->assertSame('https://okapi.example.edu', $info->getUrl());
    }

    public function testGetTenantId(): void {
        $info = new FolioInformation($this->makeConfig(['tenant_id' => 'diku']), new StubAuth());

        $this->assertSame('diku', $info->getTenantId());
    }

    public function testGetCentralTenantIdDefaultsToEmptyStringWhenNull(): void {
        $info = new FolioInformation($this->makeConfig(), new StubAuth());

        $this->assertSame('', $info->getCentralTenantId());
    }

    public function testGetCentralTenantIdReturnsValueWhenSet(): void {
        $info = new FolioInformation($this->makeConfig(['central_tenant_id' => 'consortium']), new StubAuth());

        $this->assertSame('consortium', $info->getCentralTenantId());
    }

    public function testGetUsername(): void {
        $info = new FolioInformation($this->makeConfig(), new StubAuth());

        $this->assertSame('diku_admin', $info->getUsername());
    }

    /**
     * @dataProvider hostnameProvider
     */
    public function testGetHostnameStripsKnownPrefixesAndSuffixes(string $url, string $expected): void {
        $info = new FolioInformation($this->makeConfig(['okapiUrl' => $url]), new StubAuth());

        $this->assertSame($expected, $info->getHostname());
    }

    public static function hostnameProvider(): array {
        return [
            'okapi- prefix' => ['https://okapi-sandbox.folio.org', 'sandbox'],
            '-okapi suffix' => ['https://sandbox-okapi.folio.org', 'sandbox'],
            'api- prefix' => ['https://api-test.folio.org', 'test'],
            'kong- prefix' => ['https://kong-demo.folio.org', 'demo'],
            'no known prefix/suffix' => ['https://plainname.folio.org', 'plainname'],
        ];
    }

    /**
     * B23: getHostname() must throw a clear exception instead of passing
     * null into explode() when the configured URL has no parseable host.
     */
    public function testGetHostnameThrowsOnUnparseableUrl(): void {
        $info = new FolioInformation($this->makeConfig(['okapiUrl' => '/just/a/path']), new StubAuth());

        $this->expectException(\Exception::class);
        $info->getHostname();
    }
}
