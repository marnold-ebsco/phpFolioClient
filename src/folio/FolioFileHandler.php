<?php declare(strict_types=1);
namespace phpFolioClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class FolioFileHandler {
    private FolioClient $client;
    private FolioConfig $config;
    private FolioAuth $auth;

    public function __construct(FolioClient $client, FolioConfig $config, FolioAuth $auth){
        $this->client = $client;
        $this->config = $config;
        $this->auth = $auth;
    }
    
    
    #file functions
    public function putFile(string $endpoint,string $filename,string|null $tenant_id = null): array|object|null{
        $tenant_id ??= $this->config->central_tenant_id ?? $this->config->tenant_id;
        try{
            $endpoint = trim($endpoint,"/ \t\r\n\0");
            if(file_exists($filename)){
                $options = [
                    'headers' => [
                        'Accept' => 'application/json',
                        'x-okapi-tenant' => $this->config->tenant_id,
                        'Content-Type' => 'application/octet-stream',
                        'x-okapi-token' => $this->auth->getAccessToken()
                    ],
					'multipart' => [
						[
							'contents' => file_get_contents($filename),
							'name'	   => 'FileContents'
						]
					],
					'curl' => [
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_FOLLOWLOCATION => true
					]
                ];
               
                $response = $this->client->_request('POST',$endpoint,null,[],$tenant_id,$options);
                return $response;
                
            }else{
                throw new \Exception("Could not open filename: $filename");
            }
        }catch(\Exception $e){
            throw new \Exception("PutFile Error: " . $e->getMessage());
        }
    }

    // alias of putField
    public function postFile(string $endpoint,string $filePath,string|null $tenant_id=null): array|object|null{
        try{
            return $this->putFile($endpoint,$filePath,$tenant_id=null);
        }catch(\Exception $e){
            throw new \Exception("PutFile Error: " . $e->getMessage());
        }
    }

    public function getFile(string $filename,string $url,string|null $tenant_id=null): void {
        try{
            $fh = fopen($filename,'w');
            if($fh){
                $client = new Client(['base_uri' => $this->config->okapiUrl,'verify'=>$this->config->sslVerify]);
                $client->get($url, ['sink' => $fh]);

                // if($this->logPath){

                //     $now = new \DateTime();
                //     fwrite($this->logFh,"Query $this->queryNum:\t" . "GET file: $filename / $url\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
                // }
                // $this->queryNum++;
                
            }else{
                throw new \Exception("Could not open filename: $filename");
            }
        }catch(\Exception $e){
            throw new \Exception("GetFile Error: " . $e->getMessage());
        }
    }
}