<?php

namespace App\Http\Requests;

use App\Enums\TaskPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('notify_on_assignment_start')) {
            $merged['requires_progress_report'] = $this->boolean('notify_on_assignment_start');
        }

        if ($this->exists('notify_on_review_completion')) {
            $value = $this->boolean('notify_on_review_completion');

            $merged['requires_completion_notification'] = $value;
            $merged['notify_on_completion'] = $value;
        }

        if ($this->exists('notify_on_due_overdue')) {
            $value = $this->boolean('notify_on_due_overdue');

            $merged['notify_on_due'] = $value;
            $merged['notify_on_overdue'] = $value;
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::enum(TaskPriorityEnum::class)],
            'requires_attachment' => ['sometimes', 'boolean'],
            'requires_completion_comment' => ['sometimes', 'boolean'],
            'requires_manager_approval' => ['sometimes', 'boolean'],
            'requires_completion_notification' => ['sometimes', 'boolean'],
            'requires_due_date' => ['sometimes', 'boolean'],
            'requires_progress_report' => ['sometimes', 'boolean'],
            'notify_on_due' => ['sometimes', 'boolean'],
            'notify_on_overdue' => ['sometimes', 'boolean'],
            'notify_on_completion' => ['sometimes', 'boolean'],
            'notify_on_assignment_start' => ['sometimes', 'boolean'],
            'notify_on_review_completion' => ['sometimes', 'boolean'],
            'notify_on_due_overdue' => ['sometimes', 'boolean'],
        ];
    }
}
