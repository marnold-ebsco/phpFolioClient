<?php declare(strict_types=1);
namespace phpFolioClient;

class FolioLogger {
    private mixed $logPath;
    private mixed $logFh = null;
    private bool $debug;
    private bool $verbose;
    private \DateTimeZone $timezone;

    public function __construct(mixed $logPath = false, bool $debug = false, bool $verbose = false) {
        $this->logPath = $logPath;
        print "log path: $logPath\n";
        $this->debug = $debug;
        $this->verbose = $verbose;

        if ($this->logPath && is_string($this->logPath)) {
            $this->logFh = fopen($this->logPath, 'w');
        }

        //default timezone
        $this->setTimezone('America/Chicago');
    }

    public function setTimezone(string $timezone): void {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function log(string $message,int $queryNum,?string $additionalData = null): void {
        if ($this->logFh) {
            $now = new \DateTime('now',$this->timezone);
            fwrite($this->logFh, $now->format("Y-m-d H:i:s.u e") . "\t(Query $queryNum)\t$message\t$additionalData" . PHP_EOL);
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
