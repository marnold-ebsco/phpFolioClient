# phpFolioClient readme

phpFolioClient is utility that can be used to interact with the FOLIO Library Management System (https://folio.org/). It allows users to use FOLIO's APIs without having to worry about authenticating, re-authenticating on long running scripts, or dealing with CURL calls/HTTP requests. There are two versions. Version 1 is a single class that does everything. Version 2 was split up into separate classes because Version 1 was becoming too unwieldy. Documention for the FOLIO APIs can be found here: https://dev.folio.org/reference/api/.

# Version 1
This version still works, but any new features will only be added to v2. 
## Installation

To deploy, copy the `composer.json` file (or create a new one) to the root of your working directory.

### composer.json

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/marnold-ebsco/phpfolioclient.git"
        }
    ],
    "require": {
        "marnold-ebsco/phpfolioclient": "^0.9.0"
    }
}
```

Then run:

```bash
composer require marnold-ebsco/phpfolioclient:^0.9.0
```

create an ini file using this template:
```bash
name        = 
okapiUrl    = 
tenant_id   = 
username    = 
password    = 
sslVerify   = "vendor/marnold-ebsco/phpfolioclient/src/folio/cacert.pem"
```

save the template as {hostname}.ini
The ini above will use the cacert.pem that is part of the upload. You can set sslVerify to 'true' to use the default CA bundle for you system (if available). You can modify your php.ini file to to set your cacert file as the default by adding/uncommenting these keys:
```bash
curl.cainfo = "C:/path/to/cacert.pem"
openssl.cafile = "C:/path/to/cacert.pem"
```

You can find the lastest cacert.pem file here: https://curl.se/docs/caextract.html. Set the path to the cacert in the .ini file.

If necessary, you can set sslVerify to false. This is not secure and is not recommended.


## Using the package
### initialize the phpFolioClient class
Create a new php file that will run your script. Include something like this at the beginning of your file:

```php
<?php
require_once('vendor/autoload.php');

use phpFolioClient\phpFolioClient;

$hostname = {hostname}; //this must match an existing .ini file

try{
    $folio = new phpFolioClient($hostname . ".ini");
}catch(Exception $e){
    print "Error: " . $e->getMessage();
    exit;
}

?>
```
## Running queries
Look at the test.php file in the vendor/marnold-ebsco/phpfolioclient/tests/ folder for examples of how to run queries, insert, update, and delete records.

## get
To get data from FOLIO you have four options:
get:
    At a minimum, pass the API endpoint
    ```php
    $response = $folio->get('loan-types');
    ```
    FOLIO will return a response object which you will need to disassemble to get the data you need.
    You can send an array of parameters to pass to the endpoint. For example you can send CQL queries, establish the number of records returned, and set offsets using something like this:
    $response = $folio->get('endpoint',['query'=>'cql.allRecords=1','limit'=>100,'offset'=>2]);
getOne:
    getOne requires the UUID of the record you are wanting to return. Unlink get, the response is just the record object and not a response object that you need to disassemble
    ```php
    $response = $folio->getOne('loan-types',{record UUID});
    ```
getAll:
    getAll works similarly to get, but returns a single record at a time. Use this to loop over all of the records returned. You must provide the endpoint, the name of the array of objects returned (found in the documentation), and an array of parameters to pass to the endpoint. It is used something like this:
    ```php
    foreach($folio->getAll('instance-storage/instances','instances',['query'=>'cql.allRecords=1','limit'=>100]) as $instance){        
        $count++;
    }
    ```
getAll_by_id_offset:
    getAll_by_id_offset uses the same structure as getAll. The difference is on the backend. getAll_by_id_offset grabs the next set of records in a different way than getAll. If you are working with larger data sets this can be substantially faster.

## put
Put is used to update a record. You will need to retrieve the record first, make what changes, and then put the record back. You will need the API endpoint, the UUID of the record you are changing, and the changed record. Only one record can be updated at a time. Put looks something like this:
```php
$folio->put('material-types',$materialTypeObject->id,$materialTypeObject)
```
## post
Post is used to create a new record. You will need the API endpoint and the new record object. (See the documentation ). Post can only create one record at a time. Post looks something like this:
```php
$folio->post('material-types',$materialTypeObject)
```

## delete
Delete is used to delete a record or an entire set of records. Needless to say, this can be a dangerous command. At a minimum, delete just needs an endpoint, but it may without discrimination delete every record created with that endpoint. In almost every case you will want to pass the UUID of one object that you want to delete. Something like this:
```php
    $folio->delete('material-types',$id);
```

# Version 2

### composer.json

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/marnold-ebsco/phpfolioclient.git"
        }
    ],
    "require": {
        "marnold-ebsco/phpfolioclient": "^2.0.0"
    }
}
```

Then run:

```bash
composer require marnold-ebsco/phpfolioclient:^2.0.0
```

Version 2 uses the same ini as discussed above in version 1. The instructions for sslVerify are also the same.

## Using the package
### initialize the phpFolioClient class
Setup depends on what classes you will be using. At a minimum you will need this:

```php
<?php
require_once('vendor/autoload.php');

use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioReferenceDataManager;

$hostname = {hostname}; //this must match an existing .ini file

try{
    $folio = new phpFolioClient($hostname . ".ini");
}catch(Exception $e){
    print "Error: " . $e->getMessage();
    exit;
}
```
If you want to use data export, you will need to use this:
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioLogger;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioDataExport;
use phpFolioClient\FolioFileHandler;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioReferenceDataManager;

## Running queries
Put, Post, and Delete works the same as in version 1. Get has been substantially reworked however. There are now 5 different flavors of get:
get: As in version 1, get can return the entire response object which must then be disassembled. It requires an extra parameter. The parameters to be passed are the API endpoint, the query (which has been separated out from the parameter array), an array of parameters to pass to the endpoint, and a constant that tells the class to return the full response object.
```php
$folio->get('locations',null,['limit'=>5],FolioClient::RETURN_FULL_OBJECT)
```

Without the RETURN_FULL_OBJECT constant, get return one record at a time. Note that for a large number of records this could be slow. Use getAll instead. It would be used something like this:
```php
foreach($folio->get('locations') as $value){
    print_r($value);
}
```
Note that the name of the array of objects returned does not need to be explicitly set. The class will attempt to derive that key from the data returned. A key can be explicitly set however:
```php
foreach($folio->get('locations',null,['limit'=>5],key: 'locations') as $value){
    print_r($value);
}
```

getOne is called just as in v1. You need the endpoint and the UUID of the record to be retrieved
$folio->getOne('locations',{uuid of record})

getEach is an alias of get where every record is returned individually. It would be used like this:
```php
foreach($folio->getEach('locations') as $value){
    print_r($value);
}
```

getAll is functionally equivalent to getAll_by_id_offset in v1. It is called like this:
```php
foreach($folio->getAll('instance-storage/instances',null,['limit'=>5000]) as $value){
    $count++;
}
```
You can see examples of various queries by looking at the testRefactor.php file in the tests/ folder.  