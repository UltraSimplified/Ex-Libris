<?php

namespace Ultrasimplified\Gallery;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::script('gallery', __DIR__.'/../dist/js/field.js');
            Nova::style('gallery', __DIR__.'/../dist/css/field.css');
        });
    }

    /**
     * Register the field's upload route, behind Nova's own sign-in and gate.
     */
    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(config('nova.api_middleware', []))
            ->prefix('nova-vendor/gallery')
            ->group(__DIR__.'/../routes/api.php');
    }
}
