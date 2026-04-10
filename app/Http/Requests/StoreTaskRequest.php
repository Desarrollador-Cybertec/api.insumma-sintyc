<?php

namespace App\Http\Requests;

use App\Enums\TaskPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'assigned_to_area_id' => ['nullable', 'exists:areas,id'],
            'external_email' => ['nullable', 'email', 'max:255'],
            'external_name' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::enum(TaskPriorityEnum::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'requires_completion_comment' => ['sometimes', 'boolean'],
            'requires_manager_approval' => ['sometimes', 'boolean'],
            'requires_completion_notification' => ['sometimes', 'boolean'],
            'requires_due_date' => ['sometimes', 'boolean'],
            'requires_progress_report' => ['sometimes', 'boolean'],
            'notify_on_due' => ['sometimes', 'boolean'],
            'notify_on_overdue' => ['sometimes', 'boolean'],
            'notify_on_completion' => ['sometimes', 'boolean'],
            'meeting_id' => ['nullable', 'exists:meetings,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();
                $hasUser = $this->filled('assigned_to_user_id');
                $hasArea = $this->filled('assigned_to_area_id');
                $hasExternal = $this->filled('external_email');

                // At least one target is required
                if (!$hasUser && !$hasArea && !$hasExternal) {
                    $validator->errors()->add(
                        'assigned_to_user_id',
                        'Debe asignar a un usuario, un área o un correo externo.'
                    );
                    return;
                }

                // Only one target type allowed
                if (($hasUser && $hasArea) || ($hasUser && $hasExternal) || ($hasArea && $hasExternal)) {
                    $validator->errors()->add(
                        'assigned_to_user_id',
                        'Solo puede asignar a un usuario, un área o un correo externo, no combinaciones.'
                    );
                    return;
                }

                if ($user->isAdminLevel()) {
                    return; // admin level can assign to anyone
                }

                if ($user->isManagerLevel()) {
                    if ($hasUser) {
                        $targetUserId = (int) $this->input('assigned_to_user_id');

                        // Self-assignment is always allowed
                        if ($targetUserId === $user->id) {
                            return;
                        }

                        // Can assign to members in their managed areas
                        $managedAreaIds = $user->managedAreas()->pluck('id');

                        // Also can assign to members of areas they belong to
                        $memberAreaIds = $user->activeAreas()->pluck('areas.id');

                        $allowedAreaIds = $managedAreaIds->merge($memberAreaIds)->unique();

                        $inAllowedArea = \DB::table('area_members')
                            ->where('user_id', $targetUserId)
                            ->where('is_active', true)
                            ->whereIn('area_id', $allowedAreaIds)
                            ->exists();

                        if (!$inAllowedArea) {
                            $validator->errors()->add(
                                'assigned_to_user_id',
                                'Solo puede asignar tareas a miembros de sus áreas o a sí mismo.'
                            );
                        }
                    }
                    // Can assign to any area (cross-area) and external email → allowed
                    return;
                }

                if ($user->isWorkerLevel()) {
                    // Workers cannot assign to areas
                    if ($hasArea) {
                        $validator->errors()->add(
                            'assigned_to_area_id',
                            'Los trabajadores no pueden asignar tareas a áreas.'
                        );
                        return;
                    }

                    // Workers can only assign to themselves
                    if ($hasUser && (int) $this->input('assigned_to_user_id') !== $user->id) {
                        $validator->errors()->add(
                            'assigned_to_user_id',
                            'Los trabajadores solo pueden crear tareas para sí mismos.'
                        );
                        return;
                    }

                    // External email → allowed
                    return;
                }

                $validator->errors()->add('assigned_to_user_id', 'No tiene permisos para crear tareas.');
            },
        ];
    }
}
