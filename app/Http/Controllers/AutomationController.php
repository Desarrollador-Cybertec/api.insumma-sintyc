<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatusEnum;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyTaskSummaryNotification;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutomationController extends Controller
{
    /**
     * Returns null for superadmin (global scope), array of area IDs for area managers.
     * Aborts 403 for workers or users with no managed areas.
     */
    private function resolveAreaScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user->isAdminLevel()) {
            return null;
        }

        if ($user->isManagerLevel()) {
            $areaIds = $user->managedAreas()->pluck('id')->toArray();
            if (empty($areaIds)) {
                abort(403);
            }
            return $areaIds;
        }

        abort(403);
    }

    /**
     * Manually trigger daily summary.
     */
    public function triggerDailySummary(Request $request): JsonResponse
    {
        $areaIds = $this->resolveAreaScope($request);
        $user = $request->user();

        $enabled = SystemSetting::getValue('daily_summary_enabled', true);
        if (!$enabled) {
            return response()->json([
                'message' => 'El resumen diario está desactivado. Actívelo en configuración antes de enviarlo.',
            ], 422);
        }

        if ($areaIds === null) {
            Artisan::call('tasks:send-daily-summary');
            $output = trim(Artisan::output());
        } else {
            $alertDays = SystemSetting::getValue('alert_days_before_due', 3);

            $activeTasks = Task::whereNotIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELLED->value,
                ])
                ->whereNotNull('current_responsible_user_id')
                ->whereIn('area_id', $areaIds)
                ->with('currentResponsible')
                ->get()
                ->groupBy('current_responsible_user_id');

            $count = 0;
            foreach ($activeTasks as $tasks) {
                $responsible = $tasks->first()->currentResponsible;
                if (!$responsible) {
                    continue;
                }

                ['dueSoon' => $dueSoon, 'notStarted' => $notStarted, 'overdue' => $overdue] = $this->buildSummaryBuckets($tasks, $alertDays);
                $summaryContent = $this->buildSummaryMessage($responsible, $tasks, $dueSoon, $notStarted, $overdue, $alertDays);

                $responsible->notify(new DailyTaskSummaryNotification(
                    summaryContent: $summaryContent,
                    totalPending: $tasks->count(),
                    overdueCount: $overdue->count(),
                    dueSoonCount: $dueSoon->count(),
                    notStartedCount: $notStarted->count(),
                ));
                $count++;
            }

            $output = "Resúmenes generados para {$count} usuarios de tu área.";
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'automation',
            'action' => 'trigger_daily_summary',
            'description' => 'Resumen diario enviado manualmente',
        ]);

        return response()->json([
            'message' => 'Resumen diario enviado correctamente',
            'output' => $output,
        ]);
    }

    /**
     * Manually trigger due reminders.
     */
    public function triggerDueReminders(Request $request): JsonResponse
    {
        $areaIds = $this->resolveAreaScope($request);
        $user = $request->user();

        $enabled = SystemSetting::getValue('emails_enabled', true);
        if (!$enabled) {
            return response()->json([
                'message' => 'Los correos automáticos están desactivados. Actívelos en configuración.',
            ], 422);
        }

        if ($areaIds === null) {
            Artisan::call('tasks:send-due-reminders');
            $output = trim(Artisan::output());
        } else {
            $count = 0;

            $dueToday = Task::where(function ($query) {
                    $query->where('notify_on_due', true)
                        ->orWhere('notify_on_overdue', true);
                })
                ->whereNotIn('status', [TaskStatusEnum::COMPLETED->value, TaskStatusEnum::CANCELLED->value])
                ->whereNotNull('due_date')
                ->whereDate('due_date', now()->toDateString())
                ->whereNotNull('current_responsible_user_id')
                ->whereIn('area_id', $areaIds)
                ->with('currentResponsible')
                ->get();

            foreach ($dueToday as $task) {
                $responsible = $task->currentResponsible;
                if (!$responsible instanceof User || $this->alreadySentDueReminderToday($responsible, $task)) {
                    continue;
                }

                $responsible->notify(new TaskDueSoonNotification($task, 0));
                $count++;
            }

            $output = "Se enviaron {$count} recordatorios de vencimiento para tu área.";
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'automation',
            'action' => 'trigger_due_reminders',
            'description' => 'Recordatorios del día de vencimiento enviados manualmente',
        ]);

        return response()->json([
            'message' => 'Recordatorios enviados correctamente',
            'output' => $output,
        ]);
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

    private function buildSummaryMessage(User $user, Collection $tasks, Collection $dueSoon, Collection $notStarted, Collection $overdue, int $alertDays): string
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

    private function alreadySentDueReminderToday(User $user, Task $task): bool
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

