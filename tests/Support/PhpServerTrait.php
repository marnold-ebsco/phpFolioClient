<?php declare(strict_types=1);
namespace phpFolioClient\Tests\Support;

/**
 * Starts/stops a real PHP built-in web server (`php -S`) for tests that
 * exercise code paths which construct their own Guzzle client internally
 * (so a MockHandler can't be injected) — namely FolioAuth::refreshTokens()
 * and FolioFileHandler::getFile(). Both are pointed at a single shared
 * router fixture (see tests/fixtures/mock_server_router.php).
 */
trait PhpServerTrait {
    /** @var resource|null */
    private static $phpServerProcess = null;
    private static string $phpServerBaseUrl = '';

    private static function startPhpServer(string $routerScript): string {
        $port = random_int(20000, 60000);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = sprintf('php -S 127.0.0.1:%d %s', $port, escapeshellarg($routerScript));
        $process = proc_open($cmd, $descriptors, $pipes, dirname($routerScript));
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start PHP built-in server for tests.');
        }
        // Detach the pipes so the child process's output doesn't block on a full buffer.
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $baseUrl = "http://127.0.0.1:$port";
        $deadline = microtime(true) + 5;
        $up = false;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($conn) {
                fclose($conn);
                $up = true;
                break;
            }
            usleep(50000);
        }
        if (!$up) {
            proc_terminate($process);
            throw new \RuntimeException('PHP built-in test server did not start in time.');
        }

        self::$phpServerProcess = $process;
        self::$phpServerBaseUrl = $baseUrl;
        return $baseUrl;
    }

    private static function stopPhpServer(): void {
        if (is_resource(self::$phpServerProcess)) {
            proc_terminate(self::$phpServerProcess);
            proc_close(self::$phpServerProcess);
            self::$phpServerProcess = null;
        }
    }
}
