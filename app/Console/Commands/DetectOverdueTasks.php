<?php

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use Illuminate\Console\Command;

class DetectOverdueTasks extends Command
{
    protected $signature = 'tasks:detect-overdue';

    protected $description = 'Mark tasks as overdue when past due date';

    public function handle(): int
    {
        if (!SystemSetting::getValue('detect_overdue_enabled', true)) {
            $this->info('Detección de tareas vencidas desactivada.');
            return self::SUCCESS;
        }

        $tasks = Task::where('due_date', '<', now())
            ->whereNotIn('status', [
                TaskStatusEnum::COMPLETED->value,
                TaskStatusEnum::CANCELLED->value,
                TaskStatusEnum::OVERDUE->value,
            ])
            ->get();

        $count = 0;

        /** @var Task $task */
        foreach ($tasks as $task) {
            $oldStatus = $task->status;

            if (!$oldStatus->canTransitionTo(TaskStatusEnum::OVERDUE)) {
                continue;
            }

            $task->update(['status' => TaskStatusEnum::OVERDUE]);

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'changed_by' => $task->created_by,
                'user_id' => $task->current_responsible_user_id,
                'from_status' => $oldStatus,
                'to_status' => TaskStatusEnum::OVERDUE,
                'note' => 'Marcada como vencida automáticamente',
            ]);

            ActivityLog::create([
                'user_id' => $task->created_by,
                'module' => 'tasks',
                'action' => 'auto_overdue',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Tarea \"{$task->title}\" marcada como vencida automáticamente",
            ]);

            $count++;
        }

        $this->info("Se marcaron {$count} tareas como vencidas.");

        return self::SUCCESS;
    }
}
