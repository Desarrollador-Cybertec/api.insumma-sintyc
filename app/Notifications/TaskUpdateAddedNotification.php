<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Services\NotificationSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskUpdateAddedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $addedBy,
        public string $updateType,
        public string $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_update_added',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'added_by' => $this->addedBy->name,
            'update_type' => $this->updateType,
            'comment' => $this->comment,
            'message' => "{$this->addedBy->name} registró un avance en la tarea \"{$this->task->title}\".",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_update_added',
            [
                'task_title'  => $this->task->title,
                'user_name'   => $notifiable->name,
                'added_by'    => $this->addedBy->name,
                'update_type' => $this->updateType,
                'comment'     => $this->comment,
            ],
            "Nuevo avance en: {$this->task->title}",
            "{$this->addedBy->name} registró un avance en la tarea \"{$this->task->title}\".\n\n{$this->comment}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
