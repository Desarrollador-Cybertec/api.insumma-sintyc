<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        return RoleResource::collection($roles);
    }

    public function toggleActive(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            abort(403);
        }

        $configurableSlugs = collect(RoleEnum::configurable())
            ->map(fn ($r) => $r->value)->toArray();

        if (!in_array($role->slug, $configurableSlugs)) {
            return response()->json([
                'message' => 'Este rol no puede ser activado/desactivado.',
            ], 422);
        }

        $role->update(['is_active' => !$role->is_active]);

        return response()->json([
            'message' => $role->is_active
                ? "Rol \"{$role->name}\" activado."
                : "Rol \"{$role->name}\" desactivado.",
            'role' => new RoleResource($role),
        ]);
    }
}
