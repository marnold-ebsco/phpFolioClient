<?php declare(strict_types=1);
namespace phpFolioClient;

use Generator;

class FolioReferenceDataManager{
    private FolioClient $client;
    private array $cachedData = [];

    public function __construct(FolioClient $client)    {
        $this->client = $client;
    }

    public function getLocationObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('locations', tenant_id: $tenant_id);
    }

    public function getLocations(string|null $tenant_id = null): array {
        $locations = [];
        foreach ($this->getLocationObjects(tenant_id: $tenant_id) as $location) {            
            $locations[$location->id] = $location->name;
        }
        return $locations;
    }

    public function getLocationCodes(string|null $tenant_id = null): array {
        $locations = [];
        foreach ($this->getLocationObjects(tenant_id: $tenant_id) as $location) {            
            $locations[$location->id] = $location->code;
        }
        return $locations;
    }

    public function getMaterialTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('material-types', tenant_id: $tenant_id);
    }

    public function getMaterialTypes(string|null $tenant_id = null): array {
        $mattypes = [];
        foreach ($this->getMaterialTypeObjects(tenant_id: $tenant_id) as $mattype) {            
            $mattypes[$mattype->id] = $mattype->name;
        }
        return $mattypes;
    }

    public function getLoanTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('loan-types', tenant_id: $tenant_id);
    }

    public function getLoanTypes(string|null $tenant_id = null): array {
        $loanTypes = [];
        foreach ($this->getLoanTypeObjects(tenant_id: $tenant_id) as $loanType) {            
            $loanTypes[$loanType->id] = $loanType->name;
        }
        return $loanTypes;
    }

    public function getDepartmentObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('departments', tenant_id: $tenant_id);
    }

    public function getDepartments(string|null $tenant_id = null): array {
        $departments = [];
        foreach ($this->getDepartmentObjects(tenant_id: $tenant_id) as $dept) {            
            $departments[$dept->id] = $dept->name;
        }
        return $departments;
    }

    public function getAddressTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('addresstypes', tenant_id: $tenant_id);
    }

    public function getAddressTypes(string|null $tenant_id = null): array {
        $addressTypes = [];
        foreach ($this->getAddressTypeObjects(tenant_id: $tenant_id) as $addType) {            
            $addressTypes[$addType->id] = $addType->addressType;
        }
        return $addressTypes;
    }

    public function getPatronGroupObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('groups', tenant_id: $tenant_id);
    }

    public function getPatronGroups(string|null $tenant_id = null): array {
        $groups = [];
        foreach ($this->getPatronGroupObjects(tenant_id: $tenant_id) as $group) {            
            $groups[$group->id] = $group->group;
        }
        return $groups;
    }

    public function getServicePointObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('service-points', tenant_id: $tenant_id);
    }

    public function getServicePoints(string|null $tenant_id = null): array {
        $servicePoints = [];
        foreach ($this->getServicePointObjects(tenant_id: $tenant_id) as $servicePoint) {            
            $servicePoints[$servicePoint->id] = $servicePoint->name;
        }
        return $servicePoints;
    }

    public function getModules(string|null $tenant_id = null): array {
        $config = $this->client->getConfig();
        $base_tenant_id = $config->getTenantId();
        $tenant ??= $tenant_id ?? $base_tenant_id;        
        $mods = $this->client->get("/_/proxy/tenants/$tenant/modules",key: FolioClient::RETURN_FULL_OBJECT, tenant_id: $tenant);

        $modules = [];
        foreach($mods as $record){
            $modules[] = $record->id;
        }
        return $modules;
    }

    public function getCustomFieldObjects(string|null $tenant_id = null): array|object {
        $config = $this->client->getConfig();
        $base_tenant_id = $config->getTenantId();
        $tenant ??= $tenant_id ?? $base_tenant_id;

        $modules = $this->getModules($tenant);
        $moduleId = '';
        $matches = preg_grep('/mod-users-[0-9].*/',$modules);
        if($matches){
            $moduleId = implode('',$matches);
            if($moduleId){
                $object = $this->client->_request('GET', 'custom-fields', null, null, $tenant, ['headers'=>['x-okapi-module-id'=>$moduleId]]);
                return (array) $object->customFields;
            }else{
                throw new \Exception("getCustomFields: Module not found");
            }
        }
        throw new \Exception("getCustomFields: No matching modules found");
    }

    public function getCustomFieldNames(string|null $tenant_id = null): array|object {
        $customFields = [];
        foreach($this->getCustomFieldObjects($tenant_id) as $customField){
            $customFields[$customField->id] = $customField->name;
        }
        return $customFields;
    }

    public function getCustomFields(string|null $tenant_id = null): array|object {
        $customFields = [];
        foreach($this->getCustomFieldObjects($tenant_id) as $customField){
            $customFields[$customField->id] = $customField->refId;
        }
        return $customFields;
    }

}