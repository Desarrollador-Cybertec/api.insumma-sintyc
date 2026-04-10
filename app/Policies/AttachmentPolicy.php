<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentAuthorizationService;

class AttachmentPolicy
{
    public function __construct(
        private AttachmentAuthorizationService $authService,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->authService->canView($user, $attachment);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->authService->canDelete($user, $attachment);
    }
}
