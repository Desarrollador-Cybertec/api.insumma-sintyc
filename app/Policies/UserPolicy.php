<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminLevel() || $user->isManagerLevel();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isAdminLevel()) {
            // Gerente cannot see superadmin profiles
            if ($user->isGerente() && $model->isSuperAdmin()) {
                return false;
            }
            return true;
        }

        if ($user->id === $model->id) {
            return true;
        }

        if ($user->isManagerLevel()) {
            $managedAreaIds = $user->managedAreas()->pluck('id');
            return $model->activeAreas()->whereIn('areas.id', $managedAreaIds)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdminLevel();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isAdminLevel()) {
            // Gerente cannot edit superadmin
            if ($user->isGerente() && $model->isSuperAdmin()) {
                return false;
            }
            return true;
        }

        return $user->id === $model->id;
    }

    public function updateRole(User $user, User $model): bool
    {
        if ($user->isGerente() && $model->isSuperAdmin()) {
            return false;
        }
        return $user->isAdminLevel() && $user->id !== $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->id !== $model->id;
    }

    public function updatePassword(User $user, User $model): bool
    {
        if ($user->isGerente() && $model->isSuperAdmin()) {
            return false;
        }
        return $user->isAdminLevel() && $user->id !== $model->id;
    }
}
