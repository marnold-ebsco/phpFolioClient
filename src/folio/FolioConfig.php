<?php declare(strict_types=1);
namespace phpFolioClient;

use Exception;
use InvalidArgumentException;

class FolioConfig {
    public string $okapiUrl;
    public string $tenant_id;
    public ?string $central_tenant_id;
    public string $username;
    public string $password;
    public string|bool $sslVerify = true;
    public bool $debug = false;
    public int $timeout = 30;
    public string $localTimeZone = 'America/Chicago';


    public function __construct(string|array|object $config) {
        if (is_string($config)) {
            $config = $this->loadFromIni($config);
        } elseif (is_object($config)) {
            $config = (array) $config;
        }

        $requiredKeys = ['okapiUrl', 'tenant_id', 'username', 'password'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Missing required config key: {$key}");
            }
            $this->$key = $config[$key];
        }

        // Assign optional properties
        $optional = ['central_tenant_id', 'sslVerify', 'debug', 'timeout', 'localTimeZone', 'name'];
        foreach ($optional as $opt) {
            if (isset($config[$opt])) {
                $this->$opt = $config[$opt];
            }
        }
    }

    private function loadFromIni(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Config file not found: {$filePath}");
        }
        $config = parse_ini_file($filePath, false);
        if ($config === false) {
            throw new Exception("Failed to parse INI config file.");
        }
        return $config;
    }

    public function getApiUrl(){
        return $this->okapiUrl;
    }

    public function getTenantId(){
        return $this->tenant_id;
    }

    public function getCentralTenantId(){
        return $this->central_tenant_id;
    }

    public function getUsername(){
        return $this->username;
    }
}
