<?php declare(strict_types=1);
namespace phpFolioClient;

/**
 * Read-only accessor for descriptive information about the current
 * FOLIO connection (URL, tenant, auth flavor, hostname, username).
 *
 * Wraps a {@see FolioConfig} and a {@see FolioAuth} instance and exposes
 * a subset of their data through simple getters, without performing any
 * network calls itself. Constructed by, and exposed via,
 * {@see FolioClient::getInformation()}.
 */
class FolioInformation {
    private FolioConfig $config;
    private FolioAuth $auth;

    /**
     * Create an information accessor bound to the given config and auth objects.
     *
     * @param $config The connection configuration to read data from.
     * @param $auth   The authenticator to read the auth flavor from.
     */
    public function __construct(FolioConfig $config, FolioAuth $auth) {
        $this->config = $config;
        $this->auth = $auth;
    }

    // information functions
    /**
     * Get the name of the authentication scheme in use.
     *
     * @return The authentication flavor (currently always `'RTR'`).
     */
    public function getAuthFlavor(): string{
        return $this->auth->getAuthFlavor();
    }

    /**
     * Get the configured Okapi base URL.
     *
     * @return The FOLIO Okapi (or gateway) base URL.
     */
    public function getUrl(): string {
        return $this->config->getApiUrl();
    }

    /**
     * Get the configured tenant id.
     *
     * @return The FOLIO tenant id.
     */
    public function getTenantId(): string {
        return $this->config->tenant_id;
    }

    /**
     * Get the configured central (ECS consortium) tenant id.
     *
     * @return The central tenant id, or an empty string if none is configured.
     */
    public function getCentralTenantId(): string {
        return $this->config->central_tenant_id ?? '';
    }

    /**
     * Derive a short hostname/environment label from the configured Okapi URL.
     *
     * Extracts the host's leading subdomain and strips common Okapi
     * gateway prefixes/suffixes (`subdomain-`, `okapi-`, `api-`, `kong-`,
     * `-okapi`) to produce a shorter, more readable label.
     *
     * @return The cleaned-up subdomain/hostname label.
     * @throws \Exception If the configured Okapi URL has no parseable host
     *                     component (e.g. it is malformed or missing a scheme).
     */
    public function getHostname(): string{
        $host = parse_url($this->config->getApiUrl(), PHP_URL_HOST);
        if ($host === null || $host === false) {
            throw new \Exception("Could not parse a host from the configured Okapi URL: " . $this->config->getApiUrl());
        }
        $subdomain = explode(".", $host)[0];

        return preg_replace('/^(subdomain|okapi|api|kong)-|-okapi$/', '', $subdomain);
    }

    /**
     * Get the configured username.
     *
     * @return The FOLIO username used to log in.
     */
    public function getUsername(): string {
        return $this->config->username;
    }
}