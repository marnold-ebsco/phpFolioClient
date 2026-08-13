<?php declare(strict_types=1);
namespace phpFolioClient\Tests;

use GuzzleHttp\Exception\ClientException;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioConfig;
use phpFolioClient\Tests\Support\PhpServerTrait;
use PHPUnit\Framework\TestCase;

/**
 * FolioAuth::refreshTokens() builds its own Guzzle client internally
 * (it's not injectable), so the login-flow tests here run against a real
 * local PHP built-in server (see tests/fixtures/mock_server_router.php)
 * rather than a MockHandler. Everything that doesn't require an actual
 * HTTP call is tested directly via reflection instead.
 */
final class FolioAuthTest extends TestCase {
    use PhpServerTrait;

    private static string $baseUrl;

    public static function setUpBeforeClass(): void {
        self::$baseUrl = self::startPhpServer(__DIR__ . '/fixtures/mock_server_router.php');
    }

    public static function tearDownAfterClass(): void {
        self::stopPhpServer();
    }

    private function makeConfig(string $username): FolioConfig {
        return new FolioConfig([
            'okapiUrl' => self::$baseUrl,
            'tenant_id' => 'diku',
            'username' => $username,
            'password' => 'secret',
            'sslVerify' => false,
        ]);
    }

    public function testConstructorInitializesExpirationToNow(): void {
        $auth = new FolioAuth($this->makeConfig('diku_admin'));

        $this->assertSame(0, $auth->getExpiration());
        $this->assertEqualsWithDelta(time(), $auth->ATExpiresObj->getTimestamp(), 5);
    }

    public function testGetAuthFlavorIsRtr(): void {
        $auth = new FolioAuth($this->makeConfig('diku_admin'));

        $this->assertSame('RTR', $auth->getAuthFlavor());
    }

    /**
     * @dataProvider needsRefreshProvider
     */
    public function testNeedsRefresh(string $token, int $atExpires, int $needsRefreshBefore, bool $expected): void {
        $auth = new FolioAuth($this->makeConfig('diku_admin'));
        $auth->needsRefreshBeforeExpires = $needsRefreshBefore;
        $auth->ATExpires = $atExpires;

        $tokenProp = new \ReflectionProperty($auth, 'token');
        $tokenProp->setValue($auth, $token);

        $method = new \ReflectionMethod($auth, 'needsRefresh');

        $this->assertSame($expected, $method->invoke($auth));
    }

    public static function needsRefreshProvider(): array {
        return [
            'no token yet' => ['', 0, 60, true],
            'token, far future expiry' => ['tok', PHP_INT_MAX, 60, false],
            'token, expiry within refresh window' => ['tok', time() + 30, 60, true],
            'token, already expired' => ['tok', time() - 10, 60, true],
        ];
    }

    public function testDebugInfoRedactsToken(): void {
        $auth = new FolioAuth($this->makeConfig('diku_admin'));

        $tokenProp = new \ReflectionProperty($auth, 'token');
        $tokenProp->setValue($auth, 'super-secret-token');

        $vars = $auth->__debugInfo();

        $this->assertSame('***REDACTED***', $vars['token']);
    }

    public function testSuccessfulLoginSetsTokenAndExpiration(): void {
        $auth = new FolioAuth($this->makeConfig('diku_admin'));

        $token = $auth->getAccessToken();

        $this->assertSame('mock-access-token-abc123', $token);
        $this->assertGreaterThan(time(), $auth->getExpiration());
    }

    public function testGetAccessTokenDoesNotRefetchWhenNotExpired(): void {
        $hitsFile = sys_get_temp_dir() . '/folio_test_login_hits.log';
        @unlink($hitsFile);

        $auth = new FolioAuth($this->makeConfig('diku_admin'));

        $auth->getAccessToken();
        $auth->getAccessToken();
        $auth->getAccessToken();

        $hits = file_exists($hitsFile) ? count(file($hitsFile)) : 0;
        $this->assertSame(1, $hits, 'Only the first call should have hit the login endpoint.');

        @unlink($hitsFile);
    }

    public function testGetAccessTokenRefetchesOnceExpired(): void {
        $hitsFile = sys_get_temp_dir() . '/folio_test_login_hits.log';
        @unlink($hitsFile);

        $auth = new FolioAuth($this->makeConfig('diku_admin'));
        $auth->getAccessToken();

        // Force the cached token to look expired.
        $auth->ATExpires = time() - 10;
        $auth->getAccessToken();

        $hits = file_exists($hitsFile) ? count(file($hitsFile)) : 0;
        $this->assertSame(2, $hits, 'An expired token must trigger a second login request.');

        @unlink($hitsFile);
    }

    public function testThrowsWhenNoCookieIsReturned(): void {
        $auth = new FolioAuth($this->makeConfig('no_cookie_user'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not get folio access token');
        $auth->getAccessToken();
    }

    /**
     * The explicit `$response->getStatusCode() != '201'` check in
     * refreshTokens() only matters for a 2xx status that isn't 201 —
     * anything 4xx/5xx is already turned into a Guzzle exception first.
     */
    public function testThrowsWhenStatusIsNot201(): void {
        $auth = new FolioAuth($this->makeConfig('wrong_status_user'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Authentication failed: 200');
        $auth->getAccessToken();
    }

    /**
     * D1: the original ClientException must be preserved as the cause of
     * the re-thrown generic \Exception, not discarded.
     */
    public function testUnauthorizedResponseChainsOriginalException(): void {
        $auth = new FolioAuth($this->makeConfig('unauthorized_user'));

        try {
            $auth->getAccessToken();
            $this->fail('Expected an exception to be thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Authentication failed', $e->getMessage());
            $this->assertInstanceOf(ClientException::class, $e->getPrevious());
        }
    }
}
