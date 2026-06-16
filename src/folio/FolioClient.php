<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use stdClass;

/* stuff left to do
    data export
    data export all
    data import
    convenience functions

 */


class FolioClient {
    public const RETURN_FULL_OBJECT = -1;
    private FolioConfig $config;
    private FolioAuth $auth;
    private FolioLogger $logger;
    private Client $httpClient;
    private FolioUtils $folioUtils;

    private int $queryNum = 0;
    private int $lastStatusCode;
    private string $lastQuery = '';
    private ?string $central_tenant_id = null;

    private int $getAllDefaultLimit = 5000;

    public function __construct(
        FolioConfig $config,
        FolioAuth $auth,
        FolioUtils $folioUtils,
        FolioLogger $logger,
        ?Client $httpClient = null
    ) {
        $this->config = $config;
        $this->auth = $auth;
        $this->folioUtils = $folioUtils;
        $this->logger = $logger;

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->config->okapiUrl,
            'timeout'  => $this->config->timeout,
            'verify'   => $this->config->sslVerify,
        ]);
    }

    public function get(string $endpoint, ?string $query = null, mixed $params = null, string|int|null $key = null, ?string $tenant_id = null): mixed {    
        // get data
        $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
        if ($key == self::RETURN_FULL_OBJECT) {     //return full object
            return $response;
        }
        
        // get implicit key and total records
        $responseInfo = $this->getResponseInfo($response);
        $key ??= $responseInfo['key'];
        
        return $this->_yieldRecords($response, $key);   //return generator
    }

    public function getOne(string $endpoint, string $id, ?string $tenant_id = null): null|stdClass {
        if($this->folioUtils->isValidUuid($id)){
            $response = $this->get("$endpoint/$id",null,null,self::RETURN_FULL_OBJECT,$tenant_id);
            return $response;
        }else{
            throw new \Exception("getOne must be passed a valid UUID");
        }
    }

    public function getEach(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key, ?string $tenant_id = null): \Generator {
        return $this->get($endpoint, $query, $params, $key, $tenant_id);
    }


    public function getAll_loop(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key = null, ?string $tenant_id = null)  {
                $query = ($query ?? 'cql.allRecords=1') . ' sortBy id';     //set initial query
                $params = (array)$params ?: [];
                $params['offset'] = $params['offset'] ?? 0;
                $params['limit'] = $params['limit'] ?? $this->getAllDefaultLimit;
        
        do {
            // get data
            $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
            // get implicit key and total records
            $responseInfo = $this->getResponseInfo($response);
            $key ??= $responseInfo['key'];
            
            foreach ($response->$key as $result) {
                yield $result;
            }
            
            if ($params['offset'] + count($response->$key) >= $responseInfo['totalRecords']) {
                break;
            }
            $params['offset'] += $params['limit'];
        } while (true);
    }

    public function getAll(string $endpoint, ?string $query = null, array|object|null $params = null, ?string $key = null, ?string $tenant_id = null)  {
        $query = ($query ?? 'cql.allRecords=1') . ' sortBy id';     //set initial query
        $origQuery = (isset($query)) ? $query : $params['query'];
        
        $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);     // get first response

        $responseInfo = $this->getResponseInfo($response);
        $key ??= $responseInfo['key'];

        $records = $response->{$key};
        if (empty($records)) {
            return;
        }
        $end = end($records)->id;

        foreach ($records as $record) {
            yield $record;
        }

        // get subsequent batches
        while ($responseInfo['totalRecords'] > 0) {
            $query = 'id > "' . $end . '" and ' . $origQuery;
            $response = $this->_request('GET', $endpoint, $query, $params, $tenant_id);
            if (empty($response->{$key})) {
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

    public function put(string $endpoint, ?string $id = null, array|object|null $params = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $json = is_object($params) ? (array) $params : json_decode($params, true);

        $options = [
            'json' => $json,
            'headers' => ['Accept' => 'text/plain']
        ];

        $this->_request('PUT', $endpoint, null, [], $options, $tenant_id);
    }

    public function patch(string $endpoint, ?string $id = null, array|object|null $params = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $json = is_object($params) ? (array) $params : json_decode($params, true);

        $options = [
            'json' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ];

        $this->_request('PATCH', $endpoint, null, [], $options, $tenant_id);
    }

    public function post(string $endpoint, array|object|null $params = null, ?string $tenant_id = null): string {
        $json = is_object($params) ? (array) $params : json_decode($params, true);

        $options = [
            'json' => $json,
            'headers' => ['Accept' => 'text/plain']
        ];

        $response = $this->_request('POST', $endpoint, null, [], $options, $tenant_id);
        return $response->id;
    }

    public function delete(string $endpoint, ?string $id = null, ?string $tenant_id = null): void {
        if ($id) {
            $endpoint .= "/$id";
        }

        $options = [
            'headers' => ['Accept' => 'text/plain']
        ];

        $this->_request('DELETE', $endpoint, null, [], $options, $tenant_id);
    }

    private function _request(string $method, string $endpoint, ?string $query, array|null $params = [], array|null $options = [], ?string $tenant_id = null): array|object|null {
        $params ??= [];
        $method = strtoupper($method);
        $uri = trim($endpoint, "/ \t\r\n\0");
        
        // Build query string (skip for PATCH requests)
        $queryString = $method !== 'PATCH' 
            ? '?' . http_build_query($this->_handleParameters($method, $params, $query))
            : '';
        
        // Merge headers with defaults
        $finalOptions = $this->_buildRequestOptions($options);
        
        $this->lastQuery = "{$method}: {$uri}";

        try {
            $response = $this->httpClient->request($method, $uri . $queryString, $finalOptions);
            $this->lastStatusCode = $response->getStatusCode();
            $this->queryNum++;

            return json_decode((string)$response->getBody()->getContents(), false);
        } catch (ClientException|ServerException $e) {
            $this->logger->log("HTTP error on {$this->lastQuery}: " . $e->getMessage());
            throw $e;
        } catch (ConnectException $e) {
            $this->logger->log("Connection error on {$this->lastQuery}: " . $e->getMessage());
            throw $e;
        }
    }

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
    private function _handleParameters(string $method, array|null $params,?string $query = null): array {
        $paramArray = match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->folioUtils->isJson($params) ? (array) json_decode($params) : ($this->folioUtils->isValidUuid($params)
                ? ['query' => 'id="' . $params . '"'] : []),
            default => [],
        };

        // set defaults
        if($method == 'GET'){
            $paramArray['limit'] = ($paramArray['limit'] ?? 0) > 0 ? $paramArray['limit'] : $this->getAllDefaultLimit;
            $paramArray['offset'] = $paramArray['offset'] ?? 0;
            $paramArray['query'] = ($paramArray['query'] ?? 'cql.allRecords=1') . ' sortBy id';
        }

        // if query is explicitly set, override implicit 
        $paramArray['query'] = $query ?? '';
        if(empty($paramArray['query'])){
            unset($paramArray['query']);
        }
        return $paramArray;
    }

    private function _setOptions(array|null $extraOptions = null,string|null $tenant_id = null): array|null{
        $tenant_id ??= $this->tenant_id;
        // $options = [
        //     'headers' => [
        //         'json' => $
        //     ]
        // ];
        if($extraOptions){
            if(is_array($extraOptions)){
                $options = array_merge_recursive($options,$extraOptions);
            }
        }
        return $options;
    }

    private function getResponseInfo(stdClass $jsonObject){
        // perform introspection on json object to get key
        $properties = get_object_vars($jsonObject);
        $arrayKeys = array_keys(array_filter($properties, 'is_array'));
        $key = array_diff($arrayKeys, ['errors'])[0];
        $totalRecords = $jsonObject->totalRecords ?? null;
        return ['key' => $key, 'totalRecords' => $totalRecords];
    }

    private function _yieldRecords(array|object $response, string $key): \Generator {
        if (!empty($response->{$key})) {
            foreach ($response->{$key} as $record) {
                yield $record;
            }
        }
    }
    
    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        unset($vars['password'],$vars['token'],$vars['folioRefreshToken'],$vars['folioAccessToken']);
        return $vars;
    }

    // information functions
    public function getAuthFlavor(){
        return $this->auth->getAuthFlavor();
    }

    public function getLastStatusCode(): int {
        return $this->lastStatusCode;
    }

    public function getStatusCode(): int {
        return $this->lastStatusCode;
    }

    public function getLastQuery(): string {
        return $this->lastQuery;
    }

    public function getLastQueryNum(): int {
        return $this->queryNum;
    }

    public function getUrl(): string {
        return $this->config->getApiUrl();
    }

    public function getTenantId(): string {
        return $this->config->tenant_id;
    }

    public function getCentralTenantId(){
        return $this->config->central_tenant_id ?? null;
    }

    public function getHostname(): string{
        $host = parse_url($this->config->getApiUrl(), PHP_URL_HOST);
        $subdomain = explode(".", $host)[0];
        
        return preg_replace('/^(subdomain|okapi|api|kong)-|-okapi$/', '', $subdomain);
    }

    public function getUsername(): string {
        return $this->config->username;
    }
}
