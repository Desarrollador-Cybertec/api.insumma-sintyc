<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'task_id' => $this->task_id,
            'area_id' => $this->area_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_original' => $this->size_original,
            'size_processed' => $this->size_processed,
            'processing_status' => $this->processing_status,
            'visibility_scope' => $this->visibility_scope,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'processed_at' => $this->processed_at,
            'uploaded_at' => $this->uploaded_at,
            'created_at' => $this->created_at,
        ];
    }
}
