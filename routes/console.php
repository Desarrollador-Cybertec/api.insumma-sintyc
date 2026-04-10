<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$canReadSettings = Schema::hasTable('system_settings');
if ($canReadSettings) {
    SystemSetting::preload();
}

Schedule::command('tasks:detect-overdue')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('detect_overdue_time', '06:00') : '06:00');

Schedule::command('tasks:send-daily-summary')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('daily_summary_time', '07:00') : '07:00');

Schedule::command('tasks:send-due-reminders')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('send_reminders_time', '08:00') : '08:00');

Schedule::command('tasks:detect-inactive')
    ->dailyAt($canReadSettings ? SystemSetting::getValue('inactivity_alert_time', '09:00') : '09:00');
