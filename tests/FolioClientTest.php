<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioConfig;
use phpFolioClient\FolioUtils;
use phpFolioClient\Tests\Support\StubAuth;
use PHPUnit\Framework\TestCase;

final class FolioClientTest extends TestCase {
    private FolioConfig $config;

    protected function setUp(): void {
        $this->config = new FolioConfig([
            'okapiUrl' => 'https://okapi.example.edu',
            'tenant_id' => 'diku',
            'username' => 'diku_admin',
            'password' => 'secret',
        ]);
    }

    /**
     * Builds a FolioClient backed by a MockHandler queue. Pass a variable
     * by reference as $history to capture each {request, response,
     * options} entry Guzzle's history middleware records.
     */
    private function buildClient(array $responses, ?array &$history = null, int $maxRetries = 0, int $retryBaseDelayMs = 1): FolioClient {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }
        $httpClient = new Client(['handler' => $stack, 'base_uri' => $this->config->okapiUrl]);

        return new FolioClient($this->config, new StubAuth(), new FolioUtils(), null, null, $httpClient, $maxRetries, $retryBaseDelayMs);
    }

    private function jsonResponse(int $status, array $body, array $headers = []): Response {
        // An empty PHP array encodes as a JSON array ("[]"), not an empty
        // object ("{}"); several endpoints here (e.g. post()) type-hint
        // their return as ?object, so an empty body must decode as an
        // object, matching how a real FOLIO endpoint would respond.
        return new Response($status, $headers, json_encode(empty($body) ? new \stdClass() : $body));
    }

    // --- get() ---------------------------------------------------------

    public function testGetYieldsRecordsFromInferredKey(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [(object) ['id' => 'a'], (object) ['id' => 'b']], 'totalRecords' => 2]),
        ]);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertCount(2, $records);
        $this->assertSame('a', $records[0]->id);
    }

    public function testGetWithReturnFullObjectReturnsRawResponse(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [], 'totalRecords' => 0]),
        ]);

        $response = $client->get('/inventory/instances', null, null, FolioClient::RETURN_FULL_OBJECT);

        $this->assertSame(0, $response->totalRecords);
    }

    /**
     * B6: a null/empty body (e.g. a 204) must yield no records instead of throwing.
     */
    public function testGetYieldsNothingOnEmptyResponseBody(): void {
        $client = $this->buildClient([new Response(204, [], '')]);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertSame([], $records);
    }

    /**
     * B5: a response whose only array property is "errors" must not crash.
     */
    public function testGetYieldsNothingWhenOnlyErrorsKeyPresent(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['errors' => [['message' => 'bad query']]]),
        ]);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertSame([], $records);
    }

    // --- getOne() --------------------------------------------------------

    public function testGetOneReturnsRecordForValidUuid(): void {
        $id = 'e4a1c3d0-1234-4abc-89ab-1234567890ab';
        $client = $this->buildClient([
            $this->jsonResponse(200, ['id' => $id, 'title' => 'A Book']),
        ]);

        $record = $client->getOne('/inventory/instances', $id);

        $this->assertSame($id, $record->id);
    }

    public function testGetOneThrowsOnInvalidUuid(): void {
        $client = $this->buildClient([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('valid UUID');
        $client->getOne('/inventory/instances', 'not-a-uuid');
    }

    // --- getEach() ---------------------------------------------------------

    /**
     * B4: getEach() must reject RETURN_FULL_OBJECT rather than silently
     * breaking its declared \Generator return type.
     */
    public function testGetEachRejectsReturnFullObject(): void {
        $client = $this->buildClient([]);

        $this->expectException(\InvalidArgumentException::class);
        $client->getEach('/inventory/instances', null, null, (string) FolioClient::RETURN_FULL_OBJECT);
    }

    public function testGetEachYieldsRecords(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [(object) ['id' => 'a']], 'totalRecords' => 1]),
        ]);

        $records = iterator_to_array($client->getEach('/inventory/instances'));

        $this->assertCount(1, $records);
    }

    // --- getAll() (id-cursor pagination) -------------------------------

    public function testGetAllPaginatesAcrossMultiplePages(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '1'], (object) ['id' => '2']], 'totalRecords' => 3]),
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '3']], 'totalRecords' => 3]),
            $this->jsonResponse(200, ['instances' => [], 'totalRecords' => 3]),
        ]);

        $records = iterator_to_array($client->getAll('/inventory/instances'));

        $this->assertSame(['1', '2', '3'], array_map(fn($r) => $r->id, $records));
    }

    public function testGetAllYieldsNothingWhenFirstPageEmpty(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [], 'totalRecords' => 0]),
        ]);

        $records = iterator_to_array($client->getAll('/inventory/instances'));

        $this->assertSame([], $records);
    }

    // --- getAll_loop() (offset/limit pagination) ------------------------

    public function testGetAllLoopPaginatesByOffset(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '1'], (object) ['id' => '2']], 'totalRecords' => 3]),
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '3']], 'totalRecords' => 3]),
        ]);

        $records = iterator_to_array($client->getAll_loop('/inventory/instances', null, ['limit' => 2]));

        $this->assertSame(['1', '2', '3'], array_map(fn($r) => $r->id, $records));
    }

    // --- put()/patch()/post()/delete() ----------------------------------

    public function testPutSendsJsonBodyToCorrectEndpoint(): void {
        $history = [];
        $client = $this->buildClient([new Response(200, [], '')], $history);

        $client->put('/inventory/instances', 'abc-123', ['title' => 'Updated']);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/inventory/instances/abc-123', (string) $request->getUri());
        $this->assertSame(['title' => 'Updated'], json_decode((string) $request->getBody(), true));
    }

    public function testPatchSendsJsonBody(): void {
        $history = [];
        $client = $this->buildClient([new Response(200, [], '')], $history);

        $client->patch('/inventory/instances', 'abc-123', ['status' => 'active']);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame(['status' => 'active'], json_decode((string) $request->getBody(), true));
    }

    public function testPostReturnsDecodedResponse(): void {
        $client = $this->buildClient([$this->jsonResponse(201, ['id' => 'new-id'])]);

        $result = $client->post('/inventory/instances', ['title' => 'New']);

        $this->assertSame('new-id', $result->id);
    }

    public function testDeleteSendsCorrectMethodAndPath(): void {
        $history = [];
        $client = $this->buildClient([new Response(204, [], '')], $history);

        $client->delete('/inventory/instances', 'abc-123');

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('/inventory/instances/abc-123', (string) $request->getUri());
    }

    // --- rawRequest() (D11) ----------------------------------------------

    public function testRawRequestDelegatesToInternalRequest(): void {
        $client = $this->buildClient([$this->jsonResponse(200, ['ok' => true])]);

        $result = $client->rawRequest('GET', '/some/endpoint');

        $this->assertTrue($result->ok);
    }

    // --- status/query accessors ------------------------------------------

    public function testLastStatusCodeDefaultsToZeroBeforeAnyRequest(): void {
        $client = $this->buildClient([]);

        $this->assertSame(0, $client->getLastStatusCode());
        $this->assertSame(0, $client->getStatusCode());
    }

    public function testLastStatusCodeReflectsMostRecentRequest(): void {
        $client = $this->buildClient([$this->jsonResponse(201, [])]);

        $client->post('/inventory/instances', []);

        $this->assertSame(201, $client->getLastStatusCode());
        $this->assertSame(201, $client->getStatusCode());
    }

    /**
     * D10: getLastQueryNum() must reflect the request just made, not the
     * "next" one — off by one before the fix.
     */
    public function testLastQueryNumReflectsCompletedRequestCount(): void {
        $client = $this->buildClient([
            $this->jsonResponse(200, []),
            $this->jsonResponse(200, []),
        ]);

        $client->post('/a', []);
        $this->assertSame(1, $client->getLastQueryNum());

        $client->post('/b', []);
        $this->assertSame(2, $client->getLastQueryNum());
    }

    public function testGetVersionReturnsVersionConstant(): void {
        $client = $this->buildClient([]);

        $this->assertSame(FolioClient::VERSION, $client->getVersion());
    }

    public function testGetInformationReturnsAutoCreatedInstance(): void {
        $client = $this->buildClient([]);

        $this->assertInstanceOf(\phpFolioClient\FolioInformation::class, $client->getInformation());
    }

    // --- retry/backoff ----------------------------------------------------

    public function testGetRetriesOnServerErrorThenSucceeds(): void {
        $history = [];
        $client = $this->buildClient([
            new Response(503, [], 'server error'),
            new Response(503, [], 'server error'),
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '1']], 'totalRecords' => 1]),
        ], $history, maxRetries: 3, retryBaseDelayMs: 1);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertCount(1, $records);
        $this->assertCount(3, $history);
    }

    public function testGetDoesNotRetryOnClientError(): void {
        $history = [];
        $client = $this->buildClient([new Response(400, [], 'bad request')], $history, maxRetries: 3, retryBaseDelayMs: 1);

        $this->expectException(ClientException::class);
        try {
            iterator_to_array($client->get('/inventory/instances'));
        } finally {
            $this->assertCount(1, $history);
        }
    }

    public function testGetExhaustsRetriesThenThrows(): void {
        $history = [];
        $client = $this->buildClient([
            new Response(503, [], 'e1'),
            new Response(503, [], 'e2'),
            new Response(503, [], 'e3'),
            new Response(503, [], 'e4'),
        ], $history, maxRetries: 3, retryBaseDelayMs: 1);

        try {
            iterator_to_array($client->get('/inventory/instances'));
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertCount(4, $history);
        }
    }

    /**
     * POST is never auto-retried, even on a transient-looking 503, since a
     * lost response doesn't guarantee the create was never applied.
     */
    public function testPostIsNeverRetriedOnServerError(): void {
        $history = [];
        $client = $this->buildClient([
            new Response(503, [], 'server error'),
            $this->jsonResponse(200, ['id' => 'new-id']),
        ], $history, maxRetries: 3, retryBaseDelayMs: 1);

        try {
            $client->post('/inventory/instances', ['title' => 'x']);
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertCount(1, $history);
        }
    }

    public function testGetRetriesOn429RespectingRetryAfter(): void {
        $history = [];
        $client = $this->buildClient([
            new Response(429, ['Retry-After' => '0'], 'rate limited'),
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '1']], 'totalRecords' => 1]),
        ], $history, maxRetries: 3, retryBaseDelayMs: 1);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertCount(1, $records);
        $this->assertCount(2, $history);
    }

    public function testGetRetriesOnConnectException(): void {
        $history = [];
        $client = $this->buildClient([
            new ConnectException('could not connect', new Request('GET', '/inventory/instances')),
            $this->jsonResponse(200, ['instances' => [(object) ['id' => '1']], 'totalRecords' => 1]),
        ], $history, maxRetries: 3, retryBaseDelayMs: 1);

        $records = iterator_to_array($client->get('/inventory/instances'));

        $this->assertCount(1, $records);
        $this->assertCount(2, $history);
    }

    public function testMaxRetriesZeroDisablesRetries(): void {
        $history = [];
        $client = $this->buildClient([
            new Response(503, [], 'server error'),
            $this->jsonResponse(200, ['instances' => [], 'totalRecords' => 0]),
        ], $history, maxRetries: 0, retryBaseDelayMs: 1);

        try {
            iterator_to_array($client->get('/inventory/instances'));
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertCount(1, $history);
        }
    }

    // --- internal helpers (reflection) -----------------------------------

    /**
     * B5: _getResponseInfo() must not crash and must gracefully report a
     * null key when "errors" is the only array property present.
     */
    public function testGetResponseInfoHandlesErrorsOnlyResponse(): void {
        $client = $this->buildClient([]);
        $method = new \ReflectionMethod($client, '_getResponseInfo');

        $result = $method->invoke($client, (object) ['errors' => [['message' => 'bad']]]);

        $this->assertNull($result['key']);
    }

    public function testGetResponseInfoFindsNonErrorsArrayKey(): void {
        $client = $this->buildClient([]);
        $method = new \ReflectionMethod($client, '_getResponseInfo');

        $result = $method->invoke($client, (object) ['errors' => [], 'instances' => [1, 2], 'totalRecords' => 2]);

        $this->assertSame('instances', $result['key']);
        $this->assertSame(2, $result['totalRecords']);
    }

    /**
     * B3: _handleParameters() must accept a bare UUID string and turn it
     * into an `id="..."` CQL query, instead of throwing a TypeError.
     */
    public function testHandleParametersAcceptsUuidString(): void {
        $client = $this->buildClient([]);
        $method = new \ReflectionMethod($client, '_handleParameters');

        $id = 'e4a1c3d0-1234-4abc-89ab-1234567890ab';
        $result = $method->invoke($client, 'GET', $id, null);

        $this->assertSame('id="' . $id . '" sortBy id', $result['query']);
    }

    public function testHandleParametersAcceptsJsonString(): void {
        $client = $this->buildClient([]);
        $method = new \ReflectionMethod($client, '_handleParameters');

        $result = $method->invoke($client, 'GET', '{"limit":10}', null);

        $this->assertSame(10, $result['limit']);
    }

    public function testHandleParametersExplicitQueryOverridesImplicit(): void {
        $client = $this->buildClient([]);
        $method = new \ReflectionMethod($client, '_handleParameters');

        $result = $method->invoke($client, 'GET', ['query' => 'ignored'], 'title="foo"');

        $this->assertSame('title="foo"', $result['query']);
    }
}
