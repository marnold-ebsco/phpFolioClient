<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioInformation {
    private $config;
    private $auth;
    private $lastStatusCode = 0;
    private $lastQuery = '';
    private $queryNum = 0;

    public function __construct($config, $auth) {
        $this->config = $config;
        $this->auth = $auth;
    }

    // information functions
    public function getAuthFlavor(){
        return $this->auth->getAuthFlavor();
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