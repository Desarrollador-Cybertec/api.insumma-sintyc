<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Events\TaskDelegated;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskDelegation;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskDelegationService
{
    public function delegate(Task $task, User $fromUser, int $toUserId, ?string $note = null): Task
    {
        return DB::transaction(function () use ($task, $fromUser, $toUserId, $note) {
            $toUser = User::findOrFail($toUserId);

            // Validate target belongs to the task's area
            if ($task->area_id && !$toUser->belongsToArea($task->area_id)) {
                throw ValidationException::withMessages([
                    'to_user_id' => ['El usuario destino no pertenece al área de la tarea.'],
                ]);
            }

            // Validate worker role
            if (!$toUser->isWorkerLevel() && !$toUser->isManagerLevel()) {
                throw ValidationException::withMessages([
                    'to_user_id' => ['El usuario destino debe ser trabajador o encargado.'],
                ]);
            }

            // Validate task is not completed or cancelled
            if (in_array($task->status, [TaskStatusEnum::COMPLETED, TaskStatusEnum::CANCELLED])) {
                throw ValidationException::withMessages([
                    'task' => ['No se puede delegar una tarea completada o cancelada.'],
                ]);
            }

            TaskDelegation::create([
                'task_id' => $task->id,
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUserId,
                'from_area_id' => $task->area_id,
                'to_area_id' => $task->area_id,
                'note' => $note,
                'delegated_at' => now(),
            ]);

            $oldStatus = $task->status;

            $task->update([
                'delegated_by' => $fromUser->id,
                'current_responsible_user_id' => $toUserId,
                'status' => TaskStatusEnum::PENDING,
            ]);

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'changed_by' => $fromUser->id,
                'user_id' => $toUserId,
                'from_status' => $oldStatus,
                'to_status' => TaskStatusEnum::PENDING,
                'note' => "Delegada a {$toUser->name}",
            ]);

            ActivityLog::create([
                'user_id' => $fromUser->id,
                'module' => 'tasks',
                'action' => 'delegated',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Tarea delegada a {$toUser->name}",
            ]);

            $task->refresh();

            event(new TaskDelegated($task, $fromUser, $toUser));

            return $task;
        });
    }
}
