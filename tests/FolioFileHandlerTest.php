<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioFileHandler;
use phpFolioClient\FolioUtils;
use phpFolioClient\Tests\Support\PhpServerTrait;
use phpFolioClient\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * putFile()/putFileX()/postFile() route through FolioClient::rawRequest(),
 * so they're testable with a MockHandler. getFile() builds its own Guzzle
 * client internally, so its tests run against the shared local PHP server
 * fixture instead (see tests/fixtures/mock_server_router.php).
 */
final class FolioFileHandlerTest extends TestCase {
    use PhpServerTrait;

    private static string $baseUrl;
    private array $tempFiles = [];

    public static function setUpBeforeClass(): void {
        self::$baseUrl = self::startPhpServer(__DIR__ . '/fixtures/mock_server_router.php');
    }

    public static function tearDownAfterClass(): void {
        self::stopPhpServer();
    }

    protected function tearDown(): void {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    private function makeTempFile(string $contents): string {
        $path = tempnam(sys_get_temp_dir(), 'folio_upload_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Builds a FolioFileHandler backed by a MockHandler queue. Pass a
     * variable by reference as $captured to record, for each request,
     * the {request, body, options} actually sent — the request body is
     * read and rewound eagerly (rather than lazily via history
     * middleware), since putFile()'s finally-block fclose() means the
     * underlying stream resource is no longer readable by the time a
     * test method would otherwise inspect it after the call returns.
     */
    private function buildFileHandler(array $responses, ?array &$captured = null): FolioFileHandler {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        if ($captured !== null) {
            $captured = [];
            $stack->push(function (callable $handler) use (&$captured) {
                return function ($request, $options) use ($handler, &$captured) {
                    $body = (string) $request->getBody();
                    $request->getBody()->rewind();
                    $captured[] = ['request' => $request, 'body' => $body, 'options' => $options];
                    return $handler($request, $options);
                };
            });
        }
        $httpClient = new Client(['handler' => $stack, 'base_uri' => 'https://okapi.example.edu']);

        $config = new FolioConfig([
            'okapiUrl' => 'https://okapi.example.edu',
            'tenant_id' => 'diku',
            'username' => 'diku_admin',
            'password' => 'secret',
        ]);
        $client = new FolioClient($config, new StubAuth(), new FolioUtils(), null, null, $httpClient);

        return new FolioFileHandler($client);
    }

    // --- putFile() -------------------------------------------------------

    public function testPutFileUploadsFileContentsAndReturnsDecodedResponse(): void {
        $filePath = $this->makeTempFile('id1,id2,id3');
        $captured = [];
        $handler = $this->buildFileHandler([new Response(200, [], '{"id":"file-def-1"}')], $captured);

        $result = $handler->putFile('/data-export/file-definitions/xyz/upload', $filePath);

        $this->assertSame('file-def-1', $result->id);
        $this->assertSame('id1,id2,id3', $captured[0]['body']);
        $this->assertSame('application/octet-stream', $captured[0]['request']->getHeaderLine('Content-Type'));
    }

    /**
     * B21 regression: a tenant override passed to putFile() must actually
     * take effect as the X-Okapi-Tenant header sent, not silently lose to
     * (or duplicate alongside) the client's default tenant.
     */
    public function testPutFileTenantOverrideTakesEffect(): void {
        $filePath = $this->makeTempFile('data');
        $captured = [];
        $handler = $this->buildFileHandler([new Response(200, [], '{}')], $captured);

        $handler->putFile('/upload', $filePath, 'central-tenant');

        $request = $captured[0]['request'];
        $this->assertSame('central-tenant', $request->getHeaderLine('X-Okapi-Tenant'));
        // Exactly one value for this header — no duplicate differently-cased entry.
        $this->assertCount(1, $request->getHeader('X-Okapi-Tenant'));
    }

    public function testPutFileThrowsWhenFileDoesNotExist(): void {
        $handler = $this->buildFileHandler([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PutFile Error');
        $handler->putFile('/upload', sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.csv');
    }

    /**
     * B20 regression: on Windows, an open file handle blocks rename/delete.
     * If putFile() left the handle open, this rename would fail.
     */
    public function testPutFileClosesFileHandleAfterUpload(): void {
        $filePath = $this->makeTempFile('data');
        $handler = $this->buildFileHandler([new Response(200, [], '{}')]);

        $handler->putFile('/upload', $filePath);

        $movedPath = $filePath . '.moved';
        $this->assertTrue(rename($filePath, $movedPath), 'File handle should be closed after putFile() returns.');
        unlink($movedPath);
        $this->tempFiles = array_filter($this->tempFiles, fn($p) => $p !== $filePath);
    }

    // --- putFileX() --------------------------------------------------------

    /**
     * S3: the hardcoded 'debug' => true option was removed.
     */
    public function testPutFileXDoesNotSetDebugOption(): void {
        $filePath = $this->makeTempFile('data');
        $captured = [];
        $handler = $this->buildFileHandler([new Response(200, [], '{}')], $captured);

        $handler->putFileX('/upload', $filePath);

        $this->assertArrayNotHasKey('debug', $captured[0]['options']);
    }

    public function testPutFileXUploadsFileContents(): void {
        $filePath = $this->makeTempFile('raw stream contents');
        $captured = [];
        $handler = $this->buildFileHandler([new Response(200, [], '{}')], $captured);

        $handler->putFileX('/upload', $filePath);

        $this->assertSame('raw stream contents', $captured[0]['body']);
    }

    // --- postFile() ----------------------------------------------------

    public function testPostFileIsAliasOfPutFile(): void {
        $filePath = $this->makeTempFile('alias test');
        $captured = [];
        $handler = $this->buildFileHandler([new Response(200, [], '{"id":"ok"}')], $captured);

        $result = $handler->postFile('/upload', $filePath);

        $this->assertSame('ok', $result->id);
        $this->assertSame('alias test', $captured[0]['body']);
    }

    public function testPostFileWrapsUnderlyingExceptionWithCause(): void {
        $handler = $this->buildFileHandler([]);

        try {
            $handler->postFile('/upload', sys_get_temp_dir() . '/missing_' . uniqid() . '.csv');
            $this->fail('Expected an exception.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('PutFile Error', $e->getMessage());
            $this->assertNotNull($e->getPrevious());
        }
    }

    // --- getFile() (local server integration) -----------------------------

    public function testGetFileDownloadsContentSuccessfully(): void {
        $handler = $this->buildFileHandler([]);
        $outPath = tempnam(sys_get_temp_dir(), 'folio_download_');
        $this->tempFiles[] = $outPath;

        $handler->getFile($outPath, self::$baseUrl . '/download/ok');

        $this->assertSame('mock exported file content', file_get_contents($outPath));
    }

    public function testGetFileThrowsOnNonSuccessStatus(): void {
        $handler = $this->buildFileHandler([]);
        $outPath = tempnam(sys_get_temp_dir(), 'folio_download_');
        $this->tempFiles[] = $outPath;

        try {
            $handler->getFile($outPath, self::$baseUrl . '/download/notfound');
            $this->fail('Expected an exception.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('GetFile Error', $e->getMessage());
            $this->assertNotNull($e->getPrevious());
        }
    }

    public function testGetFileThrowsWhenDestinationDirectoryMissing(): void {
        $handler = $this->buildFileHandler([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('GetFile Error');
        $handler->getFile('/no/such/directory/out.csv', self::$baseUrl . '/download/ok');
    }

    /**
     * B20 regression, for getFile()'s write-side handle.
     */
    public function testGetFileClosesFileHandleAfterDownload(): void {
        $handler = $this->buildFileHandler([]);
        $outPath = tempnam(sys_get_temp_dir(), 'folio_download_');

        $handler->getFile($outPath, self::$baseUrl . '/download/ok');

        $movedPath = $outPath . '.moved';
        $this->assertTrue(rename($outPath, $movedPath), 'File handle should be closed after getFile() returns.');
        unlink($movedPath);
    }
}
