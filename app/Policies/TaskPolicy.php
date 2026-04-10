<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    private function isForeignPersonalTask(User $user, Task $task): bool
    {
        return is_null($task->area_id) && $task->created_by !== $user->id;
    }

    /**
     * Returns true if $manager manages the active area of the given user.
     * Used for cross-area task scenarios (e.g. Ventas assigns a task to a Desarrollo worker).
     */
    private function isManagerOfUserArea(User $manager, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        return DB::table('area_members')
            ->join('areas', 'area_members.area_id', '=', 'areas.id')
            ->where('area_members.user_id', $userId)
            ->where('area_members.is_active', true)
            ->where('areas.manager_user_id', $manager->id)
            ->exists();
    }

    /**
     * Returns true if $user is a manager-level role AND is an active member of the given area.
     */
    private function isManagerLevelMemberOfArea(User $user, ?int $areaId): bool
    {
        if (!$areaId || !$user->isManagerLevel()) {
            return false;
        }
        return $user->belongsToArea($areaId);
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        // Personal task: only the creator can see it
        if (is_null($task->area_id)) {
            return $task->created_by === $user->id;
        }

        // Area task: user is currently responsible AND task is still active
        $terminalStatuses = [
            \App\Enums\TaskStatusEnum::COMPLETED->value,
            \App\Enums\TaskStatusEnum::CANCELLED->value,
        ];
        if ($task->current_responsible_user_id === $user->id
            && !in_array($task->status->value, $terminalStatuses)) {
            return true;
        }

        // Area task: user currently manages this area (worker-created tasks are personal so have no area_id)
        if ($user->isManagerOfArea($task->area_id)) {
            $workerLevelSlugs = collect(RoleEnum::workerLevel())
                ->map(fn ($r) => $r->value)->toArray();
            return !DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->where('users.id', $task->created_by)
                ->whereIn('roles.slug', $workerLevelSlugs)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // Workers can edit their own personal tasks (no area)
        if (is_null($task->area_id) && $task->created_by === $user->id) {
            return true;
        }

        return false;
    }

    public function delegate(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        if (!$task->area_id) {
            return false;
        }

        return $user->isManagerOfArea($task->area_id)
            || ($user->isManagerLevel() && $task->current_responsible_user_id === $user->id)
            // Cross-area: allow if the current responsible belongs to one of the manager's areas
            || $this->isManagerOfUserArea($user, $task->current_responsible_user_id)
            // Creator of the task can delegate it
            || ($user->isManagerLevel() && $task->created_by === $user->id);
    }

    public function start(User $user, Task $task): bool
    {
        // Normal case: the current responsible starts the task
        if ($task->current_responsible_user_id === $user->id) {
            return true;
        }

        // Admin or area manager can start the task (e.g. after reopening it)
        if ($user->isAdminLevel() && !$this->isForeignPersonalTask($user, $task)) {
            return true;
        }

        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // External task: the creator manages it on behalf of the external contact
        return $task->external_email && $task->created_by === $user->id;
    }

    public function submitForReview(User $user, Task $task): bool
    {
        // Normal case: the current responsible submits
        if ($task->current_responsible_user_id === $user->id) {
            return true;
        }

        // Admin or area manager can close the task (e.g. after reopening it)
        if ($user->isAdminLevel() && !$this->isForeignPersonalTask($user, $task)) {
            return true;
        }

        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // External task: the creator marks it complete on behalf of the external contact
        return $task->external_email && $task->created_by === $user->id;
    }

    public function approve(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        return $task->area_id && $user->isManagerOfArea($task->area_id);
    }

    public function reject(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        return $task->area_id && $user->isManagerOfArea($task->area_id);
    }

    public function cancel(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        // Area manager can cancel tasks in their area
        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // Current responsible (worker or manager) can cancel the task assigned to them
        if ($task->current_responsible_user_id === $user->id) {
            return true;
        }

        // Creator can cancel their own tasks
        return $task->created_by === $user->id;
    }

    public function reopen(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // Workers can reopen tasks they created or are responsible for
        return $task->created_by === $user->id
            || $task->current_responsible_user_id === $user->id;
    }

    public function claim(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        if ($task->area_id && $user->isManagerOfArea($task->area_id)) {
            return true;
        }

        // Cross-area: allow if the assigned user belongs to one of the manager's areas
        if ($this->isManagerOfUserArea($user, $task->assigned_to_user_id)) {
            return true;
        }

        // Creator of the task can claim it
        return $user->isManagerLevel() && $task->created_by === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->isAdminLevel()) {
            return !$this->isForeignPersonalTask($user, $task);
        }

        // Workers can delete their own personal tasks (no area)
        return is_null($task->area_id) && $task->created_by === $user->id;
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function addAttachment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
