<?php

namespace App\Http\Resources;

use App\Enums\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enum = RoleEnum::tryFrom($this->slug);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'is_configurable' => $enum && in_array($enum, RoleEnum::configurable()),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
