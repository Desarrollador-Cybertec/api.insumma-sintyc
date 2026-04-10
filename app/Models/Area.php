<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon_key',
        'process_identifier',
        'manager_user_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'area_members')
            ->withPivot(['assigned_by', 'claimed_by', 'joined_at', 'left_at', 'is_active'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('is_active', true);
    }

    public function activeWorkers(): BelongsToMany
    {
        $workerLevelSlugs = collect(\App\Enums\RoleEnum::workerLevel())
            ->map(fn ($r) => $r->value)->toArray();
        return $this->activeMembers()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $workerLevelSlugs));
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'area_id');
    }
}
