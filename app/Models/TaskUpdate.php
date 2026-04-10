<?php

namespace App\Models;

use App\Enums\UpdateTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskUpdate extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'update_type',
        'comment',
        'progress_percent',
    ];

    protected function casts(): array
    {
        return [
            'update_type' => UpdateTypeEnum::class,
            'progress_percent' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
