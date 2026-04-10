<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentTypeEnum;
use App\Events\TaskAttachmentAdded;
use App\Events\TaskCommentAdded;
use App\Events\TaskUpdateAdded;
use App\Http\Requests\ApproveTaskRequest;
use App\Http\Requests\DelegateTaskRequest;
use App\Http\Requests\RejectTaskRequest;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\StoreTaskUpdateRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskAttachmentResource;
use App\Http\Resources\TaskCommentResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskUpdateResource;
use App\Enums\TaskStatusEnum;
use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskStatusHistory;
use App\Models\TaskUpdate;
use App\Services\TaskCompletionService;
use App\Services\TaskCreationService;
use App\Services\TaskDelegationService;
use App\Services\TaskStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tasks = Task::with(['creator', 'currentResponsible', 'area', 'latestUpdate'])
            ->withCount(['comments', 'attachments'])
            ->when($user->isAdminLevel(), function ($q) use ($user) {
                // Superadmin/Gerente sees all organization tasks + their own personal tasks
                $q->where(function ($query) use ($user) {
                    $query->whereNotNull('area_id')
                          ->orWhere('created_by', $user->id);
                });
            })
            ->when(!$user->isAdminLevel(), function ($q) use ($user) {
                $q->where(function ($query) use ($user) {
                    // 1. Personal tasks created by the user (no area)
                    $query->where(function ($q) use ($user) {
                        $q->whereNull('area_id')->where('created_by', $user->id);
                    });

                    // 2. Area tasks where user is currently responsible AND task is still active
                    $terminalStatuses = [
                        \App\Enums\TaskStatusEnum::COMPLETED->value,
                        \App\Enums\TaskStatusEnum::CANCELLED->value,
                    ];
                    $query->orWhere(function ($q) use ($user, $terminalStatuses) {
                        $q->where('current_responsible_user_id', $user->id)
                          ->whereNotIn('status', $terminalStatuses);
                    });

                    if ($user->isManagerLevel()) {
                        // 3. Non-worker-created tasks in areas the manager currently manages
                        $workerLevelSlugs = collect(\App\Enums\RoleEnum::workerLevel())
                            ->map(fn ($r) => $r->value)->toArray();

                        $workerIds = \App\Models\User::whereHas('role', fn ($r) =>
                            $r->whereIn('slug', $workerLevelSlugs)
                        )->select('id');

                        $query->orWhere(function ($q) use ($user, $workerIds) {
                            $q->whereIn('area_id', Area::where('manager_user_id', $user->id)->select('id'))
                              ->whereNotIn('created_by', $workerIds);
                        });
                    }
                });
            })
            ->when($request->query('status'), fn ($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->query('priority'), fn ($q, $priority) =>
                $q->where('priority', $priority)
            )
            ->when($request->query('area_id'), fn ($q, $areaId) =>
                $q->where('area_id', $areaId)
            )
            ->when($request->query('sort'), function ($q, $sort) {
                return match ($sort) {
                    'oldest' => $q->oldest(),
                    'due_date' => $q->orderBy('due_date'),
                    'priority' => $q->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END"),
                    default => $q->latest(),
                };
            }, fn ($q) => $q->latest())
            ->paginate(20);

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, TaskCreationService $service): JsonResponse
    {
        $task = $service->create($request->validated(), $request->user());

        return response()->json(
            new TaskResource($task->load(['creator', 'assignedUser', 'assignedArea', 'area', 'latestUpdate'])),
            201
        );
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource(
            $task->load([
                'creator',
                'assigner',
                'assignedUser',
                'assignedArea',
                'delegator',
                'currentResponsible',
                'area',
                'meeting',
                'comments.user',
                'attachments.uploader',
                'delegations.fromUser',
                'delegations.toUser',
                'statusHistory.changedByUser',
                'statusHistory.responsibleUser',
                'updates.user',
                'latestUpdate',
            ])->loadCount(['comments', 'attachments', 'updates'])
        );
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->fresh(['creator', 'currentResponsible', 'area', 'latestUpdate']));
    }

    public function claim(Task $task): TaskResource|JsonResponse
    {
        $this->authorize('claim', $task);

        if ($task->status !== TaskStatusEnum::PENDING_ASSIGNMENT) {
            return response()->json(
                ['message' => 'Solo se puede reclamar una tarea en estado pending_assignment.'],
                422
            );
        }

        $user = request()->user();

        $task->update([
            'current_responsible_user_id' => $user->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'changed_by' => $user->id,            'user_id'     => $user->id,            'from_status' => TaskStatusEnum::PENDING_ASSIGNMENT,
            'to_status' => TaskStatusEnum::PENDING,
            'note' => 'Tarea reclamada como responsable',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'tasks',
            'action' => 'claimed',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'description' => "Tarea reclamada por {$user->name}",
        ]);

        return new TaskResource($task->fresh(['currentResponsible', 'area', 'latestUpdate']));
    }

    public function delegate(DelegateTaskRequest $request, Task $task, TaskDelegationService $service): TaskResource
    {
        $task = $service->delegate(
            $task,
            $request->user(),
            $request->to_user_id,
            $request->note
        );

        return new TaskResource($task->load(['currentResponsible', 'delegator', 'area', 'latestUpdate']));
    }

    public function start(Request $request, Task $task, TaskStatusService $service): TaskResource
    {
        $this->authorize('start', $task);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $task = $service->start($task, $request->user(), $validated['comment']);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate']));
    }

    public function submitForReview(Request $request, Task $task, TaskCompletionService $service): TaskResource
    {
        $this->authorize('submitForReview', $task);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $task = $service->submitForReview($task, $request->user(), $validated['comment']);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate']));
    }

    public function approve(ApproveTaskRequest $request, Task $task, TaskCompletionService $service): TaskResource
    {
        $task = $service->approve($task, $request->user(), $request->note);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate']));
    }

    public function reject(RejectTaskRequest $request, Task $task, TaskCompletionService $service): TaskResource
    {
        $task = $service->reject($task, $request->user(), $request->note);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->forceDelete();

        return response()->json(['message' => 'Tarea eliminada correctamente.']);
    }

    public function cancel(Request $request, Task $task, TaskStatusService $service): TaskResource
    {
        $this->authorize('cancel', $task);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $task = $service->cancel($task, $request->user(), $validated['comment']);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate', 'statusHistory.changedByUser', 'statusHistory.responsibleUser']));
    }

    public function reopen(Request $request, Task $task, TaskStatusService $service): TaskResource
    {
        $this->authorize('reopen', $task);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $task = $service->reopen($task, $request->user(), $validated['comment']);

        return new TaskResource($task->load(['currentResponsible', 'area', 'latestUpdate', 'statusHistory.changedByUser', 'statusHistory.responsibleUser']));
    }

    public function comment(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $validated = $request->validated();

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
            'type' => $validated['type'] ?? 'comment',
        ]);

        event(new TaskCommentAdded($task, $comment, $request->user()));

        return response()->json(
            new TaskCommentResource($comment->load('user')),
            201
        );
    }

    public function addAttachment(StoreTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');
        $path = $file->store("tasks/{$task->id}", 'local');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'uploaded_by' => $request->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'attachment_type' => $validated['attachment_type'] ?? AttachmentTypeEnum::SUPPORT->value,
        ]);

        event(new TaskAttachmentAdded($task, $request->user(), $file->getClientOriginalName()));

        return response()->json(
            new TaskAttachmentResource($attachment->load('uploader')),
            201
        );
    }

    public function addUpdate(StoreTaskUpdateRequest $request, Task $task): JsonResponse
    {
        $validated = $request->validated();

        $update = TaskUpdate::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'update_type' => $validated['update_type'] ?? 'progress',
            'comment' => $validated['comment'],
            'progress_percent' => $validated['progress_percent'] ?? null,
        ]);

        if (isset($validated['progress_percent'])) {
            $task->update(['progress_percent' => $validated['progress_percent']]);
        }

        event(new TaskUpdateAdded($task, $update, $request->user()));

        return response()->json(
            new TaskUpdateResource($update->load('user')),
            201
        );
    }
}
