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

class TaskDelegatedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $delegatedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_delegated',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'delegated_by' => $this->delegatedBy->name,
            'due_date' => $this->task->due_date?->toDateString(),
            'message' => "Se te ha delegado la tarea \"{$this->task->title}\" por {$this->delegatedBy->name}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->task->due_date?->toDateString() ?? 'Sin fecha';
        $priority = $this->task->priority->value ?? $this->task->priority;

        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_delegated',
            [
                'task_title'   => $this->task->title,
                'user_name'    => $notifiable->name,
                'delegated_by' => $this->delegatedBy->name,
                'due_date'     => $dueDate,
                'priority'     => $priority,
            ],
            "Tarea delegada: {$this->task->title}",
            "Se te ha delegado la tarea \"{$this->task->title}\" por {$this->delegatedBy->name}.\n\nFecha límite: {$dueDate}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
