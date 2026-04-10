<?php

namespace App\Models;

use App\Enums\ProcessingStatusEnum;
use App\Enums\VisibilityScopeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Attachment extends Model
{
    protected $fillable = [
        'uuid',
        'task_id',
        'area_id',
        'owner_user_id',
        'uploaded_by',
        'disk',
        'bucket',
        'storage_path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_original',
        'size_processed',
        'processing_status',
        'visibility_scope',
        'checksum',
        'metadata',
        'processed_at',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_status' => ProcessingStatusEnum::class,
            'visibility_scope' => VisibilityScopeEnum::class,
            'metadata' => 'array',
            'size_original' => 'integer',
            'size_processed' => 'integer',
            'processed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attachment $attachment) {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ──

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ──

    public function isReady(): bool
    {
        return $this->processing_status === ProcessingStatusEnum::READY;
    }

    public function isFailed(): bool
    {
        return $this->processing_status === ProcessingStatusEnum::FAILED;
    }

    public function isPending(): bool
    {
        return $this->processing_status === ProcessingStatusEnum::PENDING;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
