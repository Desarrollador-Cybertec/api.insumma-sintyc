<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Events\TaskAttachmentAdded;
use App\Models\Attachment;
use App\Models\Area;
use App\Models\Task;
use App\Services\AttachmentUploadService;
use App\Services\AttachmentUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(
        private AttachmentUploadService $uploadService,
        private AttachmentUrlService $urlService,
    ) {}

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attachment = $this->uploadService->upload(
            file: $request->file('file'),
            user: $request->user(),
            taskId: $validated['task_id'] ?? null,
            areaId: $validated['area_id'] ?? null,
        );

        // Notify task participants when a file is attached to a task
        if (!empty($validated['task_id'])) {
            $task = \App\Models\Task::find($validated['task_id']);
            if ($task) {
                event(new TaskAttachmentAdded($task, $request->user(), $request->file('file')->getClientOriginalName()));
            }
        }

        return response()->json([
            'message' => 'Archivo recibido y enviado a procesamiento.',
            'data' => new AttachmentResource($attachment->load('uploader')),
        ], 201);
    }

    public function taskAttachments(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $attachments = Attachment::with('uploader')
            ->where('task_id', $task->id)
            ->where('processing_status', 'ready')
            ->latest()
            ->paginate(20);

        return AttachmentResource::collection($attachments);
    }

    public function areaAttachments(Request $request, Area $area): AnonymousResourceCollection
    {
        $user = $request->user();

        abort_unless(
            $user->isAdminLevel() || $user->isManagerOfArea($area->id) || $user->belongsToArea($area->id),
            403,
            'No autorizado.'
        );

        $attachments = Attachment::with('uploader')
            ->where('area_id', $area->id)
            ->where('processing_status', 'ready')
            ->latest()
            ->paginate(20);

        return AttachmentResource::collection($attachments);
    }

    public function signedUrl(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);

        $download = $request->boolean('download', false);
        $url = $this->urlService->signedUrl($attachment, $download);

        if (!$url) {
            return response()->json([
                'message' => 'El archivo aún no está disponible.',
            ], 422);
        }

        return response()->json([
            'url' => $url,
            'expires_at' => $this->urlService->expiresAt($download),
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        // Delete from storage if already uploaded
        if ($attachment->isReady() && $attachment->storage_path) {
            Storage::disk($attachment->disk)->delete($attachment->storage_path);
        }

        // Delete temp file if still pending
        if ($attachment->isPending() && $attachment->storage_path) {
            Storage::disk('local')->delete($attachment->storage_path);
        }

        $attachment->delete();

        return response()->json([
            'message' => 'Archivo eliminado correctamente.',
        ]);
    }
}
