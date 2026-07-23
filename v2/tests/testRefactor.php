<?php
require_once('vendor/autoload.php');

use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioLogger;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioDataExport;
use phpFolioClient\FolioFileHandler;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioReferenceDataManager;

$hostname = 'lsedemo';

// clean up old files
$directory = '/home/marnold/phpFolioClient2';
$today = strtotime('today');

// Find all .mrc files matching the pattern
foreach (glob($directory . "/*.mrc") as $file) {
    // Validate that it is a file and was not created today
    if (is_file($file) && filemtime($file) < $today) {
        unlink($file); // Delete the file
    }
}


// tests
try{
    $config = new FolioConfig($hostname . ".ini");
    $utils = new FolioUtils();
    $auth = new FolioAuth($config);
    $logger = new FolioLogger('folioClientLog.txt');
    $information = new FolioInformation($config,$auth);

    $folio = new FolioClient($config,$auth,$utils,$logger);
    
    $refData = new FolioReferenceDataManager($folio);
    $fileHandler = new FolioFileHandler($folio);
    $exportHandler = new FolioDataExport($folio,$fileHandler);
    

}catch(Exception $e){
    print "Exception: " . $e->getMessage() . PHP_EOL;
}


// $exportHandler->dataExport("/home/marnold/phpFolioClient2/testExport.csv");
// exit;

// print "Export all marc data\n";
// $exportHandler->dataExportAll();


$locNames = $refData->getLocations();
print "Location names count: " . sizeof($locNames) . PHP_EOL;

$locCodes = $refData->getLocationCodes();
print "Location codes count: " . sizeof($locCodes) . PHP_EOL;

$mattypes = $refData->getMaterialTypes();
print "Mattype count: " . sizeof($mattypes) . PHP_EOL;

$loanTypes = $refData->getLoanTypes();
print "Loan types count: " . sizeof($loanTypes) . PHP_EOL;

$departments = $refData->getDepartments();
print "Departments (user) count: " . sizeof($departments) . PHP_EOL;

$addressTypes = $refData->getAddressTypes();
print "Address type (user) count: " . sizeof($addressTypes) . PHP_EOL;

$patronGroups = $refData->getPatronGroups();
print "Patron group count: " . sizeof($patronGroups) . PHP_EOL;

$servicePoints = $refData->getServicePoints();
print "Service point count: " . sizeof($servicePoints) . PHP_EOL;

$modules = $refData->getModules();
print "Modules count: " . sizeof($modules) . PHP_EOL;

$customFields = $refData->getCustomFieldNames();
print "Custom field names (user) count: " . sizeof($customFields) . PHP_EOL;

$customFields = $refData->getCustomFields();
print "Custom fields refid (user) count: " . sizeof($customFields) . PHP_EOL;


print"status information";
// foreach($folio->get('locations') as $location){
//     print "$location->code, ";
// }
print"\n";
print"last status code: ";
print $folio->getLastStatusCode() . PHP_EOL;
print"status code (alias): ";
print $folio->getStatusCode() . PHP_EOL;

print"last query: ";
print $folio->getLastQuery() . PHP_EOL;

print"last query number: ";
print $folio->getLastQueryNum() . PHP_EOL;


print"authFlavor: " . $folio->getInformation()->getAuthFlavor() . PHP_EOL;

print"get api url: ";
print $folio->getInformation()->getUrl() . PHP_EOL;

print"get tenant id: ";
print $folio->getInformation()->getTenantId() . PHP_EOL;

print"get central tenant id: ";
print $folio->getInformation()->getCentralTenantId() . PHP_EOL;

print"get hostname: ";
print $folio->getInformation()->getHostname() . PHP_EOL;

print"get username: ";
print $folio->getInformation()->getUsername() . PHP_EOL;


try{
    $begin=microtime(true);
    print"\nTesting PATCH specification rule\n";
    $obj = new stdClass();
    $obj->enabled='true';
    $folio->patch('specification-storage/specifications/6eefa4c6-bbf7-4845-ad82-de7fc4abd0e3/rules/7c843a14-4c87-4c7d-9ad6-5c7654bff9b5',null,$obj);
    if($folio->getLastStatusCode() == 204){
        print"  succeeded\n";
    }else{
        print"  failed\n";
    }
}catch(Exception $e){
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $begin),2) . " seconds.\n\n";
}


