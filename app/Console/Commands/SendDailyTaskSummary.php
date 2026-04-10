<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyTaskSummaryNotification;
use Illuminate\Console\Command;
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
            ->with(['currentResponsible', 'updates'])
            ->get()
            ->groupBy('current_responsible_user_id');

        $count = 0;
        $alertDays = SystemSetting::getValue('alert_days_before_due', 3);

        foreach ($activeTasks as $userId => $tasks) {
            $user = $tasks->first()->currentResponsible;
            if (!$user) {
                continue;
            }

            $overdue = $tasks->filter(fn (Task $t) => $t->isOverdue());
            $dueSoon = $tasks->filter(function (Task $t) use ($alertDays) {
                /** @var \Illuminate\Support\Carbon|null $dueDate */
                $dueDate = $t->due_date;
                return $dueDate !== null
                    && $dueDate->isFuture()
                    && $dueDate->diffInDays(now()) <= $alertDays;
            });

            $message = $this->buildSummaryMessage($user, $tasks, $overdue, $dueSoon, $alertDays);

            $user->notify(new DailyTaskSummaryNotification(
                summaryContent: $message,
                totalPending: $tasks->count(),
                overdueCount: $overdue->count(),
                dueSoonCount: $dueSoon->count(),
            ));

            $count++;
        }

        $this->info("Resúmenes generados para {$count} usuarios.");

        return self::SUCCESS;
    }

    private function buildSummaryMessage(User $user, Collection $tasks, Collection $overdue, Collection $dueSoon, int $alertDays = 3): string
    {
        $lines = ["Resumen diario para {$user->name}:"];
        $lines[] = "Total pendientes: {$tasks->count()}";

        if ($overdue->isNotEmpty()) {
            $lines[] = "Vencidas: {$overdue->count()}";
        }

        if ($dueSoon->isNotEmpty()) {
            $lines[] = "Próximas a vencer ({$alertDays} días): {$dueSoon->count()}";
        }

        $withoutUpdates = $tasks->filter(fn (Task $t) => $t->updates->isEmpty());
        if ($withoutUpdates->isNotEmpty()) {
            $lines[] = "Sin avance reportado: {$withoutUpdates->count()}";
        }

        $lines[] = '';

        // Order by age: oldest first (created_at ascending)
        $sorted = $tasks->sortBy('created_at');

        foreach ($sorted->take(15) as $task) {
            $status = $task->status->value;
            $due = $task->due_date ? $task->due_date->toDateString() : 'Sin fecha';
            $ageDays = (int) $task->created_at->diffInDays(now());

            $lastUpdate = $task->updates->sortByDesc('created_at')->first();
            $daysSinceUpdate = $lastUpdate
                ? (int) $lastUpdate->created_at->diffInDays(now())
                : $ageDays;

            $inactivityFlag = $daysSinceUpdate >= 7 ? ' ⚠' : '';
            $lines[] = "- [{$status}] {$task->title} (Antigüedad: {$ageDays}d, Sin avance: {$daysSinceUpdate}d, Vence: {$due}){$inactivityFlag}";
        }

        if ($tasks->count() > 15) {
            $remaining = $tasks->count() - 15;
            $lines[] = "... y {$remaining} más.";
        }

        return implode("\n", $lines);
    }
}
