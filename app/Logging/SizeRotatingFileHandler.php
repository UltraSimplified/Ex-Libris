<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes to a fixed filename and rotates by size, shifting older generations to
 * `.1`, `.2`, and so on, discarding whatever falls past `$maxFiles`.
 *
 * Laravel Boost reads `storage/logs/browser.log` from a hardcoded path, so the
 * date-based `daily` driver would leave its `browser-logs` tool with nothing to
 * find. Rotating underneath a stable filename keeps that tool working while
 * stopping the file from growing without limit.
 */
class SizeRotatingFileHandler extends StreamHandler
{
    public function __construct(
        protected string $filename,
        protected int $maxBytes = 10485760,
        protected int $maxFiles = 3,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($filename, $level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->rotateIfOversized();

        parent::write($record);
    }

    /**
     * Rotate once the active file has reached its ceiling. The stream is closed
     * first so the parent reopens it at the original path on the next write.
     */
    protected function rotateIfOversized(): void
    {
        clearstatcache(true, $this->filename);

        if (! is_file($this->filename) || filesize($this->filename) < $this->maxBytes) {
            return;
        }

        $this->close();

        for ($generation = $this->maxFiles - 1; $generation >= 1; $generation--) {
            $olderName = $this->filename.'.'.$generation;

            if (is_file($olderName)) {
                rename($olderName, $this->filename.'.'.($generation + 1));
            }
        }

        rename($this->filename, $this->filename.'.1');
    }
}