print "POST\n";
$location = new stdClass();
$location->name = 'Test location';
$location->code = 'Test0';
$location->discoveryDisplayName = $location->name;
$location->isActive = true;
$location->institutionId = "ba7fc3fe-1c7a-433e-a4cd-37ba26c1d36c"; // Generic State University
$location->campusId = "4f476eb0-af07-4483-bf96-a8fa19226915"; // Lorem campus
$location->libraryId = "ee532760-20af-487b-8272-b4b067498e41"; // Lorem library
$location->primaryServicePoint = "32489163-1292-44fc-95b0-6caf81c391dd";
$location->servicePointIds = ["32489163-1292-44fc-95b0-6caf81c391dd"];
$response= $folio->post('locations',$location);
$id = $response->id;
print "id: $id\n";
print "status: " . $folio->getLastStatusCode() . PHP_EOL;

$scriptBegin=microtime(true);
print "DELETE\n";
$folio->delete('locations',$id);
print "status: " . $folio->getLastStatusCode() . PHP_EOL;
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";


//
print"PUT\n";
$count = 0;
$scriptBegin=microtime(true);
print"GET One\n";
// original name: Lorem Circulation Holding Shelf (Staff Area)
$location = $folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344');
// print_r($location);

print "start put<<<<<<<<\n";
$location->name = 'Lorem Circulation Holding Shelf (Staff Area)';
unset($location->metadata);
$folio->put('locations','094cf617-8114-457c-a4f9-7b9a546d6344',$location);
print "status: " . $folio->getLastStatusCode() . PHP_EOL;

print "validate put\n";
$location = $folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344');
print_r($location);

print "count: $count\n";
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";



print"GET ALL empty\n";
$count = 0;
$scriptBegin=microtime(true);
foreach($folio->getAll('instance-storage/instances','statisticalCodeIds="8028ab79-5a16-44eb-b48c-da94f60c8149"',['limit'=>5000]) as $value){
    // print_r($value);
    $count++;
}
print "count: $count\n";
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";

print"GET ALL\n";
$count = 0;
$scriptBegin=microtime(true);
foreach($folio->getAll('instance-storage/instances',null,['limit'=>5000]) as $value){
    // print_r($value);
    $count++;
}
print "count: $count\n";
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";

print"GET ALL with loop\n";
$count = 0;
$scriptBegin=microtime(true);
foreach($folio->getAll_loop('instance-storage/instances',null,['limit'=>5000]) as $value){
    // print_r($value);
    $count++;
}
print "count: $count\n";
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";


$count = 0;
$scriptBegin=microtime(true);
print"GET with implicit key\n";
foreach($folio->get('instance-storage/instances') as $value){
    // print_r($value);
    $count++;
}
print "count: $count\n";
print "Elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n\n";


print"GET Full Object\n";
print_r($folio->get('locations',null,['limit'=>5],FolioClient::RETURN_FULL_OBJECT));

print"GET with implicit key\n";
foreach($folio->get('locations') as $value){
    print_r($value);
}

print"GET with explicit key\n";
foreach($folio->get('locations',null,['limit'=>5],key: 'locations') as $value){
    print_r($value);
}

print"GET One\n";
print_r($folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344'));


print"GETEACH\n";
$count= 0;
foreach($folio->getEach('locations',null,['limit'=>10],'locations') as $value){
    print "Count $count\n";
    print_r($value);
    $count++;
}


exit;
// 
$done = false;
$beDone = time() + (12 * 60);
while(time() < $beDone){
    print time() . "\t$beDone\n";
    sleep(5);
    print_r($folio->get('locations'));
    if(time() > $beDone){
        print time() . "\t$beDone\n";
        $done = false;
    }
}

exit;



try{
    $folio = new FolioConfig($hostname . ".ini",false,'folioClientLog.txt');
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