<?php

namespace App\Models;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property TaskStatusEnum $status
 * @property TaskPriorityEnum $priority
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $completion_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $assigned_by
 * @property int|null $assigned_to_user_id
 * @property int|null $assigned_to_area_id
 * @property int|null $delegated_by
 * @property int|null $current_responsible_user_id
 * @property int|null $area_id
 * @property int|null $closed_by
 * @property int|null $cancelled_by
 * @property int|null $meeting_id
 * @property int $progress_percent
 * @property bool $requires_attachment
 * @property bool $requires_completion_comment
 * @property bool $requires_manager_approval
 * @property bool $requires_completion_notification
 * @property bool $requires_due_date
 * @property bool $requires_progress_report
 * @property bool $notify_on_due
 * @property bool $notify_on_overdue
 * @property bool $notify_on_completion
 */
class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'assigned_by',
        'assigned_to_user_id',
        'assigned_to_area_id',
        'external_email',
        'external_name',
        'delegated_by',
        'current_responsible_user_id',
        'area_id',
        'priority',
        'status',
        'start_date',
        'due_date',
        'completed_at',
        'requires_attachment',
        'requires_completion_comment',
        'requires_manager_approval',
        'requires_completion_notification',
        'requires_due_date',
        'completion_notified_at',
        'closed_by',
        'cancelled_by',
        'meeting_id',
        'requires_progress_report',
        'notify_on_due',
        'notify_on_overdue',
        'notify_on_completion',
        'progress_percent',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatusEnum::class,
            'priority' => TaskPriorityEnum::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'completion_notified_at' => 'datetime',
            'requires_attachment' => 'boolean',
            'requires_completion_comment' => 'boolean',
            'requires_manager_approval' => 'boolean',
            'requires_completion_notification' => 'boolean',
            'requires_due_date' => 'boolean',
            'requires_progress_report' => 'boolean',
            'notify_on_due' => 'boolean',
            'notify_on_overdue' => 'boolean',
            'notify_on_completion' => 'boolean',
            'progress_percent' => 'integer',
        ];
    }

    // ── Relationships ──

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'assigned_to_area_id');
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function currentResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_responsible_user_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(TaskDelegation::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TaskNotification::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class);
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(TaskUpdate::class)->latestOfMany();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    // ── Helpers ──

    public function isAssignedToArea(): bool
    {
        return $this->assigned_to_area_id !== null && $this->assigned_to_user_id === null;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && !in_array($this->status, [TaskStatusEnum::COMPLETED, TaskStatusEnum::CANCELLED]);
    }
}
