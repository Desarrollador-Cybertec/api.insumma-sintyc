<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminLevel() || $user->isManagerLevel() || $user->isWorkerLevel();
    }

    public function view(User $user, Area $area): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($area->manager_user_id === $user->id) {
            return true;
        }

        return $user->belongsToArea($area->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdminLevel();
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isAdminLevel();
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->isAdminLevel();
    }

    public function assignManager(User $user, Area $area): bool
    {
        return $user->isAdminLevel();
    }

    public function claimWorker(User $user, Area $area): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        return $area->manager_user_id === $user->id;
    }

    public function availableWorkers(User $user, Area $area): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        return $area->manager_user_id === $user->id;
    }

    public function removeMember(User $user, Area $area): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        return $area->manager_user_id === $user->id;
    }
}
