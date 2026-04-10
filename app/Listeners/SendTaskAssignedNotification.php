<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\NotificationSettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskAssignedNotification implements ShouldQueue
{
    /** Run after the DB transaction commits so a mail failure cannot roll back the assignment. */
    public bool $afterCommit = true;

    public function handle(TaskAssigned $event): void
    {
        $task = $event->task;
        $assignedTo = $task->current_responsible_user_id
            ? User::find($task->current_responsible_user_id)
            : null;

        if (!$assignedTo || $assignedTo->id === $event->assignedBy->id) {
            return; // Don't notify self-assignment
        }

        $assignedTo->notify(new TaskAssignedNotification($task, $event->assignedBy));

        // Track notified user IDs to prevent any duplicates
        $notifiedIds = [$event->assignedBy->id, $assignedTo->id];

        // Copy to manager if enabled
        $settings = app(NotificationSettingsService::class);

        if ($settings->shouldCopyManager() && $task->area_id) {
            $manager = $task->area?->manager;
            if ($manager && !in_array($manager->id, $notifiedIds, true)) {
                $manager->notify(new TaskAssignedNotification($task, $event->assignedBy));
            }
        }
    }
}
