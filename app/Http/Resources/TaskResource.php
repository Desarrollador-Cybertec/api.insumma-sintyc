<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastUpdate = $this->relationLoaded('latestUpdate') ? $this->latestUpdate : null;
        $ageDays = (int) $this->created_at->diffInDays(now());
        $daysSinceUpdate = $lastUpdate
            ? (int) $lastUpdate->created_at->diffInDays(now())
            : $ageDays;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assigner' => new UserResource($this->whenLoaded('assigner')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'assigned_area' => new AreaResource($this->whenLoaded('assignedArea')),
            'external_email' => $this->external_email,
            'external_name' => $this->external_name,
            'delegator' => new UserResource($this->whenLoaded('delegator')),
            'current_responsible' => new UserResource($this->whenLoaded('currentResponsible')),
            'area' => new AreaResource($this->whenLoaded('area')),
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at,
            'requires_attachment' => $this->requires_attachment,
            'requires_completion_comment' => $this->requires_completion_comment,
            'requires_manager_approval' => $this->requires_manager_approval,
            'requires_completion_notification' => $this->requires_completion_notification,
            'requires_due_date' => $this->requires_due_date,
            'requires_progress_report' => $this->requires_progress_report,
            'notify_on_due' => $this->notify_on_due,
            'notify_on_overdue' => $this->notify_on_overdue,
            'notify_on_completion' => $this->notify_on_completion,
            'progress_percent' => $this->progress_percent,
            'meeting' => new MeetingResource($this->whenLoaded('meeting')),
            'is_overdue' => $this->isOverdue(),
            'age_days' => $ageDays,
            'days_without_update' => $daysSinceUpdate,
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'updates_count' => $this->whenCounted('updates'),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'delegations' => TaskDelegationResource::collection($this->whenLoaded('delegations')),
            'status_history' => TaskStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'updates' => TaskUpdateResource::collection($this->whenLoaded('updates')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
