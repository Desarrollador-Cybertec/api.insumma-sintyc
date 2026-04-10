<?php

namespace App\Http\Requests;

use App\Enums\TaskPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMeetingTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdminLevel() || $this->user()->isManagerLevel();
    }

    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'min:1', 'max:50'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'tasks.*.assigned_to_area_id' => ['nullable', 'exists:areas,id'],
            'tasks.*.external_email' => ['nullable', 'email', 'max:255'],
            'tasks.*.external_name' => ['nullable', 'string', 'max:255'],
            'tasks.*.priority' => ['sometimes', Rule::enum(TaskPriorityEnum::class)],
            'tasks.*.start_date' => ['nullable', 'date'],
            'tasks.*.due_date' => ['nullable', 'date', 'after_or_equal:tasks.*.start_date'],
            'tasks.*.requires_attachment' => ['sometimes', 'boolean'],
            'tasks.*.requires_completion_comment' => ['sometimes', 'boolean'],
            'tasks.*.requires_manager_approval' => ['sometimes', 'boolean'],
            'tasks.*.requires_completion_notification' => ['sometimes', 'boolean'],
            'tasks.*.requires_due_date' => ['sometimes', 'boolean'],
            'tasks.*.requires_progress_report' => ['sometimes', 'boolean'],
            'tasks.*.notify_on_due' => ['sometimes', 'boolean'],
            'tasks.*.notify_on_overdue' => ['sometimes', 'boolean'],
            'tasks.*.notify_on_completion' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();
                $tasks = $this->input('tasks', []);

                foreach ($tasks as $index => $task) {
                    $hasUser = !empty($task['assigned_to_user_id']);
                    $hasArea = !empty($task['assigned_to_area_id']);
                    $hasExternal = !empty($task['external_email']);

                    if (!$hasUser && !$hasArea && !$hasExternal) {
                        $validator->errors()->add(
                            "tasks.{$index}.assigned_to_user_id",
                            'Debe asignar a un usuario, un área o un correo externo.'
                        );
                        continue;
                    }

                    if (($hasUser && $hasArea) || ($hasUser && $hasExternal) || ($hasArea && $hasExternal)) {
                        $validator->errors()->add(
                            "tasks.{$index}.assigned_to_user_id",
                            'Solo puede asignar a un usuario, un área o un correo externo, no combinaciones.'
                        );
                        continue;
                    }

                    if ($user->isAdminLevel()) {
                        continue;
                    }

                    if ($user->isManagerLevel() && $hasUser) {
                        $targetUserId = (int) $task['assigned_to_user_id'];

                        if ($targetUserId === $user->id) {
                            continue;
                        }

                        $managedAreaIds = $user->managedAreas()->pluck('id');
                        $inManagedArea = \DB::table('area_members')
                            ->where('user_id', $targetUserId)
                            ->where('is_active', true)
                            ->whereIn('area_id', $managedAreaIds)
                            ->exists();

                        if (!$inManagedArea) {
                            $validator->errors()->add(
                                "tasks.{$index}.assigned_to_user_id",
                                'Solo puede asignar tareas a trabajadores de sus áreas o a sí mismo.'
                            );
                        }
                    }
                }
            },
        ];
    }
}
