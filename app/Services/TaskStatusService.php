<?php

namespace App\Services;

use App\Enums\CommentTypeEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskCommentAdded;
use App\Events\TaskStatusChanged;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskStatusService
{
    public function transition(Task $task, TaskStatusEnum $newStatus, User $changedBy, ?string $note = null): Task
    {
        if (!$task->status->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Transición de {$task->status->value} a {$newStatus->value} no permitida."],
            ]);
        }

        return DB::transaction(function () use ($task, $newStatus, $changedBy, $note) {
            $oldStatus = $task->status;

            $updateData = [
                'status'           => $newStatus,
                'progress_percent' => $newStatus->defaultProgress(),
            ];

            if ($newStatus === TaskStatusEnum::COMPLETED) {
                $updateData['completed_at'] = now();
                $updateData['closed_by'] = $changedBy->id;
            }

            if ($newStatus === TaskStatusEnum::CANCELLED) {
                $updateData['cancelled_by'] = $changedBy->id;
            }

            $task->update($updateData);

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'changed_by' => $changedBy->id,
                'user_id' => $task->current_responsible_user_id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'note' => $note,
            ]);

            ActivityLog::create([
                'user_id' => $changedBy->id,
                'module' => 'tasks',
                'action' => 'status_changed',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Estado cambiado de {$oldStatus->value} a {$newStatus->value}",
            ]);

            $task->refresh();

            event(new TaskStatusChanged($task, $oldStatus->value, $newStatus->value, $changedBy, $note));

            return $task;
        });
    }

    public function start(Task $task, User $user, string $note): Task
    {
        return DB::transaction(function () use ($task, $user, $note) {
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'comment' => $note,
                'type'    => \App\Enums\CommentTypeEnum::PROGRESS,
            ]);

            return $this->transition($task, TaskStatusEnum::IN_PROGRESS, $user, 'Tarea iniciada');
        });
    }

    public function cancel(Task $task, User $user, string $reason): Task
    {
        return DB::transaction(function () use ($task, $user, $reason) {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'comment' => $reason,
                'type'    => CommentTypeEnum::CANCELLATION_NOTE,
            ]);

            event(new TaskCommentAdded($task, $comment, $user));

            return $this->transition($task, TaskStatusEnum::CANCELLED, $user, $reason);
        });
    }

    public function reopen(Task $task, User $user, string $reason): Task
    {
        $newStatus = $task->status === TaskStatusEnum::CANCELLED
            ? TaskStatusEnum::PENDING
            : TaskStatusEnum::IN_PROGRESS;

        return DB::transaction(function () use ($task, $newStatus, $user, $reason) {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'comment' => $reason,
                'type'    => CommentTypeEnum::REOPEN_NOTE,
            ]);

            event(new TaskCommentAdded($task, $comment, $user));

            // Clear terminal state fields
            $task->update([
                'completed_at'  => null,
                'closed_by'     => null,
                'cancelled_by'  => null,
            ]);

            return $this->transition($task->fresh(), $newStatus, $user, $reason);
        });
    }
}
