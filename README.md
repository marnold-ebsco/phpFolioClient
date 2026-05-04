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
Look at the test.php file in the vendor/marnold-ebsco/phpfolioclient/tests/ folder for examples of how to run queries, insert, update, and delete records.


