<?php declare(strict_types=1);
namespace phpFolioClient;

use Exception;
use InvalidArgumentException;

/**
 * Holds and validates connection settings for a FOLIO tenant.
 *
 * Accepts its settings from a string (path to an INI file), an array,
 * or an object, and normalizes them into typed public properties. The
 * required keys are `okapiUrl`, `tenant_id`, `username`, and `password`;
 * a handful of other keys (`central_tenant_id`, `sslVerify`, `debug`,
 * `timeout`, `localTimeZone`, `name`) are optional and fall back to
 * sensible defaults. `name` is a free-form label (e.g. for distinguishing
 * multiple configs in an app that talks to several FOLIO tenants); it is
 * not used internally by this library.
 *
 * Every other class in this library ({@see FolioAuth}, {@see FolioClient},
 * {@see FolioInformation}, {@see FolioFileHandler}, etc.) is constructed
 * with (or ultimately reads from) a `FolioConfig` instance.
 */
class FolioConfig {
    public string $okapiUrl;
    public string $tenant_id;
    public ?string $central_tenant_id = null;
    public string $username;
    public string $password;
    public string|bool $sslVerify = true;
    public bool $debug = false;
    public int $timeout = 30;
    public string $localTimeZone = 'America/Chicago';
    public string $name = '';


    /**
     * Build a configuration object from an INI file path, an array, or
     * an object of settings.
     *
     * @param $config Either the path to an INI file to load, an
     *                associative array of settings, or an object of
     *                settings (converted to an array internally). Must
     *                contain at least `okapiUrl`, `tenant_id`, `username`,
     *                and `password`.
     * @throws InvalidArgumentException If a required config key
     *                     (`okapiUrl`, `tenant_id`, `username`, `password`)
     *                     is missing, or if the given INI file path does
     *                     not exist.
     * @throws Exception If the given INI file exists but fails to parse.
     */
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

        // Normalize sslVerify to a real bool when it was supplied as a
        // recognizable boolean-like string (e.g. from an array/object
        // config source that isn't run through INI_SCANNER_TYPED). A
        // string that doesn't match one of these is left as-is, since
        // Guzzle also accepts a CA-bundle file path string for this option.
        if (is_string($this->sslVerify)) {
            $boolStrings = ['true' => true, 'false' => false, 'yes' => true, 'no' => false, 'on' => true, 'off' => false, '1' => true, '0' => false, '' => false];
            $normalized = strtolower(trim($this->sslVerify));
            if (array_key_exists($normalized, $boolStrings)) {
                $this->sslVerify = $boolStrings[$normalized];
            }
        }
    }

    /**
     * Customize `var_dump()` output for this object by redacting the
     * plaintext password.
     *
     * @return This object's properties as an associative array, with
     *         `password` replaced by a redaction marker.
     */
    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        if (array_key_exists('password', $vars)) {
            $vars['password'] = '***REDACTED***';
        }
        return $vars;
    }

    /**
     * Read and parse an INI file into an associative array of settings.
     *
     * @param $filePath Path to the INI file to load.
     * @return Associative array of key/value settings parsed from the file,
     *                     with booleans/integers/null converted to their
     *                     native PHP types (see `INI_SCANNER_TYPED`) rather
     *                     than left as strings.
     * @throws InvalidArgumentException If the file does not exist.
     * @throws Exception If the file exists but `parse_ini_file()` fails
     *                     to parse it.
     */
    private function loadFromIni(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Config file not found: {$filePath}");
        }
        // INI_SCANNER_TYPED converts "true"/"false"/"yes"/"no"/"on"/"off"
        // to real booleans and numeric strings to int/float. Without it,
        // parse_ini_file() returns every value as a string, which throws a
        // TypeError when assigned to the typed bool $debug / int $timeout
        // properties below under strict_types.
        $config = parse_ini_file($filePath, false, INI_SCANNER_TYPED);
        if ($config === false) {
            throw new Exception("Failed to parse INI config file.");
        }
        return $config;
    }

    /**
     * Get the configured Okapi base URL.
     *
     * @return The FOLIO Okapi (or gateway) base URL used for all requests.
     */
    public function getApiUrl(): string {
        return $this->okapiUrl;
    }

    /**
     * Get the configured tenant id.
     *
     * @return The FOLIO tenant id used for authentication and requests.
     */
    public function getTenantId(): string {
        return $this->tenant_id;
    }

    /**
     * Get the configured central (ECS consortium) tenant id, if any.
     *
     * @return The central tenant id, or null if this configuration is not
     *         part of an ECS (consortial) environment.
     */
    public function getCentralTenantId(): ?string {
        return $this->central_tenant_id;
    }

    /**
     * Get the configured username used for authentication.
     *
     * @return The FOLIO username used to log in.
     */
    public function getUsername(): string {
        return $this->username;
    }
}
