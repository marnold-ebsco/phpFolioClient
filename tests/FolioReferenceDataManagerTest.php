<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioReferenceDataManager;
use phpFolioClient\FolioUtils;
use phpFolioClient\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

final class FolioReferenceDataManagerTest extends TestCase {
    private function buildManager(array $responses, ?array &$history = null): FolioReferenceDataManager {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }
        $config = new FolioConfig([
            'okapiUrl' => 'https://okapi.example.edu',
            'tenant_id' => 'diku',
            'username' => 'diku_admin',
            'password' => 'secret',
        ]);
        $httpClient = new Client(['handler' => $stack, 'base_uri' => $config->okapiUrl]);
        $client = new FolioClient($config, new StubAuth(), new FolioUtils(), null, null, $httpClient);

        return new FolioReferenceDataManager($client);
    }

    private function jsonResponse(array $body): Response {
        return new Response(200, [], json_encode($body));
    }

    // --- locations (representative *Objects()/getX() pair) ----------------

    public function testGetLocationObjectsYieldsRecords(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['locations' => [(object) ['id' => 'loc-1', 'name' => 'Main', 'code' => 'MAIN']], 'totalRecords' => 1]),
        ]);

        $records = iterator_to_array($manager->getLocationObjects());

        $this->assertCount(1, $records);
        $this->assertSame('Main', $records[0]->name);
    }

    public function testGetLocationsReturnsIdNameMap(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['locations' => [(object) ['id' => 'loc-1', 'name' => 'Main', 'code' => 'MAIN']], 'totalRecords' => 1]),
        ]);

        $this->assertSame(['loc-1' => 'Main'], $manager->getLocations());
    }

    public function testGetLocationCodesReturnsIdCodeMap(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['locations' => [(object) ['id' => 'loc-1', 'name' => 'Main', 'code' => 'MAIN']], 'totalRecords' => 1]),
        ]);

        $this->assertSame(['loc-1' => 'MAIN'], $manager->getLocationCodes());
    }

    // --- a couple of the other categories, to exercise toIdMap() generically ---

    public function testGetAddressTypesUsesAddressTypeField(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['addressTypes' => [(object) ['id' => 'at-1', 'addressType' => 'Home']], 'totalRecords' => 1]),
        ]);

        $this->assertSame(['at-1' => 'Home'], $manager->getAddressTypes());
    }

    public function testGetPatronGroupsUsesGroupField(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['usergroups' => [(object) ['id' => 'pg-1', 'group' => 'faculty']], 'totalRecords' => 1]),
        ]);

        $this->assertSame(['pg-1' => 'faculty'], $manager->getPatronGroups());
    }

    // --- getModules() (B24: tolerate both response shapes) ----------------

    public function testGetModulesHandlesEnvelopedResponse(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [(object) ['id' => 'mod-a-1.0.0'], (object) ['id' => 'mod-b-2.0.0']]]),
        ]);

        $this->assertSame(['mod-a-1.0.0', 'mod-b-2.0.0'], $manager->getModules('diku'));
    }

    public function testGetModulesHandlesRawArrayResponse(): void {
        $manager = $this->buildManager([
            new Response(200, [], json_encode([(object) ['id' => 'mod-a-1.0.0']])),
        ]);

        $this->assertSame(['mod-a-1.0.0'], $manager->getModules('diku'));
    }

    // --- getCustomFieldObjects() (D6: now a Generator) ---------------------

    public function testGetCustomFieldObjectsYieldsFields(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [(object) ['id' => 'mod-users-19.2.1']]]),
            $this->jsonResponse(['customFields' => [(object) ['id' => 'cf-1', 'name' => 'Notes', 'refId' => 'notes-ref']]]),
        ]);

        $result = $manager->getCustomFieldObjects('diku');

        $this->assertInstanceOf(\Generator::class, $result);
        $fields = iterator_to_array($result);
        $this->assertCount(1, $fields);
        $this->assertSame('Notes', $fields[0]->name);
    }

    /**
     * B25: with multiple mod-users-* matches, the first must be used
     * cleanly — not concatenated together into a garbled string.
     */
    public function testGetCustomFieldObjectsPicksFirstMatchWhenMultipleModulesMatch(): void {
        $history = [];
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [
                (object) ['id' => 'mod-users-19.2.1'],
                (object) ['id' => 'mod-users-bl-3.0.0'],
            ]]),
            $this->jsonResponse(['customFields' => []]),
        ], $history);

        iterator_to_array($manager->getCustomFieldObjects('diku'));

        $customFieldsRequest = $history[1]['request'];
        $this->assertSame('mod-users-19.2.1', $customFieldsRequest->getHeaderLine('x-okapi-module-id'));
    }

    public function testGetCustomFieldObjectsThrowsWhenNoMatchingModule(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [(object) ['id' => 'mod-inventory-1.0.0']]]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No matching modules found');
        iterator_to_array($manager->getCustomFieldObjects('diku'));
    }

    public function testGetCustomFieldNamesReturnsIdNameMap(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [(object) ['id' => 'mod-users-19.2.1']]]),
            $this->jsonResponse(['customFields' => [(object) ['id' => 'cf-1', 'name' => 'Notes', 'refId' => 'notes-ref']]]),
        ]);

        $this->assertSame(['cf-1' => 'Notes'], $manager->getCustomFieldNames('diku'));
    }

    public function testGetCustomFieldsReturnsIdRefIdMap(): void {
        $manager = $this->buildManager([
            $this->jsonResponse(['modules' => [(object) ['id' => 'mod-users-19.2.1']]]),
            $this->jsonResponse(['customFields' => [(object) ['id' => 'cf-1', 'name' => 'Notes', 'refId' => 'notes-ref']]]),
        ]);

        $this->assertSame(['cf-1' => 'notes-ref'], $manager->getCustomFields('diku'));
    }
}
