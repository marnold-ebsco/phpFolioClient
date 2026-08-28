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
    $pattern = 'instance-all-*.mrc';
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

if (!$allowLiveWrites) {
    print "UPSERT (insert then update) item - skipped (set FOLIO_ALLOW_LIVE_WRITES=1 to run)\n\n";
} else {
    // Master data (e.g. items) gets deterministic v5 ids: FOLIO's own
    // well-known namespace (matching the FOLIO-FSE/folio_uuid migration
    // library's convention) hashed with "{tenant}:{recordType}:{key}" —
    // unlike reference data's random v4 ids (see the location POST test
    // above). Same tenant+barcode always maps to the same id, which is
    // exactly the property an upsert test needs to exercise both the
    // insert and update path against one id.
    $folioUuidNamespace = '8405ae4d-b315-42e1-918a-d1919900cf3f';
    $upsertBarcode = 'upsert-test-barcode-0001';
    $upsertId = null;
    $areaBegin=microtime(true);
    try{
        // borrow valid reference-data ids from an existing item so this
        // test doesn't have to hardcode/guess a valid holdings/material/
        // loan type combination
        $existingItems = $folio->get('item-storage/items',null,['limit'=>1],FolioClient::RETURN_FULL_OBJECT);
        if (!$existingItems->totalRecords) {
            throw new Exception('No existing item found to borrow reference ids from');
        }
        $template = $existingItems->items[0];

        print "UPSERT (insert)\n";
        $tenantId = $folio->getInformation()->getTenantId();
        $folioUuidName = implode(':', [$tenantId, 'items', $upsertBarcode]);
        $upsertId = generateUuid(5, $folioUuidNamespace, $folioUuidName);
        $item = new stdClass();
        $item->id = $upsertId;
        $item->barcode = $upsertBarcode;
        $item->holdingsRecordId = $template->holdingsRecordId;
        $item->materialTypeId = $template->materialTypeId;
        $item->permanentLoanTypeId = $template->permanentLoanTypeId;
        $item->status = (object) ['name' => 'Available'];

        $insertBegin = microtime(true);
        $folio->upsert('item-storage/items', $item);
        $insertElapsed = microtime(true) - $insertBegin;
        print "insert status: " . $folio->getLastStatusCode() . PHP_EOL;
        print "insert elapsed: " . number_format($insertElapsed, 2) . " seconds.\n";

        $created = $folio->getOne('item-storage/items', $upsertId);
        if ($created->barcode === $upsertBarcode) {
            print "  insert succeeded\n";
        } else {
            $failures++;
            print "  insert failed (barcode mismatch)\n";
        }

        print "UPSERT (update)\n";
        // PUT must echo back server-assigned fields (hrid, metadata, etc.)
        // unchanged, so the update is built on the record just read back
        // rather than the minimal insert payload.
        $item = $created;
        unset($item->metadata);
        $item->copyNumber = 'upsert test copy';
        $updateBegin = microtime(true);
        $folio->upsert('item-storage/items', $item);
        $updateElapsed = microtime(true) - $updateBegin;
        print "update status: " . $folio->getLastStatusCode() . PHP_EOL;
        print "update elapsed: " . number_format($updateElapsed, 2) . " seconds.\n";

        $updated = $folio->getOne('item-storage/items', $upsertId);
        if ($updated->copyNumber === $item->copyNumber) {
            print "  update succeeded\n";
        } else {
            $failures++;
            print "  update failed (copyNumber mismatch)\n";
        }

        // re-derive the id from the same namespace + tenant:type:barcode
        // name to confirm it's deterministic, as master data ids are expected to be
        $rederivedId = generateUuid(5, $folioUuidNamespace, $folioUuidName);
        if ($rederivedId !== $upsertId) {
            $failures++;
            print "  v5 UUID was not deterministic: $rederivedId != $upsertId\n";
        }
    }catch(Exception $e){
        $failures++;
        print "  Exception: " . $e->getMessage() . PHP_EOL;
    }finally{
        // Always attempt cleanup, even if insert/update above failed, so a
        // broken run doesn't leave a test item behind.
        if ($upsertId) {
            try {
                $folio->delete('item-storage/items', $upsertId);
            } catch (Exception $e) {
                $failures++;
                print "  Cleanup exception: " . $e->getMessage() . PHP_EOL;
            }
        }
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



/**
 * Generate a UUID of the given RFC 4122 version.
 *
 * - v1 (time-based): no arguments needed; node id is randomized (no NIC
 *   lookup) with its multicast bit set, per RFC 4122 4.5, to mark it as
 *   not a real MAC address.
 * - v3/v5 (name-based, MD5/SHA-1): deterministic — the same
 *   $namespace + $name always produces the same UUID. This is how FOLIO
 *   master data (e.g. items) is expected to get its ids in this test,
 *   as opposed to the random v4 ids reference data uses.
 * - v4 (random): default; no arguments needed.
 */
function generateUuid(int $version = 4, ?string $namespace = null, ?string $name = null): string {
    return match ($version) {
        1 => generateUuidV1(),
        3, 5 => generateUuidNameBased($version, $namespace, $name),
        4 => generateUuidV4(),
        default => throw new \InvalidArgumentException("Unsupported UUID version: $version"),
    };
}

function generateUuidV1(): string {
    // 60-bit count of 100-ns intervals since 1582-10-15, per RFC 4122 4.1.4.
    $gregorianOffset = 0x01B21DD213814000;
    $timestamp = (int) (microtime(true) * 10_000_000) + $gregorianOffset;

    $timeLow = $timestamp & 0xFFFFFFFF;
    $timeMid = ($timestamp >> 32) & 0xFFFF;
    $timeHiAndVersion = (($timestamp >> 48) & 0x0FFF) | 0x1000;

    $clockSeq = random_int(0, 0x3FFF);
    $clockSeqHiAndReserved = (($clockSeq >> 8) & 0x3F) | 0x80;
    $clockSeqLow = $clockSeq & 0xFF;

    // No real NIC available; use a random node id with the multicast bit
    // set, marking it explicitly as not a real MAC address (RFC 4122 4.5).
    $node = random_bytes(6);
    $node[0] = chr(ord($node[0]) | 0x01);

    return sprintf(
        '%08x-%04x-%04x-%02x%02x-%s',
        $timeLow, $timeMid, $timeHiAndVersion, $clockSeqHiAndReserved, $clockSeqLow, bin2hex($node)
    );
}

function generateUuidV4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant
    return formatUuidBytes($bytes);
}

function generateUuidNameBased(int $version, ?string $namespace, ?string $name): string {
    if ($namespace === null || $name === null) {
        throw new \InvalidArgumentException("UUID v$version requires both a namespace UUID and a name");
    }
    $namespaceBytes = hex2bin(str_replace('-', '', $namespace));
    if ($namespaceBytes === false || strlen($namespaceBytes) !== 16) {
        throw new \InvalidArgumentException("Namespace must be a valid UUID");
    }

    $hash = $version === 3 ? md5($namespaceBytes . $name, true) : sha1($namespaceBytes . $name, true);
    $bytes = substr($hash, 0, 16); // sha1 yields 20 bytes; only the first 16 are used

    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | ($version << 4));
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant

    return formatUuidBytes($bytes);
}

function formatUuidBytes(string $bytes): string {
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

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
