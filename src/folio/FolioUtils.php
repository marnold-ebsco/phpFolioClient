<?php declare(strict_types=1);
namespace phpFolioClient;

/**
 * Small standalone helper functions shared across the library.
 *
 * Holds no state and no dependencies on other Folio* classes; it is
 * injected into {@see FolioClient} (and used internally there) to
 * validate identifiers and detect JSON-encoded strings before deciding
 * how to build request parameters.
 */
class FolioUtils {
    /**
     * Check whether a string is a valid FOLIO record UUID.
     *
     * Deliberately narrow: this only accepts UUID versions 4 and 5 with
     * an RFC-4122 variant-1 layout (i.e. it matches
     * `xxxxxxxx-xxxx-[45]xxx-[89ab]xxx-xxxxxxxxxxxx`), which is the
     * format FOLIO itself generates for record ids. Other UUID
     * versions/variants (1, 2, 3, nil, etc.) are intentionally rejected
     * even though they may be otherwise well-formed. This is NOT a
     * general-purpose UUID validator — do not reuse it outside of FOLIO
     * id validation.
     *
     * @param $uuid The string to validate.
     * @return True if `$uuid` matches the accepted UUID v4/v5 pattern.
     */
    public function isValidUuid(string $uuid): bool {
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[4-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
    }

    /**
     * Check whether a string is valid JSON.
     *
     * @param $string The string to test, or null.
     * @return True if `$string` is non-null/non-empty and decodes
     *         without a JSON error. Note that `"0"` is valid JSON (it
     *         decodes to the integer `0`) and correctly returns true,
     *         even though it is falsy as a PHP string.
     */
    public function isJson(?string $string): bool {
        if ($string === null || $string === '') {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}