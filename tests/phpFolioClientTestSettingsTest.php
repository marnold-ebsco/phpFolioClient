<?php

use PHPUnit\Framework\TestCase;
use phpFolioClient\phpFolioClient;

//  https://pguso.medium.com/a-beginners-guide-to-phpunit-writing-and-running-unit-tests-in-php-d0b23b96749f
//  to run tests: ./vendor/bin/phpunit

require_once 'src/bootstrap.php';

class phpFolioClientTestSettingsTest extends TestCase {
	// protected $folio;
	protected static $folio;

	public static function setUpBeforeClass(): void
	{
		// Code here runs once before any test in this class
		// e.g., establishing a database connection, loading a large dataset
		self::$folio = new phpFOLIOClient('/home/marnold/phpFolioClient2/lsedemo.ini');
		
	}

	protected function setUp(): void
	{
		// $this->folio = new phpFOLIOClient('lsedemo.ini');
	}

	public function testGetSetTimeout(){
		$timeout = self::$folio->getTimeout();
		$this->assertEquals(30, $timeout);
		self::$folio->setTimeout(60);

		$newTimeout = self::$folio->getTimeout();
		$this->assertEquals(60, $newTimeout);
	}

	public function testSetVerbose(){
		self::$folio->setVerbose(true);
		self::$folio->connect(true);
		$this->expectOutputRegex("/authentication/");

		self::$folio->setVerbose(false);
		self::$folio->connect(true);
		$this->expectOutputString('');
	}

	public function testGetFlavor(){
		self::$folio->connect(true);
		$this->assertEquals('RTR',self::$folio->getFlavor());
	}

	public function testGetUrl(){
		$this->assertStringContainsString('https', self::$folio->getUrl());
	}

	public function testTenantId(){
		$this->assertNotEmpty(self::$folio->getTenantId());
	}

	public function testCentralTenantId(){
		$this->assertEmpty(self::$folio->getCentralTenantId());
	}

	public function testGetHostname(){
		$this->assertNotEmpty(self::$folio->getHostname());
		$this->assertStringNotContainsString('api',self::$folio->getHostname());
		$this->assertStringNotContainsString('kong',self::$folio->getHostname());
	}

	public function testGetUsername(){
		$this->assertNotEmpty(self::$folio->getUsername());
	}

	

	
}

