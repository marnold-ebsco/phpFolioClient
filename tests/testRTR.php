<?php
require_once('vendor/autoload.php');

use phpFolioClient\phpFolioClient;

$connectionObj = new stdClass();
$connectionObj->okapiUrl = 'https://folio-snapshot-okapi.dev.folio.org/';
$connectionObj->tenant_id = 'diku';
$connectionObj->username = 'diku_admin';
$connectionObj->password = 'admin';
$connectionObj->sslVerify = 'cacert.pem';

$connectionArray = ['okapiUrl' => 'https://folio-snapshot-okapi.dev.folio.org/',
                    'tenant_id'=>'diku','username'=>'diku_admin','password'=>'admin','sslVerify'=>true];


$connectionObj = new stdClass();
//$connectionObj->okapiUrl = 'https://okapi-bugfest-orchid.int.aws.folio.org';
$connectionObj->okapiUrl = 'https://okapi-bugfest-poppy.int.aws.folio.org';
$connectionObj->tenant_id = 'fs09000000';
$connectionObj->username = 'folio';
$connectionObj->password = 'folio';
//$connectionObj->sslVerify = true;

$ecsObj = new stdClass();
$ecsObj->okapiUrl = 'https://folio-testing-sprint-okapi.ci.folio.org';
$ecsObj->tenant_id = 'cs00000int';
$ecsObj->username = 'ECSAdmin';
$ecsObj->password = 'admin';

$connection_ini = 'snapshot.ini';

try{  
    $begin=time();
    $folio = new phpFolioClient($connection_ini,true,'folioClientLog.txt');
    $folio->setTimeout(0);

    $continue = true;
    $count = 0;
    do{
        $response = $folio->get('loan-types');
        // print_r($response) . PHP_EOL;
        $count++;
        print str_pad($count,5," ",STR_PAD_LEFT) . " | " . time() . " | " . str_pad((time() - $begin),5," ",STR_PAD_LEFT) . " | " . $folio->token() . "\r";

        sleep(10);
        // if((time() - $begin) > 1300){
        //     $continue = false;
        //     print "quitting: " . time() . " | $begin\n";
        // }
    }while($continue);
    print str_pad($count,5," ",STR_PAD_LEFT) . " | " . time() . " | " . str_pad((time() - $begin),5," ",STR_PAD_LEFT) . " | " . $folio->token() . "\n";


}catch(Exception $e){
    print "Error: " . $e->getMessage() . PHP_EOL;
}finally{
    print "Elapsed time: " . time() - $begin . " seconds.\n";
}