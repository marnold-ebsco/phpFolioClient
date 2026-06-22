<?php

use PHPUnit\Framework\TestCase;
use phpFolioClient\phpFolioClient;

//  https://pguso.medium.com/a-beginners-guide-to-phpunit-writing-and-running-unit-tests-in-php-d0b23b96749f
//  ./vendor/bin/phpunit

require_once 'src/bootstrap.php';

class phpFolioClientTestConnectTest extends TestCase {
	public function testCreateClassNoUrl(){

		$connectionObj = new stdClass();
		// $connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
		// $connectionObj->tenant_id = 'diku';
		// $connectionObj->username = 'diku_admin';
		// $connectionObj->password = 'admin';
		// $connectionObj->sslVerify = 'cacert.pem';
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/okapiUrl does not exist/');

		$folio = new phpFOLIOClient($connectionObj);
	}

	
	public function testCreateClassNoTenantId(){

		$connectionObj = new stdClass();
		$connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/okapiUrl does not exist/');
		$this->expectExceptionMessageMatches('/tenant_id does not exist/');

		$folio = new phpFOLIOClient($connectionObj);
	}

	public function testCreateClassNoUsername(){

		$connectionObj = new stdClass();
		$connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
		$connectionObj->tenant_id = 'diku';
		// $connectionObj->username = 'diku_admin';
		// $connectionObj->password = 'admin';
		// $connectionObj->sslVerify = 'cacert.pem';
		// $this->expectException(Exception::class);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/okapiUrl does not exist/');
		$this->expectExceptionMessageMatches('/tenant_id does not exist/');
		$this->expectExceptionMessageMatches('/username does not exist/');
		// $this->expectExceptionMessage('password does not exist');
		// $this->expectExceptionMessage('tenant_id does not exist');

		$folio = new phpFOLIOClient($connectionObj);
	}

	public function testCreateClassNoPassword(){

		$connectionObj = new stdClass();
		$connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
		$connectionObj->tenant_id = 'diku';
		$connectionObj->username = 'diku_admin';
		// $connectionObj->password = 'admin';
		// $connectionObj->sslVerify = 'cacert.pem';
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/okapiUrl does not exist/');
		$this->expectExceptionMessageMatches('/tenant_id does not exist/');
		$this->expectExceptionMessageMatches('/username does not exist/');
		$this->expectExceptionMessageMatches('/password does not exist/');

		$folio = new phpFOLIOClient($connectionObj);
	}

	public function testCreateClass(){
		$connectionObj = new stdClass();
		$connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
		$connectionObj->tenant_id = 'diku';
		$connectionObj->username = 'diku_admin';
		$connectionObj->password = 'admin';
		$connectionObj->sslVerify = 'false';

		$folio = new phpFOLIOClient($connectionObj);
		$this->assertInstanceOf(phpFolioClient::class,$folio);
		$this->expectOutputRegex("/sslVerify to false/");
		
	}

	public function testCreateClassViaIni(){
		
		$folio = new phpFOLIOClient('lsedemo.ini');
		$this->assertObjectHasProperty('okapiUrl', $folio);
	}

}

class ExceptionTest extends TestCase{
	public function testException(): void
	{
		// $connectionObj = new stdClass();
		// $connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
		// // $connectionObj->tenant_id = 'diku';
		// // $connectionObj->username = 'diku_admin';
		// // $connectionObj->password = 'admin';
		// // $connectionObj->sslVerify = 'cacert.pem';
		// $folio = new phpFOLIOClient($connectionObj);

		$this->expectException(ArgumentCountError::class);

	}
}
