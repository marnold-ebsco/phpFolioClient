<?php declare(strict_types=1);
/**
 * Minimal router for PHP's built-in web server, used by integration-style
 * tests for the two code paths in this library that construct their own
 * Guzzle client internally and so can't take an injected MockHandler:
 * FolioAuth::refreshTokens() (POST /authn/login) and
 * FolioFileHandler::getFile() (GET /download/{ok|notfound}).
 */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'POST' && $path === '/authn/login') {
    $body = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $username = $body['username'] ?? '';

    // Always record a hit so tests can verify how many real login
    // requests were made (FolioAuth always POSTs to this fixed path with
    // no query string, so a per-test counter file can't be threaded
    // through — every test run shares this one file and clears it first).
    file_put_contents(sys_get_temp_dir() . '/folio_test_login_hits.log', "hit\n", FILE_APPEND | LOCK_EX);

    if ($username === 'no_cookie_user') {
        http_response_code(201);
        header('X-Okapi-Token: token-no-cookie');
        echo '{}';
        return true;
    }

    if ($username === 'wrong_status_user') {
        http_response_code(200);
        header('X-Okapi-Token: token-wrong-status');
        echo '{}';
        return true;
    }

    if ($username === 'unauthorized_user') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo '{"errorMessage":"invalid credentials"}';
        return true;
    }

    $expires = time() + 3600;
    http_response_code(201);
    header('X-Okapi-Token: mock-access-token-abc123');
    header('Set-Cookie: folioAccessToken=mock-refresh-value; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT; Path=/; HttpOnly');
    echo '{}';
    return true;
}

if ($method === 'GET' && preg_match('#^/download/(ok|notfound)$#', (string) $path, $m)) {
    if ($m[1] === 'notfound') {
        http_response_code(404);
        echo 'not found';
        return true;
    }
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'mock exported file content';
    return true;
}

http_response_code(404);
echo 'no route';
return true;
