<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

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

$scheduleTimezone = config('app.schedule_timezone', config('app.timezone', 'UTC'));

Schedule::command('tasks:send-daily-summary')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('daily_summary_time', '07:00') : '07:00')
    ->timezone($scheduleTimezone);

Schedule::command('tasks:send-due-reminders')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('send_reminders_time', '08:00') : '08:00')
    ->timezone($scheduleTimezone);
