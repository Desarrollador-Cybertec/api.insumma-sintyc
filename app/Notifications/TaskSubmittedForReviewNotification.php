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

class TaskSubmittedForReviewNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_submitted_for_review',
            'category' => $this->task->area_id ? 'organizational' : 'personal',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'submitted_by' => $this->submittedBy->name,
            'message' => "{$this->submittedBy->name} envió la tarea \"{$this->task->title}\" a revisión.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(NotificationSettingsService::class)->buildMailMessage(
            'task_submitted_review',
            [
                'task_title'   => $this->task->title,
                'user_name'    => $notifiable->name,
                'submitted_by' => $this->submittedBy->name,
            ],
            "Tarea enviada a revisión: {$this->task->title}",
            "{$this->submittedBy->name} envió la tarea \"{$this->task->title}\" a revisión.\n\nRequiere tu aprobación."
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
