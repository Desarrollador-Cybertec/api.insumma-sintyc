<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';

    protected $description = 'Send a single reminder on the task due date';

    public function handle(): int
    {
        if (!SystemSetting::getValue('send_reminders_enabled', true)) {
            $this->info('Recordatorios de vencimiento desactivados.');
            return self::SUCCESS;
        }

        $count = 0;

        $dueToday = Task::where(function ($query) {
                $query->where('notify_on_due', true)
                    ->orWhere('notify_on_overdue', true);
            })
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELLED->value,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', now()->toDateString())
            ->whereNotNull('current_responsible_user_id')
            ->with('currentResponsible')
            ->get();

        foreach ($dueToday as $task) {
            $user = $task->currentResponsible;
            if (!$user instanceof User) {
                continue;
            }

            if ($this->alreadySentToday($user, $task)) {
                continue;
            }

            $user->notify(new TaskDueSoonNotification($task, 0));
            $count++;
        }

        $this->info("Se enviaron {$count} recordatorios.");

        return self::SUCCESS;
    }

    private function alreadySentToday(User $user, Task $task): bool
    {
        return $user->notifications()
            ->where('type', TaskDueSoonNotification::class)
            ->whereDate('created_at', now()->toDateString())
            ->get()
            ->contains(function ($notification) use ($task) {
                return ($notification->data['task_id'] ?? null) === $task->id
                    && ($notification->data['due_date'] ?? null) === $task->due_date?->toDateString();
            });
    }
}
