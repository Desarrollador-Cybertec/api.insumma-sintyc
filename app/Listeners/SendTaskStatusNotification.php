<?php

namespace App\Listeners;

use App\Enums\TaskStatusEnum;
use App\Events\TaskStatusChanged;
use App\Models\User;
use App\Notifications\TaskApprovedNotification;
use App\Notifications\TaskCancelledNotification;
use App\Notifications\TaskCompletedNotification;
use App\Notifications\TaskRejectedNotification;
use App\Notifications\TaskReopenedNotification;
use App\Notifications\TaskStartedNotification;
use App\Notifications\TaskSubmittedForReviewNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskStatusNotification implements ShouldQueue
{
    /** Run after the DB transaction commits so a mail failure cannot roll back the status change. */
    public bool $afterCommit = true;

    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;
        $changedBy = $event->changedBy;

        match ($event->toStatus) {
            TaskStatusEnum::IN_REVIEW->value   => $this->handleSubmittedForReview($task, $changedBy),
            TaskStatusEnum::COMPLETED->value   => $this->handleCompleted($task, $changedBy),
            TaskStatusEnum::REJECTED->value    => $this->handleRejected($task, $changedBy, $event->note),
            TaskStatusEnum::CANCELLED->value   => $this->handleCancelled($task, $changedBy, $event->note),
            TaskStatusEnum::PENDING->value     => $this->handlePossibleReopen($task, $changedBy, $event->fromStatus, $event->note),
            TaskStatusEnum::IN_PROGRESS->value => $this->handleInProgress($task, $changedBy, $event->fromStatus, $event->note),
            default => null,
        };
    }

    /**
     * Worker submits task for review → notify whoever is responsible for approving it.
     *
     * Priority chain:
     *  1. The user who last delegated the task (delegated_by)
     *  2. The user who assigned the task (assigned_by)
     *  3. The task creator (created_by) as final fallback
     *
     * This covers all scenarios naturally:
     *  - Superadmin assigned → superadmin is notified
     *  - Area manager assigned or delegated → area manager is notified
     *  - No specific assigner found → creator is notified
     */
    private function handleSubmittedForReview($task, User $submittedBy): void
    {
        $approverId = $task->delegated_by
            ?? $task->assigned_by
            ?? $task->created_by;

        if (!$approverId) {
            return;
        }

        $approver = User::find($approverId);

        if ($approver && $approver->id !== $submittedBy->id) {
            $approver->notify(new TaskSubmittedForReviewNotification($task, $submittedBy));
        }
    }

    /**
     * Task completed:
     * - By someone else (manager approved) → notify responsible worker (TaskApproved).
     * - By the responsible user (no approval needed) → notify area manager (TaskCompleted),
     *   OR the task creator (fallback for personal tasks with no area).
     */
    private function handleCompleted($task, User $changedBy): void
    {
        $responsible = $this->getResponsible($task);

        if ($responsible && $responsible->id !== $changedBy->id) {
            // Manager/admin approved → notify the worker
            $responsible->notify(new TaskApprovedNotification($task, $changedBy));
        } elseif ($responsible && $responsible->id === $changedBy->id) {
            // Worker self-completed (no approval needed)
            if ($task->area_id) {
                // Organizational task → notify area manager
                $this->notifyAreaManager(
                    $task,
                    $changedBy,
                    new TaskCompletedNotification($task, $changedBy)
                );
            } else {
                // Personal task (no area) → notify the creator if different from completer
                $this->notifyCreator($task, $changedBy, new TaskCompletedNotification($task, $changedBy));
            }
        }
    }

    /**
     * Task rejected → notify responsible worker.
     */
    private function handleRejected($task, User $changedBy, ?string $note): void
    {
        $responsible = $this->getResponsible($task);

        if ($responsible && $responsible->id !== $changedBy->id) {
            $responsible->notify(
                new TaskRejectedNotification($task, $changedBy, $note ?? 'Sin motivo especificado')
            );
        }
    }

    /**
     * Task cancelled → notify responsible if different from canceller.
     */
    private function handleCancelled($task, User $cancelledBy, ?string $note): void
    {
        $responsible = $this->getResponsible($task);

        if ($responsible && $responsible->id !== $cancelledBy->id) {
            $responsible->notify(new TaskCancelledNotification($task, $cancelledBy, $note));
        }
    }

    /**
     * Task reopened from a terminal state → notify responsible if different.
     */
    private function handlePossibleReopen($task, User $reopenedBy, string $fromStatus, ?string $note): void
    {
        $terminalStatuses = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELLED->value,
            TaskStatusEnum::OVERDUE->value,
        ];

        if (!in_array($fromStatus, $terminalStatuses)) {
            return; // Normal transition (e.g., PENDING → IN_PROGRESS), not a reopen
        }

        $responsible = $this->getResponsible($task);

        if ($responsible && $responsible->id !== $reopenedBy->id) {
            $responsible->notify(new TaskReopenedNotification($task, $reopenedBy, $note));
        }
    }

    /**
     * Notify the area manager of the task (organizational tasks only).
     */
    private function notifyAreaManager($task, User $excludeUser, $notification): void
    {
        if (!$task->area_id) {
            return;
        }

        $manager = $task->area?->manager;

        if ($manager && $manager->id !== $excludeUser->id) {
            $manager->notify($notification);
        }
    }

    /**
     * Notify the task creator (fallback for personal tasks with no area manager).
     */
    private function notifyCreator($task, User $excludeUser, $notification): void
    {
        if (!$task->created_by) {
            return;
        }

        $creator = User::find($task->created_by);

        if ($creator && $creator->id !== $excludeUser->id) {
            $creator->notify($notification);
        }
    }

    private function getResponsible($task): ?User
    {
        return $task->current_responsible_user_id
            ? User::find($task->current_responsible_user_id)
            : null;
    }

    /**
     * Task moved to IN_PROGRESS:
     *  - From a terminal state (completed/cancelled/overdue) → reopen, notify responsible.
     *  - From PENDING / PENDING_ASSIGNMENT / REJECTED → fresh start, notify approver chain.
     */
    private function handleInProgress($task, User $changedBy, string $fromStatus, ?string $note): void
    {
        $terminalStatuses = [
            TaskStatusEnum::COMPLETED->value,
            TaskStatusEnum::CANCELLED->value,
            TaskStatusEnum::OVERDUE->value,
        ];

        if (in_array($fromStatus, $terminalStatuses)) {
            $this->handlePossibleReopen($task, $changedBy, $fromStatus, $note);
            return;
        }

        // Fresh start (PENDING → IN_PROGRESS or REJECTED → IN_PROGRESS)
        $this->handleStarted($task, $changedBy);
    }

    /**
     * Worker starts a task → notify the approver chain so they know work began.
     *
     * Priority chain (same as submit-for-review):
     *  1. delegated_by
     *  2. assigned_by
     *  3. created_by (fallback)
     */
    private function handleStarted($task, User $startedBy): void
    {
        $approverId = $task->delegated_by
            ?? $task->assigned_by
            ?? $task->created_by;

        if (!$approverId) {
            return;
        }

        $approver = User::find($approverId);

        if ($approver && $approver->id !== $startedBy->id) {
            $approver->notify(new TaskStartedNotification($task, $startedBy));
        }
    }
}
