<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelegateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delegate', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
