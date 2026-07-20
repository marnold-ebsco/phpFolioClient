<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use phpFolioClient\FolioFileHandler;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioFileHandlerTest extends TestCase
{
    private FolioFileHandler $handler;
    private FolioClient $mockClient;
    private FolioConfig $mockConfig;
    private FolioAuth $mockAuth;

    protected function setUp(): void
    {
        $this->mockAuth = $this->createMock(FolioAuth::class);
        $this->mockAuth->method('getAccessToken')->willReturn('test-token-123');

        $this->mockConfig = $this->createMock(FolioConfig::class);
        $this->mockConfig->central_tenant_id = null;
        $this->mockConfig->tenant_id = 'test-tenant';
        $this->mockConfig->okapiUrl = 'https://example.com';
        $this->mockConfig->sslVerify = true;

        $this->mockClient = $this->createMock(FolioClient::class);
        $this->mockClient->method('getConfig')->willReturn($this->mockConfig);
        $this->mockClient->method('getAuth')->willReturn($this->mockAuth);

        $this->handler = new FolioFileHandler($this->mockClient);
    }

    #[Test]
    public function testPutFileWithValidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test file content');

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->with(
                'POST',
                'endpoint',
                null,
                [],
                'test-tenant',
                $this->anything()
            )
            ->willReturn(['success' => true]);

        $result = $this->handler->putFile('endpoint', $tempFile);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        unlink($tempFile);
    }

    #[Test]
    public function testPutFileWithNonexistentFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Could not open filename/');

        $this->handler->putFile('endpoint', '/nonexistent/path/to/file.txt');
    }

    #[Test]
    public function testPutFileWithEndpointTrimming(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->with(
                'POST',
                'endpoint',
                null,
                [],
                'test-tenant',
                $this->anything()
            )
            ->willReturn([]);

        $this->handler->putFile('  /endpoint/ ', $tempFile);

        unlink($tempFile);
    }

    #[Test]
    public function testPutFileWithCustomTenantId(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->with(
                'POST',
                'endpoint',
                null,
                [],
                'custom-tenant',
                $this->anything()
            )
            ->willReturn([]);

        $this->handler->putFile('endpoint', $tempFile, 'custom-tenant');

        unlink($tempFile);
    }

    #[Test]
    public function testPutFileWithCentralTenantId(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $this->mockConfig->central_tenant_id = 'central-tenant';

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->with(
                'POST',
                'endpoint',
                null,
                [],
                'central-tenant',
                $this->anything()
            )
            ->willReturn([]);

        $this->handler->putFile('endpoint', $tempFile);

        unlink($tempFile);
    }

    #[Test]
    public function testPostFileCallsPutFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->willReturn(['success' => true]);

        $result = $this->handler->postFile('endpoint', $tempFile, 'test-tenant');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        unlink($tempFile);
    }

    #[Test]
    public function testPostFileWithException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/PutFile Error/');

        $this->handler->postFile('endpoint', '/nonexistent/file.txt');
    }

    // #[Test]
    // public function testGetFileCreatesFileAtDestination(): void
    // {
    //     $tempDir = sys_get_temp_dir();
    //     $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'get_test_' . uniqid() . '.pdf';
    //     $url = 'https://example.com/file.pdf';

    //     $mockGuzzleResponse = $this->createMock(\GuzzleHttp\Psr7\Response::class);
    //     $mockGuzzleResponse->method('getStatusCode')->willReturn(200);

    //     $mockGuzzleClient = $this->createMock(\GuzzleHttp\Client::class);
    //     $mockGuzzleClient
    //         ->expects($this->once())
    //         ->method('get')
    //         ->with($url, $this->anything())
    //         ->willReturn($mockGuzzleResponse);

    //     // Since getFile instantiates Client directly, we test the error path instead
    //     $this->expectException(\Exception::class);
    //     $this->expectExceptionMessageMatches('/GetFile Error/');
        
    //     $this->handler->getFile($tempFile, $url);
    // }


    #[Test]
    public function testGetFileWithInvalidFilename(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/GetFile Error/');

        $invalidFile = '/nonexistent/directory/path/invalid_file_' . uniqid() . '.txt';
        $this->handler->getFile($invalidFile, 'https://invalid-url-that-does-not-exist.com/file.pdf');
    }

    #[Test]
    #[DataProvider('provideInvalidEndpoints')]
    public function testPutFileWithVariousEndpointFormats(string $endpoint, string $expectedEndpoint): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $this->mockClient
            ->expects($this->once())
            ->method('_request')
            ->with(
                'POST',
                $expectedEndpoint,
                null,
                [],
                'test-tenant',
                $this->anything()
            )
            ->willReturn([]);

        $this->handler->putFile($endpoint, $tempFile);

        unlink($tempFile);
    }

    public static function provideInvalidEndpoints(): array
    {
        return [
            'endpoint with leading slash' => ['/endpoint', 'endpoint'],
            'endpoint with trailing slash' => ['endpoint/', 'endpoint'],
            'endpoint with both slashes' => ['/endpoint/', 'endpoint'],
            'endpoint with spaces' => ['  endpoint  ', 'endpoint'],
            'endpoint with tabs and newlines' => ["\tendpoint\n", 'endpoint'],
        ];
    }
}
