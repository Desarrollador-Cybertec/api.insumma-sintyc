<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminLevel() || $user->isManagerLevel();
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($meeting->area_id && $user->isManagerOfArea($meeting->area_id)) {
            return true;
        }

        return $meeting->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdminLevel() || $user->isManagerLevel();
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($meeting->is_closed) {
            return false;
        }

        if ($user->isAdminLevel()) {
            return true;
        }

        return $meeting->created_by === $user->id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->isSuperAdmin();
    }

    public function close(User $user, Meeting $meeting): bool
    {
        if ($meeting->is_closed) {
            return false;
        }

        if ($user->isAdminLevel()) {
            return true;
        }

        return $meeting->created_by === $user->id;
    }
}
