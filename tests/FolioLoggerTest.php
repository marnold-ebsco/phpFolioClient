<?php declare(strict_types=1);

namespace phpFolioClient\Tests;

use phpFolioClient\FolioLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FolioLoggerTest extends TestCase {
    private FolioLogger $logger;

    protected function setUp(): void {
        $this->logger = new FolioLogger();
    }

    #[Test]
    public function testConstructorWithDefaultValues(): void {
        $logger = new FolioLogger();
        $this->assertInstanceOf(FolioLogger::class, $logger);
    }

    #[Test]
    public function testConstructorWithLogPath(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        $this->assertInstanceOf(FolioLogger::class, $logger);
        unlink($tempFile);
    }

    #[Test]
    public function testConstructorWithDebugFlag(): void {
        $logger = new FolioLogger(false, true);
        $this->assertInstanceOf(FolioLogger::class, $logger);
    }

    #[Test]
    public function testConstructorWithVerboseFlag(): void {
        $logger = new FolioLogger(false, false, true);
        $this->assertInstanceOf(FolioLogger::class, $logger);
    }

    #[Test]
    public function testSetTimezone(): void {
        $this->logger->setTimezone('Europe/London');
        $this->assertInstanceOf(FolioLogger::class, $this->logger);
    }

    #[Test]
    public function testSetTimezoneWithInvalidTimezone(): void {
        $this->expectException(\Exception::class);
        $this->logger->setTimezone('Invalid/Timezone');
    }

    #[Test]
    public function testLogWithValidInput(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Test message', 1);
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('Query 1', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithAdditionalData(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Test message', 2, 'extra data');
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('Query 2', $content);
        $this->assertStringContainsString('extra data', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithoutLogFile(): void {
        $logger = new FolioLogger(false);
        $logger->log('Test message', 1);
        $this->assertTrue(true);
    }

    #[Test]
    public function testLogWithDebugEnabled(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile, true);
        
        $logger->log('Debug message', 1);
        $this->assertTrue(true);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithMultipleQueries(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Message 1', 1);
        $logger->log('Message 2', 2);
        $logger->log('Message 3', 3);
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Query 1', $content);
        $this->assertStringContainsString('Query 2', $content);
        $this->assertStringContainsString('Query 3', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogMessageFormatting(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Test', 1, 'data');
        
        $content = file_get_contents($tempFile);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+/', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithEmptyMessage(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('', 1);
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Query 1', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithZeroQueryNum(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Test', 0);
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Query 0', $content);
        
        unlink($tempFile);
    }

    #[Test]
    public function testLogWithNullAdditionalData(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'folio_test');
        $logger = new FolioLogger($tempFile);
        
        $logger->log('Test message', 1, null);
        
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Test message', $content);
        
        unlink($tempFile);
    }
}
