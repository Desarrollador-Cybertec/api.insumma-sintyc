<?php

namespace App\Http\Requests;

use App\Enums\MeetingClassificationEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdminLevel() || $this->user()->isManagerLevel();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'classification' => ['nullable', Rule::enum(MeetingClassificationEnum::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
