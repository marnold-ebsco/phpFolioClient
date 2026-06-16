<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Cookie\CookieJar;

class FolioAuth {
    private FolioConfig $config;
    private string $token = '';
    private string $authFlavor = 'RTR';
    public int $ATExpires = 0;
    public \dateTime $ATExpiresObj;
    public int $needsRefreshBeforeExpires = 60;

    public function __construct(FolioConfig $config) {
        $this->config = $config;
    }

    public function getAccessToken(): string {
        if ($this->needsRefresh()) {
            $this->refreshTokens();
        }
        return $this->token;
    }

    public function getExpiration(){
        return $this->ATExpires;
    }

    private function needsRefresh(): bool {
        return empty($this->token) || time() >= ($this->ATExpires - $this->needsRefreshBeforeExpires); // Refresh 1 min before expiration
    }

    private function refreshTokens(): void {
        try {
            $jar = new CookieJar();     //set up cookies
            // set up Guzzle client
            $client = new Client([
                'base_uri' => $this->config->okapiUrl,
                'connect_timeout'=>30,
                'read_timeout'=>30,
                'timeout'=>30,
                'verify'=>$this->config->sslVerify,
                'debug'=>$this->config->debug, 'cookies'=>$jar
            ]);

            // post id/password and get response
            $response = $client->post('/authn/login', [
                'json' => [
                    'username' => $this->config->username,
                    'password' => $this->config->password,
                ],
                'headers' => ['X-Okapi-Tenant' => $this->config->tenant_id]
            ]);

            if($response->getStatusCode() != '201'){
                throw new \Exception("Authentication failed: " . $response->getStatusCode() . " / " . $response->getReasonPhrase());
            }

            // set variables
            $token = $jar->getCookieByName('folioAccessToken');
            if (!$token) {
                throw new \Exception("Could not get folio access token");
            }
            $this->token = $response->getHeaderLine('X-Okapi-Token');
            $expiration = $token->getExpires();
            $this->ATExpires = $expiration;
            
            $date = new \DateTime();
            $date->setTimestamp($expiration);
            $date->setTimezone(new \DateTimeZone($this->config->localTimeZone));
            $this->ATExpiresObj = $date;
            
        } catch (ClientException $e) {
            throw new \Exception("Authentication failed: " . $e->getMessage());
        }
    }

    
    public function getAuthFlavor(){
        return $this->authFlavor;
    }
}
