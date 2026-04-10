<?php

namespace App\Listeners;

use App\Events\TaskAttachmentAdded;
use App\Models\User;
use App\Notifications\TaskAttachmentAddedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskAttachmentNotification implements ShouldQueue
{
    /** Run after the DB transaction commits so a mail failure cannot roll back the attachment record. */
    public bool $afterCommit = true;

    public function handle(TaskAttachmentAdded $event): void
    {
        $task = $event->task;
        $addedBy = $event->addedBy;
        $notified = [];

        // Notify the responsible user (if different from who added the attachment)
        $responsible = $task->current_responsible_user_id
            ? User::find($task->current_responsible_user_id)
            : null;

        if ($responsible && $responsible->id !== $addedBy->id) {
            $responsible->notify(
                new TaskAttachmentAddedNotification($task, $addedBy, $event->fileName)
            );
            $notified[] = $responsible->id;
        }

        // Notify the creator if different from uploader and not already notified
        $creator = $task->created_by ? User::find($task->created_by) : null;
        if ($creator
            && $creator->id !== $addedBy->id
            && !in_array($creator->id, $notified)
        ) {
            $creator->notify(
                new TaskAttachmentAddedNotification($task, $addedBy, $event->fileName)
            );
        }
    }
}
