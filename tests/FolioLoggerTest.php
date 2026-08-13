<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use phpFolioClient\FolioLogger;
use PHPUnit\Framework\TestCase;

final class FolioLoggerTest extends TestCase {
    private ?string $logPath = null;
    private ?string $errorLogPath = null;
    private ?string $previousErrorLogIni = null;

    protected function tearDown(): void {
        if ($this->logPath !== null && file_exists($this->logPath)) {
            unlink($this->logPath);
        }
        if ($this->errorLogPath !== null && file_exists($this->errorLogPath)) {
            unlink($this->errorLogPath);
        }
        if ($this->previousErrorLogIni !== null) {
            ini_set('error_log', $this->previousErrorLogIni);
        }
    }

    public function testFalseLogPathDisablesFileLoggingWithoutError(): void {
        $logger = new FolioLogger(false);

        // Should simply do nothing (no file handle to write to) — no error/exception.
        $logger->log('hello', 1);
        $this->addToAssertionCount(1);
    }

    public function testLogWritesTabDelimitedLineWithQueryNumAndMessage(): void {
        $this->logPath = tempnam(sys_get_temp_dir(), 'folio_log_');
        $logger = new FolioLogger($this->logPath);

        $logger->log('GET: /inventory/instances', 3, 'Content Length: 42');

        $contents = file_get_contents($this->logPath);
        $this->assertStringContainsString("(Query 3)", $contents);
        $this->assertStringContainsString("GET: /inventory/instances", $contents);
        $this->assertStringContainsString("Content Length: 42", $contents);
        $this->assertSame(1, substr_count($contents, PHP_EOL));
    }

    /**
     * S5: the log file opens in append mode, so existing content must
     * survive across multiple FolioLogger instances against the same path.
     */
    public function testLogAppendsInsteadOfTruncating(): void {
        $this->logPath = tempnam(sys_get_temp_dir(), 'folio_log_');
        file_put_contents($this->logPath, "pre-existing line\n");

        $logger = new FolioLogger($this->logPath);
        $logger->log('second message', 1);
        unset($logger);

        $contents = file_get_contents($this->logPath);
        $this->assertStringContainsString('pre-existing line', $contents);
        $this->assertStringContainsString('second message', $contents);
    }

    public function testSetTimezoneViaConstructorAffectsTimestampFormat(): void {
        $this->logPath = tempnam(sys_get_temp_dir(), 'folio_log_');
        $logger = new FolioLogger($this->logPath, false, false, 'UTC');

        $logger->log('msg', 1);

        $contents = file_get_contents($this->logPath);
        // The log format includes the "e" (timezone identifier) token.
        $this->assertStringContainsString('UTC', $contents);
    }

    public function testSetTimezoneMethodOverridesDefault(): void {
        $this->logPath = tempnam(sys_get_temp_dir(), 'folio_log_');
        $logger = new FolioLogger($this->logPath);
        $logger->setTimezone('UTC');

        $logger->log('msg', 1);

        $contents = file_get_contents($this->logPath);
        $this->assertStringContainsString('UTC', $contents);
    }

    public function testDebugMirrorsMessageToErrorLog(): void {
        $this->errorLogPath = tempnam(sys_get_temp_dir(), 'folio_errlog_');
        $this->previousErrorLogIni = ini_get('error_log');
        ini_set('error_log', $this->errorLogPath);

        $logger = new FolioLogger(false, true);
        $logger->log('debug mirrored message', 1);

        $contents = file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('debug mirrored message', $contents);
    }

    public function testNonDebugDoesNotMirrorToErrorLog(): void {
        $this->errorLogPath = tempnam(sys_get_temp_dir(), 'folio_errlog_');
        $this->previousErrorLogIni = ini_get('error_log');
        ini_set('error_log', $this->errorLogPath);

        $logger = new FolioLogger(false, false);
        $logger->log('should not appear', 1);

        $contents = file_exists($this->errorLogPath) ? file_get_contents($this->errorLogPath) : '';
        $this->assertStringNotContainsString('should not appear', $contents);
    }

    public function testDestructClosesFileHandle(): void {
        $this->logPath = tempnam(sys_get_temp_dir(), 'folio_log_');
        $logger = new FolioLogger($this->logPath);

        $reflection = new \ReflectionProperty($logger, 'logFh');
        $handle = $reflection->getValue($logger);
        $this->assertIsResource($handle);

        unset($logger);

        $this->assertFalse(is_resource($handle));
    }
}
