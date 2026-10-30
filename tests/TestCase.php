<?php

namespace Tests;

use App\Services\Geocoding\PostcodeGeocoder;
use App\Support\SiteMeta;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Nova\Nova;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Database names the test suite is permitted to run against.
     *
     * Widen this only for databases that are genuinely disposable. Anything listed
     * here can and will be dropped by RefreshDatabase.
     *
     * @var list<string>
     */
    protected array $allowedTestDatabases = [':memory:'];

    /**
     * Boot the application, then verify the suite is not pointed at a real database.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->guardAgainstNonTestingDatabase();

        $this->keepVenuesOffThePostcodeService();
    }

    /**
     * Saving a venue looks its postcode up on postcodes.io, and the
     * factories give every venue a postcode, so without this every test
     * that made one would reach out to the internet. The tests of the
     * lookup itself put the real geocoder back and fake the service.
     */
    protected function keepVenuesOffThePostcodeService(): void
    {
        $this->app->instance(PostcodeGeocoder::class, new class extends PostcodeGeocoder
        {
            public function locate(?string $postcode): ?array
            {
                return null;
            }
        });
    }

    /**
     * Nova keeps what is registered with it - scripts, styles, resources -
     * in static arrays, which outlive the application from one test to the
     * next. NovaServiceProvider registers the Office's scripts on every
     * boot, so by the browser tests the Office carried each one some 700
     * times, ran every copy and froze for ten minutes or more. A real
     * request boots once, so only the test run needs this.
     *
     * Before the application goes: flushing resolves Nova's Fortify
     * settings from the container.
     */
    protected function tearDown(): void
    {
        Nova::flushState();
        SiteMeta::flush();

        parent::tearDown();
    }

    /**
     * Abort the run if the resolved database connection is not a disposable test database.
     *
     * RefreshDatabase runs `migrate:fresh`, which drops every table on the default
     * connection. A cached config file or an exported DB_CONNECTION variable will
     * silently override phpunit.xml and point that at production, so the resolved
     * values are checked here — before setUpTraits() runs a single migration.
     *
     * @throws RuntimeException
     */
    protected function guardAgainstNonTestingDatabase(): void
    {
        if ($this->app->configurationIsCached()) {
            throw new RuntimeException(
                'Refusing to run tests: the application config is cached, so phpunit.xml '
                .'cannot override the database connection. Run `php artisan config:clear` '
                .'and try again.'
            );
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite') {
            throw new RuntimeException(sprintf(
                'Refusing to run tests: the default database connection is "%s" (driver "%s"), '
                .'not sqlite. RefreshDatabase would drop every table on it. Check for an '
                .'exported DB_CONNECTION variable in your shell.',
                $connection,
                $driver ?? 'unknown',
            ));
        }

        if (! in_array($database, $this->allowedTestDatabases, true)) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests: the sqlite connection points at "%s", which is not an '
                .'allowed test database (%s). RefreshDatabase would drop every table on it.',
                $database ?? 'null',
                implode(', ', $this->allowedTestDatabases),
            ));
        }
    }
}
