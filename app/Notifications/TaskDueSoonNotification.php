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

class TaskDueSoonNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->daysRemaining === 0
            ? "La tarea \"{$this->task->title}\" vence hoy."
            : "La tarea \"{$this->task->title}\" vence en {$this->daysRemaining} día(s).";

        return [
            'type' => 'task_due_soon',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'days_remaining' => $this->daysRemaining,
            'due_date' => $this->task->due_date?->toDateString(),
            'message' => $message,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->task->due_date?->toDateString() ?? 'Sin fecha';

        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_reminder',
            [
                'task_title'     => $this->task->title,
                'user_name'      => $notifiable->name,
                'days_remaining' => $this->daysRemaining,
                'due_date'       => $dueDate,
            ],
            "Recordatorio: \"{$this->task->title}\" vence pronto",
            "La tarea \"{$this->task->title}\" vence en {$this->daysRemaining} día(s).\n\nFecha límite: {$dueDate}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
