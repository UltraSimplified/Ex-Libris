<?php

namespace App\Providers;

use App\Services\Geo\CloudflareGeoResolver;
use App\Services\Geo\GeoResolver;
use App\Services\Geo\LocalityBucketer;
use App\Services\Geo\NullGeoResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the geolocation resolver and locality bucketer into the container.
 */
final class GeoServiceProvider extends ServiceProvider
{
    /**
     * Register the GeoResolver driver and the LocalityBucketer singleton.
     */
    public function register(): void
    {
        $this->app->singleton(GeoResolver::class, function () {
            return match (config('geo.driver')) {
                'cloudflare' => new CloudflareGeoResolver,
                default => new NullGeoResolver,
            };
        });

        $this->app->singleton(LocalityBucketer::class, function () {
            return new LocalityBucketer(config('geo.buckets', []));
        });
    }
}
