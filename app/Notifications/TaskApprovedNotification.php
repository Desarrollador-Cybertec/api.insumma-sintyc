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

class TaskApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $approvedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_approved',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'approved_by' => $this->approvedBy->name,
            'message' => "La tarea \"{$this->task->title}\" ha sido aprobada.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_approved',
            [
                'task_title'  => $this->task->title,
                'user_name'   => $notifiable->name,
                'approved_by' => $this->approvedBy->name,
            ],
            "Tarea aprobada: {$this->task->title}",
            "La tarea \"{$this->task->title}\" ha sido aprobada.\n\n¡Buen trabajo!"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
