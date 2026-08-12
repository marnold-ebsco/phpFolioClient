<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;

/**
 * Uploads and downloads raw files to/from FOLIO endpoints.
 *
 * Wraps a {@see FolioClient} (reusing its config and auth) to stream
 * local files up as request bodies (`putFile()`/`postFile()`) and to
 * stream remote URLs down to local files (`getFile()`), bypassing the
 * client's usual JSON request/response handling. Used by
 * {@see FolioDataExport} to upload file definitions and download
 * completed export files.
 */
class FolioFileHandler {
    private FolioClient $client;
    private FolioConfig $config;
    private FolioAuth $auth;

    /**
     * Create a file handler bound to a FOLIO client, reusing its config and auth.
     *
     * @param $client The client whose config/auth and low-level
     *                `rawRequest()` method will be used for file transfers.
     */
    public function __construct(FolioClient $client){
        $this->client = $client;
        $this->config = $client->getConfig();
        $this->auth   = $client->getAuth();
    }


    /**
     * Upload a local file to a FOLIO endpoint as a raw octet-stream request body.
     *
     * @param $endpoint  API endpoint to upload to.
     * @param $filename  Path to the local file to read and send.
     * @param $tenant_id Tenant id to send with the request, for ECS
     *                   (consortial) environments; defaults to the
     *                   config's central tenant id, then its default tenant.
     * @return The decoded response from the upload request.
     * @throws \Exception If the local file does not exist/cannot be
     *                     opened, or if the underlying request fails
     *                     (wrapped as `"PutFile Error: ..."`, with the
     *                     original exception preserved as the cause).
     */
    public function putFile(string $endpoint,string $filename,string|null $tenant_id = null): array|object|null{
        $tenant_id ??= $this->config->central_tenant_id ?? $this->config->tenant_id;

        $fileStream = null;
        try{
            $endpoint = trim($endpoint,"/ \t\r\n\0");
            if(!file_exists($filename)){
                throw new \Exception("Could not open filename: $filename");
            }
            $fileStream = fopen($filename, 'rb');
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Okapi-Tenant' => $tenant_id,
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => filesize($filename),
                    'X-Okapi-Token' => $this->auth->getAccessToken()
                ],
                'body' => $fileStream,
                'curl' => [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true
                ]
            ];

            return $this->client->rawRequest('POST',$endpoint,null,[],$tenant_id,$options);
        }catch(\Exception $e){
            throw new \Exception("PutFile Error: " . $e->getMessage(), 0, $e);
        }finally{
            if(is_resource($fileStream)){
                fclose($fileStream);
            }
        }
    }

    #file functions
    /**
     * Experimental/alternate variant of {@see putFile()} for uploading a
     * local file: sends the body via the public {@see FolioClient::post()}
     * method instead of the lower-level request executor.
     *
     * @param $endpoint  API endpoint to upload to.
     * @param $filename  Path to the local file to read and send.
     * @param $tenant_id Tenant id to send with the request, for ECS
     *                   (consortial) environments; defaults to the
     *                   config's central tenant id, then its default tenant.
     * @return The decoded response from the upload request.
     * @throws \Exception If the local file does not exist/cannot be
     *                     opened, or if the underlying request fails
     *                     (wrapped as `"PutFile Error: ..."`, with the
     *                     original exception preserved as the cause).
     */
    public function putFileX(string $endpoint,string $filename,string|null $tenant_id = null): array|object|null{
        $tenant_id ??= $this->config->central_tenant_id ?? $this->config->tenant_id;

        $fileStream = null;
        try{
            $endpoint = trim($endpoint,"/ \t\r\n\0");
            if(!file_exists($filename)){
                throw new \Exception("Could not open filename: $filename");
            }
            $fileStream = fopen($filename, 'r');
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Okapi-Tenant' => $tenant_id,
                    'Content-Type' => 'application/octet-stream',
                    'X-Okapi-Token' => $this->auth->getAccessToken()
                ],
                'body' => $fileStream, // send raw stream directly, no multipart wrapper
                'curl' => [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true
                ]
            ];

            return $this->client->post($endpoint,null,$tenant_id,$options);
        }catch(\Exception $e){
            throw new \Exception("PutFile Error: " . $e->getMessage(), 0, $e);
        }finally{
            if(is_resource($fileStream)){
                fclose($fileStream);
            }
        }
    }

    /**
     * Alias of {@see putFile()}.
     *
     * @param $endpoint  API endpoint to upload to.
     * @param $filePath  Path to the local file to read and send.
     * @param $tenant_id Tenant id to send with the request, for ECS
     *                   (consortial) environments; null defers to
     *                   {@see putFile()}'s own defaulting.
     * @return The decoded response from the upload request.
     * @throws \Exception If the underlying {@see putFile()} call fails
     *                     (re-wrapped as `"PutFile Error: ..."`, with the
     *                     original exception preserved as the cause).
     */
    public function postFile(string $endpoint,string $filePath,string|null $tenant_id=null): array|object|null{
        try{
            return $this->putFile($endpoint,$filePath,$tenant_id);
        }catch(\Exception $e){
            throw new \Exception("PutFile Error: " . $e->getMessage(), 0, $e);
        }
    }


    /**
     * Download a file from a URL directly to a local path.
     *
     * Uses a plain (unauthenticated) Guzzle client to stream the given
     * URL's response body into `$filename` (e.g. for FOLIO's pre-signed
     * export download links, which don't require Okapi auth headers).
     *
     * @param $filename  Local path to write the downloaded content to;
     *                   its parent directory must already exist and be writable.
     * @param $url       URL to download from.
     * @param $tenant_id Currently unused by this method; accepted for
     *                   signature consistency with other file operations.
     * @throws \Exception If the destination directory doesn't exist/isn't
     *                     writable, the local file can't be opened, or the
     *                     download response status is not in the 2xx range
     *                     (all wrapped as `"GetFile Error: ..."`, with the
     *                     original exception preserved as the cause).
     */
    public function getFile(string $filename,string $url,string|null $tenant_id=null): void {
        $fh = null;
        try{
            $dir = dirname($filename);
            if(!is_dir($dir) || !is_writable($dir)){
                throw new \Exception("Could not write to filename: $filename");
            }
            $fh = fopen($filename,'w');
            if(!$fh){
                throw new \Exception("Could not open filename: $filename");
            }
            $client = new Client(['base_uri' => $this->config->okapiUrl,'verify'=>$this->config->sslVerify]);
            $response = $client->get($url, ['sink' => $fh]);
            if(!$response || $response->getStatusCode() < 200 || $response->getStatusCode() >= 300){
                throw new \Exception("Failed to download file: Invalid response from server");
            }
        }catch(\Exception $e){
            throw new \Exception("GetFile Error: " . $e->getMessage(), 0, $e);
        }finally{
            if(is_resource($fh)){
                fclose($fh);
            }
        }
    }
}
