# phpFolioClient readme

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
sslVerify   = 
```

save the template as {hostname}.ini
set sslVerify to 'true' to use the default CA bundle. You can modify your php.ini file to to set your cacert file as the default by adding/uncommenting these keys:
```bash
curl.cainfo = "C:/path/to/cacert.pem"
openssl.cafile = "C:/path/to/cacert.pem"
```

You can also set sslVerify explicitly. Download the lastest cacert.pem file here: https://curl.se/docs/caextract.html. Set the path to the cacert in the .ini file. You can also 
set sslVerify to 'true'

If necessary, you can set sslVerify to false. This is highly unsecure and it now recommended.


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
There are multiple ways to run a query.

The following runs a query against the item-storage/items endpoint (documented here: https://s3.amazonaws.com/foliodocs/api/mod-inventory-storage/r/item-storage.html)

```php
$results = $folio->get('item-storage/items',['query'=>'materialTypeId=="24a4178c-4733-4bfe-b8cb-e08068e65cbd" sortby effectiveLocationId','limit'=>2,'offset'=>10])
```
The results will looking something like this:

```json
{
    "items": [
        {
            "id": "3e6786fb-5904-518d-a6a5-e8ad6aae4e00",
            "_version": 2,
            "hrid": "it00000329960",
            "holdingsRecordId": "d5806b83-1273-5008-862b-10c33f903c37",
            "formerIds": [
                "784723-1001",
                "u784723ONLINEI198125097G4TF024"
            ],
            "discoverySuppress": false,
            "barcode": "784723-1001",
            "effectiveShelvingOrder": "I 219 281 !525097  !G 14  !TF !224  11",
            "effectiveCallNumberComponents": {
                "callNumber": "I 19.81:25097-G 4-TF-024/",
                "typeId": "fc388041-6cd0-4806-8a74-ebe3b9ab4c6e"
            },
            "yearCaption": [],
            "copyNumber": "1",
            "administrativeNotes": [],
            "notes": [
                {
                    "itemNoteTypeId": "afb2d3b7-afff-400d-977d-c3c595001dda",
                    "note": "3/5/2021",
                    "staffOnly": true
                },
                {
                    "itemNoteTypeId": "462b75fa-d5ab-4652-848f-4456bd44d2aa",
                    "note": "Y",
                    "staffOnly": true
                }
            ],
            "circulationNotes": [],
            "status": {
                "name": "Available",
                "date": "2026-04-03T15:29:53.658+00:00"
            },
            "materialTypeId": "19335b1f-c026-4328-861b-a293557830d7",
            "permanentLoanTypeId": "2b94c631-fca9-4892-a730-03ee529ffe27",
            "effectiveLocationId": "0bae8d5c-d11e-46ba-89ac-e50091d8ee13",
            "electronicAccess": [],
            "statisticalCodeIds": [],
            "metadata": {
                "createdDate": "2026-04-03T16:15:49.362+00:00",
                "createdByUserId": "6fb6124d-cd48-4db7-8e13-d894e86e0792",
                "updatedDate": "2026-04-03T19:22:31.667+00:00",
                "updatedByUserId": "6fb6124d-cd48-4db7-8e13-d894e86e0792"
            }
        },
        {
            "id": "b1d98be6-8c64-5fe3-8459-a347918a76f1",
            "_version": 2,
            "hrid": "it00000329961",
            "holdingsRecordId": "02b1348a-cf22-5cc8-aa64-82c20fb8b85a",
            "formerIds": [
                "784724-1001",
                "u784724ONLINEI198125097H2TF024"
            ],
            "discoverySuppress": false,
            "barcode": "784724-1001",
            "effectiveShelvingOrder": "I 219 281 !525097  !H 12  !TF !224  11",
            "effectiveCallNumberComponents": {
                "callNumber": "I 19.81:25097-H 2-TF-024/",
                "typeId": "fc388041-6cd0-4806-8a74-ebe3b9ab4c6e"
            },
            "yearCaption": [],
            "copyNumber": "1",
            "administrativeNotes": [],
            "notes": [
                {
                    "itemNoteTypeId": "afb2d3b7-afff-400d-977d-c3c595001dda",
                    "note": "3/5/2021",
                    "staffOnly": true
                },
                {
                    "itemNoteTypeId": "462b75fa-d5ab-4652-848f-4456bd44d2aa",
                    "note": "Y",
                    "staffOnly": true
                }
            ],
            "circulationNotes": [],
            "status": {
                "name": "Available",
                "date": "2026-04-03T15:29:53.659+00:00"
            },
            "materialTypeId": "19335b1f-c026-4328-861b-a293557830d7",
            "permanentLoanTypeId": "2b94c631-fca9-4892-a730-03ee529ffe27",
            "effectiveLocationId": "0bae8d5c-d11e-46ba-89ac-e50091d8ee13",
            "electronicAccess": [],
            "statisticalCodeIds": [],
            "metadata": {
                "createdDate": "2026-04-03T16:15:49.362+00:00",
                "createdByUserId": "6fb6124d-cd48-4db7-8e13-d894e86e0792",
                "updatedDate": "2026-04-03T19:22:31.667+00:00",
                "updatedByUserId": "6fb6124d-cd48-4db7-8e13-d894e86e0792"
            }
        }
    ],
    "totalRecords": 1676,
    "resultInfo": {
        "totalRecords": 1676,
        "facets": [],
        "diagnostics": []
    }
}
```
If you have the UUID of a single records and want to retrieve just the item object, you can use this command:
```php
$item = $folio->getOne('item-storage/items','b1d98be6-8c64-5fe3-8459-a347918a76f1');
```

There are two ways to get and process a large quantity of records. Both require the same information. Because of some extra overhead, for small data sets, getAll is faster, but when working with larger data sets getAll_by_id_offset is faster. Both need the API endpoint. Both also require the name of the array that will hold the objects returned. Unlike the 'get' method above, you need to wrap these commands in a foreach statement
```php
foreach($folio->getAll('item-storage/items','items',['query'=>'cql.allRecords=1','limit'=>100]) as $item){        
}

foreach($folio->getAll_by_id_offset('item-storage/items','items',['query'=>'cql.allRecords=1','limit'=>100]) as $item){
}
```


