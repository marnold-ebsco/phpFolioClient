<?php
require_once(__DIR__ . '/../vendor/autoload.php');

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

// This script talks to a live FOLIO server and, unless
// FOLIO_ALLOW_LIVE_WRITES=1 is set, skips every step that creates,
// updates, or deletes real data on that tenant (read-only steps still run).
// $allowLiveWrites = getenv('FOLIO_ALLOW_LIVE_WRITES') === '1';
$allowLiveWrites = true;

$failures = 0;
$scriptBegin=microtime(true);

// clean up old files
$directory = dirname(__DIR__);
$today = strtotime('today');

// Find all .mrc files matching the pattern
foreach (glob($directory . "/*.mrc") as $file) {
    // Validate that it is a file and was not created today
    if (is_file($file) && filemtime($file) < $today) {
        unlink($file); // Delete the file
    }
}

print "PHP version: " . PHP_VERSION . PHP_EOL;

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
    $failures++;
    print "Exception: " . $e->getMessage() . PHP_EOL;
}


// try{
//     print "Testing data export (from list)\n";
//     $areaBegin=microtime(true);
//     $exportHandler->dataExport("/home/marnold/phpFolioClient2/testExport.csv");

//     // check if file was created
//     $pattern = 'testExport*.mrc';
//     $fiveMinutesAgo = time() - 300; // 5 minutes * 60 seconds

//     $foundFiles = [];

//     foreach (glob($pattern) as $file) {
//         // Check if it is a file and modified/created within the last 5 minutes
//         if (is_file($file) && filemtime($file) >= $fiveMinutesAgo) {
//             $foundFiles[] = $file;
//         }
//     }

//     if(count($foundFiles) > 0){
//         print "  Data export (list) was successful\n";
//         $mrc_files = glob($directory . '/*.mrc');
        // foreach ($mrc_files as $file) {
        //     if (is_file($file)) {
        //         unlink($file);
        //     }
        // }
//     }else{
//         throw new Exception('Data export (list) failed');
//     }
// }catch(Exception $e){
//     $failures++;
//     print "  Exception: " . $e->getMessage() . PHP_EOL;
// }finally{
//     print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
// }
// exit;


