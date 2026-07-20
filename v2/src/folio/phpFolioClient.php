<?php declare(strict_types=1);
namespace phpFolioClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\CookieJar;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;      //Connection/timeout errors
use GuzzleHttp\Exception\ClientException;       //4xx errors
use GuzzleHttp\Exception\ServerException;       //5xx errors

class phpFolioClient {
    private string $folioAccessToken;
    private int $ATExpires;
    private string $ATDomain;
    private ?int $ATrenew;

    private string $folioRefreshToken;
    private int $RTExpires;
    private string $RTDomain;

    private string $token;

    private string $okapiUrl;
    private string $tenant_id;
    private string $central_tenant_id;
    private string $username;
    private string $password;
    // https://docs.guzzlephp.org/en/stable/request-options.html#verify-option
    // private $sslVerify = 'src/folio/cacert.pem';
    private string|bool $sslVerify;
    private string $name;

    private mixed $logPath = false;
    private mixed $logFh;
    private int $queryNum = 1;
    private int $lastStatusCode;
    private string $lastQuery;
    private int $timeout = 30;           //number of seconds to wait for a response from server after request (0 is infinity)
    private int $maxRetries = 5;
    private int $getAllDefaultLimit = 5000;
    private string $authFlavor;
    private bool $debug = false;

    private bool $verbose = false;


    /* 
     *   This function can take connection information in three ways:
     *   As a standard object with the following fields required:
     *   okapiUrl, tenant_id, username, password. 
     *   The name and sslVerify fields are optional
     *
     *   As an associative array with the following keys required:
     *   okapiUrl, tenant_id, username, password. 
     *   The name and sslVerify fields are optional
     *
     *   As the path to a single level ini file. The following keys required:
     *   okapiUrl, tenant_id, username, password. 
     *   The name and sslVerify fields are optional
     *
     *   if you include a logPath, a query log will be created
     */
    public function __construct(mixed $connection, bool $verbose = false,mixed $logPath = false){
        $this->ATrenew = null;
        $this->verbose = $verbose;
        $this->logPath = $logPath;

        $this->_initializeConnection($connection);

        // fix sslVerify variable
        if (strcasecmp($this->sslVerify, 'true') === 0) {
            $this->sslVerify = true;
        } elseif (strcasecmp($this->sslVerify, 'false') === 0) {
            $this->sslVerify = false;
            print "Caution: setting sslVerify to false should only be used for development work!\n";
        }
        // Otherwise leave as string (e.g., path to certificate)
        

        $this->connect();
        $this->okapiUrl = trim($this->okapiUrl, "/");

        if ($logPath) {
            $fh = fopen($logPath, 'w');
            if ($fh === false) {
                throw new \Exception("Problem opening FOLIO client log file: $logPath");
            }
            $this->logFh = $fh;
        }
    }

    private function _initializeConnection(mixed $connection): void{
        $keys = [];
        
        $data = match(gettype($connection)) {
            'object' => get_object_vars($connection),
            'array' => $connection,
            'string' => $this->_parseConnectionFile($connection),
            default => throw new \Exception("Connection type not recognized.")
        };

        foreach ($data as $key => $value) {
            $this->$key = $value;
            $keys[] = $key;
        }

        $this->_validateConnectionData($keys);
    }

    private function _parseConnectionFile(string $path): array {
        if (!file_exists($path)) {
            throw new \Exception("File: $path does not exist");
        }
        
        $result = parse_ini_file($path);
        if ($result === false) {
            throw new \Exception("File: $path is not a valid ini file");
        }
        
        return $result;
    }

    #configuration/information methods
    /*
     *  set timeout in seconds
     */ 
    public function setTimeout(int|float $timeout): void {
        $this->timeout = $timeout;
    }

    public function getTimeout(): int {
        return $this->timeout;
    }

    public function getSslVerify(){
        if (is_string($this->sslVerify)) {
            return $this->sslVerify;
        } elseif (is_bool($this->sslVerify)) {
            // Returns "true" for true and "false" for false
            return $this->sslVerify ? 'true' : 'false';
        }
        return ''; // Default fallback
    }

    public function setVerbose(bool $verbose): void {
        $this->verbose = $verbose;
    }

    // get information
    public function getFlavor(){
        return $this->authFlavor;
    }

