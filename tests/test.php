<?php
require_once('vendor/autoload.php');

use phpFolioClient\phpFolioClient;

$hostname = 'lsedemo';

try{
    $folio = new phpFolioClient($hostname . ".ini",false,'folioClientLog.txt');
}catch(Exception $e){
    print "Error: " . $e->getMessage();
    exit;
}

$shortopts = "a";
$options = getopt($shortopts);
$isAbbreviated = isset($options['a']);
if($isAbbreviated){
    $doExportTests = false;
    print "Running abbreviated tests\n";
}


// export tests take some time, but they exercise the postFile and getFile methods
$doExportTests = false;


$scriptBegin=microtime(true);
print "Username: " . $folio->getUsername() . PHP_EOL;
if($doExportTests){
    print "  Export tests will be performed\n\n";
}else{
    print "  Export tests will not be performed\n\n";
}

try{
    print "Testing settings\n";
    $begin=microtime(true);
    $timeout = $folio->getTimeout();
    $folio->setTimeout(($timeout * 2));
    $newTimeout = $folio->getTimeout();
    if($newTimeout != (2 * $timeout)){
        throw new Exception("Timeout not set correctly");
    }

    $folio->setVerbose(true);
    $folio->setVerbose(false);

    print "API url: " . $folio->getUrl() . PHP_EOL;
    print "Hostname: " . $folio->getHostname() . PHP_EOL;
    print "Tenant id: " . $folio->getTenantId() . PHP_EOL;
    print "Central tenant id: " . $folio->getCentralTenantId() . PHP_EOL;
    print "Username: " . $folio->getUsername() . PHP_EOL;
    print "Timeout: $timeout\n";
    print "sslVerify: " . $folio->getSslVerify() . PHP_EOL;


}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}


