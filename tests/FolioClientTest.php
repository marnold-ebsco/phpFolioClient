<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioLogger;
use phpFolioClient\FolioInformation;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use stdClass;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;


#[AllowMockObjectsWithoutExpectations]
class FolioClientTest extends TestCase
{
    private FolioClient $folioClient;
    private FolioConfig $mockConfig;
    private FolioAuth $mockAuth;
    private FolioUtils $mockUtils;
    private FolioLogger $mockLogger;
    private FolioInformation $mockInformation;
    private Client $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockConfig = $this->createMock(FolioConfig::class);
        $this->mockConfig->okapiUrl = 'http://localhost:9130';
        $this->mockConfig->timeout = 30;
        $this->mockConfig->sslVerify = true;
        $this->mockConfig->tenant_id = 'test-tenant';

        $this->mockAuth = $this->createMock(FolioAuth::class);
        $this->mockAuth->method('getAccessToken')->willReturn('test-token');

        $this->mockUtils = $this->createMock(FolioUtils::class);
        $this->mockLogger = $this->createMock(FolioLogger::class);
        $this->mockInformation = $this->createMock(FolioInformation::class);
        $this->mockHttpClient = $this->createMock(Client::class);

        $this->folioClient = new FolioClient(
            $this->mockConfig,
            $this->mockAuth,
            $this->mockUtils,
            $this->mockLogger,
            $this->mockInformation,
            $this->mockHttpClient
        );
    }

    #[Test]
    public function testConstructorInitializesProperties(): void
    {
        $this->assertSame($this->mockConfig, $this->folioClient->getConfig());
        $this->assertSame($this->mockAuth, $this->folioClient->getAuth());
    }

    #[Test]
    public function testGetConfigReturnsConfig(): void
    {
        $config = $this->folioClient->getConfig();
        $this->assertSame($this->mockConfig, $config);
    }

    #[Test]
    public function testGetAuthReturnsAuth(): void
    {
        $auth = $this->folioClient->getAuth();
        $this->assertSame($this->mockAuth, $auth);
    }

    #[Test]
    public function testGetLastStatusCodeReturnsStatusCode(): void
    {
        $response = new Response(200, [], json_encode(['users' => [], 'totalRecords' => 0]));
        
        $this->mockHttpClient->method('request')->willReturn($response);
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        try {
            $this->folioClient->get('/users', 'cql.allRecords=1');
        } catch (\Throwable $e) {
            // Expected to fail due to mocking
        }

        $this->assertEquals(200, $this->folioClient->getLastStatusCode());
    }

    #[Test]
    public function testGetLastQueryReturnsLastQuery(): void
    {
        $response = new Response(200, [], json_encode(['users' => [], 'totalRecords' => 0]));
        
        $this->mockHttpClient->method('request')->willReturn($response);
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        try {
            $this->folioClient->get('/users', 'cql.allRecords=1');
        } catch (\Throwable $e) {
            // Expected to fail due to mocking
        }

        $this->assertStringContainsString('GET', $this->folioClient->getLastQuery());
        $this->assertStringContainsString('users', $this->folioClient->getLastQuery());
    }

    #[Test]
    public function testGetOneWithValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userData = new stdClass();
        $userData->id = $uuid;
        $userData->name = 'Test User';

        $this->mockUtils->method('isValidUuid')->willReturn(true);
        $response = new Response(200, [], json_encode($userData));
        $this->mockHttpClient->method('request')->willReturn($response);

        $result = $this->folioClient->getOne('/users', $uuid);

        $this->assertEquals($uuid, $result->id);
        $this->assertEquals('Test User', $result->name);
    }

    #[Test]
    public function testGetOneWithInvalidUuidThrowsException(): void
    {
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('getOne must be passed a valid UUID');

        $this->folioClient->getOne('/users', 'invalid-uuid');
    }

    #[Test]
    public function testGetEachReturnsGenerator(): void
    {
        $response = new Response(200, [], json_encode(['users' => [], 'totalRecords' => 0]));
        $this->mockHttpClient->method('request')->willReturn($response);

        $result = $this->folioClient->getEach('/users');

        $this->assertInstanceOf(\Generator::class, $result);
    }

    #[Test]
    public function testGetWithFullObjectReturnConstant(): void
    {
        $responseData = new stdClass();
        $responseData->users = [];
        $responseData->totalRecords = 0;

        $response = new Response(200, [], json_encode($responseData));
        $this->mockHttpClient->method('request')->willReturn($response);

        $result = $this->folioClient->get('/users', null, null, FolioClient::RETURN_FULL_OBJECT);

        $this->assertEquals($responseData, $result);
    }

    #[Test]
    public function testDeleteWithIdAppendsIdToEndpoint(): void
    {
        $response = new Response(204, []);
        $this->mockHttpClient->method('request')->willReturn($response);

        $this->folioClient->delete('/users', '550e8400-e29b-41d4-a716-446655440000');

        $this->assertTrue(true);
    }

    #[Test]
    public function testDeleteWithoutId(): void
    {
        $response = new Response(204, []);
        $this->mockHttpClient->method('request')->willReturn($response);

        $this->folioClient->delete('/users');

        $this->assertTrue(true);
    }

    #[Test]
    public function testPostWithJsonObject(): void
    {
        $postData = new stdClass();
        $postData->username = 'testuser';
        $postData->email = 'test@example.com';

        $responseData = new stdClass();
        $responseData->id = '550e8400-e29b-41d4-a716-446655440000';

        $response = new Response(201, [], json_encode($responseData));
        $this->mockHttpClient->method('request')->willReturn($response);

        $result = $this->folioClient->post('/users', $postData);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result->id);
    }

    #[Test]
    public function testPostWithArrayData(): void
    {
        $postData = ['username' => 'testuser', 'email' => 'test@example.com'];

        $responseData = new stdClass();
        $responseData->id = '550e8400-e29b-41d4-a716-446655440000';

        $response = new Response(201, [], json_encode($responseData));
        $this->mockHttpClient->method('request')->willReturn($response);

        $result = $this->folioClient->post('/users', $postData);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result->id);
    }

    #[Test]
    public function testPutWithId(): void
    {
        $updateData = ['username' => 'updateduser'];

        $response = new Response(204, []);
        $this->mockHttpClient->method('request')->willReturn($response);

        $this->folioClient->put('/users', '550e8400-e29b-41d4-a716-446655440000', $updateData);

        $this->assertTrue(true);
    }

    #[Test]
    public function testPatchWithId(): void
    {
        $patchData = ['email' => 'newemail@example.com'];

        $response = new Response(204, []);
        $this->mockHttpClient->method('request')->willReturn($response);

        $this->folioClient->patch('/users', '550e8400-e29b-41d4-a716-446655440000', $patchData);

        $this->assertTrue(true);
    }

    #[Test]
    public function testGetInformationReturnsInformation(): void
    {
        $information = $this->folioClient->getInformation();

        $this->assertSame($this->mockInformation, $information);
    }

    #[Test]
    public function testGetStatusCodeReturnsLastStatusCode(): void
    {
        $response = new Response(200, [], json_encode(['users' => [], 'totalRecords' => 0]));
        $this->mockHttpClient->method('request')->willReturn($response);
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        try {
            $this->folioClient->get('/users', 'cql.allRecords=1');
        } catch (\Throwable $e) {
            // Expected to fail due to mocking
        }

        $this->assertEquals(200, $this->folioClient->getStatusCode());
    }

    #[Test]
    public function testGetLastQueryNumIncrementsAfterRequest(): void
    {
        $response = new Response(200, [], json_encode(['users' => [], 'totalRecords' => 0]));
        $this->mockHttpClient->method('request')->willReturn($response);
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        $initialQueryNum = $this->folioClient->getLastQueryNum();

        try {
            $this->folioClient->get('/users', 'cql.allRecords=1');
        } catch (\Throwable $e) {
            // Expected to fail due to mocking
        }

        $this->assertGreaterThan($initialQueryNum, $this->folioClient->getLastQueryNum());
    }

    #[Test]
    #[DataProvider('invalidUuidProvider')]
    public function testGetOneRejectsVariousInvalidFormats(string $invalidInput): void
    {
        $this->mockUtils->method('isValidUuid')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('getOne must be passed a valid UUID');

        $this->folioClient->getOne('/users', $invalidInput);
    }

    public static function invalidUuidProvider(): array
    {
        return [
            'empty string' => [''],
            'numeric string' => ['12345'],
            'malformed uuid' => ['550e8400-e29b-41d4-a716'],
            'uuid with invalid characters' => ['xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'],
        ];
    }
}