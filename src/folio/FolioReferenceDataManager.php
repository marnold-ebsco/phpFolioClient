<?php declare(strict_types=1);
namespace phpFolioClient;

use Generator;

/**
 * Convenience wrapper around {@see FolioClient} for fetching FOLIO
 * reference/lookup data (locations, material types, loan types,
 * departments, address types, patron groups, service points, modules,
 * and custom fields).
 *
 * For each kind of reference data, this class exposes two forms: a
 * `*Objects()` method that returns a `\Generator` of the full record
 * objects (streamed via {@see FolioClient::get()}), and a plain method
 * that materializes those objects into a simple `id => name` (or
 * `id => code`) array for easy lookups, via the private {@see toIdMap()} helper.
 */
class FolioReferenceDataManager{
    private FolioClient $client;

    /**
     * Create a reference data manager bound to a FOLIO client.
     *
     * @param $client The client used to fetch reference data endpoints.
     */
    public function __construct(FolioClient $client)    {
        $this->client = $client;
    }

    /**
     * Materialize a stream of record objects into an `id => $field` map.
     *
     * @param $records Iterable of record objects, each expected to have
     *                  an `id` property and the named `$field` property.
     * @param $field   Name of the property on each record to use as the map's value.
     * @return Associative array mapping each record's `id` to the value of `$field`.
     */
    private function toIdMap(iterable $records, string $field): array {
        $map = [];
        foreach ($records as $record) {
            $map[$record->id] = $record->$field;
        }
        return $map;
    }

    /**
     * Stream all location records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each location record object.
     */
    public function getLocationObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('locations', tenant_id: $tenant_id);
    }

    /**
     * Get all locations as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping location id to location name.
     */
    public function getLocations(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getLocationObjects(tenant_id: $tenant_id), 'name');
    }

    /**
     * Get all locations as an `id => code` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping location id to location code.
     */
    public function getLocationCodes(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getLocationObjects(tenant_id: $tenant_id), 'code');
    }

    /**
     * Stream all material type records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each material type record object.
     */
    public function getMaterialTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('material-types', tenant_id: $tenant_id);
    }

    /**
     * Get all material types as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping material type id to name.
     */
    public function getMaterialTypes(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getMaterialTypeObjects(tenant_id: $tenant_id), 'name');
    }

    /**
     * Stream all loan type records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each loan type record object.
     */
    public function getLoanTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('loan-types', tenant_id: $tenant_id);
    }

    /**
     * Get all loan types as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping loan type id to name.
     */
    public function getLoanTypes(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getLoanTypeObjects(tenant_id: $tenant_id), 'name');
    }

    /**
     * Stream all department records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each department record object.
     */
    public function getDepartmentObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('departments', tenant_id: $tenant_id);
    }

    /**
     * Get all departments as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping department id to name.
     */
    public function getDepartments(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getDepartmentObjects(tenant_id: $tenant_id), 'name');
    }

    /**
     * Stream all address type records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each address type record object.
     */
    public function getAddressTypeObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('addresstypes', tenant_id: $tenant_id);
    }

    /**
     * Get all address types as an `id => addressType` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping address type id to its
     *         `addressType` label.
     */
    public function getAddressTypes(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getAddressTypeObjects(tenant_id: $tenant_id), 'addressType');
    }

    /**
     * Stream all patron group records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each patron group record object.
     */
    public function getPatronGroupObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('groups', tenant_id: $tenant_id);
    }

    /**
     * Get all patron groups as an `id => group` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping patron group id to its `group` name.
     */
    public function getPatronGroups(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getPatronGroupObjects(tenant_id: $tenant_id), 'group');
    }

    /**
     * Stream all service point records.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return A `\Generator` yielding each service point record object.
     */
    public function getServicePointObjects(string|null $tenant_id = null): Generator {
        yield from $this->client->get('service-points', tenant_id: $tenant_id);
    }

    /**
     * Get all service points as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant id to query against, for ECS (consortial)
     *                   environments; null uses the client's default tenant.
     * @return Associative array mapping service point id to name.
     */
    public function getServicePoints(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getServicePointObjects(tenant_id: $tenant_id), 'name');
    }

    /**
     * Get the list of module ids installed for a tenant.
     *
     * Tolerant of two possible response shapes for this endpoint: a raw
     * JSON array of module descriptors, or an enveloped
     * `{"modules": [...]}` object like most other FOLIO collection
     * endpoints.
     *
     * @param $tenant_id Tenant to look up installed modules for; null
     *                   uses the client config's default tenant.
     * @return Array of module id strings (e.g. `mod-users-19.2.1`)
     *         installed for the tenant.
     */
    public function getModules(string|null $tenant_id = null): array {
        $config = $this->client->getConfig();
        $tenant = $tenant_id ?? $config->getTenantId();
        $response = $this->client->get("/_/proxy/tenants/$tenant/modules", key: FolioClient::RETURN_FULL_OBJECT, tenant_id: $tenant);

        $records = (is_object($response) && isset($response->modules)) ? $response->modules : $response;

        $modules = [];
        foreach ((array) $records as $record) {
            $modules[] = is_object($record) ? $record->id : $record;
        }
        return $modules;
    }

    /**
     * Stream all custom field definitions for a tenant.
     *
     * Finds the installed `mod-users-*` module id (custom fields require
     * the requesting module's id to be sent as the `x-okapi-module-id`
     * header), issues a single low-level request via
     * {@see FolioClient::rawRequest()}, and yields each of the tenant's
     * custom field definitions in turn.
     *
     * @param $tenant_id Tenant to look up custom fields for; null uses
     *                   the client config's default tenant.
     * @return A `\Generator` yielding each custom field definition object.
     * @throws \Exception If no installed module matches `mod-users-*`,
     *                     or if a match is found but resolves to an
     *                     empty module id.
     */
    public function getCustomFieldObjects(string|null $tenant_id = null): Generator {
        $config = $this->client->getConfig();
        $tenant = $tenant_id ?? $config->getTenantId();

        $modules = $this->getModules($tenant);
        $matches = array_values(preg_grep('/mod-users-[0-9].*/', $modules) ?: []);
        if (empty($matches)) {
            throw new \Exception("getCustomFields: No matching modules found");
        }
        $moduleId = $matches[0];
        if (!$moduleId) {
            throw new \Exception("getCustomFields: Module not found");
        }

        $object = $this->client->rawRequest('GET', 'custom-fields', null, null, $tenant, ['headers' => ['x-okapi-module-id' => $moduleId]]);
        yield from ($object->customFields ?? []);
    }

    /**
     * Get all custom fields as an `id => name` lookup array.
     *
     * @param $tenant_id Tenant to look up custom fields for; null uses
     *                   the client config's default tenant.
     * @return Associative array mapping custom field id to its display name.
     * @throws \Exception Propagated from {@see getCustomFieldObjects()}
     *                     if the underlying module/field lookup fails.
     */
    public function getCustomFieldNames(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getCustomFieldObjects($tenant_id), 'name');
    }

    /**
     * Get all custom fields as an `id => refId` lookup array.
     *
     * @param $tenant_id Tenant to look up custom fields for; null uses
     *                   the client config's default tenant.
     * @return Associative array mapping custom field id to its `refId`.
     * @throws \Exception Propagated from {@see getCustomFieldObjects()}
     *                     if the underlying module/field lookup fails.
     */
    public function getCustomFields(string|null $tenant_id = null): array {
        return $this->toIdMap($this->getCustomFieldObjects($tenant_id), 'refId');
    }

}
