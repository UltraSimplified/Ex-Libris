<?php

namespace Ultrasimplified\SpecificDays;

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
            Nova::script('specific-days', __DIR__.'/../dist/js/field.js');
            Nova::style('specific-days', __DIR__.'/../dist/css/field.css');
        });
    }
}
