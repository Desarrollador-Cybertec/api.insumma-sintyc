<?php

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNotification extends Model
{
    protected $fillable = [
        'task_id',
        'triggered_by',
        'notify_to_user_id',
        'channel',
        'message',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelEnum::class,
            'sent_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function notifyToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notify_to_user_id');
    }
}