    public function getLastStatusCode(): int {
        return $this->lastStatusCode;
    }

    public function getStatusCode(): int {
        return $this->lastStatusCode;
    }

    public function getLastQuery(): string {
        return $this->lastQuery;
    }

    public function getLastQueryNum(): int {
        return $this->queryNum;
    }

    public function getUrl(): string {
        return $this->okapiUrl;
    }

    public function getTenantId(): string {
        return $this->tenant_id;
    }

    public function getCentralTenantId(){
        return $this->central_tenant_id ?? null;
    }

    public function getHostname(): string{
        $host = parse_url($this->okapiUrl, PHP_URL_HOST);
        $subdomain = explode(".", $host)[0];
        
        return preg_replace('/^(subdomain|okapi|api|kong)-|-okapi$/', '', $subdomain);
    }

    public function getUsername(): string {
        return $this->username;
    }

    // public function token(): string{
    //     return substr($this->token,-10) . " | " . substr($this->folioAccessToken,-10);
    // }


    //Helper functions to get some important reference data
    public function getLocations(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $locations = [];
        foreach($this->getAll('locations','locations',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $location){
            $locations[$location->id] = $location->name;
        }
        return $locations;
    }

    public function getLocationCodes(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $locations = [];
        foreach($this->getAll('locations','locations',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $location){
            $locations[$location->id] = $location->code;
        }
        return $locations;
    }

    public function getMaterialTypes(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $mattypes = [];
        foreach($this->getAll('material-types','mtypes',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $mattype){
            $mattypes[$mattype->id] = $mattype->name;
        }
        return $mattypes;
    }

    public function getLoanTypes(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $loantypes = [];
        foreach($this->getAll('loan-types','loantypes',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $loantype){
            $loantypes[$loantype->id] = $loantype->name;
        }
        return $loantypes;
    }

    public function getDepartments(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $departments = [];
        foreach($this->getAll('departments','departments',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $dept){
            $departments[$dept->id] = $dept->name;
        }
        return $departments;
    }

    public function getAddressTypes(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $addressTypes = [];
        foreach($this->getAll('addresstypes','addressTypes',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $addressType){
            $addressTypes[$addressType->id] = $addressType->addressType;
        }
        return $addressTypes;
    }

    public function getPatronGroups(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $patronGroups = [];
        foreach($this->getAll('groups','usergroups',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $patronGroup){
            $patronGroups[$patronGroup->id] = $patronGroup->group;
        }
        return $patronGroups;
    }

    public function getServicePoints(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $servicePoints = [];
        foreach($this->getAll('service-points','servicepoints',['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id) as $servicePoint){
            $servicePoints[$servicePoint->id] = $servicePoint->name;
        }
        return $servicePoints;
    }

    public function getModules(string|null $tenant_id = null): array {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $modules = [];
        $r = $this->get("/_/proxy/tenants/$this->tenant_id/modules",['query'=>'cql.allRecords=1','limit'=>500],null,$tenant_id);
        foreach($r as $obj){
            $modules[] = $obj->id;
        }
        return $modules;
    }

    public function getCustomFields(string|bool $returnNames = false, string|null $tenant_id = null): array|object {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $modules = $this->getModules($tenant_id);
        $moduleId = '';
        $matches = preg_grep('/mod-users-[0-9].*/',$modules);
        if($matches){
            $moduleId = implode('',$matches);
            if($moduleId){
                $customFields = $this->get('custom-fields',[],['headers'=>['x-okapi-module-id'=>$moduleId],'limit'=>1000]);
                if($customFields && $returnNames){
                    $names = [];
                    foreach($customFields->customFields as $field){
                        $names[$field->refId] = $field->name;
                    }
                    return $names;

                }else{
                    return $customFields;
                }
            }else{
                throw new \Exception("getCustomFields: Module not found");
            }
        }
        throw new \Exception("getCustomFields: No matching modules found");
    }


    // Core methods
    /*
     * input: $id must be a UUID
     * output: output will be a single record object, not an array of objects
    */
    public function getOne(string $endpoint,string|null $id=null,array|null $extraOptions=null,string|null $tenant_id=null): object|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $endpoint = trim($endpoint,"/ \t\r\n\0");
        if($id){
            if(!$this->_isValidUuid($id)){
                throw new \Exception("Id: $id is not a valid UUID.");
            }
            $endpoint = trim("$endpoint/$id");
        }else{
            throw new \Exception("getOne must be supplied with a record id.");
        }

        $options = $this->_setOptions($extraOptions,$tenant_id);
        try{
            $response = $this->_request('GET',$endpoint,null,$options);
            return $response;
        }catch(\Exception $e){
            throw $e;
        }
    }
    
    /*
     *  input: params can be an:
     *    object - will be converted into array before being passed to request
     *    array - will be passed as is to request
     *    string - if the string is json encoded, it will be decoded
     *      and passed to request as a string
     *             if the string is a UUID the string will be converted into a query
     *             else the string will be passed directly to the request as is
     *   output: an array of objects (even if a single UUID was passed in)
     * 
    */ 
    public function get(string $endpoint,array|object|null $params=null,array|null $extraOptions=null,string|null $tenant_id = null,string|null $key = null): array|object|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $endpoint = trim($endpoint,"/ \t\r\n\0");
        
        $p = $this->_handleParameters($params);
        
        $options = $this->_setOptions($extraOptions,$tenant_id);
        try{
            $response = $this->_request('GET',$endpoint,$p,$options);
        }catch(\Exception $e){
            throw $e;
        }
        
        return $key ? $response->{$key} : $response;
    }
    
    /*
     * Unlike the 'get' method, this does not return an array. Instead
     * it returns one record at a time. It needs to be called inside of
     * a foreach loop. This method will make multiple calls as needed to
     * retrieve all records requested. If you set a limit, that many records
     * will be returned with each call. It won't effect what you see returned
     * 
     * All input rules that apply to 'get' apply to this method as well
     */
    public function getAll(string $endpoint,string $key = '',array|object|null $params=null,array|null $extraOptions=null,string|null $tenant_id = null): object|array|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $endpoint = trim($endpoint,"/ \t\r\n\0");
        $options = $this->_setOptions($extraOptions,$tenant_id);

        $p = match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->_isJson($params) 
            ? json_decode($params, true) 
            : throw new \Exception("Cannot use string query for getAll"),
            default => [],
        };
        
        $p['limit'] = ($p['limit'] ?? 0) > 0 ? $p['limit'] : $this->getAllDefaultLimit;
        $p['offset'] = $p['offset'] ?? 0;
        $p['query'] = ($p['query'] ?? 'cql.allRecords=1') . ' sortBy id';
        
        do{
            if(time() > $this->ATrenew){
                $this->connect(true);
                $options['headers']['x-okapi-token'] = $this->token;
            }
            $queryString = '?' . http_build_query($p);
            $response = $this->_request('GET', $endpoint, $queryString, $options);
            
            foreach($response->$key as $result){
                yield $result;
            }
            
            if(sizeof($response->$key) < $p['limit']){
                break;
            }
            $p['offset'] += $p['limit'];
        }while(true);
    }

    public function getAll_by_id_offset(string $endpoint,string $key = '',array|object|null $params = null,array|null $extraOptions = null,string|null $tenant_id = null): object|array|null{
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $endpoint = trim($endpoint, "/ \t\r\n\0");
        $options = $this->_setOptions($extraOptions, $tenant_id);

        $p = match (gettype($params)) {
            'object' => (array) $params,
            'array' => $params,
            'string' => $this->_isJson($params) 
            ? json_decode($params, true) 
            : throw new \Exception("Cannot use string query for getAll_by_id_offset"),
            default => [],
        };

        if(time() > $this->ATrenew){
            $this->connect(true);
            $options['headers']['x-okapi-token'] = $this->token;
        }
        $p['limit'] = ($p['limit'] ?? 0) > 0 ? $p['limit'] : $this->getAllDefaultLimit;
        $p['offset'] = $p['offset'] ?? 0;
        $p['query'] = ($p['query'] ?? 'cql.allRecords=1') . ' sortBy id';

        // get initial batch of records
        $origQuery = $p['query'];
        $response = $this->get($endpoint, $p, $options);
        
        if(empty($response->{$key})){
            return;
        }
        $end = $response->{$key}[sizeof($response->{$key}) - 1]->id;    // get the ending record
        foreach($response->{$key} as $result){
            yield $result;
        }

        // get subsequent batches
        while($response->totalRecords > 0){
            if(time() > $this->ATrenew){
                $this->connect(true);
                $options['headers']['x-okapi-token'] = $this->token;
            }
            $p['query'] = 'id > "' . $end . '" and ' . $origQuery;
            $response = $this->get($endpoint, $p, $options);
            
            if(empty($response->{$key})){
                break;
            }

            $end = $response->{$key}[sizeof($response->{$key}) - 1]->id;
            foreach($response->{$key} as $result){
                yield $result;
            }
        }
    }

    public function post(string $endpoint,array|object|null $params = null,array|null $extraOptions = null,string|null $tenant_id = null): object|array|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        
        if(is_object($params)){
            $params = (array) $params;
        }elseif($this->_isJson($params)){
            $params = json_decode($params);
        }

        $endpoint = trim($endpoint, "/ \t\r\n\0");
        $options = $this->_setOptions(
            array_merge_recursive(['json' => $params], $extraOptions ?? []),
            $tenant_id
        );
        
        try{
            return $this->_request('POST', $endpoint, '', $options);
        }catch(\Exception $e){
            throw $e;
        }
    }

    public function put(string $endpoint,string $id,array|object|null $params, array|null $extraOptions = null, string|null $tenant_id = null): object|array|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        
        if(is_object($params)){
            $params = (array) $params;
        }elseif($this->_isJson($params)){
            $params = json_decode($params);
        }

        $endpoint = trim($endpoint, "/ \t\r\n\0");
        $options = $this->_setOptions(
            array_merge_recursive(['json' => $params], $extraOptions ?? []),
            $tenant_id
        );
        $options['headers']['Accept'] = 'text/plain';
        
        try{
            return $this->_request('PUT', "$endpoint/$id", null, $options);
        }catch(\Exception $e){
            throw $e;
        }
    }

    public function patch(string $endpoint,string|null $id,array|object|null $params,array|null $extraOptions = null,string|null $tenant_id = null): array|object|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        
        if(is_object($params)){
            $params = (array) $params;
        }elseif($this->_isJson($params)){
            $params = json_decode($params);
        }

        $endpoint = trim($endpoint,"/ \t\r\n\0");
        $o = ['json'=>$params];
        $extraOptions = $o;

        $options = $this->_setOptions($extraOptions,$tenant_id);
        if($id){
            $endpoint = "$endpoint/$id";
        }
        try{
            return $this->_request('PATCH', "$endpoint", null, $options);
        }catch(\Exception $e){
            throw $e;
        }
    }
    
    public function delete(string $endpoint,string|null $id = null,array|object|null  $params = null,array|null $extraOptions = null,string|null $tenant_id = null): array|object|null {
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;
        $endpoint = trim($endpoint, "/ \t\r\n\0");
        
        if($id){
            $endpoint .= "/$id";
        }
        
        if(is_object($params)){
            $params = (array) $params;
        }elseif($this->_isJson($params)){
            $params = json_decode($params);
        }

        if($params){
            $extraOptions = array_merge_recursive(['json' => $params], $extraOptions ?? []);
        }
        
        $options = $this->_setOptions($extraOptions, $tenant_id);
        $options['headers']['Accept'] = 'text/plain';
        
        try{
            return $this->_request('DELETE', $endpoint, '', $options);
        }catch(\Exception $e){
            throw $e;
        }
    }

    #data export methods
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

    function dataExport(string $filename,string $exportProfileName = 'Default instances export job profile',string $out_Path = '',string|null $tenant_id = null): array|object|null {
        // https://folio-org.atlassian.net/browse/UXPROD-2330
        $tenant_id ??= $this->central_tenant_id ?? $this->tenant_id;       

        try{
            // step 1 - get export profile id
            $profile=$this->get('/data-export/job-profiles',['query'=>'name=="' . $exportProfileName . '"'],null,$tenant_id);
            $jobProfileId=$profile->jobProfiles[0]->id;
            ($this->verbose) ? print "step 1 - get profile id: $jobProfileId\n" : '';
            ($this->verbose) ? print_r($profile) : '';

            // step 2 - get file id
            $fileInfo = new \stdClass();
            $fileInfo->fileName = $filename;
            $fileDef=$this->post('/data-export/file-definitions',json_encode($fileInfo),null,$tenant_id);
            $fileId=$fileDef->id;
            $jobExecId = $fileDef->jobExecutionId;
            ($this->verbose) ? print "step 2 - get file id / jobExecutionId: $fileId / $jobExecId\n" : '';
            ($this->verbose) ? print_r($fileDef) : '';

            // step 3 - upload the file
            $uploadInfo=$this->putFile("/data-export/file-definitions/$fileId/upload",$filename,$tenant_id);
            ($this->verbose) ? print "step 3 - get job id\n" : '';
            ($this->verbose) ? print_r($uploadInfo) : '';

            // step 4 - initiate file export
            $exportInfo=['fileDefinitionId'=>$fileId,'jobProfileId'=>$jobProfileId,
                            'idType'=>'instance','recordType'=>'INSTANCE','deletedRecords'=>false,
                            'suppressedFromDiscovery'=>false
                        ];
            $fileDef=$this->post('/data-export/export',json_encode($exportInfo),null,$tenant_id);
            ($this->verbose) ? print "step 4 - initiate export \n" : '';
            ($this->verbose) ? print_r($fileDef) : '';

            // step 5 - poll until job execution completes
            ($this->verbose) ? print "step 5 - execute job\n" : '';

            $timeLimit = 300;
            $continue = true;
            $time = time();
            $continue = true;
            do{
                $jobExecInfo=$this->get('/data-export/job-executions',['query'=>'id=="' . $jobExecId . '"'],null,$tenant_id);
                $status = $jobExecInfo->jobExecutions[0]->status;
                
                if($jobExecInfo->jobExecutions[0]->status == 'SUCCESS' || 
                    $jobExecInfo->jobExecutions[0]->status == 'COMPLETED' || 
                    $jobExecInfo->jobExecutions[0]->status == 'COMPLETED_WITH_ERRORS' || 
                    $jobExecInfo->jobExecutions[0]->status == 'FAIL'){
                    $continue=false;
                    ($this->verbose) ? "$status\n" : '';
                }
                if(time() - $time > $timeLimit){
                    throw new \Exception('Step 5 took too long');
                }
                sleep(1);
            }while ($continue);
            if($status == 'FAIL'){
                throw new \Exception("Export failed because job returned with a status of 'FAIL'");
            }
            $fileId=$jobExecInfo->jobExecutions[0]->exportedFiles[0]->fileId;
            $stats = $jobExecInfo->jobExecutions[0]->progress;
            ($this->verbose) ? print "step 5 - fileId: $fileId\n" : '';
            ($this->verbose) ? print_r($jobExecInfo) : '';

            // step 6 - get link to retrieve file
            if($jobExecId && $fileId){
                $linkInfo=$this->get("/data-export/job-executions/$jobExecId/download/$fileId",null,null,$tenant_id);
                $url=$linkInfo->link;
                ($this->verbose) ? print "step 6 - get link\n" : '';
                ($this->verbose) ? print_r($linkInfo) : '';
            }else{
                ($this->verbose) ? print 'Step 6. Could not retrieve job or file id' : '';
            }

            // step 7 - get file
            $this->getFile($out_Path . $jobExecInfo->jobExecutions[0]->exportedFiles[0]->fileName,$url,$tenant_id);
            ($this->verbose) ? print "step 7 - get file\n" : '';

            return $jobExecInfo;
        }catch (\Exception $e){
            throw new \Exception("Data Export Error: " . $e->getMessage());
        }finally{
            $this->verbose = false;
        }
    }

    public function dataExportAll(string $exportProfileName = 'Default instances export job profile',string $out_Path = '',bool $includeDeleted = false,bool $includeSuppressed = false,string|null $tenant_id = null): array|object|null {
        try{
            // step 1 - get export profile id
            $profile=$this->get('/data-export/job-profiles',['query'=>'name=="' . $exportProfileName . '"'],null,$tenant_id);
            if($profile->totalRecords == 0){
                throw new Exception("Export profile: '$exportProfileName' not found");
            }
            $jobProfileId=$profile->jobProfiles[0]->id;
            ($this->verbose) ? print "step 1 - get profile id: $jobProfileId\n" : '';
            ($this->verbose) ? print_r($profile) : '';

            // step 2 - begin export all
            $obj = new \stdClass();
            $obj->jobProfileId = $jobProfileId;
            $obj->idType = 'instance';
            $obj->deletedRecords = $includeDeleted;
            $obj->suppressedFromDiscovery = $includeSuppressed;

            $currentRunningJobsArray = [];
            $runningAfterExportStartedArray = [];
            // get running jobs
            $currentRunningJobs = $this->get('data-export/job-executions',['query'=>'status=(IN_PROGRESS) and jobProfileId=="' . $jobProfileId . '"']);
            if($currentRunningJobs->totalRecords){
                foreach($currentRunningJobs->jobExecutions as $jobExecution){
                    $currentRunningJobsArray[$jobExecution->id] = $jobExecution->id;
                }
            }
            // begin export all
            $response = $this->post('/data-export/export-all',$obj);
            // get running jobs after export started
             $runningAfterExportStarted = $this->get('data-export/job-executions',['query'=>'status=(IN_PROGRESS) and jobProfileId=="' . $jobProfileId . '" sortBy startedDate']);
            if( $runningAfterExportStarted->totalRecords){
                foreach( $runningAfterExportStarted->jobExecutions as $jobExecution){
                    $runningAfterExportStartedArray[$jobExecution->id] = $jobExecution;
                }
            }
            ($this->verbose) ? print "running jobs\n" . print_r($runningAfterExportStartedArray) : '';
            $diff = array_diff(array_keys($runningAfterExportStartedArray),array_keys($currentRunningJobsArray));
            $reindexed = array_values($diff);

            $jobExecId = $reindexed[0];
            if($jobExecId){
                ($this->verbose) ? print "step 3 - begin export all\n" : '';
                ($this->verbose) ? print "jobExecutionId: $jobExecId\n" : '';

                // step 3 - poll until job execution completes
                $done = false;
                $begin = time();
                $loopNum = 0;
                do{
                    $jobExecInfo=$this->get('/data-export/job-executions',['query'=>'id=="' . $jobExecId . '"'],null,$tenant_id);
                    $status = $jobExecInfo->jobExecutions[0]->status;
                    
                    if($jobExecInfo->jobExecutions[0]->status == 'SUCCESS' || 
                        $jobExecInfo->jobExecutions[0]->status == 'COMPLETED' || 
                        $jobExecInfo->jobExecutions[0]->status == 'COMPLETED_WITH_ERRORS' || 
                        $jobExecInfo->jobExecutions[0]->status == 'FAIL'){
                            $done=true;
                        }
                    sleep(1);
                    $loopNum++;
                    ($this->verbose) ? print "Loop num: $loopNum\r" : '';
                }while(!$done);
                $fileId=$jobExecInfo->jobExecutions[0]->exportedFiles[0]->fileId;
                $stats = $jobExecInfo->jobExecutions[0]->progress;
                ($this->verbose) ? print "step 3 - fileId: $fileId\n" : '';
                ($this->verbose) ? print_r($jobExecInfo) : '';

                // step 4 - get link to retrieve file
                if($jobExecId && $fileId){
                    $linkInfo=$this->get("/data-export/job-executions/$jobExecId/download/$fileId",null,null,$tenant_id);
                    $url=$linkInfo->link;
                    ($this->verbose) ? print "step 4 - get link\n" : '';
                    ($this->verbose) ? print_r($linkInfo) : '';
                }else{
                    ($this->verbose) ? print 'Step 4. Could not retrieve job or file id' : '';
                }

                // step 5 - get file
                $this->getFile($out_Path . $jobExecInfo->jobExecutions[0]->exportedFiles[0]->fileName,$url,$tenant_id);
                ($this->verbose) ? print "step 5 - get file\n" : '';

                return $jobExecInfo;
            }else{
                throw new \Exception("Export all failed");
            }
        }catch(\Exception $e){
            throw new \Exception("Export all exception: " . $e->getMessage());
        }
    }
    
    #utility functions
    private function _handleParameters(array|null $params):string|array|null {
        return match (gettype($params)) {
            'object' => '?' . http_build_query((array) $params),
            'array' => '?' . http_build_query($params),
            'string' => $this->_isJson($params)
            ? json_decode($params)
            : ($this->_isValidUuid($params)
                ? '?' . http_build_query(['query' => 'id="' . $params . '"'])
                : "?$params"),
            default => null,
        };
    }

    private function _setOptions(array|null $extraOptions = null,string|null $tenant_id = null): array|null{
        $tenant_id ??= $this->tenant_id;
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'x-okapi-tenant' => $tenant_id,
                'Content-Type' => 'application/json',
                'x-okapi-token' => $this->token
            ]
        ];
        if($extraOptions){
            if(is_array($extraOptions)){
                $options = array_merge_recursive($options,$extraOptions);
            }
        }
        return $options;
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

    #this method does most of the real work
    private function _request(string $verb, string $endpoint, string|array|object|null $params = null,array|object|null $options = null): mixed {
        $try = 0;
        $prevTimeout = $this->timeout;

        if(time() > $this->ATrenew){
            $this->connect(true);
        }

        do{
            try{
                if($try > 0){
                    print "$try\n";
                }
                
                $client = new Client(['base_uri' => $this->okapiUrl, 'verify' => $this->sslVerify]);
                $request = new Request($verb, "/$endpoint$params");
                
                $this->_logRequest($request);
                $this->lastQuery = strtoupper($request->getMethod()) . ": " . $request->getUri();
                
                $response = $client->send($request, $options);
                $contents = $response->getBody()->getContents();
                
                $this->_logResponse($response, $contents, $verb);
                $this->lastStatusCode = $response->getStatusCode();
                
                return json_decode($contents, false);
            }catch (RequestException $e){
                $this->_handleClientException($e);
            }catch (ClientException $e){
                $this->_handleClientException($e);
                $try = $this->maxRetries;
                
            }catch (ServerException $e){
                $this->_handleServerException($e);
                $try = $this->maxRetries;
                
            }catch(ConnectException $e){
                $this->_handleConnectException($e, $try, $prevTimeout);
                
            }finally{
                if($this->logPath){
                    fwrite($this->logFh, PHP_EOL);
                }
            }
        }while($try < $this->maxRetries);
    }

    #log methods
    private function _logRequest($request){
        if($this->logPath){
            $now = new \DateTime();
            fwrite($this->logFh, "Query $this->queryNum:\t" . strtoupper($request->getMethod()) . ": " . $request->getUri() . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
            fwrite($this->logFh, "Query $this->queryNum (decoded):\t" . urldecode((string)$request->getUri()) . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
        }
    }

    private function _logResponse(object $response, string|null $contents, string $verb): void {
        if($this->logPath){
            $now = new \DateTime();
            fwrite($this->logFh, "Response $this->queryNum:\t" . $response->getStatusCode() . ' / ' . $response->getReasonPhrase() . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
            if($verb != 'GET'){
                fwrite($this->logFh, "Response Body $this->queryNum:\n" . print_r($contents, true) . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
            }
            $this->queryNum++;
        }
    }

    #exception methods
    private function _handleClientException(object $e): void {
        if($e->hasResponse()){
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            if(null !== $response->getBody()){
                $body = $statusCode . ": " . $response->getBody()->getContents();
            }else{
                $body = $statusCode;
            }
            if($this->logPath){
                $now = new \DateTime();
                fwrite($this->logFh, "Error $this->queryNum:\t" . $statusCode . ": " . $response->getBody()->getContents() . ' / ' . $response->getReasonPhrase() . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
                $this->queryNum++;
            }
            throw new \Exception("Client Error: " . $body);
        }
        throw new \Exception("Unspecified request error");
    }

    private function _handleServerException(object $e): void {
        if($e->hasResponse()){
            $response = $e->getResponse();
            $this->lastStatusCode = $response->getStatusCode();
            $body = $this->getLastStatusCode() . ": " . $response->getBody()->getContents();
            if($this->logPath){
                $now = new \DateTime();
                fwrite($this->logFh, "Error $this->queryNum:\t" . $this->getLastStatusCode() . ": " . $response->getBody()->getContents() . ' / ' . $response->getReasonPhrase() . "\t" . $now->format("Y-m-d H:i:s.u") . PHP_EOL);
                $this->queryNum++;
            }
            throw new \Exception("Server Error: " . $body);
        }
        throw new \Exception("Unspecified request error");
    }

    private function _handleConnectException(object $e,int &$try,int $prevTimeout): void {
        if($this->timeout > 0 && $try < $this->maxRetries){
            $try++;
            $this->timeout = round(($prevTimeout * 1.5) * 100) / 100;
        }elseif($this->timeout > 0){
            throw new \Exception("Max retries exceeded. Connect Error: " . $e->getMessage());
        }
    }

    #connection methods
    public function connect(bool $force = false): void {
        ($this->verbose) ? print "  " . $this->ATrenew . " / " .($this->ATrenew - time()) . PHP_EOL : '';

        if (isset($this->authFlavor)) {
            if ($this->authFlavor === "LEGACY" || (!$force && time() < $this->ATrenew)) {
                return;
            }
        }
        $statusCode = $this->_rtrConnect($force);
        if($statusCode == 404){
            $this->_legacyConnect();
            ($this->verbose) ? print "Using legacy authentication\n" : '';
            $this->authFlavor = "LEGACY";
        }else{
            ($this->verbose) ? print "Using RTR authentication\n" : '';
            $this->authFlavor = "RTR";
        }
    }


    private function _connectOptions(): array {
        if(isset($this->central_tenant_id)){
            $tenant = $this->central_tenant_id;
        }else{
            $tenant = $this->tenant_id;
        }
        return [
            'json' => [
                'username' => $this->username,
                'password' => $this->password
            ],
            'headers' => [
                'x-okapi-tenant' => $tenant,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ];
    }

    private function _legacyConnect(): int {
        try{
            $client = new Client(['base_uri' => $this->okapiUrl,'connect_timeout'=>30,
                                'read_timeout'=>30,'timeout'=>30,'verify'=>$this->sslVerify,
                                'debug'=>$this->debug, 'cookies'=>false]);
            $response = $client->request('POST','/authn/login',$this->_connectOptions());
            if ($response) {
                if(intval($response->getStatusCode()) == 201){
                    if($response->getHeaderLine('x-okapi-token')){
                        $this->token = $response->getHeaderLine('x-okapi-token');
                        return intval($response->getStatusCode());
                    }else{
                        throw new \Exception("No token returned from server");
                    }
                    
                }else{
                    throw new \Exception("Legacy status code: " . $response->getStatusCode());
                }
                
            }else{
                throw new \Exception('No response from server');
            }
        }catch(\Exception $e){
            throw new \Exception('FOLIO connection exception: ' . $e->getMessage());
        }
    }

    private function _rtrConnect(): int {
        try{
            $jar = new CookieJar();
            $client = new Client([
                'base_uri' => $this->okapiUrl,
                'connect_timeout' => 30,
                'read_timeout' => 30,
                'timeout' => 30,
                'verify' => $this->sslVerify,
                'debug' => $this->debug,
                'cookies' => $jar
            ]);

            $response = $client->request('POST', '/authn/login-with-expiry', $this->_connectOptions());
            
            $statusCode = intval($response->getStatusCode());
            if ($statusCode !== 201) {
                return $statusCode === 404 ? 404 : throw new \Exception("RTR status code: $statusCode");
            }

            $cookie = $jar->getCookieByName('folioAccessToken');
            if (!$cookie) {
                throw new \Exception("Could not get folio access token");
            }

            $this->folioAccessToken = $cookie->getValue();
            $this->ATExpires = $cookie->getExpires();
            $this->ATDomain = $cookie->getDomain();
            $this->token = $this->folioAccessToken;
            $this->ATrenew = $this->ATExpires - 30;

            $refreshCookie = $jar->getCookieByName('folioRefreshToken');
            if ($refreshCookie) {
                $this->folioRefreshToken = $refreshCookie->getValue();
                $this->RTExpires = $refreshCookie->getExpires();
                $this->RTDomain = $refreshCookie->getDomain();
            }

            return $statusCode;
        }catch(\Exception $e){
            throw new \Exception('FOLIO connection exception: ' . $e->getMessage());
        }
    }

    private function _validateConnectionData(): void {
        $required = ['okapiUrl','tenant_id','username','password'];

        foreach($required as $key){
            if(!isset($this->$key)){
                throw new \Exception("Required key: $key does not exist");
            }
        }
    }
}