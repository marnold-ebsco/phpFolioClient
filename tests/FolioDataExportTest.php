<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioDataExport;
use phpFolioClient\FolioFileHandler;
use phpFolioClient\FolioUtils;
use phpFolioClient\Tests\Support\PhpServerTrait;
use phpFolioClient\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

/**
 * dataExport()/dataExportAll() compose several FolioClient calls (mocked
 * via a MockHandler queue, since FolioFileHandler::putFile() also routes
 * through the same client) followed by a real file download through
 * FolioFileHandler::getFile() against the shared local server fixture.
 *
 * Both methods poll their job execution in a do/while loop that always
 * sleeps 1 real second per iteration (even on the very first, immediately
 * terminal check) — that's pre-existing production behavior, not
 * something these tests introduce, so each happy-path test here takes
 * a bit over a second.
 */
final class FolioDataExportTest extends TestCase {
    use PhpServerTrait;

    private static string $baseUrl;
    private array $tempFiles = [];
    private array $tempDirs = [];

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
        foreach ($this->tempDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        $this->tempFiles = [];
        $this->tempDirs = [];
    }

    private function makeIdFile(): string {
        $path = tempnam(sys_get_temp_dir(), 'folio_export_ids_');
        file_put_contents($path, "id1\nid2\nid3\n");
        $this->tempFiles[] = $path;
        return $path;
    }

    private function makeOutDir(): string {
        $dir = sys_get_temp_dir() . '/folio_export_out_' . uniqid();
        mkdir($dir);
        $this->tempDirs[] = $dir;
        return $dir . '/';
    }

