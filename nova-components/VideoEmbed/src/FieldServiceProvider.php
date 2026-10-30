<?php

namespace Ultrasimplified\VideoEmbed;

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
        Nova::serving(function (ServingNova $event) {
            Nova::script('video-embed', __DIR__.'/../dist/js/field.js');
            Nova::style('video-embed', __DIR__.'/../dist/css/field.css');
        });
    }
}
