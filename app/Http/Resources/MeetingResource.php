<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'meeting_date' => $this->meeting_date?->toDateString(),
            'area' => new AreaResource($this->whenLoaded('area')),
            'classification' => $this->classification,
            'notes' => $this->notes,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'is_closed' => $this->is_closed,
            'closed_at' => $this->closed_at,
            'tasks_count' => $this->whenCounted('tasks'),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
