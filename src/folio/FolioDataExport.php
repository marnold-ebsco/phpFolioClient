<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioDataExport {
    # data export
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
}