<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSystemSettingRequest;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SystemSettingController extends Controller
{
    /**
     * List all settings, optionally filtered by group.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SystemSetting::class);

        $query = SystemSetting::query();

        if ($group = $request->query('group')) {
            $query->where('group', $group);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        // Group settings by their group field
        $grouped = $settings->groupBy('group')->map(
            fn ($items) => SystemSettingResource::collection($items)
        );

        return response()->json(['data' => $grouped]);
    }

    /**
     * Create a new setting key.
     */
    public function store(StoreSystemSettingRequest $request): JsonResponse
    {
        $setting = SystemSetting::create($request->validated());

        return (new SystemSettingResource($setting))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a setting key.
     */
    public function destroy(SystemSetting $systemSetting): Response
    {
        $this->authorize('delete', $systemSetting);

        $systemSetting->delete();

        return response()->noContent();
    }

    /**
     * Bulk update settings.
     */
    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        foreach ($request->settings as $item) {
            SystemSetting::setValue($item['key'], $item['value']);
        }

        // Return fresh grouped settings so the client can update its state
        $settings = SystemSetting::query()->orderBy('group')->orderBy('key')->get();

        $grouped = $settings->groupBy('group')->map(
            fn ($items) => SystemSettingResource::collection($items)
        );

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'data'    => $grouped,
        ]);
    }
}
