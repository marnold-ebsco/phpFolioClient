<?php declare(strict_types=1);
namespace phpFolioClient;

/**
 * Simple optional logger for API activity performed by {@see FolioClient}.
 *
 * When constructed with a log file path, appends timestamped log lines
 * to that file (existing content is preserved); when `debug` is enabled,
 * also mirrors messages to `error_log()`. Injected into (and called by)
 * `FolioClient::_request()` to record each outgoing request and its
 * resulting status code; a null logger effectively disables logging.
 */
class FolioLogger {
    private mixed $logPath;
    private mixed $logFh = null;
    private bool $debug;
    private bool $verbose;
    private \DateTimeZone $timezone;

    /**
     * Create a logger, optionally opening a log file for writing.
     *
     * @param $logPath  Path to a log file to open for appending, or
     *                  `false`/falsy to disable file logging. Existing
     *                  log content at this path is preserved (not
     *                  truncated) across multiple `FolioLogger` instances.
     * @param $debug    Whether to also mirror log messages to PHP's
     *                  `error_log()`.
     * @param $verbose  Reserved flag for verbose logging; stored but not
     *                  currently consulted by this class.
     * @param $timezone Timezone used when timestamping log entries; pass
     *                  `$config->localTimeZone` here to keep log
     *                  timestamps consistent with {@see FolioAuth}'s
     *                  token-expiration timezone. Defaults to `'America/Chicago'`.
     */
    public function __construct(mixed $logPath = false, bool $debug = false, bool $verbose = false, string $timezone = 'America/Chicago') {
        $this->logPath = $logPath;
        $this->debug = $debug;
        $this->verbose = $verbose;

        if ($this->logPath && is_string($this->logPath)) {
            $this->logFh = fopen($this->logPath, 'a');
        }

        $this->setTimezone($timezone);
    }

    /**
     * Set the timezone used when timestamping log entries.
     *
     * @param $timezone A valid PHP timezone identifier (e.g. `'America/Chicago'`).
     */
    public function setTimezone(string $timezone): void {
        $this->timezone = new \DateTimeZone($timezone);
    }

    /**
     * Write a single log entry, if file logging and/or debug logging is enabled.
     *
     * @param $message        The log message to record.
     * @param $queryNum       The sequence number of the API call this
     *                        entry relates to, included in the log line.
     * @param $additionalData Optional extra context (e.g. content length)
     *                        appended to the log line.
     */
    public function log(string $message,int $queryNum,?string $additionalData = null): void {
        if ($this->logFh) {
            $now = new \DateTime('now',$this->timezone);
            fwrite($this->logFh, $now->format("Y-m-d H:i:s.u e") . "\t(Query $queryNum)\t$message\t$additionalData" . PHP_EOL);
        }
        if ($this->debug) {
            error_log($message);
        }
    }

    /**
     * Close the log file handle, if one is open, when the logger is destroyed.
     */
    public function __destruct() {
        if ($this->logFh && is_resource($this->logFh)) {
            fclose($this->logFh);
        }
    }
}
