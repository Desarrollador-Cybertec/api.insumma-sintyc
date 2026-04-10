<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueSoonNotification;
use App\Notifications\TaskOverdueNotification;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';

    protected $description = 'Send reminders for tasks due soon or overdue with notify flags enabled';

    public function handle(): int
    {
        if (!SystemSetting::getValue('send_reminders_enabled', true)) {
            $this->info('Recordatorios de vencimiento desactivados.');
            return self::SUCCESS;
        }

        $count = 0;
        $alertDays = SystemSetting::getValue('alert_days_before_due', 3);

        // Due today or in N days with notify_on_due
        $dueSoon = Task::where('notify_on_due', true)
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELLED->value,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays($alertDays)->endOfDay())
            ->whereNotNull('current_responsible_user_id')
            ->get();

        foreach ($dueSoon as $task) {
            $user = User::find($task->current_responsible_user_id);
            if (!$user) continue;

            $daysLeft = (int) now()->startOfDay()->diffInDays($task->due_date, false);
            $user->notify(new TaskDueSoonNotification($task, $daysLeft));
            $count++;
        }

        // Overdue with notify_on_overdue
        $overdue = Task::where('notify_on_overdue', true)
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELLED->value,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->whereNotNull('current_responsible_user_id')
            ->get();

        foreach ($overdue as $task) {
            $user = User::find($task->current_responsible_user_id);
            if (!$user) continue;

            $daysOverdue = (int) $task->due_date->diffInDays(now());
            $user->notify(new TaskOverdueNotification($task, $daysOverdue));
            $count++;
        }

        $this->info("Se enviaron {$count} recordatorios.");

        return self::SUCCESS;
    }
}
