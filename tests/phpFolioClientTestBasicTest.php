<?php

use PHPUnit\Framework\TestCase;
use phpFolioClient\phpFolioClient;

//  https://pguso.medium.com/a-beginners-guide-to-phpunit-writing-and-running-unit-tests-in-php-d0b23b96749f
//  to run tests: ./vendor/bin/phpunit

require_once 'src/bootstrap.php';

class phpFolioClientTestBasicTest extends TestCase {
	// protected $folio;
	protected static $folio;

	public static function setUpBeforeClass(): void
	{
		// Code here runs once before any test in this class
		// e.g., establishing a database connection, loading a large dataset
		self::$folio = new phpFOLIOClient('lsedemo.ini');
	}

	protected function setUp(): void
	{
		// $this->folio = new phpFOLIOClient('lsedemo.ini');
	}

	public function testGetLocations(){
		$response = self::$folio->getLocations();
		
		$this->assertGreaterThan(0, sizeof($response));
	}

	public function testGetAll(){
		$response = self::$folio->get('locations',['query'=>'cql.allRecords=1']);
		$count = 0;
		foreach(self::$folio->getAll('locations','locations',['query'=>'cql.allRecords=1','limit'=>500]) as $loc){
			$count++;
		}
		
		$this->assertGreaterThan(0, $count);
		$this->assertEquals($response->totalRecords,$count);
	}

	public function testGetAllByOffset(){
		$response = self::$folio->get('locations',['query'=>'cql.allRecords=1']);
		$count = 0;
		foreach(self::$folio->getAll_by_id_offset('locations','locations',['query'=>'cql.allRecords=1','limit'=>2]) as $loc){
			$count++;
		}
		$this->assertGreaterThan(0, $count);
		$this->assertEquals($response->totalRecords,$count);
	}

	public function testGet(){
		$response = self::$folio->get('loan-types',['query'=>'name=="Can circulate"','offset'=>0,'limit'=>100]);
		$this->assertGreaterThan(0, $response->totalRecords);
		$this->assertEquals('2b94c631-fca9-4892-a730-03ee529ffe27',$response->loantypes[0]->id);
		$this->assertEquals('200',self::$folio->getLastStatusCode());
	}

	public function testGetOne(){
		$response = self::$folio->get('loan-types',['query'=>'name=="Can circulate"','offset'=>0,'limit'=>100]);
		$id = $response->loantypes[0]->id;
		$response = self::$folio->getOne('loan-types',$id);
		$this->assertEquals('2b94c631-fca9-4892-a730-03ee529ffe27',$response->id);
		$this->assertEquals('200',self::$folio->getLastStatusCode());
	}

	public function testPost(){
		$obj = new \stdClass();
		$obj->name = 'TestType';
		$response = self::$folio->post('loan-types',$obj);
		$this->assertEquals('201',self::$folio->getLastStatusCode());
	}

	public function testPut(){
		$response = self::$folio->get('loan-types',['query'=>'name=="TestType"','offset'=>0,'limit'=>100]);
		$obj = $response->loantypes[0];
		$obj->name = 'TestTypeMod';
		$response = self::$folio->put('loan-types',$obj->id,$obj);
		$this->assertEquals('204',self::$folio->getLastStatusCode());
	}

	public function testPatch(){
		$obj = new stdClass();
		$obj->enabled='true';
		// try{
			self::$folio->patch('specification-storage/specifications/6eefa4c6-bbf7-4845-ad82-de7fc4abd0e3/rules/7c843a14-4c87-4c7d-9ad6-5c7654bff9b5',null,$obj);
			$this->assertEquals('204',self::$folio->getLastStatusCode());
		// }catch(Exception $e){
		// 	fwrite(STDERR,print_r($e,true));
		// }
			
		
	}

	public function testDelete(){
		$response = self::$folio->get('loan-types',['query'=>'name=="TestTypeMod"','offset'=>0,'limit'=>100]);
		$obj = $response->loantypes[0];
		$response = self::$folio->delete('loan-types',$obj->id);
		$this->assertEquals('204',self::$folio->getLastStatusCode());
	}



	
}

