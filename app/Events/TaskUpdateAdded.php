<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdateAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public TaskUpdate $update,
        public User $addedBy,
    ) {}
}
