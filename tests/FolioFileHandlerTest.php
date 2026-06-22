<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use phpFolioClient\FolioFileHandler;
use Exception;

require_once 'src/bootstrap.php';

// class FolioFileHandlerTest extends TestCase
// {
//     private FolioFileHandler $handler;
//     private string $testDir;
//     private string $testFile;

//     protected function setUp(): void
//     {
//         $this->handler = new FolioFileHandler();
//         $this->testDir = sys_get_temp_dir() . '/folio_test_' . uniqid();
//         $this->testFile = $this->testDir . '/test_file.txt';
        
//         if (!is_dir($this->testDir)) {
//             mkdir($this->testDir, 0755, true);
//         }
//     }

//     protected function tearDown(): void
//     {
//         if (is_dir($this->testDir)) {
//             $this->removeDirectory($this->testDir);
//         }
//     }

//     private function removeDirectory(string $dir): void
//     {
//         if (is_dir($dir)) {
//             $files = scandir($dir);
//             foreach ($files as $file) {
//                 if ($file !== '.' && $file !== '..') {
//                     $path = $dir . '/' . $file;
//                     if (is_dir($path)) {
//                         $this->removeDirectory($path);
//                     } else {
//                         unlink($path);
//                     }
//                 }
//             }
//             rmdir($dir);
//         }
//     }

//     public function testWriteFile(): void
//     {
//         $content = 'Test content for the file';
//         $this->handler->writeFile($this->testFile, $content);
        
//         $this->assertFileExists($this->testFile);
//         $this->assertEquals($content, file_get_contents($this->testFile));
//     }

//     public function testReadFile(): void
//     {
//         $content = 'Content to read from file';
//         file_put_contents($this->testFile, $content);
        
//         $result = $this->handler->readFile($this->testFile);
        
//         $this->assertEquals($content, $result);
//     }

//     public function testReadFileNotFound(): void
//     {
//         $this->expectException(Exception::class);
//         $this->handler->readFile('/nonexistent/file.txt');
//     }

//     public function testFileExists(): void
//     {
//         file_put_contents($this->testFile, 'test');
        
//         $this->assertTrue($this->handler->fileExists($this->testFile));
//         $this->assertFalse($this->handler->fileExists($this->testDir . '/nonexistent.txt'));
//     }

//     public function testDeleteFile(): void
//     {
//         file_put_contents($this->testFile, 'test');
//         $this->assertFileExists($this->testFile);
        
//         $this->handler->deleteFile($this->testFile);
        
//         $this->assertFileDoesNotExist($this->testFile);
//     }

//     public function testCreateDirectory(): void
//     {
//         $newDir = $this->testDir . '/new_folder';
//         $this->handler->createDirectory($newDir);
        
//         $this->assertDirectoryExists($newDir);
//     }

//     public function testGetFileSize(): void
//     {
//         $content = 'Test content';
//         file_put_contents($this->testFile, $content);
        
//         $size = $this->handler->getFileSize($this->testFile);
        
//         $this->assertEquals(strlen($content), $size);
//     }

//     public function testAppendToFile(): void
//     {
//         $initial = 'Initial content';
//         $append = ' appended text';
//         file_put_contents($this->testFile, $initial);
        
//         $this->handler->appendToFile($this->testFile, $append);
        
//         $this->assertEquals($initial . $append, file_get_contents($this->testFile));
//     }

//     public function testListFiles(): void
//     {
//         file_put_contents($this->testDir . '/file1.txt', 'content1');
//         file_put_contents($this->testDir . '/file2.txt', 'content2');
//         mkdir($this->testDir . '/subdir');
        
//         $files = $this->handler->listFiles($this->testDir);
        
//         $this->assertIsArray($files);
//         $this->assertGreaterThanOrEqual(2, count($files));
//     }

//     public function testGetFileExtension(): void
//     {
//         $extension = $this->handler->getFileExtension($this->testFile);
        
//         $this->assertEquals('txt', $extension);
//     }

//     public function testCopyFile(): void
//     {
//         $content = 'Content to copy';
//         file_put_contents($this->testFile, $content);
//         $copyPath = $this->testDir . '/copy.txt';
        
//         $this->handler->copyFile($this->testFile, $copyPath);
        
//         $this->assertFileExists($copyPath);
//         $this->assertEquals($content, file_get_contents($copyPath));
//     }

//     public function testMoveFile(): void
//     {
//         $content = 'Content to move';
//         file_put_contents($this->testFile, $content);
//         $movePath = $this->testDir . '/moved.txt';
        
//         $this->handler->moveFile($this->testFile, $movePath);
        
//         $this->assertFileDoesNotExist($this->testFile);
//         $this->assertFileExists($movePath);
//         $this->assertEquals($content, file_get_contents($movePath));
//     }
// }
