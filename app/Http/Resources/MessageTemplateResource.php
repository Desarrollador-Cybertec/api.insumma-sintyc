<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'active' => $this->active,
            'updated_at' => $this->updated_at,
        ];
    }
}
