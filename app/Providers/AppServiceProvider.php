<?php

namespace App\Providers;

use App\Mail\UntrackedLettrTransport;
use App\Services\Shop\Payments\PaymentGateway;
use App\Services\Shop\Payments\StripePaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lettr\Laravel\Support\IdempotencyKeyGenerator;
use Lettr\Lettr;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLettrTransport();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        URL::forceHttps(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Replace the transport Lettr's own provider registered, which boots
     * before this one, with one that sends without tracking.
     */
    protected function configureLettrTransport(): void
    {
        Mail::extend('lettr', fn (array $config = []): UntrackedLettrTransport => new UntrackedLettrTransport(
            $this->app->make(Lettr::class),
            $config['options'] ?? [],
            $this->app->make(IdempotencyKeyGenerator::class),
            (bool) config('lettr.idempotency.enabled', true),
        ));
    }
}
