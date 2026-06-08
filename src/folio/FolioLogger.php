<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioLogger {
    private mixed $logPath;
    private mixed $logFh = null;
    private bool $debug;
    private bool $verbose;

    public function __construct(mixed $logPath = false, bool $debug = false, bool $verbose = false) {
        $this->logPath = $logPath;
        $this->debug = $debug;
        $this->verbose = $verbose;

        if ($this->logPath && is_string($this->logPath)) {
            $this->logFh = fopen($this->logPath, 'a');
        }
    }

    public function log(string $message): void {
        if ($this->logFh) {
            fwrite($this->logFh, date('c') . " - " . $message . PHP_EOL);
        }
        if ($this->debug) {
            error_log($message);
        }
    }

    public function __destruct() {
        if ($this->logFh && is_resource($this->logFh)) {
            fclose($this->logFh);
        }
    }
}
