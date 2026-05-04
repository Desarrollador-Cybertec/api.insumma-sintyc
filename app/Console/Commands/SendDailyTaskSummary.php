<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyTaskSummaryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SendDailyTaskSummary extends Command
{
    protected $signature = 'tasks:send-daily-summary';

    protected $description = 'Generate consolidated task summaries grouped by responsible user';

    public function handle(): int
    {
        if (!SystemSetting::getValue('daily_summary_enabled', true)) {
            $this->info('Resumen diario desactivado.');
            return self::SUCCESS;
        }

        $activeTasks = Task::whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELLED->value,
            ])
            ->whereNotNull('current_responsible_user_id')
            ->with('currentResponsible')
            ->get()
            ->groupBy('current_responsible_user_id');

        $count = 0;
        $alertDays = SystemSetting::getValue('alert_days_before_due', 3);

        foreach ($activeTasks as $userId => $tasks) {
            $user = $tasks->first()->currentResponsible;
            if (!$user) {
                continue;
            }

            ['dueSoon' => $dueSoon, 'notStarted' => $notStarted, 'overdue' => $overdue] = $this->buildSummaryBuckets($tasks, $alertDays);

            $message = $this->buildSummaryMessage($user, $tasks, $dueSoon, $notStarted, $overdue, $alertDays);

            $user->notify(new DailyTaskSummaryNotification(
                summaryContent: $message,
                totalPending: $tasks->count(),
                overdueCount: $overdue->count(),
                dueSoonCount: $dueSoon->count(),
                notStartedCount: $notStarted->count(),
            ));

            $count++;
        }

        $this->info("Resúmenes generados para {$count} usuarios.");

        return self::SUCCESS;
    }

    private function buildSummaryBuckets(Collection $tasks, int $alertDays): array
    {
        $today = now()->startOfDay();
        $windowEnd = $today->copy()->addDays($alertDays);

        return [
            'dueSoon' => $tasks->filter(fn (Task $task) => $this->isDueSoon($task, $today, $windowEnd)),
            'notStarted' => $tasks->filter(fn (Task $task) => $task->status === TaskStatusEnum::PENDING),
            'overdue' => $tasks->filter(fn (Task $task) => $this->isOverdue($task, $today)),
        ];
    }

    private function buildSummaryMessage(User $user, Collection $tasks, Collection $dueSoon, Collection $notStarted, Collection $overdue, int $alertDays = 3): string
    {
        $lines = ["Resumen diario para {$user->name}:"];
        $lines[] = "Total activas: {$tasks->count()}";
        $lines[] = "Por vencer ({$alertDays} días): {$dueSoon->count()}";
        $lines[] = "Sin empezar: {$notStarted->count()}";
        $lines[] = "Vencidas: {$overdue->count()}";

        $lines[] = '';

        if ($dueSoon->isEmpty()) {
            $lines[] = "No hay tareas por vencer en los próximos {$alertDays} días.";
            return implode("\n", $lines);
        }

        $lines[] = 'Detalle de tareas por vencer:';

        foreach ($dueSoon->sortBy('due_date')->take(15) as $task) {
            $status = $task->status->value;
            $due = $task->due_date ? $task->due_date->toDateString() : 'Sin fecha';
            $lines[] = "- {$task->title} (Estado: {$status}, vence: {$due})";
        }

        if ($dueSoon->count() > 15) {
            $remaining = $dueSoon->count() - 15;
            $lines[] = "... y {$remaining} tarea(s) por vencer más.";
        }

        return implode("\n", $lines);
    }

    private function isDueSoon(Task $task, Carbon $today, Carbon $windowEnd): bool
    {
        $dueDate = $task->due_date?->copy()->startOfDay();

        return $dueDate !== null
            && $dueDate->greaterThanOrEqualTo($today)
            && $dueDate->lessThanOrEqualTo($windowEnd);
    }

    private function isOverdue(Task $task, Carbon $today): bool
    {
        $dueDate = $task->due_date?->copy()->startOfDay();

        return $dueDate !== null && $dueDate->lt($today);
    }
}
