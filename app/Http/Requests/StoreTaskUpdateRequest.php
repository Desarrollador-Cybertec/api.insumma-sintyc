<?php

namespace App\Http\Requests;

use App\Enums\UpdateTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task->current_responsible_user_id === $this->user()->id
            || $this->user()->isSuperAdmin()
            || ($task->area_id && $this->user()->isManagerOfArea($task->area_id));
    }

    public function rules(): array
    {
        return [
            'update_type' => ['sometimes', Rule::enum(UpdateTypeEnum::class)],
            'comment' => ['required', 'string', 'max:5000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
