<?php

namespace App\Listeners;

use App\Events\TaskUpdateAdded;
use App\Models\User;
use App\Notifications\TaskUpdateAddedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskUpdateNotification implements ShouldQueue
{
    /** Run after the DB transaction commits so a mail failure cannot roll back the update. */
    public bool $afterCommit = true;

    public function handle(TaskUpdateAdded $event): void
    {
        $task = $event->task;
        $addedBy = $event->addedBy;
        $update = $event->update;
        $notified = [];

        $updateType = $update->update_type instanceof \App\Enums\UpdateTypeEnum
            ? $update->update_type->label()
            : (string) $update->update_type;

        // Notify the responsible user (if different from who added the update)
        $responsible = $task->current_responsible_user_id
            ? User::find($task->current_responsible_user_id)
            : null;

        if ($responsible && $responsible->id !== $addedBy->id) {
            $responsible->notify(
                new TaskUpdateAddedNotification($task, $addedBy, $updateType, $update->comment)
            );
            $notified[] = $responsible->id;
        }

        // Notify the creator if different from updater and not already notified
        $creator = $task->created_by ? User::find($task->created_by) : null;
        if ($creator
            && $creator->id !== $addedBy->id
            && !in_array($creator->id, $notified)
        ) {
            $creator->notify(
                new TaskUpdateAddedNotification($task, $addedBy, $updateType, $update->comment)
            );
        }
    }
}
