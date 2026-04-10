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

class TaskRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $rejectedBy,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_rejected',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'rejected_by' => $this->rejectedBy->name,
            'reason' => $this->reason,
            'message' => "La tarea \"{$this->task->title}\" ha sido rechazada: {$this->reason}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_rejected',
            [
                'task_title'       => $this->task->title,
                'user_name'        => $notifiable->name,
                'rejection_reason' => $this->reason,
                'rejected_by'      => $this->rejectedBy->name,
            ],
            "Tarea rechazada: {$this->task->title}",
            "La tarea \"{$this->task->title}\" necesita correcciones.\n\nMotivo: {$this->reason}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
