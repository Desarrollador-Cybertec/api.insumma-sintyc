<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Events\TaskAssigned;
use App\Mail\ExternalTaskMail;
use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TaskCreationService
{
    public function create(array $data, User $creator): Task
    {
        return DB::transaction(function () use ($data, $creator) {
            $isExternalTask = !empty($data['external_email']);
            $isAreaAssignment = !empty($data['assigned_to_area_id']) && empty($data['assigned_to_user_id']);

            // Resolve area_id: use explicit area, or derive from the assigned user's active area.
            // Exception: self-assignment (creator assigns to themselves) creates a personal task
            // with no area, so it won't appear in any dashboard.
            $areaId = $data['assigned_to_area_id'] ?? null;
            $isSelfAssignment = !empty($data['assigned_to_user_id'])
                && (int) $data['assigned_to_user_id'] === $creator->id;
            $isManagerAssignment = false;
            if (!$areaId && !empty($data['assigned_to_user_id']) && !$isSelfAssignment) {
                // First try area_members (worker or manager-level member)
                $areaId = DB::table('area_members')
                    ->where('user_id', $data['assigned_to_user_id'])
                    ->where('is_active', true)
                    ->value('area_id');

                // If not found in members, check if the assigned user is the area manager
                if (!$areaId) {
                    $managedAreaId = DB::table('areas')
                        ->where('manager_user_id', $data['assigned_to_user_id'])
                        ->where('active', true)
                        ->value('id');
                    if ($managedAreaId) {
                        $areaId = $managedAreaId;
                        $isManagerAssignment = true;
                    }
                }
            }

            // Determine status
            if ($isExternalTask) {
                $status = TaskStatusEnum::PENDING;
            } elseif ($isAreaAssignment || $isManagerAssignment) {
                // Area assignments AND manager-user assignments both start as pending_assignment:
                // the manager must claim the task (take responsibility) or delegate it.
                $status = TaskStatusEnum::PENDING_ASSIGNMENT;
            } else {
                $status = TaskStatusEnum::PENDING;
            }

            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'created_by' => $creator->id,
                'assigned_by' => $creator->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'assigned_to_area_id' => $data['assigned_to_area_id'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'external_name' => $data['external_name'] ?? null,
                'area_id' => $areaId,
                // Manager-user assignments: manager must claim or delegate → no responsible yet
                'current_responsible_user_id' => $isManagerAssignment ? null : ($data['assigned_to_user_id'] ?? null),
                'priority' => $data['priority'] ?? 'medium',
                'status' => $status,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'requires_attachment' => $data['requires_attachment'] ?? false,
                'requires_completion_comment' => $data['requires_completion_comment'] ?? false,
                'requires_manager_approval' => $data['requires_manager_approval'] ?? false,
                'requires_completion_notification' => $data['requires_completion_notification'] ?? false,
                'requires_due_date' => $data['requires_due_date'] ?? false,
                'requires_progress_report' => $data['requires_progress_report'] ?? false,
                'notify_on_due' => $data['notify_on_due'] ?? false,
                'notify_on_overdue' => $data['notify_on_overdue'] ?? false,
                'notify_on_completion' => $data['notify_on_completion'] ?? false,
                'meeting_id' => $data['meeting_id'] ?? null,
            ]);

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'changed_by' => $creator->id,
                'user_id' => $task->current_responsible_user_id,
                'from_status' => null,
                'to_status' => $task->status,
                'note' => 'Tarea creada',
            ]);

            ActivityLog::create([
                'user_id' => $creator->id,
                'module' => 'tasks',
                'action' => 'created',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Tarea \"{$task->title}\" creada",
            ]);

            // Send email to external recipient
            if ($isExternalTask) {
                Mail::to($data['external_email'])->queue(new ExternalTaskMail($task));
            }

            // Dispatch notification for task assignment
            if ($task->current_responsible_user_id) {
                // Direct assignment to a worker — notify them via event
                event(new TaskAssigned($task, $creator));
            } elseif ($task->status === TaskStatusEnum::PENDING_ASSIGNMENT) {
                // Area assignment or manager-user assignment:
                // the task is pending_assignment and needs the manager to claim/delegate.
                // Notify the area manager so they know a task is waiting.
                if ($isManagerAssignment && !empty($data['assigned_to_user_id'])) {
                    // Assigned directly to a manager-user: notify that manager
                    $manager = User::find($data['assigned_to_user_id']);
                    if ($manager && $manager->id !== $creator->id) {
                        $manager->notify(new TaskAssignedNotification($task, $creator));
                    }
                } elseif ($isAreaAssignment && $areaId) {
                    // Assigned to an area: notify the area's manager
                    $area = Area::find($areaId);
                    $manager = $area?->manager;
                    if ($manager && $manager->id !== $creator->id) {
                        $manager->notify(new TaskAssignedNotification($task, $creator));
                    }
                }
            }

            return $task;
        });
    }
}
