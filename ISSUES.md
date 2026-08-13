# FOLIO PHP Client — Code Review Findings

Reviewed: `FolioAuth.php`, `FolioClient.php`, `FolioConfig.php`, `FolioDataExport.php`,
`FolioFileHandler.php`, `FolioInformation.php`, `FolioLogger.php`,
`FolioReferenceDataManager.php`, `FolioUtils.php`.

All classes live in namespace `phpFolioClient` and use `declare(strict_types=1)`, which
means several of the bugs below were hard `TypeError`/`Error` crashes rather than silent
misbehavior.

Severity legend: 🔴 High (crash / data loss / security) · 🟡 Medium · ⚪ Low / cleanup.

**Status: every bug (B1–B27) and security issue (S1–S3, S5) has been fixed. S4 remains an
open documented limitation (no reliable general fix exists). Every design/consistency issue
(D1, D3, D5–D12) has been fixed; D2 and D4 were deliberately left as documentation-only
(see their entries below for why). Five extra latent bugs turned up while fixing the above
and while writing the test suite (`tests/`) — see
[§0](#0-extra-issues-found-while-fixing-the-above). All 135 tests pass; see
[README.md](README.md#running-tests) for how to run them.**

---

## Changelog — fixes applied

### Session 1 (initial pass)

| Issue | File | Change |
|---|---|---|
| B1 | `FolioClient.php` | Null-safe logger calls (`$this->logger?->log(...)`) in `_request()`. |
| B2 | `FolioClient.php` | `$lastStatusCode` now defaults to `0` instead of being uninitialized. |
| S1 | `FolioAuth.php` | `refreshTokens()` no longer forwards `config->debug` into the login request's Guzzle client (was leaking the plaintext password when debug mode was on). |
| B12 | `FolioConfig.php` | `loadFromIni()` uses `INI_SCANNER_TYPED` so `debug`/`timeout` parse as real bool/int instead of throwing. |
| B4 | `FolioClient.php` | `getEach()` now rejects `RETURN_FULL_OBJECT` with a clear `\InvalidArgumentException` instead of silently breaking its `\Generator` return type. |

### Session 2 (this pass) — decisions made first

Four items involved a real design trade-off, so they were confirmed with the user before
implementing:

| Decision | Choice made |
|---|---|
| B26 — UUID validation strictness | **Keep strict** (v4/v5 only, matching FOLIO's own id format); documented explicitly rather than loosened. |
| S3/D7 — `putFileX()` dead/risky code | **Keep the method**, just remove the credential-leaking hardcoded debug flag and the surrounding dead commented-out code. |
| D11 — `_request()` public/private mismatch | **Make it genuinely private.** Since `protected` doesn't help here (`FolioFileHandler`/`FolioReferenceDataManager` don't extend `FolioClient`, so PHP's `protected` visibility wouldn't let them call it), added a new public `rawRequest()` wrapper as the real sanctioned low-level entry point. |
| D6 — `getCustomFieldObjects()` breaking the `*Objects()` convention | **Converted it to return a `\Generator`**, matching every sibling method, instead of renaming it. |

Everything else below was fixed directly (each is a bug fix, security fix, or low-risk
additive/backward-compatible change — nothing else warranted a decision):

| Issue | File | Change |
|---|---|---|
| B3 | `FolioClient.php` | `_request()`/`rawRequest()`/`_handleParameters()` now accept `mixed $params` instead of the too-narrow `array\|null`, so the documented "pass a JSON or UUID string" feature no longer throws a `TypeError` before it can run. |
| B5 | `FolioClient.php` | `_getResponseInfo()` now does `array_values(array_diff(...))[0] ?? null` instead of an unreindexed `array_diff(...)[0]`, so it can no longer silently resolve to `null`/crash when `errors` is the first array key. |
| B6 | `FolioClient.php` | `get()`, `getAll_loop()`, and `getAll()` all now check for a `null` response (e.g. a 204) before calling `_getResponseInfo()`, and treat it as "no records" instead of throwing. `_yieldRecords()` accepts a nullable response/key for the same reason. |
| B7 | `FolioClient.php` | `_request()` now calls `_handleParameters()` once into a local variable instead of twice per request. |
| B8 | `FolioClient.php` | `getAll()`'s misleading `while ($responseInfo['totalRecords'] > 0)` (a stale snapshot that never changes) replaced with `while (true)`, with a comment explaining that termination is via the internal `break`. |
| B9 | `FolioClient.php` | `getAll()`'s tautological `$origQuery = (isset($query)) ? $query : $params['query'];` simplified to `$origQuery = $query;`. |
| B10 | `FolioClient.php` | Verified already accurate — a previous documentation pass had already rewritten `put()`'s docblock to describe its real single-record contract. No code/doc change needed this round. |
| B11 | `FolioClient.php` | Added a `catch (RequestException $e)` fallback after the `ClientException\|ServerException` and `ConnectException` catches, so other Guzzle request failures are logged before rethrowing instead of bypassing the error-logging path entirely. |
| B13 | `FolioConfig.php` | Declared `public string $name = '';` so the optional `name` config key no longer creates an undeclared dynamic property. |
| B14 | `FolioConfig.php` | Constructor now normalizes `sslVerify` to a real `bool` when it's a recognizable boolean-like string (`"true"`/`"false"`/`"yes"`/`"no"`/`"on"`/`"off"`/`"1"`/`"0"`/`""`) regardless of config source (array/object, not just INI), while leaving other strings untouched so a CA-bundle file path still works. Combined with the earlier `INI_SCANNER_TYPED` fix, this closes the issue for every config source. |
| B15 | `FolioDataExport.php` | `dataExport()` now checks `$profile->totalRecords == 0` and throws a clear "profile not found" exception, matching the guard `dataExportAll()` already had. |
| B16 | `FolioDataExport.php` | `dataExport()` now validates `file_exists($filename)` up front and sends `basename($filename)` (not the caller's local absolute path via `realpath()`) as the API-facing `fileName`. |
| B17 | `FolioDataExport.php` | Both `dataExport()` and `dataExportAll()` now check for a `FAIL` status right after the polling loop and throw a clear exception, instead of falling through to an undefined `$url` and a confusing `TypeError` in `getFile()`. |
| B18 | `FolioDataExport.php` | Added a `bool $verbose = false` constructor parameter (backward compatible — new trailing optional arg) so the dozen+ `($this->verbose) ? print ... : ''` branches are reachable instead of permanently dead. |
| B19 | `FolioFileHandler.php` | Deleted the meaningless `$tenant_id ??= $tenant_id;` self-assignment in `putFile()`. |
| B20 | `FolioFileHandler.php` | `putFile()`, `putFileX()`, and `getFile()` now open their file handles inside a `try`/`finally` that closes them (`is_resource()`-guarded) on every exit path, including exceptions. |
| B21 | `FolioFileHandler.php` | Header keys in `putFile()`/`putFileX()` changed from lowercase (`x-okapi-tenant`, `x-okapi-token`) to match `FolioClient::_buildRequestOptions()`'s capitalization (`X-Okapi-Tenant`, `X-Okapi-Token`) exactly, so tenant/token overrides can no longer end up duplicated across two differently-cased array keys. |
| B22 | `FolioFileHandler.php` | Removed the stale `// alias of putField` comment (no such method exists); the accurate docblock above `postFile()` already says "Alias of `putFile()`." |
| B23 | `FolioInformation.php` | `getHostname()` now throws a clear exception if `parse_url()` can't find a host in the configured Okapi URL, instead of passing `null` into `explode()` and silently misbehaving. |
| B24 | `FolioReferenceDataManager.php` | `getModules()` now handles both a raw JSON array of module descriptors and an enveloped `{"modules": [...]}` object, so it works regardless of which shape that endpoint actually returns (couldn't be verified against a live server). |
| B25 | `FolioReferenceDataManager.php` | `getCustomFieldObjects()`'s module-id lookup now takes `array_values($matches)[0]` instead of `implode('', $matches)`, so it can no longer produce a garbled module id when more than one module matches. |
| B26 | `FolioUtils.php` | Kept the strict v4/v5-only UUID check (per decision above); docblock now explicitly says it's FOLIO-specific and not a general-purpose validator. |
| B27 | `FolioUtils.php` | `isJson()` now checks `$string === null \|\| $string === ''` instead of `!$string`, so the valid JSON string `"0"` is no longer misclassified as not-JSON. |
| S2 | `FolioConfig.php`, `FolioAuth.php` | Both classes now implement their own `__debugInfo()` that redacts their actual `password`/`token` properties. (`FolioClient::__debugInfo()`'s existing no-op redaction — it doesn't own those properties — is unchanged and already documented as such.) |
| S3 | `FolioFileHandler.php` | Removed the hardcoded `'debug' => true` from `putFileX()`'s Guzzle options (per the "keep the method" decision above), which would otherwise have printed the session auth token to stdout/logs if the method were ever called. |
| S5 | `FolioLogger.php` | Log file now opens in append mode (`'a'`) instead of truncate mode (`'w'`), so prior log history survives across multiple `FolioLogger` instances against the same path. |
| D1 | `FolioAuth.php`, `FolioDataExport.php`, `FolioFileHandler.php` | All `catch (\Exception $e) { throw new \Exception("...: " . $e->getMessage()); }` sites now chain the original exception: `throw new \Exception($msg, 0, $e);`, preserving the original type/stack trace for debugging. |
| D3 | `FolioLogger.php` | Constructor gained an optional `string $timezone = 'America/Chicago'` parameter, so callers can pass `$config->localTimeZone` explicitly to keep log timestamps consistent with `FolioAuth`'s token-expiration timezone, without forcing the two classes to depend on each other. |
| D5 | `FolioReferenceDataManager.php` | Added a private `toIdMap(iterable $records, string $field): array` helper; every `getX()` method (locations, material types, loan types, departments, address types, patron groups, service points, custom field names/refIds) now calls it instead of repeating the same loop. |
| D6 | `FolioReferenceDataManager.php` | `getCustomFieldObjects()` now returns a `\Generator` (per decision above), consistent with every other `*Objects()` method. |
| D7 | `FolioClient.php`, `FolioFileHandler.php` | Removed: the stale "stuff left to do" TODO block; unused `use GuzzleHttp\Psr7\Request;` (both files) and `use GuzzleHttp\Utils;` imports; the dead `FolioClient::$central_tenant_id` property; large commented-out alternative-implementation blocks inside `putFileX()` and `FolioDataExport`'s two methods. |
| D8 | `FolioAuth.php`, `FolioClient.php`, `FolioConfig.php` | Added explicit return types to previously-untyped getters: `FolioAuth::getExpiration(): int`/`getAuthFlavor(): string`; `FolioClient::getConfig(): FolioConfig`/`getAuth(): FolioAuth`; `FolioConfig::getApiUrl(): string`/`getTenantId(): string`/`getCentralTenantId(): ?string`/`getUsername(): string`. |
| D9 | `FolioClient.php` | `getStatusCode()` now calls `$this->getLastStatusCode()` instead of duplicating its body, so there's a single source of truth. |
| D10 | `FolioClient.php` | `queryNum` now increments at the *start* of `_request()` (before logging) instead of in a `finally` block at the end, so `getLastQueryNum()` correctly reflects the most recently completed/in-flight request instead of the "next" one. |
| D11 | `FolioClient.php`, `FolioFileHandler.php`, `FolioReferenceDataManager.php` | `_request()` is now genuinely `private`. Added a new public `rawRequest()` wrapper, documented as the sanctioned low-level entry point for other library classes; `FolioFileHandler` and `FolioReferenceDataManager` were updated to call `rawRequest()` instead of reaching into `_request()` directly. |

All edited files were re-checked with `php -l` after each change (no syntax errors), and no
behavior outside the documented fixes above was changed.

---

## 0a. Feature added after the review (not a bug fix)

`FolioClient::_request()` now retries idempotent requests (`GET`/`PUT`/`DELETE`/`HEAD`) with
exponential backoff (plus jitter, honoring `Retry-After`) on connection failures, 5xx errors,
and HTTP 429. `POST`/`PATCH` are deliberately never auto-retried, since a lost response
doesn't guarantee the request wasn't already applied server-side. Configurable via two new
trailing, optional `FolioClient` constructor parameters (`$maxRetries` default `3`,
`$retryBaseDelayMs` default `200`); `maxRetries: 0` disables retries. This closes the
"no retry/backoff on transient failures" limitation previously called out in
[README.md](README.md). Verified with a `MockHandler`-based smoke test covering: retry+succeed
on repeated 503s, no retry on a 400, retry exhaustion after `maxRetries` attempts, no retry on
`POST` even for a 503, 429 with `Retry-After` retries and succeeds, and `ConnectException` retries.

## 0. Extra issues found while fixing the above

These weren't in the original 27-bug list — they turned up while touching the surrounding
code for other fixes, and were fixed in the same pass since they're the same class of bug
and low-risk to address:

- **🔴 `FolioClient::_handleParameters()` silently dropped every implicit CQL query
  whenever `$query` was `null`** — found while writing unit tests for the retry/backoff
  and B3 fixes. The final lines were:
  ```php
  $paramArray['query'] = $query ?? '';
  if (empty($paramArray['query'])) { unset($paramArray['query']); }
  ```
  This unconditionally overwrote whatever implicit query had just been built a few lines
  above (either the `'cql.allRecords=1 sortBy id'` GET default, or a UUID-derived
  `id="..."` filter) with an empty string whenever the caller didn't pass an explicit
  `$query` — which then got `unset()` for being empty. Before the B3 fix, this bug was
  unreachable for the UUID-string case (a `TypeError` fired first), which is likely why it
  went unnoticed. But it affected the **far more common path**: any `get()`/`getAll()`/
  `getAll_loop()` call made *without* an explicit `$query` argument (the normal way to
  request "all records") was silently sending no `query` parameter to FOLIO at all,
  instead of the intended `cql.allRecords=1 sortBy id` default. **Fixed** by only
  overwriting `$paramArray['query']` when `$query !== null`, so the implicit default (or
  UUID-derived filter) survives when no explicit query is given, exactly as the existing
  docblock already claimed. Covered by `FolioClientTest::testHandleParametersAcceptsUuidString()`
  and the passing GET-default behavior exercised throughout the rest of the suite.
- **`FolioConfig::$central_tenant_id` was an uninitialized typed property** (the same bug
  as B2) — calling `getCentralTenantId()` before the optional `central_tenant_id` config key
  was ever set would throw `Error: must not be accessed before initialization`. Now defaults to `null`.
- **`FolioReferenceDataManager::getModules()`/`getCustomFieldObjects()` referenced an
  undefined `$tenant` variable**: `$tenant ??= $tenant_id ?? $base_tenant_id;` — since
  `$tenant` was never previously assigned, this triggered a PHP warning on every call before
  falling through to the intended value anyway. Simplified to `$tenant = $tenant_id ?? $config->getTenantId();`.
- **`FolioDataExport::dataExportAll()` could throw "undefined array key 0"** on
  `$jobExecId = $reindexed[0];` if no newly-started job execution was detected (empty diff).
  Changed to `$reindexed[0] ?? null`, so the existing `if ($jobExecId) { ... } else { throw
  new \Exception("Export all failed"); }` guard below it now works as intended.
- **Both `dataExport()` and `dataExportAll()` had a silent "could not retrieve job or file
  id" fallback** that only printed (and only in verbose mode) before falling through to
  `getFile()` with an undefined `$url` — the same failure shape as B17, just for a different
  edge case (a terminal, non-`FAIL` job execution with no `exportedFiles[0]`). Changed to
  throw a clear exception instead of a silent print.

---

## 1. Bugs / correctness errors

### 🔴 B1 — Null logger crashes every request — ✅ FIXED
**File:** `FolioClient.php` (`_request()`, ~lines 318, 332)

```php
$this->logger->log("$method: $uri$queryString", $this->queryNum);
```

The constructor accepts `?FolioLogger $logger = null`, but `_request()` never null-checks
before calling `->log()`. Any consumer that builds a `FolioClient` without a logger gets a
fatal `Call to a member function log() on null` on the very first API call.

**Fix:** use `$this->logger?->log(...)`, or default to a no-op `FolioLogger` in the
constructor instead of `null`.

### 🔴 B2 — Uninitialized typed property crashes status accessors — ✅ FIXED
**File:** `FolioClient.php`, `private int $lastStatusCode;`

No default value. Calling `getLastStatusCode()`/`getStatusCode()` before any request throws
`Error: Typed property FolioClient::$lastStatusCode must not be accessed before initialization`.

**Fix:** initialize to `0` (or `-1`) at declaration.

### 🔴 B3 — `get()` can pass a non-array `$params` into a strictly-typed `array|null` parameter — ✅ FIXED
**File:** `FolioClient.php`, `get()` → `_request()`

`get()` accepts `mixed $params`, implying a JSON string or bare UUID string is acceptable
(and `_handleParameters()` has a `match` branch for exactly that), but `_request()` declared
`array|null $params`. Under `strict_types=1`, passing a string threw a `TypeError` before
`_handleParameters()` ever ran — the "pass a UUID string as `$params`" feature was dead code.

**Fix applied:** widened `_request()`, `rawRequest()`, and `_handleParameters()` to accept
`mixed $params`, matching what `_handleParameters()`'s `match(gettype($params))` actually normalizes.

### 🟡 B4 — `getEach()`'s return type doesn't match its documented behavior — ✅ FIXED
**File:** `FolioClient.php`, `getEach(): \Generator`

Its own docblock (copied from `get()`) said passing `FolioClient::RETURN_FULL_OBJECT` as
`$key` returns the full response object — but the declared return type was strictly
`\Generator`. Doing so threw `TypeError: Return value must be of type Generator, stdClass returned`.

**Fix applied:** `getEach()` now throws a clear `\InvalidArgumentException` up front if `$key`
loosely equals `RETURN_FULL_OBJECT`, instead of ever delegating in a way that could break its return type.

### 🔴 B5 — Unreindexed `array_diff` can return a `null` response key — ✅ FIXED
**File:** `FolioClient.php`, `_getResponseInfo()`

```php
$key = array_diff($arrayKeys, ['errors'])[0];
```

`array_diff()` preserves original keys. If `'errors'` happened to be element `0` of
`$arrayKeys`, the result had no index `0` — this silently evaluated to `null`, which then
got passed into `_yieldRecords(string $key)` and threw a `TypeError`.

**Fix applied:** `$key = array_values(array_diff($arrayKeys, ['errors']))[0] ?? null;`, with
`_yieldRecords()` and its callers now explicitly handling a `null` key as "no records."

### 🔴 B6 — No null-guard before `_getResponseInfo(stdClass $jsonObject)` — ✅ FIXED
**File:** `FolioClient.php`, `get()`/`getAll()`/`getAll_loop()`

`_request()` can return `null` (e.g. a 204 response), but `_getResponseInfo()`'s parameter is
strictly `stdClass`. Passing `null` threw a `TypeError` instead of being treated as "no records."

**Fix applied:** all three methods now short-circuit (`return`/`break`) on a `null` response
before calling `_getResponseInfo()`.

### ⚪ B7 — `_handleParameters()` computed twice per request — ✅ FIXED
**File:** `FolioClient.php` — computed once into a local variable (`$handledParams`) and reused.

### ⚪ B8 — Dead `while` condition in `getAll()` — ✅ FIXED
**File:** `FolioClient.php` — replaced the misleading `while ($responseInfo['totalRecords'] > 0)`
(a one-time snapshot that never changes) with `while (true)`, with a comment explaining that
pagination actually stops via the internal `break`.

### ⚪ B9 — Tautological ternary in `getAll()` — ✅ FIXED
**File:** `FolioClient.php` — simplified `$origQuery = (isset($query)) ? $query : $params['query'];`
to `$origQuery = $query;`.

### 🟡 B10 — `put()`'s docblock describes a different method — ✅ VERIFIED ALREADY FIXED
**File:** `FolioClient.php` — a previous documentation pass had already rewritten `put()`'s
docblock to accurately describe its single-record update contract; re-checked and confirmed
correct, no further change needed.

### 🟡 B11 — Broader `RequestException` subtypes aren't caught — ✅ FIXED
**File:** `FolioClient.php`, `_request()` — added a `catch (RequestException $e)` fallback
after the `ClientException|ServerException` and `ConnectException` catches, so any other
Guzzle request failure is logged (and rethrown) instead of bypassing the error-logging path.

### 🔴 B12 — INI config loading throws `TypeError` for `debug`/`timeout` — ✅ FIXED
**File:** `FolioConfig.php`, `loadFromIni()`

`parse_ini_file()` always returns strings. Assigning a string into strictly-typed
`bool $debug` / `int $timeout` properties threw `TypeError` whenever an INI file set either
key — INI-based config was effectively broken for those two.

**Fix applied:** `parse_ini_file($filePath, false, INI_SCANNER_TYPED)`.

### 🟡 B13 — `'name'` is in the optional-keys list but isn't a declared property — ✅ FIXED
**File:** `FolioConfig.php` — declared `public string $name = '';` (rather than removing the
key), since it's a plausible free-form label for distinguishing multiple configs and no
behavior depended on its absence.

### 🔴 B14 — `sslVerify` string coercion can silently defeat/break TLS verification — ✅ FIXED
**File:** `FolioConfig.php` / consumed in `FolioClient.php`, `FolioAuth.php`

INI's `sslVerify=false` became the **string** `""`, not boolean `false`; the same problem
existed for any array/object config source passing a boolean-like string. Guzzle only
disables verification for a literal `=== false`; a string is treated as a CA-bundle path
instead — behavior was undefined and likely to break rather than do what was intended.

**Fix applied:** the [B12](#-b12--ini-config-loading-throws-typeerror-for-debugtimeout--fixed)
fix (`INI_SCANNER_TYPED`) handles the INI-loading path. On top of that, the constructor now
normalizes `sslVerify` to a real `bool` whenever it's a recognizable boolean-like string
(`"true"/"false"/"yes"/"no"/"on"/"off"/"1"/"0"/""`, case-insensitive) from *any* config
source, while leaving other strings untouched so passing a real CA-bundle file path still works.

### 🟡 B15 — `dataExport()` doesn't validate the job-profile lookup (inconsistent with `dataExportAll()`) — ✅ FIXED
**File:** `FolioDataExport.php` — `dataExport()` now checks `$profile->totalRecords == 0` and
throws `"Export profile: '...' not found"`, matching the guard already present in `dataExportAll()`.

### 🟡 B16 — Local absolute filesystem path sent to the remote API as `fileName` — ✅ FIXED
**File:** `FolioDataExport.php` — now validates `file_exists($filename)` up front (throwing a
clear error if missing) and sends `basename($filename)` instead of `realpath($filename)` as
the API-facing `fileName`.

### 🔴 B17 — Failed export jobs crash with an unrelated `TypeError` instead of a clear error — ✅ FIXED
**File:** `FolioDataExport.php`, `dataExport()` and `dataExportAll()`

The `FAIL`-status short-circuit was commented out. On a failed job, `$fileId` was falsy, the
`else` branch ran (only a `print`, and only if `verbose`), and `$url` was **never assigned**.
Execution fell through to `getFile($path, $url, ...)`, whose `$url` parameter is non-nullable
`string` — this threw a confusing `TypeError` instead of the purpose-built exception sitting
right there, commented out. Same bug in both methods.

**Fix applied:** both methods now check `if ($status == 'FAIL') { throw ...; }` right after
the polling loop, before ever reaching the file-download step.

### 🟡 B18 — `$verbose` can never be turned on — all verbose branches are dead code — ✅ FIXED
**File:** `FolioDataExport.php` — added `bool $verbose = false` as a new (trailing, optional,
backward-compatible) constructor parameter, so the existing `($this->verbose) ? print ... : ''`
branches are now reachable.

### ⚪ B19 — Meaningless self-assignment — ✅ FIXED
**File:** `FolioFileHandler.php`, `putFile()` — deleted `$tenant_id ??= $tenant_id;`.

### 🟡 B20 — File handles are never closed — ✅ FIXED
**File:** `FolioFileHandler.php` — `putFile()`, `putFileX()`, and `getFile()` now wrap their
work in `try { ... } finally { if (is_resource($fh)) fclose($fh); }`.

### 🔴 B21 — Case-sensitive header keys silently create duplicate/conflicting headers — ✅ FIXED
**File:** `FolioFileHandler.php` — header keys changed from lowercase (`x-okapi-tenant`,
`x-okapi-token`) to match `FolioClient::_buildRequestOptions()`'s capitalization exactly
(`X-Okapi-Tenant`, `X-Okapi-Token`), so `array_replace()`-based header merging can no longer
treat them as two different keys.

### ⚪ B22 — Stale comment references a nonexistent method — ✅ FIXED
**File:** `FolioFileHandler.php` — removed the stale `// alias of putField` comment.

### 🟡 B23 — `getHostname()` doesn't guard against `parse_url()` returning `null` — ✅ FIXED
**File:** `FolioInformation.php` — now throws a clear exception if no host can be parsed from
the configured Okapi URL, instead of passing `null` into `explode()`.

### 🟡 B24 — `getModules()` assumes a non-enveloped array response (verify against real API) — ✅ FIXED (defensively)
**File:** `FolioReferenceDataManager.php` — since the actual response shape couldn't be
verified against a live FOLIO instance, `getModules()` now handles both a raw JSON array and
an enveloped `{"modules": [...]}` object, so it's correct either way.

### 🟡 B25 — `implode('', $matches)` can corrupt the module id — ✅ FIXED
**File:** `FolioReferenceDataManager.php`, `getCustomFieldObjects()` — now takes
`array_values($matches)[0]` instead of concatenating every match together.

### 🟡 B26 — `isValidUuid()` rejects legitimate non-v4/v5 UUIDs — ✅ RESOLVED (kept strict, by decision)
**File:** `FolioUtils.php` — per an explicit decision (this only matters for ids FOLIO itself
generates, which are always v4), the strict v4/v5-only check was kept as-is. The docblock now
says explicitly that this is a FOLIO-specific check, not a general-purpose UUID validator, so
it isn't mistakenly reused elsewhere for that purpose.

### ⚪ B27 — `isJson()` misclassifies the string `"0"` as not-JSON — ✅ FIXED
**File:** `FolioUtils.php` — changed the guard to `$string === null || $string === ''`.

---

## 2. Security issues

### 🔴 S1 — Enabling debug mode leaks the plaintext username/password — ✅ FIXED
**File:** `FolioAuth.php`, `refreshTokens()` — `config->debug` is no longer forwarded into the
Guzzle client used for the `/authn/login` POST.

**Note:** as a consequence, `FolioConfig::$debug` is now unused by this library entirely (it
was never wired into `FolioClient`'s main Guzzle client either) — see [D2](#d2) below.

### 🔴 S2 — `__debugInfo()` redacts properties that don't exist on the object it's defined on — ✅ FIXED
**File:** `FolioClient.php` (existing no-op documented, unchanged), `FolioConfig.php`, `FolioAuth.php` (new)

`FolioClient::__debugInfo()` unsets `password`/`token`/etc., but `FolioClient` itself never
had those properties — they live on nested `FolioConfig`/`FolioAuth`, so a `var_dump()` of a
`FolioClient` fully exposed them.

**Fix applied:** `FolioConfig` and `FolioAuth` now each implement their own `__debugInfo()`
that redacts their actual `password`/`token` property. `var_dump($folioClient)` still shows
`FolioConfig`/`FolioAuth` as nested objects, but PHP calls each nested object's own
`__debugInfo()` when dumping it, so the redaction now actually takes effect.

### 🟡 S3 — Hardcoded `'debug' => true` in dormant code would leak the session token — ✅ FIXED
**File:** `FolioFileHandler.php`, `putFileX()` — per decision, the method was kept (nothing
calls it currently, but it may be useful later), and the hardcoded `'debug' => true` was removed.

### ⚪ S4 — Full request URIs (including CQL query strings) are logged unredacted — OPEN (documented limitation)
**File:** `FolioClient.php`, `_request()` — no code change: there's no reliable general way to
tell "this query parameter is PII" from "this query parameter is safe" without
endpoint-specific knowledge, so a generic redaction would either under- or over-redact.
Documented in [README.md](README.md) as a caveat: treat log files as potentially containing PII.

### ⚪ S5 — Log file is opened in truncate mode, destroying prior history — ✅ FIXED
**File:** `FolioLogger.php` — now opens in append mode (`'a'`) instead of truncate mode (`'w'`).

*No SQL injection, command injection, path traversal, or insecure deserialization vectors were
found — the library only issues HTTP requests via Guzzle. `getFile()`/`putFile()` take
caller-supplied local file paths without sanitization; this is a caveat for downstream
consumers (don't pass untrusted input as a path) rather than a bug in the library itself.*

---

## 3. Design / consistency issues

- **D1 — No exception chaining. — ✅ FIXED** `FolioAuth`, `FolioDataExport`, `FolioFileHandler`
  all now use `throw new \Exception($msg, 0, $e);` instead of discarding the original exception.
- <a name="d2"></a>**D2 — Two unrelated "debug" flags share a name. — Documentation-only, left as-is.**
  `FolioConfig::$debug` and `FolioLogger::$debug` are independent by design: `FolioLogger`
  is a standalone class with no dependency on `FolioConfig` (by design — see its class
  docblock), so there's no code-level way to "unify" them without coupling the two classes
  together, which would be a bigger architectural change than this fix warrants. After the
  S1 fix, `FolioConfig::$debug` is no longer consumed by *any* code in this library — it's
  available for application code to read for its own purposes, but toggling it has no effect
  here. This is now called out explicitly in both classes' docblocks and in [README.md](README.md).
- **D3 — Timezone handling is duplicated. — ✅ FIXED** `FolioLogger`'s constructor now accepts
  an optional `$timezone` parameter (defaulting to `'America/Chicago'`, unchanged); pass
  `$config->localTimeZone` when constructing it to keep log timestamps consistent with
  `FolioAuth`'s token-expiration timezone.
- **D4 — Inconsistent getter vs. direct-property access. — Documentation-only, left as-is.**
  `FolioConfig`'s properties are all `public`, so its `getApiUrl()`/`getTenantId()`/etc.
  getters provide no real encapsulation — they exist for interface parity with other classes
  in the library (like `FolioInformation`) that wrap it. Making the properties `private` to
  force getter usage would be a breaking change for every other class in this library, all of
  which read `FolioConfig`'s properties directly (`FolioAuth`, `FolioClient`,
  `FolioDataExport`, `FolioFileHandler`, `FolioInformation`). Not worth the churn for a
  purely stylistic inconsistency; documented instead.
- **D5 — Duplicated boilerplate in `FolioReferenceDataManager`. — ✅ FIXED** Added a private
  `toIdMap()` helper; every plain `getX()` method now calls it instead of repeating the loop.
- **D6 — `getCustomFieldObjects()` breaks the `*Objects()` naming convention. — ✅ FIXED**
  Converted to return a `\Generator`, per decision.
- **D7 — Dead code. — ✅ FIXED** Removed the stale TODO block in `FolioClient.php`; unused
  `GuzzleHttp\Psr7\Request`/`GuzzleHttp\Utils` imports; the dead `FolioClient::$central_tenant_id`,
  `FolioInformation::$lastStatusCode`/`$lastQuery`/`$queryNum` properties; large commented-out
  alternative-implementation blocks in `putFileX()` and `FolioDataExport`.
- **D8 — Missing return type hints. — ✅ FIXED** Added explicit return types to every getter
  called out in the original finding.
- **D9 — Redundant duplicate method. — ✅ FIXED** `getStatusCode()` now delegates to `getLastStatusCode()`.
- **D10 — `queryNum` is off-by-one relative to its name. — ✅ FIXED** Now incremented at the
  start of `_request()` instead of in a trailing `finally` block.
- **D11 — `_request()` is `public` despite the underscore convention. — ✅ FIXED** Made
  genuinely `private`; added a public `rawRequest()` wrapper as the real sanctioned entry
  point for `FolioFileHandler`/`FolioReferenceDataManager`.
- **D12 — No PHPDoc anywhere in the codebase. — ✅ FIXED** (in an earlier documentation pass;
  all 9 files now have full `@param`/`@return`/`@throws` docblocks on every class and method.)

---

## 4. Suggested priority order (historical — all resolved)

This section is kept for history; every item it originally called out has been fixed as of
this pass. See the changelog at the top of this document for what changed and why.
