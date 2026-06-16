<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use stdClass;

class FolioClient {
    public const RETURN_FULL_OBJECT = -1;
    private FolioConfig $config;
    private FolioAuth $auth;
    private FolioLogger $logger;
    private Client $httpClient;

    private int $queryNum = 1;
    private int $lastStatusCode;
    private string $lastQuery = '';
    private ?string $central_tenant_id = null;

    private int $getAllDefaultLimit = 5000;

    public function __construct(
        FolioConfig $config,
        FolioAuth $auth,
        FolioLogger $logger,
        ?Client $httpClient = null
    ) {
        $this->config = $config;
        $this->auth = $auth;
        $this->logger = $logger;

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->config->okapiUrl,
            'timeout'  => $this->config->timeout,
            'verify'   => $this->config->sslVerify,
        ]);
    }

    public function get(string $endpoint, mixed $params = null, string|int|null $key = null, ?string $tenant_id = null): mixed {
        $response = $this->_request('GET', $endpoint, $params);
        if ($key == self::RETURN_FULL_OBJECT) {     //return full object
            return $response;
        }
        
        $responseInfo = $this->getResponseInfo($response);  // get implicit key
        if($key == null && isset($responseInfo['key'])){
            $key = $responseInfo['key'];
        }
        
        return $this->_yieldRecords($response, $key);   //return generator
    }

    public function getOne(string $endpoint, string $id, ?string $tenant_id = null): null|stdClass {
        $response = $this->get("$endpoint/$id",null,self::RETURN_FULL_OBJECT,$tenant_id);
        
        return $response;
    }

    public function getEach(string $endpoint, string $key, array|object|null $params = null, ?string $tenant_id = null): \Generator {
        return $this->get($endpoint, $params, $key, $tenant_id);
    }


    public function getAll_loop(string $endpoint, array|object|null $params = null, string $key = null, ?string $tenant_id = null)  {
        $params = (array)$params ?: [];
        $params['offset'] = $params['offset'] ?? 0;
        $params['limit'] = $params['limit'] ?? $this->getAllDefaultLimit;
        
        do {
            $response = $this->_request('GET', $endpoint, $params);
            $responseInfo = $this->getResponseInfo($response);
            $key ??= $responseInfo['key'];
            
            if (empty($response->$key)) {
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


    public function getAll(string $endpoint, array|object|null $params = null, string $key = null, ?string $tenant_id = null)  {
        $params['query'] = ($params['query'] ?? 'cql.allRecords=1') . ' sortBy id';     //set initial query
        $origQuery = $params['query'];
        $response = $this->_request('GET', $endpoint, $params);     // get first response
        $responseInfo = $this->getResponseInfo($response);
        
        if ($key == null && isset($responseInfo['key'])) {      // get implicit key if not explicitly set
            $key = $responseInfo['key'];
        }

        if (empty($response->{$key})) {     // return if array is empty
            return;
        }

        $records = $response->{$key};
        $count = count($records);
        $end = $records[$count - 1]->id;

        foreach ($records as $record) {
            yield $record;
        }

        // get subsequent batches
        while ($responseInfo['totalRecords'] > 0) {
            $params['query'] = 'id > "' . $end . '" and ' . $origQuery;
            $response = $this->_request('GET', $endpoint, $params);
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


    private function _request(string $method, string $endpoint, array|null $params = []): array|object {
        $params ??= [];
        
        $p = $this->_handleParameters($params);
        
        $queryString = '?' . http_build_query($this->_handleParameters($p));
        $defaultOptions = [
            'headers' => [
                'X-Okapi-Tenant' => $this->config->tenant_id,
                'X-Okapi-Token'  => $this->auth->getAccessToken(),
                'Accept'         => 'application/json',
            ]
        ];
        $uri = trim($endpoint,"/ \t\r\n\0");

        $this->lastQuery = "{$method}: {$uri}";

        try {
            $response = $this->httpClient->request($method, $uri . $queryString, $defaultOptions);
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

    // Utility functions
    private function _handleParameters(array|null $params): array {
        $paramArray = match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->_isJson($params) ? (array) json_decode($params) : ($this->_isValidUuid($params)
                ? ['query' => 'id="' . $params . '"'] : []),
            default => [],
        };

        $paramArray['limit'] = ($paramArray['limit'] ?? 0) > 0 ? $paramArray['limit'] : $this->getAllDefaultLimit;
        $paramArray['offset'] = $paramArray['offset'] ?? 0;
        $paramArray['query'] = ($paramArray['query'] ?? 'cql.allRecords=1') . ' sortBy id';

        return $paramArray;
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
        foreach ($response->{$key} as $record) {
            yield $record;
        }
    }

    private function _isValidUuid(string $uuid): bool {
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[4-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
    }

    private function _isJson(?string $string): bool {
        if (!$string) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        unset($vars['password'],$vars['token'],$vars['folioRefreshToken'],$vars['folioAccessToken']);
        return $vars;
    }
}
