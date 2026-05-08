<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Event listeners are auto-discovered from app/Listeners/ by Laravel 12.
     * Do NOT manually register them here — that would cause each event to fire twice.
     */
    public function boot(): void
    {
        // Pause the queue worker loop while queue_paused flag is active.
        // The worker keeps running but sleeps in 5-second intervals until resumed.
        Queue::looping(function () {
            while (Cache::get('queue_paused', false)) {
                sleep(5);
            }
        });
    }
}
