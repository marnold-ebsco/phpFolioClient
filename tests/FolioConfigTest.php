<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use phpFolioClient\FolioConfig;

require_once 'src/bootstrap.php';

// class FolioConfigTest extends TestCase
// {
//     private FolioConfig $config;

//     protected function setUp(): void
//     {
//         $hostname = 'lsedemo';
//         $this->config = new FolioConfig($hostname . ".ini");
//     }

//     public function testConfigInitialization(): void
//     {
//         $this->assertInstanceOf(FolioConfig::class, $this->config);
//     }

//     public function testSetAndGetHost(): void
//     {
//         // $this->config->setHost('localhost');
//         $this->assertEquals('localhost', $this->config->getHost());
//     }

//     public function testSetAndGetPort(): void
//     {
//         $this->config->setPort(8080);
//         $this->assertEquals(8080, $this->config->getPort());
//     }

//     public function testSetAndGetOkapiUrl(): void
//     {
//         $url = 'http://localhost:9130';
//         $this->config->setOkapiUrl($url);
//         $this->assertEquals($url, $this->config->getOkapiUrl());
//     }

//     public function testSetAndGetTenant(): void
//     {
//         $this->config->setTenant('diku');
//         $this->assertEquals('diku', $this->config->getTenant());
//     }

//     public function testSetAndGetUsername(): void
//     {
//         $this->config->setUsername('admin');
//         $this->assertEquals('admin', $this->config->getUsername());
//     }

//     public function testSetAndGetPassword(): void
//     {
//         $this->config->setPassword('password123');
//         $this->assertEquals('password123', $this->config->getPassword());
//     }

//     public function testConfigFromArray(): void
//     {
//         $configArray = [
//             'host' => 'example.com',
//             'port' => 9130,
//             'okapi_url' => 'http://example.com:9130',
//             'tenant' => 'supertenant',
//             'username' => 'user',
//             'password' => 'pass'
//         ];

//         $this->config->loadFromArray($configArray);

//         $this->assertEquals('example.com', $this->config->getHost());
//         $this->assertEquals(9130, $this->config->getPort());
//         $this->assertEquals('supertenant', $this->config->getTenant());
//     }

//     public function testIsValidConfiguration(): void
//     {
//         $this->config->setHost('localhost');
//         $this->config->setPort(9130);
//         $this->config->setTenant('diku');
//         $this->config->setUsername('admin');
//         $this->config->setPassword('password');

//         $this->assertTrue($this->config->isValid());
//     }

//     public function testInvalidConfigurationMissingRequiredField(): void
//     {
//         $this->config->setHost('localhost');
//         $this->config->setTenant('diku');

//         $this->assertFalse($this->config->isValid());
//     }
// }