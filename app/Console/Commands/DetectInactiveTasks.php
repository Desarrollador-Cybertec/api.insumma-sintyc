<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskInactivityNotification;
use Illuminate\Console\Command;

class DetectInactiveTasks extends Command
{
    protected $signature = 'tasks:detect-inactive';

    protected $description = 'Alert on tasks with no progress updates for a configured number of days';

    public function handle(): int
    {
        if (!SystemSetting::getValue('inactivity_alert_enabled', true)) {
            $this->info('Alertas por inactividad desactivadas.');
            return self::SUCCESS;
        }

        $inactivityDays = SystemSetting::getValue('inactivity_alert_days', 7);
        $cutoff = now()->subDays($inactivityDays);

        $inactiveTasks = Task::whereIn('status', [
                TaskStatusEnum::IN_PROGRESS->value,
                TaskStatusEnum::PENDING->value,
            ])
            ->whereNotNull('current_responsible_user_id')
            ->with('latestUpdate')
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($q) use ($cutoff) {
                    // Has updates but last one is older than cutoff
                    $q->whereHas('updates')
                      ->whereDoesntHave('updates', fn ($sub) => $sub->where('created_at', '>=', $cutoff));
                })->orWhere(function ($q) use ($cutoff) {
                    // Has no updates at all and was created before cutoff
                    $q->whereDoesntHave('updates')
                      ->where('created_at', '<', $cutoff);
                });
            })
            ->get();

        // Group by responsible user for consolidated notification
        $grouped = $inactiveTasks->groupBy('current_responsible_user_id');
        $count = 0;

        foreach ($grouped as $userId => $tasks) {
            $user = User::find($userId);
            if (!$user) continue;

            $taskData = $tasks->map(function (Task $task) {
                $lastUpdate = $task->latestUpdate;
                $daysSince = $lastUpdate
                    ? (int) $lastUpdate->created_at->diffInDays(now())
                    : (int) $task->created_at->diffInDays(now());

                return [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'days_inactive' => $daysSince,
                    'due_date' => $task->due_date?->toDateString(),
                ];
            });

            $user->notify(new TaskInactivityNotification($taskData, $inactivityDays));
            $count++;
        }

        $this->info("Se enviaron alertas de inactividad a {$count} usuarios.");

        return self::SUCCESS;
    }
}
