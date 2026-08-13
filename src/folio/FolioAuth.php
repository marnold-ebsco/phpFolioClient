<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Handles FOLIO authentication using Refresh Token Rotation (RTR).
 *
 * Logs in by POSTing credentials to `/authn/login`, reads the bearer
 * access token from the `X-Okapi-Token` response header, and reads the
 * access token's expiration from the `folioAccessToken` cookie set on
 * the response. Automatically re-authenticates when the current token
 * is close to (or past) expiration.
 *
 * Consumed by {@see FolioClient} (which uses it to build request headers)
 * and {@see FolioInformation} (which exposes some of its data read-only).
 * Requires a {@see FolioConfig} instance for the Okapi URL, tenant id,
 * and credentials.
 */
class FolioAuth {
    private FolioConfig $config;
    private string $token = '';
    private string $authFlavor = 'RTR';
    public int $ATExpires = 0;
    public \DateTime $ATExpiresObj;
    public int $needsRefreshBeforeExpires = 60;

    /**
     * Create a new authenticator bound to a given FOLIO configuration.
     *
     * @param $config Connection/credential settings (Okapi URL, tenant id,
     *                username/password) used to perform the login request.
     */
    public function __construct(FolioConfig $config) {
        $this->config = $config;
        $this->ATExpiresObj = new \DateTime();
    }

    /**
     * Get a valid bearer access token, refreshing it first if it is
     * missing or close to/past expiration.
     *
     * @return The current (possibly just-refreshed) Okapi access token.
     * @throws \Exception If the login request fails, the response status
     *                     is not 201, or no `folioAccessToken` cookie is
     *                     returned by the server.
     */
    public function getAccessToken(): string {
        if ($this->needsRefresh()) {
            $this->refreshTokens();
        }
        return $this->token;
    }

    /**
     * Get the raw Unix timestamp at which the current access token expires.
     *
     * @return Expiration time of the current access token, as a Unix
     *         timestamp (0 if no token has been obtained yet).
     */
    public function getExpiration(): int {
        return $this->ATExpires;
    }

    /**
     * Determine whether the current access token is missing or is within
     * `$needsRefreshBeforeExpires` seconds of (or past) expiration.
     *
     * @return True if a call to {@see refreshTokens()} is required before
     *         the token can be used.
     */
    private function needsRefresh(): bool {
        return empty($this->token) || time() >= ($this->ATExpires - $this->needsRefreshBeforeExpires); // Refresh 1 min before expiration
    }

    /**
     * Perform Refresh Token Rotation login against `/authn/login`.
     *
     * POSTs the configured username/password (with the tenant id header)
     * using a fresh Guzzle client/cookie jar, then extracts the bearer
     * token from the `X-Okapi-Token` response header and the token
     * expiration from the `folioAccessToken` cookie, storing both on
     * this instance (`$token`, `$ATExpires`, `$ATExpiresObj`).
     *
     * @throws \Exception If the HTTP response status is not 201, if no
     *                     `folioAccessToken` cookie is present in the
     *                     response, or if the underlying Guzzle request
     *                     throws a `ClientException` (wrapped and rethrown
     *                     as a generic `\Exception`).
     */
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
                // Never forward config->debug here: Guzzle's debug output
                // dumps the raw request body, which would print the
                // plaintext username/password on every token refresh.
                'cookies'=>$jar
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
            throw new \Exception("Authentication failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the name of the authentication scheme this class implements.
     *
     * @return The authentication flavor identifier (currently always
     *         `'RTR'`, for Refresh Token Rotation).
     */
    public function getAuthFlavor(): string {
        return $this->authFlavor;
    }

    /**
     * Customize `var_dump()` output for this object by redacting the
     * current bearer token.
     *
     * @return This object's properties as an associative array, with
     *         `token` replaced by a redaction marker.
     */
    public function __debugInfo(): array {
        $vars = get_object_vars($this);
        if (array_key_exists('token', $vars)) {
            $vars['token'] = '***REDACTED***';
        }
        return $vars;
    }
}
