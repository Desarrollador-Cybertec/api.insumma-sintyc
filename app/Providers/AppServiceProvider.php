<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Event listeners are auto-discovered from app/Listeners/ by Laravel 12.
     * Do NOT manually register them here — that would cause each event to fire twice.
     */
    public function boot(): void
    {
        //
    }
}
