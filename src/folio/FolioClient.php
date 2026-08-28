<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use stdClass;

/**
 * Main entry point for talking to the FOLIO Okapi/gateway API.
 *
 * Wraps a Guzzle HTTP client together with a {@see FolioAuth} instance
 * (for bearer tokens), a {@see FolioConfig} instance (for connection
 * settings), a {@see FolioLogger} (optional request logging), and a
 * {@see FolioUtils} instance (parameter/UUID helpers). Provides generic
 * CRUD-style methods (`get`, `getOne`, `getAll`, `getAll_loop`, `put`,
 * `patch`, `post`, `delete`) that other classes in this library build on
 * top of: {@see FolioFileHandler} and {@see FolioReferenceDataManager}
 * call `rawRequest()` for lower-level access, {@see FolioDataExport}
 * composes several `get`/`post` calls, and {@see FolioInformation} is
 * constructed and exposed via `getInformation()`.
 *
 * Requests made with an idempotent HTTP method (`GET`/`PUT`/`DELETE`/`HEAD`)
 * are automatically retried with exponential backoff on connection
 * failures, 5xx server errors, and HTTP 429 (rate limiting) — see
 * `_request()`, and the `$maxRetries`/`$retryBaseDelayMs` constructor
 * parameters to configure it. `POST`/`PATCH` requests are never
 * auto-retried, since a lost response doesn't guarantee the request
 * itself was never applied server-side.
 */
class FolioClient {
    public const VERSION = '2.0.8';
    public const RETURN_FULL_OBJECT = -1;

    private FolioConfig $config;
    private FolioAuth $auth;
    private ?FolioLogger $logger;
    private Client $httpClient;
    private FolioUtils $folioUtils;
    private FolioInformation $information;

    private int $lastStatusCode = 0;
    private string $lastQuery = '';

    private int $queryNum = 0;

    private int $getAllDefaultLimit = 5000;

    private int $maxRetries;
    private int $retryBaseDelayMs;

    /** HTTP methods considered safe to automatically retry: idempotent
     *  operations only. `POST`/`PATCH` are deliberately excluded, since
     *  retrying them after a transient failure whose response was lost
     *  (rather than never sent) risks duplicate side effects — e.g.
     *  creating the same record twice. */
    private const RETRYABLE_METHODS = ['GET', 'PUT', 'DELETE', 'HEAD'];

