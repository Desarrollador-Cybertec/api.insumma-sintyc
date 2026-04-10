<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public string $fromStatus,
        public string $toStatus,
        public User $changedBy,
        public ?string $note = null,
    ) {}
}
