<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_members')
            ->withPivot(['assigned_by', 'claimed_by', 'joined_at', 'left_at', 'is_active'])
            ->withTimestamps();
    }

    public function activeAreas(): BelongsToMany
    {
        return $this->areas()->wherePivot('is_active', true);
    }

    public function managedAreas(): HasMany
    {
        return $this->hasMany(Area::class, 'manager_user_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    public function responsibleTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'current_responsible_user_id');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'uploaded_by');
    }

    // ── Helpers ──

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === RoleEnum::SUPERADMIN->value;
    }

    public function isGerente(): bool
    {
        return $this->role?->slug === RoleEnum::GERENTE->value;
    }

    public function isAreaManager(): bool
    {
        return $this->role?->slug === RoleEnum::AREA_MANAGER->value;
    }

    public function isDirector(): bool
    {
        return $this->role?->slug === RoleEnum::DIRECTOR->value;
    }

    public function isLeader(): bool
    {
        return $this->role?->slug === RoleEnum::LEADER->value;
    }

    public function isCoordinator(): bool
    {
        return $this->role?->slug === RoleEnum::COORDINATOR->value;
    }

    public function isWorker(): bool
    {
        return $this->role?->slug === RoleEnum::WORKER->value;
    }

    public function isAnalyst(): bool
    {
        return $this->role?->slug === RoleEnum::ANALYST->value;
    }

    /**
     * SuperAdmin or Gerente level.
     */
    public function isAdminLevel(): bool
    {
        $slug = $this->role?->slug;
        return $slug && RoleEnum::from($slug)->isAdminLevel();
    }

    /**
     * Area Manager, Director, Leader, or Coordinator level.
     */
    public function isManagerLevel(): bool
    {
        $slug = $this->role?->slug;
        return $slug && RoleEnum::from($slug)->isManagerLevel();
    }

    /**
     * Worker or Analyst level.
     */
    public function isWorkerLevel(): bool
    {
        $slug = $this->role?->slug;
        return $slug && RoleEnum::from($slug)->isWorkerLevel();
    }

    public function hasRole(RoleEnum $role): bool
    {
        return $this->role?->slug === $role->value;
    }

    public function belongsToArea(int $areaId): bool
    {
        if ($this->relationLoaded('activeAreas')) {
            return $this->activeAreas->contains('id', $areaId);
        }
        return $this->activeAreas()->where('areas.id', $areaId)->exists();
    }

    public function isManagerOfArea(int $areaId): bool
    {
        if ($this->relationLoaded('managedAreas')) {
            return $this->managedAreas->contains('id', $areaId);
        }
        return $this->managedAreas()->where('id', $areaId)->exists();
    }
}
