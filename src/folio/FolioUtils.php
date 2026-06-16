<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioUtils {
    public function isValidUuid(string $uuid): bool {
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[4-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
    }

    public function isJson(?string $string): bool {
        if (!$string) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}