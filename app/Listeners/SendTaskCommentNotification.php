<?php

namespace App\Listeners;

use App\Events\TaskCommentAdded;
use App\Models\User;
use App\Notifications\TaskCommentAddedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskCommentNotification implements ShouldQueue
{
    /** Run after the DB transaction commits so a mail failure cannot roll back the comment. */
    public bool $afterCommit = true;

    public function handle(TaskCommentAdded $event): void
    {
        $task = $event->task;
        $commentBy = $event->commentBy;
        $notified = [];

        // Notify the responsible user (if different from commenter)
        $responsible = $task->current_responsible_user_id
            ? User::find($task->current_responsible_user_id)
            : null;

        if ($responsible && $responsible->id !== $commentBy->id) {
            $responsible->notify(
                new TaskCommentAddedNotification($task, $commentBy, $event->comment->comment)
            );
            $notified[] = $responsible->id;
        }

        // Notify the creator if different from commenter and not already notified
        $creator = $task->created_by ? User::find($task->created_by) : null;
        if ($creator
            && $creator->id !== $commentBy->id
            && !in_array($creator->id, $notified)
        ) {
            $creator->notify(
                new TaskCommentAddedNotification($task, $commentBy, $event->comment->comment)
            );
        }
    }
}
