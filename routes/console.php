<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    $canReadSettings = Schema::hasTable('system_settings');
    if ($canReadSettings) {
        SystemSetting::preload();
    }
} catch (\Throwable $e) {
    $canReadSettings = false;
}