try{
    print "Testing getModules\n";
    $begin=microtime(true);
    $modules = $folio->getModules();
    if($modules){
        print "  successful\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

try{
    print "Testing getCustomFields\n";
    $begin=microtime(true);
    $custom = $folio->getCustomFields();
    if($custom){
        print "  successful\n";
        // print_r($custom);
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

try{
    print "Testing getCustomFields names\n";
    $begin=microtime(true);
    $custom = $folio->getCustomFields(true);
    if($custom){
        print "  successful\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

if(!$isAbbreviated){
    try{
        print "Testing GET ALL\n";
        $begin=microtime(true);
        $limit = 10000;
        $count = 0;
        foreach($folio->getAll('instance-storage/instances','instances',['query'=>'cql.allRecords=1','limit'=>$limit]) as $instance){        
            if(($count % $limit) == 0){
                print "Count: $count\r";
            }
            $count++;
        }
        print "Count: $count\n";
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }

    try{
        print "Testing GET ALL BY OFFSET\n";
        $begin=microtime(true);
        $count = 0;
        $limit = 10000;
        foreach($folio->getAll_by_id_offset('instance-storage/instances','instances',['query'=>'cql.allRecords=1','limit'=>$limit]) as $instance){        
            // print $instance . PHP_EOL;
            if(($count % $limit) == 0){
                print "Count: $count\r";
            }
            $count++;
        }
        print "Count: $count\n";
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }

    try{
        print "Testing GET ALL with query\n";
        $begin=microtime(true);
        $limit = 10000;
        $count = 0;
        foreach($folio->getAll('instance-storage/instances','instances',['query'=>'source==MARC','limit'=>$limit]) as $instance){        
            if(($count % $limit) == 0){
                print "Count: $count\r";
            }
            $count++;
        }
        print "Count: $count\n";
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }


    try{
        print "Testing GET ALL BY OFFSET with query\n";
        $begin=microtime(true);
        $count = 0;
        $limit = 10000;
        foreach($folio->getAll_by_id_offset('instance-storage/instances','instances',['query'=>'source==MARC','limit'=>$limit]) as $instance){        
            // print $instance . PHP_EOL;
            if(($count % $limit) == 0){
                print "Count: $count\r";
            }
            $count++;
        }
        print "Count: $count\n";
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }
}

try{
    print "testing GET/INSERT/UPDATE/DELETE\n";
    $begin=microtime(true);

    $testType = 'testMType';
    $uuid = mattypeExists($testType);
    if($uuid){
        updateMattype($testType . '-update',$uuid);
    }else{
        $uuid = addMattype($testType);
    }
    deleteMattype($uuid);
    if(mattypeExists($testType)){
        print "Mattype delete failed\n";
    }else{
        print "testing succeeded\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

#test getOne
try{
    $begin=microtime(true);
    print "Testing getOne\n";
    $response = $folio->get('loan-types');
    $one = $response->loantypes[0]->id;
    $response = $folio->getOne('loan-types',$one);
    if($response->id == $one){
        print "getOne succeeded\n";
    }else{
        print "getOne failed\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

#test patch
try{
    $begin=microtime(true);
    print "Testing PATCH item\n";
    $items = $folio->get('item-storage/items');
    if($items->totalRecords){
        $item = $items->items[0];
        $copyNum = $item->copyNumber;

        $newCopyNumber = 'copy number: test';
        $patch = new stdClass();
        $patch->copyNumber = $newCopyNumber;
        $patch->id = $item->id;

        $folio->patch('item-storage/items',$item->id,$patch);

        $test = $folio->getOne('item-storage/items',$item->id);
        if($test->copyNumber == $newCopyNumber){
            print "Patch succeeded\n";
        }else{
            print "Patch failed\n";
        }
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}

try{
    $begin=microtime(true);
    print"\nTesting PATCH specification rule\n";
    $obj = new stdClass();
    $obj->enabled='true';
    $folio->patch('specification-storage/specifications/6eefa4c6-bbf7-4845-ad82-de7fc4abd0e3/rules/7c843a14-4c87-4c7d-9ad6-5c7654bff9b5',null,$obj);
    if($folio->getStatusCode() == 204){
        print"  succeeded\n";
    }else{
        print"  failed\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}


if($doExportTests){
    try{
        $begin=microtime(true);
        print"\nTesting MARC export all\n";
        $exportProfileName = 'Default instances export job profile';
        $tenant_id = $folio->getTenantId();
        // $folio->setVerbose(true);
        $response = $folio->dataExportAll($exportProfileName);
        $fileName = $response->jobExecutions[0]->exportedFiles[0]->fileName;
        // $folio->setVerbose(false);
        if(file_exists($fileName)){
            print"  succeeded\n";
        }else{
            print"  failed\n";
        }
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }

    try{
        $begin=microtime(true);
        print"\nTesting MARC export select records\n";
        $records = $folio->get('instance-storage/instances',['query'=>'cql.allRecords=1','limit'=>500]);
        $toExportArray = [];
        foreach($records->instances as $instance){
            $toExportArray[] = $instance->id;
        }
        file_put_contents('fileExportTest.csv',implode("\n",$toExportArray));

        $exportProfileName = 'Default instances export job profile';
        $tenant_id = $folio->getTenantId();
        // $folio->setVerbose(true);
        $response = $folio->dataExport('fileExportTest.csv',$exportProfileName);
        $fileName = $response->jobExecutions[0]->exportedFiles[0]->fileName;
        // $folio->setVerbose(false);
        if(file_exists($fileName)){
            print"  succeeded\n";
        }else{
            print"  failed\n";
        }
    }catch(Exception $e){
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
    }
}

print "Script elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n";


exit;



function mattypeExists($name){
    global $folio;
    $results = $folio->get('material-types',['query'=>'name=="' . $name . '"']);
    if($results->totalRecords == 1){
        return $results->mtypes[0]->id;
    }else{
        return false;
    }
}

function addMattype($name){
    global $folio;
    $materialType =new stdClass();
    $materialType->name = $name;
    $folio->post('material-types',$materialType);
    return mattypeExists($name);
}

function updateMattype($name, $id){
    global $folio;

    print "update\n";
    $materialType =new stdClass();
    $materialType->name = $name;

    $folio->put('material-types',$id,$materialType);

}

function deleteMattype($id){
    global $folio;
    $folio->delete('material-types',$id);
}