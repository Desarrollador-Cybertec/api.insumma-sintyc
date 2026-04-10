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

class TaskAttachmentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $addedBy,
        public string $fileName,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_attachment_added',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'added_by' => $this->addedBy->name,
            'file_name' => $this->fileName,
            'message' => "{$this->addedBy->name} adjuntó un archivo en la tarea \"{$this->task->title}\".",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_attachment_added',
            [
                'task_title' => $this->task->title,
                'user_name'  => $notifiable->name,
                'added_by'   => $this->addedBy->name,
                'file_name'  => $this->fileName,
            ],
            "Nuevo adjunto en: {$this->task->title}",
            "{$this->addedBy->name} adjuntó \"{$this->fileName}\" en la tarea \"{$this->task->title}\"."
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
