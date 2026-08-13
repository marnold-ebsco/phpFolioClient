<?php declare(strict_types=1);
namespace phpFolioClient\Tests\Support;

use phpFolioClient\FolioAuth;

/**
 * A FolioAuth stand-in that never performs a real login request — used by
 * tests for classes that only need *a* bearer token (FolioClient,
 * FolioFileHandler, FolioInformation, etc.), as opposed to tests of
 * FolioAuth's own login/refresh behavior.
 */
class StubAuth extends FolioAuth {
    public function __construct(private string $tokenValue = 'stub-token') {
        // Deliberately skip the parent constructor: it only sets up
        // $ATExpiresObj, which callers of this stub don't need.
    }

    public function getAccessToken(): string {
        return $this->tokenValue;
    }
}
