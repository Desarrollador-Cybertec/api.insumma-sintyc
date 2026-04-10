<?php

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\User;

class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->isSuperAdmin();
    }
}