    /**
     * Build a FOLIO client, wiring up its collaborators.
     *
     * @param $config      Connection settings (Okapi URL, tenant id, timeout,
     *                     TLS verification, etc.).
     * @param $auth        Authenticator used to obtain bearer tokens for requests.
     * @param $folioUtils  Helper used for UUID/JSON detection when building
     *                     request parameters.
     * @param $logger      Optional logger for outgoing requests; when null,
     *                     `_request()` simply skips logging.
     * @param $information Optional pre-built information accessor; when
     *                     null, a new {@see FolioInformation} is created
     *                     from `$config`/`$auth`.
     * @param $httpClient  Optional pre-configured Guzzle client (useful for
     *                     tests); when null, one is created from `$config`.
     * @param $maxRetries      Number of times to retry a request that fails
     *                         with a transient error (connection failure,
     *                         5xx server error, or HTTP 429) before giving
     *                         up and letting the exception propagate. Only
     *                         applied to idempotent methods — see
     *                         {@see RETRYABLE_METHODS}. `0` disables retries.
     * @param $retryBaseDelayMs Base delay (in milliseconds) for the
     *                         exponential backoff between retries; doubles
     *                         after each attempt and has random jitter
     *                         added, unless the server sent a `Retry-After`
     *                         header, which takes precedence.
     */
    public function __construct(
        FolioConfig $config,
        FolioAuth $auth,
        FolioUtils $folioUtils,
        ?FolioLogger $logger = null,
        ?FolioInformation $information = null,
        ?Client $httpClient = null,
        int $maxRetries = 3,
        int $retryBaseDelayMs = 200,
    ) {
        $this->config = $config;
        $this->auth = $auth;
        $this->folioUtils = $folioUtils;
        $this->logger = $logger;
        $this->information = $information ?: new FolioInformation($config, $auth);
        $this->maxRetries = $maxRetries;
        $this->retryBaseDelayMs = $retryBaseDelayMs;

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->config->okapiUrl,
            'timeout'  => $this->config->timeout,
            'verify'   => $this->config->sslVerify,
        ]);
    }

    /**
     * Get the configuration object this client was constructed with.
     *
     * @return The client's {@see FolioConfig} instance.
     */
    public function getConfig(): FolioConfig {
        return $this->config;
    }

    /**
     * Get the authenticator object this client was constructed with.
     *
     * @return The client's {@see FolioAuth} instance.
     */
    public function getAuth(): FolioAuth {
        return $this->auth;
    }

    /**
     * Perform a single-page GET request against a FOLIO endpoint.
     *
     * Intended for small data sets that fit comfortably within one page
     * (no automatic pagination is performed).
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $query     CQL query string, or null to use the default
     *                   (`cql.allRecords=1`).
     * @param $params    Array (or array-like value) of CQL parameters
     *                   such as `offset`/`limit`.
     * @param $key       Name of the array property in the JSON response
     *                   that holds the records. If omitted/null, the key
     *                   is inferred from the response. If set to
     *                   `self::RETURN_FULL_OBJECT`, the full raw response
     *                   object is returned instead (useful when you need
     *                   metadata like `totalRecords`).
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Either the full raw decoded response object (when
     *         `$key === self::RETURN_FULL_OBJECT`), or a `\Generator`
     *         that yields the matching records one at a time (yields
     *         nothing if the response is empty or has no records key).
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function get(string $endpoint, ?string $query = null, mixed $params = null, string|int|null $key = null, ?string $tenant_id = null): mixed {
        // get data
        $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
        if ($key == self::RETURN_FULL_OBJECT) {     //return full object
            return $response;
        }
        if ($response === null) {
            return $this->_yieldRecords(null, null);
        }

        // get implicit key and total records
        $responseInfo = $this->_getResponseInfo($response);
        $key ??= $responseInfo['key'];

        return $this->_yieldRecords($response, $key);   //return generator
    }

    /**
     * Fetch a single record by its UUID.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $id        UUID of the record of interest.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return The decoded record object, or null if not found.
     * @throws \Exception If `$id` is not a valid UUID (per
     *                     {@see FolioUtils::isValidUuid()}).
     */
    public function getOne(string $endpoint, string $id, ?string $tenant_id = null): null|stdClass {
        if($this->folioUtils->isValidUuid($id)){
            $response = $this->get("$endpoint/$id",null,null,self::RETURN_FULL_OBJECT,$tenant_id);
            return $response;
        }else{
            throw new \Exception("getOne must be passed a valid UUID");
        }
    }

    /**
     * Fetch matching records one at a time via {@see get()}, guaranteeing
     * a `\Generator` is always returned.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $query     CQL query string, or null to use the default.
     * @param $params    Array (or array-like value) of CQL parameters
     *                   such as `offset`/`limit`.
     * @param $key       Name of the array property in the JSON response
     *                   that holds the records, or null to infer it from
     *                   the response. Unlike {@see get()}, this method
     *                   always yields records one at a time, so
     *                   `self::RETURN_FULL_OBJECT` is not accepted here.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each matching record in turn.
     * @throws \InvalidArgumentException If `$key` is (or loosely equals)
     *                     `self::RETURN_FULL_OBJECT`; use {@see get()}
     *                     directly if you need the full response object.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function getEach(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key = null, ?string $tenant_id = null): \Generator {
        if ($key == self::RETURN_FULL_OBJECT) {
            throw new \InvalidArgumentException('getEach() always returns a Generator of records; call get() directly if you need self::RETURN_FULL_OBJECT.');
        }
        return $this->get($endpoint, $query, $params, $key, $tenant_id);
    }

    /**
     * Retrieve all records matching a query by paginating with an
     * offset/limit cursor.
     *
     * Intended for small to midsize data sets where offset/limit
     * pagination is required (e.g. when `id > cursor` pagination, as
     * used by {@see getAll()}, is not viable for the endpoint). This
     * approach is not optimized for speed: each page re-scans from the
     * growing offset, which gets more expensive as the offset increases.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $query     CQL query string, or null to use the default
     *                   (`cql.allRecords=1`); `sortBy id` is always appended.
     * @param $params    Array (or array-like value) of CQL parameters;
     *                   `offset` and `limit` default to 0 and the
     *                   client's default limit respectively, and are
     *                   advanced automatically between pages.
     * @param $key       Name of the array property in the JSON response
     *                   that holds the records, or null to infer it from
     *                   the response.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each matching record in turn,
     *         across all pages (yields nothing once a page comes back
     *         empty, has no records key, or the server returns no body).
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function getAll_loop(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key = null, ?string $tenant_id = null)  {
                $query = ($query ?? 'cql.allRecords=1') . ' sortBy id';     //set initial query
                $params = (array)$params ?: [];
                $params['offset'] = $params['offset'] ?? 0;
                $params['limit'] = $params['limit'] ?? $this->getAllDefaultLimit;

        do {
            // get data
            $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
            if ($response === null) {
                break;
            }
            // get implicit key and total records
            $responseInfo = $this->_getResponseInfo($response);
            $key ??= $responseInfo['key'];
            if ($key === null || empty($response->$key)) {
                break;
            }

            foreach ($response->$key as $result) {
                yield $result;
            }

            if ($params['offset'] + count($response->$key) >= $responseInfo['totalRecords']) {
                break;
            }
            $params['offset'] += $params['limit'];
        } while (true);
    }

    /**
     * Retrieve all records matching a query by paginating with an
     * `id > cursor` CQL filter.
     *
     * Intended for midsize to large data sets; optimized for speed
     * because each subsequent page is fetched with a `id > "<last id>"`
     * filter (records are sorted by id) rather than an ever-growing
     * offset. Use {@see getAll_loop()} instead for endpoints where this
     * cursor-based approach isn't viable.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $query     CQL query string, or null to use the default
     *                   (`cql.allRecords=1`); `sortBy id` is always appended.
     * @param $params    Array (or array-like value) of CQL parameters
     *                   such as `offset`/`limit`.
     * @param $key       Name of the array property in the JSON response
     *                   that holds the records, or null to infer it from
     *                   the response.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each matching record in turn,
     *         across all pages. Returns nothing (yields no records) if
     *         the first page comes back empty, has no records key, or
     *         the server returns no body.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function getAll(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key = null, ?string $tenant_id = null)  {
        $query = ($query ?? 'cql.allRecords=1') . ' sortBy id';     //set initial query
        $origQuery = $query;

        $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);     // get first response
        if ($response === null) {
            return;
        }

        $responseInfo = $this->_getResponseInfo($response);
        $key ??= $responseInfo['key'];
        if ($key === null) {
            return;
        }

        $records = $response->{$key};
        if (empty($records)) {
            return;
        }
        $end = end($records)->id;

        foreach ($records as $record) {
            yield $record;
        }

        // get subsequent batches; termination is via the break below once
        // a page comes back empty (see B8 in ISSUES.md: totalRecords is a
        // one-time snapshot from the first page, not a live loop condition).
        while (true) {
            $query = 'id > "' . $end . '" and ' . $origQuery;
            $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
            if ($response === null || empty($response->{$key})) {
                break;
            }
            $records = $response->{$key};
            $count = count($records);
            $end = $records[$count - 1]->id;
            foreach ($records as $result) {
                yield $result;
            }
        }
    }

    /**
     * Update a record with a PUT request.
     *
     * Unlike {@see get()}, this addresses a single record directly (by
     * appending `$id` to the endpoint) and does not expect/return the
     * usual paginated response structure.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $id        UUID of the record to update; appended to the
     *                   endpoint path when given.
     * @param $params    The record data to send, as an array, object, or
     *                   JSON string; normalized to an array for the
     *                   request body.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function put(string $endpoint, ?string $id = null, mixed $params = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $json = is_object($params) ? (array) $params : (is_string($params) ? json_decode($params, true) : $params);

        $options = [
            'json' => $json,
            'headers' => ['Accept' => 'text/plain']
        ];

        $this->_request('PUT', $endpoint, null, [], $tenant_id, $options);
    }

    /**
     * Update a record with a PATCH request.
     *
     * Note: only a very small number of FOLIO endpoints actually support
     * PATCH; most records must be updated via {@see put()} instead.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $id        UUID of the record to update; appended to the
     *                   endpoint path when given.
     * @param $params    The partial record data to send, as an array,
     *                   object, or JSON string; normalized to an array
     *                   for the request body.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function patch(string $endpoint, ?string $id = null, array|object|null $params = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $json = is_object($params) ? (array) $params : (is_string($params) ? json_decode($params, true) : $params);

        $options = [
            'json' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ];
    
        $this->_request('PATCH', $endpoint, null, [], $tenant_id, $options);
    }

    /**
     * Create a record (or trigger an action) with a POST request.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $params    Properly-formed request payload: an array,
     *                   object, or JSON string; normalized to an array
     *                   for the request body.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @param $options   Extra Guzzle request options merged over (and
     *                   taking precedence over) the default `json`/
     *                   `headers` options.
     * @return The decoded response object, or null if the response body
     *         is empty/not valid JSON.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function post(string $endpoint, mixed $params = null, ?string $tenant_id = null,?array $options = null): ?object {
        $json = is_object($params) ? (array) $params : (is_string($params) ? json_decode($params, true) : $params);

        $defaultOptions = [
            'json' => $json,
            'headers' => ['Accept' => 'text/plain', 'Content-Type' => 'application/json']
        ];

        $options = array_replace_recursive($defaultOptions, $options ?? []);

        return $this->_request('POST', $endpoint, null , [], $tenant_id, $options);

    }

    /**
     * Create or update a record, keyed by its own `id` field: {@see put()}
     * if a record with that id already exists, {@see post()} if it doesn't.
     *
     * Existence is determined with a single-record `GET $endpoint/$id`
     * (the same lookup {@see getOne()} performs) — one HTTP round trip
     * to decide, then one more to write, which is the minimum possible
     * for a client that can't assume either outcome ahead of time. A 404
     * on the lookup means "doesn't exist yet"; any other error propagates
     * unchanged.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $params    The record data to send, as an array, object, or
     *                   JSON string; must include a valid UUID `id` field
     *                   (per {@see FolioUtils::isValidUuid()}) — unlike
     *                   {@see post()}, `upsert()` has to know the id
     *                   up front to check for an existing record.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @throws \Exception If `$params` has no `id` field, or it isn't a valid UUID.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response
     *                     (other than the 404 used to detect a missing record).
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function upsert(string $endpoint, mixed $params, ?string $tenant_id = null): void {
        $body = is_object($params) ? (array) $params : (is_string($params) ? json_decode($params, true) : $params);
        $id = $body['id'] ?? null;
        if (!is_string($id) || !$this->folioUtils->isValidUuid($id)) {
            throw new \Exception("upsert requires \$params to have a valid UUID 'id' field");
        }

        $exists = true;
        try {
            $this->get("$endpoint/$id", null, null, self::RETURN_FULL_OBJECT, $tenant_id);
        } catch (ClientException $e) {
            if ($e->getResponse()?->getStatusCode() !== 404) {
                throw $e;
            }
            $exists = false;
        }

        $exists
            ? $this->put($endpoint, $id, $params, $tenant_id)
            : $this->post($endpoint, $params, $tenant_id);
    }

    /**
     * Delete a record with a DELETE request.
     *
     * Use with extreme caution: if called without `$id`, some FOLIO
     * endpoints will delete every record at that endpoint rather than
     * erroring out.
     *
     * @param $endpoint  API endpoint to call (required).
     * @param $id        UUID of the record to delete; appended to the
     *                   endpoint path when given.
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the underlying HTTP request returns a 4xx/5xx response.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     */
    public function delete(string $endpoint, ?string $id = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $options = [
            'headers' => ['Accept' => 'text/plain']
        ];

        $this->_request('DELETE', $endpoint, null, [], $tenant_id, $options);
    }

    /**
     * Low-level request executor: builds the URI/query string and
     * headers, performs the HTTP call via Guzzle, logs the outcome, and
     * returns the decoded JSON body.
     *
     * This is a genuinely internal method — all of `get()`, `put()`,
     * `patch()`, `post()`, and `delete()` funnel through it. Other classes
     * in this library that need lower-level access ({@see FolioFileHandler},
     * {@see FolioReferenceDataManager}) must go through the public
     * {@see rawRequest()} wrapper instead of calling this directly.
     *
     * @param $method    HTTP method to use (e.g. `GET`, `POST`, `PUT`,
     *                   `PATCH`, `DELETE`); normalized to uppercase.
     * @param $endpoint  API endpoint to call.
     * @param $query     CQL query string, or null.
     * @param $params    CQL/query parameters (offset, limit, etc.), as an
     *                   array, object, JSON string, or bare UUID string —
     *                   see {@see _handleParameters()} for how each form
     *                   is normalized.
     * @param $tenant_id Tenant id to send as the `X-Okapi-Tenant` header,
     *                   for ECS (consortial) environments; null uses the
     *                   client's default tenant.
     * @param $options   Extra Guzzle request options (e.g. `headers`,
     *                   `json`, `body`) merged with the default auth/tenant headers.
     * @return The JSON-decoded response body (object/array), or null if
     *         the body is empty or not valid JSON.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the response status is 4xx/5xx and either the
     *                     method isn't retryable, the status isn't a
     *                     retryable one (429 for 4xx; any 5xx is retryable),
     *                     or `$maxRetries` attempts have already been used
     *                     (logged, then rethrown).
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot
     *                     connect to the server and retries (if any) are
     *                     exhausted (logged, then rethrown).
     * @throws \GuzzleHttp\Exception\RequestException For any other Guzzle
     *                     request failure not covered above (not retried;
     *                     logged, then rethrown).
     */
    private function _request(string $method, string $endpoint, ?string $query, mixed $params = [], ?string $tenant_id = null, array|null $options = []): array|object|null {
        $method = strtoupper($method);
        $uri = trim($endpoint, "/ \t\r\n\0");

        $handledParams = $this->_handleParameters($endpoint,$method, $params, $query);
        $queryString = !empty($handledParams) ? '?' . http_build_query($handledParams) : '';

        // Merge headers with defaults
        $finalOptions = $this->_buildRequestOptions($options);

        $this->queryNum++;
        $this->lastQuery = "{$method}: {$uri}";

        $canRetry = in_array($method, self::RETRYABLE_METHODS, true);
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->logger?->log(
                "$method: $uri$queryString" . ($attempt > 1 ? " (attempt $attempt)" : ''),
                $this->queryNum
            );
            try {
                $response = $this->httpClient->request($method, $uri . $queryString, $finalOptions);
                $this->lastStatusCode = $response->getStatusCode();

                if ($response->hasHeader('Content-Length')) {
                    // getHeader() returns an array; access index 0 for the value
                    $len = (int) $response->getHeader('Content-Length')[0];
                    $contentLength = "Content Length: " . $len;
                } else {
                    $contentLength =  "";
                }

                $this->logger?->log("Status code: " . $this->lastStatusCode,$this->queryNum,$contentLength);

                return json_decode((string)$response->getBody()->getContents(), false);
            } catch (ClientException $e) {
                // Only HTTP 429 (rate limiting) is treated as transient among 4xx responses.
                $isRateLimited = $e->getResponse()?->getStatusCode() === 429;
                if ($canRetry && $isRateLimited && $this->_shouldRetry($attempt)) {
                    $this->logger?->log("Rate limited (429) on {$this->lastQuery}, will retry: " . $e->getMessage(),$this->queryNum);
                    $this->_backoffSleep($attempt, $e->getResponse());
                    continue;
                }
                $this->logger?->log("HTTP error on {$this->lastQuery}: " . $e->getMessage(),$this->queryNum);
                throw $e;
            } catch (ServerException $e) {
                if ($canRetry && $this->_shouldRetry($attempt)) {
                    $this->logger?->log("Server error on {$this->lastQuery}, will retry: " . $e->getMessage(),$this->queryNum);
                    $this->_backoffSleep($attempt, $e->getResponse());
                    continue;
                }
                $this->logger?->log("HTTP error on {$this->lastQuery}: " . $e->getMessage(),$this->queryNum);
                throw $e;
            } catch (ConnectException $e) {
                if ($canRetry && $this->_shouldRetry($attempt)) {
                    $this->logger?->log("Connection error on {$this->lastQuery}, will retry: " . $e->getMessage(),$this->queryNum);
                    $this->_backoffSleep($attempt);
                    continue;
                }
                $this->logger?->log("Connection error on {$this->lastQuery}: " . $e->getMessage(),$this->queryNum);
                throw $e;
            } catch (RequestException $e) {
                // Not retried: covers failure modes (e.g. too many redirects)
                // that aren't simple transient connectivity/server errors.
                $this->logger?->log("Request error on {$this->lastQuery}: " . $e->getMessage(),$this->queryNum);
                throw $e;
            }
        }
    }

    /**
     * Determine whether another retry attempt is still within budget.
     *
     * @param $attempt Number of attempts already made (1 for the initial,
     *                 non-retry attempt).
     * @return True if `$attempt` has not yet exhausted `$maxRetries`.
     */
    private function _shouldRetry(int $attempt): bool {
        return $attempt <= $this->maxRetries;
    }

    /**
     * Sleep for an exponentially-increasing (plus jitter) backoff delay
     * before the next retry attempt.
     *
     * Honors the response's `Retry-After` header (seconds) when present,
     * since that reflects the server's own guidance and should take
     * precedence over a guessed backoff delay.
     *
     * @param $attempt  Number of attempts already made; used to compute
     *                  the exponential delay (`retryBaseDelayMs * 2^(attempt-1)`).
     * @param $response The failed response, if any (used to read `Retry-After`).
     */
    private function _backoffSleep(int $attempt, ?\Psr\Http\Message\ResponseInterface $response = null): void {
        $retryAfterSeconds = $response?->hasHeader('Retry-After') ? (float) $response->getHeaderLine('Retry-After') : null;

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $delayMs = $retryAfterSeconds * 1000;
        } else {
            $delayMs = $this->retryBaseDelayMs * (2 ** ($attempt - 1));
            $delayMs += random_int(0, (int) ($delayMs / 2)); // up to 50% jitter
        }

        usleep((int) ($delayMs * 1000));
    }

    /**
     * Public low-level request entry point for other classes in this
     * library that need direct HTTP access beyond `get()`/`put()`/
     * `patch()`/`post()`/`delete()` — currently {@see FolioFileHandler}
     * (file upload/download) and {@see FolioReferenceDataManager} (custom
     * field module lookup). General application code should use the CRUD
     * methods above instead of calling this directly. Subject to the same
     * retry/backoff behavior as those methods (see `_request()`).
     *
     * @param $method    HTTP method to use (e.g. `GET`, `POST`, `PUT`,
     *                   `PATCH`, `DELETE`); normalized to uppercase.
     * @param $endpoint  API endpoint to call.
     * @param $query     CQL query string, or null.
     * @param $params    CQL/query parameters (offset, limit, etc.), as an
     *                   array, object, JSON string, or bare UUID string.
     * @param $tenant_id Tenant id to send as the `X-Okapi-Tenant` header,
     *                   for ECS (consortial) environments; null uses the
     *                   client's default tenant.
     * @param $options   Extra Guzzle request options (e.g. `headers`,
     *                   `json`, `body`) merged with the default auth/tenant headers.
     * @return The JSON-decoded response body (object/array), or null if
     *         the body is empty or not valid JSON.
     * @throws \GuzzleHttp\Exception\ClientException|\GuzzleHttp\Exception\ServerException
     *                     If the response status is 4xx/5xx.
     * @throws \GuzzleHttp\Exception\ConnectException If the request cannot connect.
     * @throws \GuzzleHttp\Exception\RequestException For any other Guzzle request failure.
     */
    public function rawRequest(string $method, string $endpoint, ?string $query = null, mixed $params = [], ?string $tenant_id = null, array|null $options = []): array|object|null {
        return $this->_request($method, $endpoint, $query, $params, $tenant_id, $options);
    }

    /**
     * Merge caller-supplied Guzzle request options with the default
     * tenant/auth/accept headers.
     *
     * @param $options Caller-supplied Guzzle options (may include a
     *                 `headers` sub-array that overrides individual default headers).
     * @return The merged Guzzle options array, with `headers` containing
     *         the default `X-Okapi-Tenant`, `X-Okapi-Token`, and `Accept`
     *         values overridden by any caller-supplied headers of the same name.
     */
    private function _buildRequestOptions(array|null $options = []): array {
        $defaultHeaders = [
            'X-Okapi-Tenant' => $this->config->tenant_id,
            'X-Okapi-Token'  => $this->auth->getAccessToken(),
            'Accept'         => 'application/json',
        ];
        
        $options ??= [];
        $customHeaders = $options['headers'] ?? [];
        unset($options['headers']);
        
        return array_replace(
            ['headers' => $defaultHeaders],
            $options,
            ['headers' => array_replace($defaultHeaders, $customHeaders)]
        );
    }

    // Utility functions
    /**
     * Normalize caller-supplied parameters into a CQL parameter array,
     * applying method-specific defaults.
     *
     * Accepts an array, object (cast to array), or string (parsed as
     * JSON, or as a UUID-based `id=` query if it looks like a UUID) and
     * normalizes it to an array. For `GET` requests against endpoints in
     * the internal defaults list, fills in default `limit`/`offset`/`query`
     * values when not already present. The explicit `$query` argument, when
     * given, always overrides any implicit `query` key, for every endpoint.
     *
     * @param $endpoint  API endpoint to call.
     * @param $method Currently-executing HTTP method (used to decide
     *                whether to apply GET-specific defaults, and whether a
     *                `query` key is guaranteed in the result).
     * @param $params Caller-supplied parameters in array, object, string
     *                (JSON or bare UUID), or null form; anything else
     *                normalizes to an empty array.
     * @param $query  Explicit CQL query string to use, or null.
     * @return The normalized parameter array. For `GET` requests, always
     *         includes a `query` key (defaulting to `''` if none was
     *         implicit or explicit). For other methods, the `query` key is
     *         removed entirely if it would otherwise be empty.
     */
    private function _handleParameters(string $endpoint, string $method, mixed $params, ?string $query = null): array {
        $paramArray = match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->folioUtils->isJson($params) ? (array) json_decode($params) : ($this->folioUtils->isValidUuid($params)
                ? ['query' => 'id="' . $params . '"'] : []),
            default => [],
        };

        //only set defaults for these endpoints. Not all endpoints accept these keys
        $noDefaultEndpoint = [];
        if ($method == 'GET' && !in_array($endpoint, $noDefaultEndpoint)) {
            $paramArray['limit'] = ($paramArray['limit'] ?? 0) > 0 ? $paramArray['limit'] : $this->getAllDefaultLimit;
            $paramArray['offset'] = $paramArray['offset'] ?? 0;
            $paramArray['query'] = ($paramArray['query'] ?? 'cql.allRecords=1') . ' sortBy id';
        }

        // if query is explicitly set, override implicit; otherwise leave
        // whatever implicit query was derived above (from $params, or the
        // GET-default 'cql.allRecords=1') alone. Applies to every endpoint.
        if ($query !== null) {
            $paramArray['query'] = $query;
        }

        // GET/search requests must always carry a query key, even if empty;
        // other methods omit it entirely when there's nothing to send.
        if ($method == 'GET') {
            $paramArray['query'] = $paramArray['query'] ?? '';
        } elseif (empty($paramArray['query'] ?? null)) {
            unset($paramArray['query']);
        }

        return $paramArray;
    }

    /**
     * Inspect a decoded JSON response to determine which property holds
     * the records array, and how many total records are available.
     *
     * @param $jsonObject The decoded JSON response object to inspect.
     * @return An associative array with `key` (the name of the array
     *         property holding the records, excluding `errors` — or null
     *         if the response has no other array property at all) and
     *         `totalRecords` (the response's `totalRecords` value, or
     *         null if not present).
     */
    private function _getResponseInfo(stdClass $jsonObject){
        // perform introspection on json object to get key
        $properties = get_object_vars($jsonObject);
        $arrayKeys = array_keys(array_filter($properties, 'is_array'));
        $dataKeys = array_values(array_diff($arrayKeys, ['errors']));
        $key = $dataKeys[0] ?? null;
        $totalRecords = $jsonObject->totalRecords ?? null;
        return ['key' => $key, 'totalRecords' => $totalRecords];
    }

    /**
     * Yield each record in a response's records array one at a time.
     *
     * @param $response The decoded response (array or object) to read
     *                   records from, or null if the request returned no body.
     * @param $key      Name of the property on `$response` holding the
     *                   records array, or null if no such property could be found.
     * @return A `\Generator` yielding each record in turn (yields nothing
     *         if `$response`/`$key` is null, or the property is empty/missing).
     */
    private function _yieldRecords(array|object|null $response, ?string $key): \Generator {
        if ($response !== null && $key !== null && !empty($response->{$key})) {
            foreach ($response->{$key} as $record) {
                yield $record;
            }
        }
    }
    
    /**
     * Customize `var_dump()` output for this object by redacting sensitive fields.
     *
     * @return This object's properties as an associative array, with any
     *         `password`, `token`, `folioRefreshToken`, and
     *         `folioAccessToken` entries removed. (Note: none of these
     *         keys are actual properties of `FolioClient` itself; this
     *         unset is a defensive no-op unless such properties are
     *         later added.)
     */
    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        unset($vars['password'],$vars['token'],$vars['folioRefreshToken'],$vars['folioAccessToken']);
        return $vars;
    }

    // information functions

    /**
     * Get the library version string.
     *
     * @return The value of {@see FolioClient::VERSION}.
     */
    public function getVersion(): string {
        return self::VERSION;
    }

    /**
     * Get the information accessor associated with this client.
     *
     * @return The {@see FolioInformation} instance for this client
     *         (either the one passed to the constructor, or one created
     *         automatically from the client's config/auth).
     */
    public function getInformation(): FolioInformation {
        return $this->information;
    }

    /**
     * Get the HTTP status code of the most recently completed request.
     *
     * @return The last HTTP status code seen.
     */
    public function getLastStatusCode(): int {
        return $this->lastStatusCode;
    }

    /**
     * Alias for {@see getLastStatusCode()}.
     *
     * @return The last HTTP status code seen.
     */
    public function getStatusCode(): int {
        return $this->getLastStatusCode();
    }

    /**
     * Get a description of the most recently issued request.
     *
     * @return The last request in `METHOD: endpoint` form.
     */
    public function getLastQuery(): string {
        return $this->lastQuery;
    }

    /**
     * Get the sequence number of the most recently issued request.
     *
     * @return The number of requests made by this client so far (the
     *         most recently completed, or currently in-flight, request's
     *         number); 0 if no request has been made yet.
     */
    public function getLastQueryNum(): int {
        return $this->queryNum;
    }
}
