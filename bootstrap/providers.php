<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GeoServiceProvider;
use App\Providers\NovaServiceProvider;
use Laravel\Cashier\CashierServiceProvider;

    return [
    AppServiceProvider::class,
    CashierServiceProvider::class,
    FortifyServiceProvider::class,
    NovaServiceProvider::class,
    GeoServiceProvider::class,
];
