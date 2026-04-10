<?php

namespace App\Http\Requests;

use App\Enums\MeetingClassificationEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdminLevel() || $this->user()->id === $this->route('meeting')->created_by;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'meeting_date' => ['sometimes', 'date'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'classification' => ['nullable', Rule::enum(MeetingClassificationEnum::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
