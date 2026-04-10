<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClaimWorkerRequest;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Http\Resources\AreaResource;
use App\Http\Resources\UserResource;
use App\Models\Area;
use App\Models\User;
use App\Services\AreaClaimService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Area::class);

        $user = $request->user();

        $areas = Area::with('manager')
            ->withCount('activeWorkers')
            ->when($user->isWorkerLevel(), function ($q) use ($user) {
                $q->where(function ($query) use ($user) {
                    $query->where('manager_user_id', $user->id)
                        ->orWhereHas('activeMembers', fn ($mq) => $mq->where('users.id', $user->id));
                });
            })
            ->when($request->has('active'), fn ($q) =>
                $q->where('active', $request->boolean('active'))
            )
            ->orderBy('name')
            ->paginate(20);

        return AreaResource::collection($areas);
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        app(LicenseService::class)->authorize('create_area', 1);

        $area = Area::create($request->validated());

        return response()->json(
            new AreaResource($area->load('manager')),
            201
        );
    }

    public function show(Area $area): AreaResource
    {
        $this->authorize('view', $area);

        return new AreaResource(
            $area->load(['manager', 'activeMembers.role'])
                ->loadCount('activeWorkers')
        );
    }

    public function update(UpdateAreaRequest $request, Area $area): AreaResource
    {
        $area->update($request->validated());

        return new AreaResource($area->fresh('manager'));
    }

    public function destroy(Area $area): JsonResponse
    {
        $this->authorize('delete', $area);

        if ($area->tasks()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un área que tiene tareas asociadas.',
            ], 422);
        }

        $area->activeMembers()->detach();
        $area->delete();

        return response()->json(['message' => 'Área eliminada correctamente.']);
    }

    public function assignManager(Request $request, Area $area): AreaResource
    {
        $this->authorize('assignManager', $area);

        $request->validate([
            'manager_user_id' => ['required', 'exists:users,id'],
        ]);

        $area->update(['manager_user_id' => $request->manager_user_id]);

        return new AreaResource($area->fresh('manager'));
    }

    public function claimWorker(ClaimWorkerRequest $request, AreaClaimService $service): JsonResponse
    {
        $member = $service->claimWorker(
            $request->user_id,
            $request->area_id,
            $request->user()
        );

        return response()->json([
            'message' => 'Trabajador reclamado exitosamente.',
            'member' => $member->load(['user', 'area']),
        ], 201);
    }

    public function availableWorkers(Request $request, Area $area): AnonymousResourceCollection
    {
        $this->authorize('claimWorker', $area);

        $workerSlugs = collect(\App\Enums\RoleEnum::workerLevel())
            ->map(fn ($r) => $r->value)->toArray();

        $users = User::with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $workerSlugs))
            ->where('active', true)
            ->whereDoesntHave('activeAreas')
            ->when($request->query('search'), fn ($q, $search) =>
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function members(Request $request, Area $area): AnonymousResourceCollection
    {
        $this->authorize('view', $area);

        $users = User::with('role')
            ->whereHas('activeAreas', fn ($q) => $q->where('areas.id', $area->id))
            ->when($request->query('search'), fn ($q, $search) =>
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function removeMember(Area $area, User $user): JsonResponse
    {
        $this->authorize('removeMember', $area);

        $member = $area->activeMembers()->where('users.id', $user->id)->first();

        if (!$member) {
            return response()->json([
                'message' => 'El usuario no es miembro activo de esta área.',
            ], 422);
        }

        $area->activeMembers()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at'   => now(),
        ]);

        return response()->json(['message' => 'Miembro desasignado del área correctamente.']);
    }
}
