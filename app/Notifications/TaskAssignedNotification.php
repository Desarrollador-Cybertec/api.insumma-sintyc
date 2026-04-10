<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Services\NotificationSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'assigned_by' => $this->assignedBy->name,
            'priority' => $this->task->priority->value ?? $this->task->priority,
            'due_date' => $this->task->due_date?->toDateString(),
            'message' => "Se te ha asignado la tarea \"{$this->task->title}\".",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $priority = $this->task->priority->value ?? $this->task->priority;
        $dueDate = $this->task->due_date?->toDateString() ?? 'Sin fecha';

        return app(NotificationSettingsService::class)->buildMailMessage(
            'new_assignment',
            [
                'task_title' => $this->task->title,
                'user_name'  => $notifiable->name,
                'priority'   => $priority,
                'due_date'   => $dueDate,
            ],
            "Nueva tarea asignada: {$this->task->title}",
            "Se te ha asignado la tarea \"{$this->task->title}\".\n\nPrioridad: {$priority}\nFecha límite: {$dueDate}\n\nPor favor revisa los detalles en la plataforma."
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
