<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatusEnum;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Notifications\DailyTaskSummaryNotification;
use App\Notifications\TaskDueSoonNotification;
use App\Notifications\TaskInactivityNotification;
use App\Notifications\TaskOverdueNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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
     * Manually trigger overdue detection.
     */
    public function triggerOverdueDetection(Request $request): JsonResponse
    {
        $areaIds = $this->resolveAreaScope($request);
        $user = $request->user();

        if ($areaIds === null) {
            Artisan::call('tasks:detect-overdue');
            $output = trim(Artisan::output());
        } else {
            if (!SystemSetting::getValue('detect_overdue_enabled', true)) {
                return response()->json([
                    'message' => 'La detección de tareas vencidas está desactivada.',
                ], 422);
            }

            $tasks = Task::where('due_date', '<', now())
                ->whereIn('area_id', $areaIds)
                ->whereNotIn('status', [
                    TaskStatusEnum::COMPLETED->value,
                    TaskStatusEnum::CANCELLED->value,
                    TaskStatusEnum::OVERDUE->value,
                ])
                ->get();

            $count = 0;
            foreach ($tasks as $task) {
                if (!$task->status->canTransitionTo(TaskStatusEnum::OVERDUE)) {
                    continue;
                }
                $oldStatus = $task->status;
                $task->update(['status' => TaskStatusEnum::OVERDUE]);
                TaskStatusHistory::create([
                    'task_id' => $task->id,
                    'changed_by' => $user->id,
                    'user_id' => $task->current_responsible_user_id,
                    'from_status' => $oldStatus,
                    'to_status' => TaskStatusEnum::OVERDUE,
                    'note' => 'Marcada como vencida manualmente por encargado',
                ]);
                $count++;
            }

            $output = "Se marcaron {$count} tareas como vencidas en tu área.";
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'automation',
            'action' => 'trigger_overdue_detection',
            'description' => 'Detección de tareas vencidas ejecutada manualmente',
        ]);

        return response()->json([
            'message' => 'Detección de tareas vencidas ejecutada correctamente',
            'output' => $output,
        ]);
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
                ->with(['currentResponsible', 'updates'])
                ->get()
                ->groupBy('current_responsible_user_id');

            $count = 0;
            foreach ($activeTasks as $tasks) {
                $responsible = $tasks->first()->currentResponsible;
                if (!$responsible) {
                    continue;
                }

                $overdue = $tasks->filter(fn ($t) => $t->isOverdue());
                $dueSoon = $tasks->filter(fn ($t) => $t->due_date && $t->due_date->isFuture() && $t->due_date->diffInDays(now()) <= $alertDays);
                $withoutUpdates = $tasks->filter(fn ($t) => $t->updates->isEmpty());

                $lines = ["Resumen para {$responsible->name}:", "Total pendientes: {$tasks->count()}"];
                if ($overdue->isNotEmpty()) {
                    $lines[] = "Vencidas: {$overdue->count()}";
                }
                if ($dueSoon->isNotEmpty()) {
                    $lines[] = "Próximas a vencer ({$alertDays}d): {$dueSoon->count()}";
                }
                if ($withoutUpdates->isNotEmpty()) {
                    $lines[] = "Sin avance reportado: {$withoutUpdates->count()}";
                }

                $responsible->notify(new DailyTaskSummaryNotification(
                    summaryContent: implode("\n", $lines),
                    totalPending: $tasks->count(),
                    overdueCount: $overdue->count(),
                    dueSoonCount: $dueSoon->count(),
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
            $alertDays = SystemSetting::getValue('alert_days_before_due', 3);
            $count = 0;

            $dueSoon = Task::where('notify_on_due', true)
                ->whereNotIn('status', [TaskStatusEnum::COMPLETED->value, TaskStatusEnum::CANCELLED->value])
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now()->startOfDay())
                ->where('due_date', '<=', now()->addDays($alertDays)->endOfDay())
                ->whereNotNull('current_responsible_user_id')
                ->whereIn('area_id', $areaIds)
                ->get();

            foreach ($dueSoon as $task) {
                $responsible = User::find($task->current_responsible_user_id);
                if (!$responsible) continue;

                $daysLeft = (int) now()->startOfDay()->diffInDays($task->due_date, false);
                $responsible->notify(new TaskDueSoonNotification($task, $daysLeft));
                $count++;
            }

            $overdue = Task::where('notify_on_overdue', true)
                ->whereNotIn('status', [TaskStatusEnum::COMPLETED->value, TaskStatusEnum::CANCELLED->value])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->startOfDay())
                ->whereNotNull('current_responsible_user_id')
                ->whereIn('area_id', $areaIds)
                ->get();

            foreach ($overdue as $task) {
                $responsible = User::find($task->current_responsible_user_id);
                if (!$responsible) continue;

                $daysOverdue = (int) $task->due_date->diffInDays(now());
                $responsible->notify(new TaskOverdueNotification($task, $daysOverdue));
                $count++;
            }

            $output = "Se enviaron {$count} recordatorios para tu área.";
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'automation',
            'action' => 'trigger_due_reminders',
            'description' => 'Recordatorios de vencimiento enviados manualmente',
        ]);

        return response()->json([
            'message' => 'Recordatorios enviados correctamente',
            'output' => $output,
        ]);
    }

    /**
     * Manually trigger inactivity detection.
     */
    public function triggerInactivityDetection(Request $request): JsonResponse
    {
        $areaIds = $this->resolveAreaScope($request);
        $user = $request->user();

        $enabled = SystemSetting::getValue('inactivity_alert_enabled', true);
        if (!$enabled) {
            return response()->json([
                'message' => 'Las alertas de inactividad están desactivadas. Actívelas en configuración.',
            ], 422);
        }

        if ($areaIds === null) {
            Artisan::call('tasks:detect-inactive');
            $output = trim(Artisan::output());
        } else {
            $inactivityDays = SystemSetting::getValue('inactivity_alert_days', 7);
            $cutoff = now()->subDays($inactivityDays);

            $inactiveTasks = Task::whereIn('status', [
                    TaskStatusEnum::IN_PROGRESS->value,
                    TaskStatusEnum::PENDING->value,
                ])
                ->whereNotNull('current_responsible_user_id')
                ->whereIn('area_id', $areaIds)
                ->with('latestUpdate')
                ->where(function ($query) use ($cutoff) {
                    $query->where(function ($q) use ($cutoff) {
                        $q->whereHas('updates')
                          ->whereDoesntHave('updates', fn ($sub) => $sub->where('created_at', '>=', $cutoff));
                    })->orWhere(function ($q) use ($cutoff) {
                        $q->whereDoesntHave('updates')
                          ->where('created_at', '<', $cutoff);
                    });
                })
                ->get();

            $grouped = $inactiveTasks->groupBy('current_responsible_user_id');
            $count = 0;

            foreach ($grouped as $userId => $tasks) {
                $responsible = User::find($userId);
                if (!$responsible) continue;

                $taskData = $tasks->map(function ($task) {
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

                $responsible->notify(new TaskInactivityNotification($taskData, $inactivityDays));
                $count++;
            }

            $output = "Se enviaron alertas de inactividad a {$count} usuarios de tu área.";
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'automation',
            'action' => 'trigger_inactivity_detection',
            'description' => 'Detección de inactividad ejecutada manualmente',
        ]);

        return response()->json([
            'message' => 'Detección de inactividad ejecutada correctamente',
            'output' => $output,
        ]);
    }
}

