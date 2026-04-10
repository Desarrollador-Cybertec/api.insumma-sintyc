<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('role')
            ->when($request->user()->isGerente(), fn ($q) =>
                $q->whereHas('role', fn ($rq) => $rq->where('slug', '!=', \App\Enums\RoleEnum::SUPERADMIN->value))
            )
            ->when($request->query('search'), fn ($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($request->query('role'), fn ($q, $role) =>
                $q->whereHas('role', fn ($rq) => $rq->where('slug', $role))
            )
            ->when($request->has('active'), fn ($q) =>
                $q->where('active', $request->boolean('active'))
            )
            ->when($request->query('exclude_area'), fn ($q, $areaId) =>
                $q->whereDoesntHave('activeAreas', fn ($aq) =>
                    $aq->where('areas.id', $areaId)
                )
            )
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        app(LicenseService::class)->authorize('create_user', 1);

        $validated = $request->validated();
        $areaId = $validated['area_id'] ?? null;
        unset($validated['area_id']);

        $user = User::create($validated);

        if ($areaId) {
            \App\Models\AreaMember::create([
                'area_id' => $areaId,
                'user_id' => $user->id,
                'assigned_by' => $request->user()->id,
                'claimed_by' => $request->user()->id,
                'joined_at' => now(),
                'is_active' => true,
            ]);
        }

        // Reportar uso después de creación
        app(LicenseService::class)->reportUserActive();

        return response()->json(
            new UserResource($user->load(['role', 'activeAreas'])),
            201
        );
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['role', 'activeAreas']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->fresh(['role', 'activeAreas']));
    }

    public function updateRole(Request $request, User $user): UserResource
    {
        $this->authorize('updateRole', $user);

        $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $newRole = \App\Models\Role::find($request->role_id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $newRole) {
            $user->update(['role_id' => $request->role_id]);

            // When demoting from manager level to worker level, remove them as manager from areas
            $managerSlugs = collect(\App\Enums\RoleEnum::managerLevel())->map(fn ($r) => $r->value)->toArray();
            $workerSlugs = collect(\App\Enums\RoleEnum::workerLevel())->map(fn ($r) => $r->value)->toArray();
            if (in_array($user->role?->slug, $managerSlugs)
                && in_array($newRole?->slug, $workerSlugs)) {
                \App\Models\Area::where('manager_user_id', $user->id)
                    ->update(['manager_user_id' => null]);
            }
        });

        return new UserResource($user->fresh(['role', 'activeAreas']));
    }

    public function toggleActive(User $user): UserResource
    {
        $this->authorize('updateRole', $user);

        $isReactivating = !$user->active;

        if ($isReactivating) {
            app(LicenseService::class)->authorize('reactivate_user', 1);
        }

        $user->update(['active' => !$user->active]);

        if ($isReactivating) {
            // Solo reportar cuando se activa — no al desactivar
            app(LicenseService::class)->reportUserActive();
        }

        return new UserResource($user->fresh(['role', 'activeAreas']));
    }

    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('updatePassword', $user);

        $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
