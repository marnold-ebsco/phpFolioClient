# phpFolioClient

A lightweight PHP client library for calling [FOLIO](https://folio.org) library
services platform APIs over Okapi. Wraps [Guzzle](https://docs.guzzlephp.org/) to handle
authentication (Refresh Token Rotation), pagination, reference-data lookups, and the
multi-step data-export workflow.

> **Known issues:** this library has undergone code review. See [ISSUES.md](ISSUES.md) for
> the full list of bugs, security issues, and design inconsistencies found, and exactly what
> changed for each one. As of the latest pass, every bug and security issue found has been
> fixed except one open documented limitation (unredacted PII in logs, [S4](ISSUES.md)) and
> two deliberately-left-as-documentation-only design notes ([D2](ISSUES.md#d2), D4).

All classes live in namespace `phpFolioClient` and use `declare(strict_types=1)`.

---

## Architecture

```
FolioConfig                    (standalone value object — holds connection/credential settings)
FolioUtils                     (standalone — UUID/JSON validation helpers)
FolioLogger                    (standalone — tab-delimited query log file)

FolioAuth(config)               depends on: FolioConfig
    │                           talks to POST /authn/login directly via its own Guzzle client
    ▼
FolioInformation(config, auth)  depends on: FolioConfig, FolioAuth
    │                           read-only tenant/environment metadata accessor
    ▼
FolioClient(config, auth, folioUtils, logger?, information?, httpClient?)
    │                           the core HTTP client — GET/PUT/PATCH/POST/DELETE + pagination
    │
    ├── FolioFileHandler(client)
    │       │                   binary upload/download (file-definitions, export downloads)
    │       ▼
    │   FolioDataExport(client, fileHandler)
    │                           orchestrates the data-export workflow end to end
    │
    └── FolioReferenceDataManager(client)
                                convenience wrappers for reference/control-vocabulary data
```

`FolioFileHandler` and `FolioReferenceDataManager` both call `FolioClient::rawRequest()`
(a thin public wrapper around the private `_request()`) rather than going through
`get()`/`post()`/etc. — this is the sanctioned low-level entry point for classes in this
library that need it; general application code should stick to `get()`/`getOne()`/`getAll()`/
`getAll_loop()`/`getEach()`/`put()`/`patch()`/`post()`/`delete()`.

---

## Setup

This directory has its own [composer.json](composer.json) (requiring `guzzlehttp/guzzle`,
plus `phpunit/phpunit` as a dev dependency for the test suite). From this directory:

```bash
composer install
```

This sets up PSR-4 autoloading for the `phpFolioClient` namespace automatically. If you'd
rather integrate this library into an existing Composer project instead, copy the
`require` entry from `composer.json` and point your own PSR-4 autoload mapping at this directory.

### Configuration

`FolioConfig` accepts a path to an INI file, an associative array, or an object.

Required keys: `okapiUrl`, `tenant_id`, `username`, `password`.
Optional keys: `central_tenant_id`, `sslVerify` (default `true`), `debug` (default `false`),
`timeout` (default `30`), `localTimeZone` (default `America/Chicago`).

```php
$config = new FolioConfig([
    'okapiUrl'  => 'https://okapi.example.edu',
    'tenant_id' => 'diku',
    'username'  => 'diku_admin',
    'password'  => getenv('FOLIO_PASSWORD'), // never hardcode credentials
]);
```


### Wiring the client together

```php
use phpFolioClient\{FolioConfig, FolioAuth, FolioUtils, FolioLogger, FolioClient};

$config  = new FolioConfig($configArrayOrIniPath);
$auth    = new FolioAuth($config);
$logger  = new FolioLogger('/var/log/folio-client.log', debug: false, timezone: $config->localTimeZone);
$client  = new FolioClient($config, $auth, new FolioUtils(), $logger);
```

Passing `$config->localTimeZone` to `FolioLogger` is optional (it defaults to
`'America/Chicago'` on its own) but keeps log timestamps consistent with `FolioAuth`'s
token-expiration timezone.

`FolioClient` will build its own Guzzle client from `$config` if none is injected, and its
own `FolioInformation` if none is injected — you don't need to construct those yourself in
the common case.

### Retries

`FolioClient` automatically retries `GET`/`PUT`/`DELETE`/`HEAD` requests (idempotent methods
only — never `POST`/`PATCH`) with exponential backoff on connection failures, 5xx server
errors, and HTTP 429 (respecting a `Retry-After` header if the server sends one). This is on
by default (3 retries, 200ms base delay) and configurable via two trailing constructor
parameters:

```php
$client = new FolioClient($config, $auth, new FolioUtils(), $logger, maxRetries: 5, retryBaseDelayMs: 300);
```

Pass `maxRetries: 0` to disable retries entirely.

---

## Usage

### Fetching records

```php
// small result sets: returns a Generator of records, one at a time
foreach ($client->get('/inventory/instances', 'title="the great gatsby"') as $instance) {
    echo $instance->id, "\n";
}

// fetch one record by id (validates the id is a UUID first)
$instance = $client->getOne('/inventory/instances', $instanceId);

// full pagination for large result sets (fast: uses an `id >` cursor)
foreach ($client->getAll('/inventory/instances') as $instance) {
    echo $instance->id, "\n";
}
```

`get()`'s `$key` parameter lets you get the raw response object instead of a record
generator — pass `FolioClient::RETURN_FULL_OBJECT` when you need `totalRecords` or other
envelope metadata rather than the records themselves.

### Writing records

```php
$client->post('/inventory/instances', $newInstanceData);
$client->put('/inventory/instances', $instanceId, $updatedInstanceData);
$client->patch('/inventory/instances', $instanceId, ['status' => 'active']);
$client->delete('/inventory/instances', $instanceId);
```

### Reference data

```php
use phpFolioClient\FolioReferenceDataManager;

$refData = new FolioReferenceDataManager($client);

$locations = $refData->getLocations();       // materialized [id => name] array
foreach ($refData->getLocationObjects() as $location) {  // Generator of full objects
    // ...
}
```

### Data export

```php
use phpFolioClient\{FolioFileHandler, FolioDataExport};

$fileHandler = new FolioFileHandler($client);
$export      = new FolioDataExport($client, $fileHandler, verbose: false);

// export a specific file of instance UUIDs (one per line)
$export->dataExport('instance-ids.txt', 'Default instances export job profile', '/tmp/out');

// export every record matching a job profile
$export->dataExportAll('Default instances export job profile', '/tmp/out');
```

Both methods poll the export job until it reaches a terminal status and download the
resulting file. A job that ends in `FAIL` status throws a clear `\Exception` rather than
attempting the download. Pass `verbose: true` to the constructor to print step-by-step
progress (including full response dumps) to stdout while an export runs.

---

## Class reference

| Class | Purpose | Depends on |
|---|---|---|
| [`FolioConfig`](src/folio/FolioConfig.php) | Connection/credential settings, loaded from INI/array/object | — |
| [`FolioUtils`](src/folio/FolioUtils.php) | UUID and JSON validation helpers | — |
| [`FolioLogger`](src/folio/FolioLogger.php) | Tab-delimited query log, optional `error_log` mirroring | — |
| [`FolioAuth`](src/folio/FolioAuth.php) | Login/token lifecycle (RTR), tracks access-token expiry | `FolioConfig` |
| [`FolioInformation`](src/folio/FolioInformation.php) | Read-only tenant/environment metadata | `FolioConfig`, `FolioAuth` |
| [`FolioClient`](src/folio/FolioClient.php) | Core HTTP client: GET/PUT/PATCH/POST/DELETE + pagination | `FolioConfig`, `FolioAuth`, `FolioUtils`, `FolioLogger`?, `FolioInformation`? |
| [`FolioFileHandler`](src/folio/FolioFileHandler.php) | Binary file upload/download | `FolioClient` |
| [`FolioDataExport`](src/folio/FolioDataExport.php) | Orchestrates the data-export workflow | `FolioClient`, `FolioFileHandler` |
| [`FolioReferenceDataManager`](src/folio/FolioReferenceDataManager.php) | Reference/control-vocabulary lookups (locations, material types, patron groups, etc.) | `FolioClient` |

Every class and public method below now has a full PHPDoc block (`@param`/`@return`/`@throws`)
directly in its source file — see the individual `.php` files for exact signatures.

---

## Running tests

Every class has a matching PHPUnit test in [`tests/`](tests). After `composer install`:

```bash
vendor/bin/phpunit
```

135 tests, ~6 seconds. Most tests use Guzzle's `MockHandler` to inject canned HTTP
responses — no real network access is needed. Two spots build their own Guzzle client
internally rather than accepting an injected one (`FolioAuth::refreshTokens()` and
`FolioFileHandler::getFile()`), so their tests instead start a real local PHP built-in web
server (`tests/Support/PhpServerTrait.php` + `tests/fixtures/mock_server_router.php`) on a
random high port for the duration of the test class. `FolioDataExportTest`'s happy-path
tests take slightly over a second each, since the polling loop in `dataExport()`/
`dataExportAll()` always sleeps 1 real second per iteration — pre-existing production
behavior, not a test artifact.

| Test file | Covers |
|---|---|
| `FolioUtilsTest` | UUID/JSON validation edge cases |
| `FolioConfigTest` | Construction from array/object/INI, required-key validation, `sslVerify` normalization, `__debugInfo()` redaction |
| `FolioLoggerTest` | Append-mode file writing, timezone, `error_log` mirroring, destructor cleanup |
| `FolioInformationTest` | Delegation to config/auth, hostname parsing edge cases |
| `FolioAuthTest` | Login flow (success/failure paths) against the local server, token caching, `needsRefresh()` logic |
| `FolioClientTest` | GET/pagination/CRUD methods, retry/backoff behavior, internal parameter/response-shape helpers |
| `FolioFileHandlerTest` | Upload/download, header casing, file-handle cleanup |
| `FolioDataExportTest` | Full export workflows end-to-end (through a real download from the local server), failure paths |
| `FolioReferenceDataManagerTest` | Reference-data lookups, `getModules()`/`getCustomFieldObjects()` edge cases |

## Known limitations

See [ISSUES.md](ISSUES.md) for the full list. In short:

- `sslVerify` should not be disabled in production; if you must (e.g. self-signed certs in a
  dev environment), pass a real boolean or a CA-bundle file path — `FolioConfig` normalizes
  common boolean-like strings (`"true"`/`"false"`/etc.) from any config source, but arbitrary
  non-boolean strings are treated as a CA-bundle path, per Guzzle's own convention.
- Logging is not redacted for PII in CQL query strings ([S4](ISSUES.md)) — treat log files as
  sensitive.
- `FolioConfig::$debug` has no effect on this library's own behavior (see [D2](ISSUES.md#d2))
  — it's available for application code to read for its own purposes only. Use
  `FolioLogger`'s `$debug` constructor parameter to control `error_log()` mirroring instead.
- `FolioClient::rawRequest()` is a public low-level escape hatch used internally by
  `FolioFileHandler` and `FolioReferenceDataManager`; general application code should prefer
  the CRUD methods (`get`/`put`/`patch`/`post`/`delete`) instead.
