<?php

use App\Logging\SizeRotatingFileHandler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Boost\Concerns\ReadsLogs;
use Monolog\Logger;

beforeEach(function () {
    $this->logDirectory = storage_path('framework/testing/browser-log');

    File::ensureDirectoryExists($this->logDirectory);
    File::cleanDirectory($this->logDirectory);

    $this->logFile = $this->logDirectory.'/browser.log';
});

afterEach(function () {
    File::deleteDirectory($this->logDirectory);
});

/**
 * Write through a Monolog logger using the handler under test.
 */
function logThrough(string $file, int $maxBytes, int $maxFiles, int $entries): void
{
    $logger = new Logger('browser');
    $logger->pushHandler(new SizeRotatingFileHandler($file, $maxBytes, $maxFiles));

    foreach (range(1, $entries) as $entry) {
        $logger->info('entry '.$entry.' '.str_repeat('x', 200));
    }
}

test('the log rotates once it passes the size ceiling', function () {
    logThrough($this->logFile, maxBytes: 500, maxFiles: 3, entries: 20);

    // Rotation is checked before each write, so the active file settles at
    // the ceiling plus at most one entry -- never the whole 20-entry history.
    expect($this->logFile)->toBeFile()
        ->and($this->logFile.'.1')->toBeFile()
        ->and(filesize($this->logFile))->toBeLessThan(1000);
});

test('rotation keeps writing to the original filename', function () {
    logThrough($this->logFile, maxBytes: 500, maxFiles: 3, entries: 20);

    expect(File::get($this->logFile))->toContain('entry 20');
});

test('older generations are discarded past the file limit', function () {
    logThrough($this->logFile, maxBytes: 500, maxFiles: 2, entries: 60);

    expect($this->logFile.'.1')->toBeFile()
        ->and($this->logFile.'.2')->toBeFile()
        ->and($this->logFile.'.3')->not->toBeFile();
});

test('nothing rotates while the log stays under the ceiling', function () {
    logThrough($this->logFile, maxBytes: 1048576, maxFiles: 3, entries: 5);

    expect($this->logFile)->toBeFile()
        ->and($this->logFile.'.1')->not->toBeFile();
});

test('the browser channel writes entries Boost can still parse', function () {
    config()->set('logging.channels.browser.handler_with.filename', $this->logFile);

    Log::channel('browser')->info('a browser event');

    $reader = new class
    {
        use ReadsLogs;

        /**
         * @return string[]
         */
        public function lastEntries(string $file, int $count): array
        {
            return $this->readLastLogEntries($file, $count);
        }
    };

    $entries = $reader->lastEntries($this->logFile, 5);

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \w+\.INFO: a browser event/');
});

test('the browser channel is configured to rotate', function () {
    expect(config('logging.channels.browser.handler'))->toBe(SizeRotatingFileHandler::class)
        ->and(config('logging.channels.browser.handler_with.filename'))->toBe(storage_path('logs/browser.log'));
});
