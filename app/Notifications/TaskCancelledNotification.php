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

class TaskCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $cancelledBy,
        public ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_cancelled',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'cancelled_by' => $this->cancelledBy->name,
            'note' => $this->note,
            'message' => "La tarea \"{$this->task->title}\" ha sido cancelada por {$this->cancelledBy->name}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $note = $this->note ?? 'Sin motivo especificado';
        $fallbackBody = "La tarea \"{$this->task->title}\" ha sido cancelada por {$this->cancelledBy->name}.";
        if ($this->note) {
            $fallbackBody .= "\n\nMotivo: {$this->note}";
        }

        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_cancelled',
            [
                'task_title'   => $this->task->title,
                'user_name'    => $notifiable->name,
                'cancelled_by' => $this->cancelledBy->name,
                'note'         => $note,
            ],
            "Tarea cancelada: {$this->task->title}",
            $fallbackBody
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