    /**
     * @param Response[] $responses
     */
    private function buildExport(array $responses, bool $verbose = false): FolioDataExport {
        $mock = new MockHandler($responses);
        $config = new FolioConfig([
            'okapiUrl' => 'https://okapi.example.edu',
            'tenant_id' => 'diku',
            'username' => 'diku_admin',
            'password' => 'secret',
        ]);
        $httpClient = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => $config->okapiUrl]);
        $client = new FolioClient($config, new StubAuth(), new FolioUtils(), null, null, $httpClient);
        $fileHandler = new FolioFileHandler($client);

        return new FolioDataExport($client, $fileHandler, $verbose);
    }

    private function json(array $body): Response {
        // An empty PHP array encodes as a JSON array ("[]"), not an empty
        // object ("{}"); post()'s return type is ?object, so an empty
        // body must decode as an object.
        return new Response(200, [], json_encode(empty($body) ? new \stdClass() : $body));
    }

    // --- dataExport() happy path -------------------------------------------

    public function testDataExportHappyPathDownloadsFile(): void {
        $idFile = $this->makeIdFile();
        $outDir = $this->makeOutDir();

        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['id' => 'file-def-1', 'jobExecutionId' => 'job-exec-1']),
            $this->json([]), // putFile's rawRequest response
            $this->json([]), // /data-export/export response
            $this->json(['jobExecutions' => [(object) [
                'status' => 'SUCCESS',
                'exportedFiles' => [(object) ['fileId' => 'exported-1', 'fileName' => 'export.csv']],
                'progress' => (object) ['total' => 3],
            ]]]),
            $this->json(['link' => self::$baseUrl . '/download/ok']),
        ]);

        $result = $export->dataExport($idFile, 'Default instances export job profile', $outDir);

        $this->assertSame('SUCCESS', $result->jobExecutions[0]->status);
        $this->assertSame('mock exported file content', file_get_contents($outDir . 'export.csv'));
    }

    /**
     * B15: an unmatched profile name must fail fast with a clear message,
     * not an "undefined array key" on jobProfiles[0].
     */
    public function testDataExportThrowsWhenProfileNotFound(): void {
        $idFile = $this->makeIdFile();
        $export = $this->buildExport([
            $this->json(['totalRecords' => 0, 'jobProfiles' => []]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Export profile: 'Default instances export job profile' not found");
        $export->dataExport($idFile);
    }

    /**
     * B16: a missing local file must fail before any HTTP calls are made.
     */
    public function testDataExportThrowsWhenFileDoesNotExist(): void {
        $export = $this->buildExport([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not find file to export');
        $export->dataExport(sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.csv');
    }

    /**
     * B17: a FAIL status must throw a clear exception instead of falling
     * through to a confusing TypeError from an undefined $url.
     */
    public function testDataExportThrowsOnFailStatus(): void {
        $idFile = $this->makeIdFile();
        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['id' => 'file-def-1', 'jobExecutionId' => 'job-exec-1']),
            $this->json([]),
            $this->json([]),
            $this->json(['jobExecutions' => [(object) ['status' => 'FAIL']]]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("status of 'FAIL'");
        $export->dataExport($idFile);
    }

    /**
     * B18: verbose output must actually be reachable now that it's wired
     * to a constructor parameter.
     */
    public function testDataExportVerboseModePrintsProgress(): void {
        $idFile = $this->makeIdFile();
        $outDir = $this->makeOutDir();

        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['id' => 'file-def-1', 'jobExecutionId' => 'job-exec-1']),
            $this->json([]),
            $this->json([]),
            $this->json(['jobExecutions' => [(object) [
                'status' => 'SUCCESS',
                'exportedFiles' => [(object) ['fileId' => 'exported-1', 'fileName' => 'export.csv']],
                'progress' => (object) ['total' => 3],
            ]]]),
            $this->json(['link' => self::$baseUrl . '/download/ok']),
        ], verbose: true);

        ob_start();
        try {
            $export->dataExport($idFile, out_Path: $outDir);
        } finally {
            $output = ob_get_clean();
        }

        $this->assertStringContainsString('step 1', $output);
        $this->assertStringContainsString('step 7', $output);
    }

    // --- dataExportAll() ----------------------------------------------------

    public function testDataExportAllHappyPathDownloadsFile(): void {
        $outDir = $this->makeOutDir();

        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['totalRecords' => 0, 'jobExecutions' => []]), // currently running jobs (none)
            $this->json([]), // export-all POST
            $this->json(['totalRecords' => 1, 'jobExecutions' => [(object) ['id' => 'job-new-1']]]), // running after start
            $this->json(['jobExecutions' => [(object) [
                'status' => 'SUCCESS',
                'exportedFiles' => [(object) ['fileId' => 'exported-1', 'fileName' => 'export-all.csv']],
                'progress' => (object) ['total' => 100],
            ]]]),
            $this->json(['link' => self::$baseUrl . '/download/ok']),
        ]);

        $result = $export->dataExportAll('Default instances export job profile', $outDir);

        $this->assertSame('SUCCESS', $result->jobExecutions[0]->status);
        $this->assertSame('mock exported file content', file_get_contents($outDir . 'export-all.csv'));
    }

    public function testDataExportAllThrowsWhenProfileNotFound(): void {
        $export = $this->buildExport([
            $this->json(['totalRecords' => 0, 'jobProfiles' => []]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Export profile: 'Default instances export job profile' not found");
        $export->dataExportAll();
    }

    public function testDataExportAllThrowsOnFailStatus(): void {
        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['totalRecords' => 0, 'jobExecutions' => []]),
            $this->json([]),
            $this->json(['totalRecords' => 1, 'jobExecutions' => [(object) ['id' => 'job-new-1']]]),
            $this->json(['jobExecutions' => [(object) ['status' => 'FAIL']]]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("status of 'FAIL'");
        $export->dataExportAll();
    }

    /**
     * Bonus bug found while fixing B15-B18: if the before/after
     * running-jobs diff is empty (no new job execution detected),
     * $reindexed[0] must not throw "undefined array key" — it should
     * cleanly fall through to the existing "Export all failed" branch.
     */
    public function testDataExportAllThrowsWhenNoNewJobDetected(): void {
        $export = $this->buildExport([
            $this->json(['totalRecords' => 1, 'jobProfiles' => [(object) ['id' => 'profile-1']]]),
            $this->json(['totalRecords' => 1, 'jobExecutions' => [(object) ['id' => 'job-x']]]),
            $this->json([]),
            // Identical job list after starting the export -> empty diff.
            $this->json(['totalRecords' => 1, 'jobExecutions' => [(object) ['id' => 'job-x']]]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Export all failed');
        $export->dataExportAll();
    }
}