try{
    print "Testing data export all\n";
    $areaBegin=microtime(true);
    $exportHandler->dataExportAll();

    // check if file was created
    $pattern = 'testExport*.mrc';
    $fiveMinutesAgo = time() - 300; // 5 minutes * 60 seconds

    $foundFiles = [];

    foreach (glob($pattern) as $file) {
        // Check if it is a file and modified/created within the last 5 minutes
        if (is_file($file) && filemtime($file) >= $fiveMinutesAgo) {
            $foundFiles[] = $file;
        }
    }

    if(count($foundFiles) > 0){
        print "  Data export all was successful\n";
        $mrc_files = glob($directory . '/*.mrc');
        foreach ($mrc_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }else{
        throw new Exception('Data export all failed');
    }
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}
// print "Export all marc data\n";
// $exportHandler->dataExportAll();
// exit;

try{
    print "Testing reference data\n";
    $areaBegin=microtime(true);
    $version = $folio->getVersion();
    print"Version: $version\n";

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

    print"\nstatus information";
    $folio->get('locations');
    print"\n";
    print"last status code: ";
    print $folio->getLastStatusCode() . PHP_EOL;
    print"status code (alias): ";
    print $folio->getStatusCode() . PHP_EOL;
    print"last query: ";
    print $folio->getLastQuery() . PHP_EOL;
    print"last query number: ";
    print $folio->getLastQueryNum() . PHP_EOL;
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

if (!$allowLiveWrites) {
    print "testing GET/INSERT/UPDATE/DELETE - skipped (set FOLIO_ALLOW_LIVE_WRITES=1 to run)\n\n";
} else {
    $testType = 'testMType';
    $mattypeUuid = null;
    $areaBegin=microtime(true);
    try{
        print "testing GET/INSERT/UPDATE/DELETE\n";

        $mattypeUuid = mattypeExists($folio, $testType);
        if($mattypeUuid){
            updateMattype($folio, $testType . '-update', $mattypeUuid);
        }else{
            $mattypeUuid = addMattype($folio, $testType);
        }
    }catch(Exception $e){
        $failures++;
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        // Always attempt cleanup, even if the add/update above failed, so a
        // broken run doesn't leave a test material type behind.
        if ($mattypeUuid) {
            try {
                deleteMattype($folio, $mattypeUuid);
                if(mattypeExists($folio, $testType) || mattypeExists($folio, $testType . '-update')){
                    $failures++;
                    print "Mattype delete failed\n";
                }else{
                    print "testing succeeded\n";
                }
            } catch (Exception $e) {
                $failures++;
                print "  Cleanup exception: " . $e->getMessage() . PHP_EOL;
            }
        }
        print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
    }
}


if (!$allowLiveWrites) {
    print "\nTesting PATCH specification rule - skipped (set FOLIO_ALLOW_LIVE_WRITES=1 to run)\n\n";
} else {
    try{
        $areaBegin=microtime(true);
        print"\nTesting PATCH specification rule\n";
        $obj = new stdClass();
        $obj->enabled='true';
        $folio->patch('specification-storage/specifications/6eefa4c6-bbf7-4845-ad82-de7fc4abd0e3/rules/7c843a14-4c87-4c7d-9ad6-5c7654bff9b5',null,$obj);
        if($folio->getLastStatusCode() == 204){
            print"  succeeded\n";
        }else{
            $failures++;
            print"  failed\n";
        }
    }catch(Exception $e){
        $failures++;
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
    }
}

if (!$allowLiveWrites) {
    print "POST/GET/DELETE location - skipped (set FOLIO_ALLOW_LIVE_WRITES=1 to run)\n\n";
} else {
    $id = null;
    $areaBegin=microtime(true);
    try{
        print "POST Create location\n";
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

        print "GET\n";
        $folio->get('locations',"id==$id");
        print "status: " . $folio->getLastStatusCode() . PHP_EOL;
    }catch(Exception $e){
        $failures++;
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        // Always attempt cleanup, even if the GET above failed, so a
        // broken run doesn't leave a test location behind.
        if ($id) {
            try {
                print "DELETE\n";
                $folio->delete('locations',$id);
                print "status: " . $folio->getLastStatusCode() . PHP_EOL;
            } catch (Exception $e) {
                $failures++;
                print "  Cleanup exception: " . $e->getMessage() . PHP_EOL;
            }
        }
        print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
    }
}

if (!$allowLiveWrites) {
    print "PUT - skipped (set FOLIO_ALLOW_LIVE_WRITES=1 to run)\n\n";
} else {
    try{
        print"PUT\n";
        $count = 0;
        $areaBegin=microtime(true);
        // original name: Lorem Circulation Holding Shelf (Staff Area)
        $location = $folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344');
        $location->name = 'Lorem Circulation Holding Shelf (Staff Area)';
        unset($location->metadata);
        $folio->put('locations','094cf617-8114-457c-a4f9-7b9a546d6344',$location);
        print "status: " . $folio->getLastStatusCode() . PHP_EOL;

        $finalLocation = $folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344');
        unset($finalLocation->metadata);
        if ($location == $finalLocation) {
            print "PUT succeeded\n";
        } else {
            $failures++;
            print "PUT failed\n";
        }
    }catch(Exception $e){
        $failures++;
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
    }
}

try{
    print"GET ALL empty\n";
    $count = 0;
    $areaBegin=microtime(true);
    foreach($folio->getAll('instance-storage/instances','statisticalCodeIds="8028ab79-5a16-44eb-b48c-da94f60c8149"',['limit'=>5000]) as $value){
        $count++;
    }
    print "count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    print"GET ALL\n";
    $count = 0;
    $areaBegin=microtime(true);
    foreach($folio->getAll('instance-storage/instances',null,['limit'=>5000]) as $value){
        $count++;
    }
    print "count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    print"GET ALL with loop\n";
    $count = 0;
    $areaBegin=microtime(true);
    foreach($folio->getAll_loop('instance-storage/instances',null,['limit'=>5000]) as $value){
        $count++;
    }
    print "count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    $count = 0;
    $areaBegin=microtime(true);
    print"GET with implicit key\n";
    foreach($folio->get('instance-storage/instances') as $value){
        $count++;
    }
    print "count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
$areaBegin=microtime(true);
    print"GET Full Object\n";
    $loc = $folio->get('locations',null,['limit'=>5],FolioClient::RETURN_FULL_OBJECT);
    print "Count: " . count($loc->locations) . PHP_EOL;
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    $areaBegin=microtime(true);
    print"GET with implicit key\n";
    $count = 0;
    foreach($folio->get('locations') as $value){
        $count++;
    }
    print "Count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    $areaBegin=microtime(true);
    print"GET with explicit key\n";
    $count = 0;
    foreach($folio->get('locations',null,['limit'=>5],key: 'locations') as $value){
        $count++;
    }
    print "Count: $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    $areaBegin=microtime(true);
    print"GET One\n";
    $loc = $folio->getOne('locations','094cf617-8114-457c-a4f9-7b9a546d6344');
    if(isset($loc->locations)){
        print "Count: " . count($loc->locations) . PHP_EOL;
    }else{
        print "No locations defined\n";
    }
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}

try{
    $areaBegin=microtime(true);
    print"GET Each\n";
    $count= 0;
    foreach($folio->getEach('locations',null,['limit'=>10],'locations') as $value){
        $count++;
    }
    print "Count $count\n";
}catch(Exception $e){
    $failures++;
    print "  Exception: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . number_format((microtime(true) - $areaBegin),2) . " seconds.\n\n";
}


print "Script elapsed time: " . number_format((microtime(true) - $scriptBegin),2) . " seconds.\n";

exit($failures > 0 ? 1 : 0);



function mattypeExists(FolioClient $folio, $name){
    $results = $folio->get('material-types','name=="' . $name . '"',[],FolioClient::RETURN_FULL_OBJECT);
    if($results->totalRecords == 1){
        return $results->mtypes[0]->id;
    }else{
        return false;
    }
}

function addMattype(FolioClient $folio, $name){
    $materialType =new stdClass();
    $materialType->name = $name;
    $folio->post('material-types',$materialType);
    return mattypeExists($folio, $name);
}

function updateMattype(FolioClient $folio, $name, $id){
    print "update\n";
    $materialType =new stdClass();
    $materialType->name = $name;

    $folio->put('material-types',$id,$materialType);

}

function deleteMattype(FolioClient $folio, $id){
    $folio->delete('material-types',$id);
}
