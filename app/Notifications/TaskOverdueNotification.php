<?php

namespace App\Notifications;

use App\Models\Task;
use App\Services\NotificationSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public int $daysOverdue,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_overdue',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'days_overdue' => $this->daysOverdue,
            'due_date' => $this->task->due_date?->toDateString(),
            'message' => "La tarea \"{$this->task->title}\" está vencida por {$this->daysOverdue} día(s).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->task->due_date?->toDateString() ?? 'Sin fecha';

        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_overdue',
            [
                'task_title'   => $this->task->title,
                'user_name'    => $notifiable->name,
                'days_overdue' => $this->daysOverdue,
                'due_date'     => $dueDate,
            ],
            "Tarea vencida: {$this->task->title}",
            "La tarea \"{$this->task->title}\" está vencida por {$this->daysOverdue} día(s).\n\nFecha límite original: {$dueDate}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
