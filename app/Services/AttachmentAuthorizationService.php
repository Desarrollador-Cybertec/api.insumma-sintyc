<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;

class AttachmentAuthorizationService
{
    public function canView(User $user, Attachment $attachment): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isAdminLevel() || $user->isManagerLevel()) {
            return $this->canManagerView($user, $attachment);
        }

        return $this->canWorkerView($user, $attachment);
    }

    public function canDelete(User $user, Attachment $attachment): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        // Area managers can delete attachments from their areas
        if (($user->isAdminLevel() || $user->isManagerLevel()) && $attachment->area_id) {
            return $user->isManagerOfArea($attachment->area_id);
        }

        // Area managers can delete attachments from tasks in their areas
        if (($user->isAdminLevel() || $user->isManagerLevel()) && $attachment->task_id) {
            $task = $attachment->task;
            return $task && $task->area_id && $user->isManagerOfArea($task->area_id);
        }

        // Owners can delete their own files
        return $attachment->uploaded_by === $user->id;
    }

    private function canManagerView(User $user, Attachment $attachment): bool
    {
        // Files owned by the manager
        if ($attachment->owner_user_id === $user->id) {
            return true;
        }

        // Files from areas managed by this user
        if ($attachment->area_id && $user->isManagerOfArea($attachment->area_id)) {
            return true;
        }

        // Files from tasks in areas managed by this user
        if ($attachment->task_id) {
            $task = $attachment->task;
            if ($task && $task->area_id && $user->isManagerOfArea($task->area_id)) {
                return true;
            }
        }

        return false;
    }

    private function canWorkerView(User $user, Attachment $attachment): bool
    {
        // Own files
        if ($attachment->owner_user_id === $user->id) {
            return true;
        }

        // Files from tasks assigned to this worker
        if ($attachment->task_id) {
            $task = $attachment->task;
            if ($task && $task->current_responsible_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }
}
