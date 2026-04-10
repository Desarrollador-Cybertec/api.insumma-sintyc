<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'from_status'      => $this->from_status,
            'to_status'        => $this->to_status,
            'note'             => $this->note,
            'changed_by'       => new UserResource($this->whenLoaded('changedByUser')),
            'user_id'          => $this->user_id,
            'responsible_user' => new UserResource($this->whenLoaded('responsibleUser')),
            'created_at'       => $this->created_at,
        ];
    }
}
