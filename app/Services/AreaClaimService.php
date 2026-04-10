<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AreaClaimService
{
    public function claimWorker(int $userId, int $areaId, User $claimedBy): AreaMember
    {
        return DB::transaction(function () use ($userId, $areaId, $claimedBy) {
            $worker = User::findOrFail($userId);
            $area = Area::findOrFail($areaId);

            if (!$worker->isWorkerLevel() && !$worker->isManagerLevel()) {
                throw ValidationException::withMessages([
                    'user_id' => ['El usuario debe tener un rol válido para pertenecer a un área.'],
                ]);
            }

            // Check if worker already belongs to an active area
            $existingMembership = AreaMember::where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($existingMembership) {
                // Superadmin can reassign: deactivate the current membership first
                if ($claimedBy->isAdminLevel()) {
                    $existingMembership->update([
                        'is_active' => false,
                        'left_at' => now(),
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        'user_id' => ['El trabajador ya pertenece a un área activa.'],
                    ]);
                }
            }

            // Validate the claimer has authority over this area
            if (!$claimedBy->isAdminLevel() && !$claimedBy->isManagerOfArea($areaId)) {
                throw ValidationException::withMessages([
                    'area_id' => ['No tienes permiso para reclamar trabajadores en esta área.'],
                ]);
            }

            $member = AreaMember::create([
                'area_id' => $areaId,
                'user_id' => $userId,
                'assigned_by' => $claimedBy->id,
                'claimed_by' => $claimedBy->id,
                'joined_at' => now(),
                'is_active' => true,
            ]);

            ActivityLog::create([
                'user_id' => $claimedBy->id,
                'module' => 'areas',
                'action' => 'worker_claimed',
                'subject_type' => AreaMember::class,
                'subject_id' => $member->id,
                'description' => "Trabajador {$worker->name} reclamado en área {$area->name}",
            ]);

            return $member;
        });
    }

    public function removeWorker(int $userId, int $areaId, User $removedBy): void
    {
        DB::transaction(function () use ($userId, $areaId, $removedBy) {
            $member = AreaMember::where('user_id', $userId)
                ->where('area_id', $areaId)
                ->where('is_active', true)
                ->firstOrFail();

            $member->update([
                'is_active' => false,
                'left_at' => now(),
            ]);

            ActivityLog::create([
                'user_id' => $removedBy->id,
                'module' => 'areas',
                'action' => 'worker_removed',
                'subject_type' => AreaMember::class,
                'subject_id' => $member->id,
                'description' => "Trabajador removido del área",
            ]);
        });
    }
}
