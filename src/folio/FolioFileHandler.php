<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioFileHandler {
    #file functions
    public function putFile(string $endpoint,string $filename,string|null $tenant_id = null): array|object|null{
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        try{
            $endpoint = trim($endpoint,"/ \t\r\n\0");
            $tenant_id ??= $this->tenant_id;
            if(file_exists($filename)){
                $options = [
                    'headers' => [
                        'Accept' => 'application/json',
                        'x-okapi-tenant' => $tenant_id,
                        'Content-Type' => 'application/octet-stream',
                        'x-okapi-token' => $this->token
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
               
                $response = $this->_request('POST',$endpoint,null,$options);
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
                $client = new Client(['base_uri' => $this->okapiUrl,'verify'=>$this->sslVerify]);
                $request = $client->get($url, ['sink' => $fh],null,$tenant_id);

                if($this->logPath){

                    $now = new \DateTime();
                    fwrite($this->logFh,"Query $this->queryNum:\t" . "GET file: $filename / $url\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
                }
                $this->queryNum++;
                
            }else{
                throw new \Exception("Could not open filename: $filename");
            }
        }catch(\Exception $e){
            throw new \Exception("GetFile Error: " . $e->getMessage());
        }
    }
}