<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SystemSetting::class);
    }

    public function rules(): array
    {
        return [
            'key'         => ['required', 'string', 'max:100', 'unique:system_settings,key'],
            'value'       => ['required', 'string'],
            'type'        => ['required', Rule::in(['string', 'boolean', 'integer', 'json'])],
            'group'       => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
