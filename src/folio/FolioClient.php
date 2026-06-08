<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class FolioClient {
    private FolioConfig $config;
    private FolioAuth $auth;
    private FolioLogger $logger;
    private Client $httpClient;

    private int $queryNum = 1;
    private int $lastStatusCode;
    private string $lastQuery;

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

    public function get(string $endpoint,array|object|null $params=null, string|null $key = null, string|null $tenant_id = null): array|object|null {
        try{
            $response = $this->_request('GET', $endpoint, $params);
        }catch(\Exception $e){
            throw $e;
        }
        // return $response;
        if ($key) {
            foreach ($response->{$key} as $record) {
                yield $record;
            }
        } else {
            return $response;
        }
    }

    public function getEach(string $endpoint,array|object|null $params=null, string $key, string|null $tenant_id = null): \Generator {
        return $this->get($endpoint, $params, $key, $tenant_id);
    }


    private function _request(string $method, string $endpoint, array|null $params = []){
        $tenant_id ??= $this->central_tenant_id ?? $this->config->tenant_id;
        
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
        print "  query: $this->lastQuery\n";

        try {
            $response = $this->httpClient->request($method, $uri . $queryString, $defaultOptions);
            $this->lastStatusCode = $response->getStatusCode();
            $this->queryNum++;

            return json_decode((string)$response->getBody()->getContents(), false);

        } catch (ClientException $e) {
            $this->logger->log("Client error on {$this->lastQuery}: " . $e->getMessage());
            throw $e;
        }
    }

    #utility functions
    private function _handleParameters(array|null $params):string|array {
        $paramArray =  match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->_isJson($params) ? json_decode($params) : ($this->_isValidUuid($params)
                ? ['query' => 'id="' . $params . '"'] : "$params"),
            default => [],
        };

        $paramArray['limit'] = ($paramArray['limit'] ?? 0) > 0 ? $paramArray['limit'] : $this->getAllDefaultLimit;
        $paramArray['offset'] = $paramArray['offset'] ?? 0;
        $paramArray['query'] = ($paramArray['query'] ?? 'cql.allRecords=1') . ' sortBy id';

        return $paramArray;
    }



    private function _isValidUuid(string $uuid ): bool {
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[4-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
    }

    private function _isJson(string|null $string): bool {
        if($string){
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        }else{
            return false;
        }
     }

    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        unset($vars['password'],$vars['token'],$vars['folioRefreshToken'],$vars['folioAccessToken']);
        return $vars;
    }
}
