<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioClient;

require_once 'src/bootstrap.php';

// class FolioInformationTest extends TestCase
// {
//     private FolioInformation $folioInfo;

//     protected function setUp(): void
//     {
//         $hostname = 'lsedemo';
//         $config = new FolioConfig($hostname . ".ini");
//         $utils = new FolioUtils();
//         $auth = new FolioAuth($config);
//         $auth->getAccessToken();
//         $folio = new FolioClient($config,$auth,$utils);
//         $information = new FolioInformation($config,$auth);
//         $this->folioInfo = new FolioInformation($config,$auth);
//     }

//     public function testCanCreateInstance(): void
//     {
//         $this->assertInstanceOf(FolioInformation::class, $this->folioInfo);
//     }

//     public function testCanSetAndGetId(): void
//     {
//         $this->assertEquals(123, $this->folioInfo->getId());
//     }

//     public function testCanSetAndGetTitle(): void
//     {
//         $title = 'Test Portfolio';
//         $this->assertEquals($title, $this->folioInfo->getTitle());
//     }

//     public function testCanSetAndGetDescription(): void
//     {
//         $description = 'A test portfolio description';
//         $this->folioInfo->setDescription($description);
//         $this->assertEquals($description, $this->folioInfo->getDescription());
//     }

//     public function testCanSetAndGetAuthor(): void
//     {
//         $author = 'John Doe';
//         $this->folioInfo->setAuthor($author);
//         $this->assertEquals($author, $this->folioInfo->getAuthor());
//     }

//     public function testCanSetAndGetCreatedAt(): void
//     {
//         $date = '2024-01-01';
//         $this->folioInfo->setCreatedAt($date);
//         $this->assertEquals($date, $this->folioInfo->getCreatedAt());
//     }

//     public function testCanSetAndGetUpdatedAt(): void
//     {
//         $date = '2024-01-15';
//         $this->folioInfo->setUpdatedAt($date);
//         $this->assertEquals($date, $this->folioInfo->getUpdatedAt());
//     }

//     public function testCanSetAndGetIsActive(): void
//     {
//         $this->folioInfo->setIsActive(true);
//         $this->assertTrue($this->folioInfo->getIsActive());

//         $this->folioInfo->setIsActive(false);
//         $this->assertFalse($this->folioInfo->getIsActive());
//     }

//     public function testInitialValuesAreNull(): void
//     {
//         $newFolio = new FolioInformation();
//         $this->assertNull($newFolio->getId());
//         $this->assertNull($newFolio->getTitle());
//         $this->assertNull($newFolio->getDescription());
//         $this->assertNull($newFolio->getAuthor());
//     }
// }
