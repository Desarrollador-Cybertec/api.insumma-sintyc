<?php

namespace App\Notifications;

use App\Services\NotificationSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class TaskInactivityNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, array{task_id: int, task_title: string, days_inactive: int, due_date: ?string}> $inactiveTasks
     */
    public function __construct(
        public Collection $inactiveTasks,
        public int $inactivityDays,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inactivity_alert',
            'category' => 'summary',
            'inactivity_days' => $this->inactivityDays,
            'task_count' => $this->inactiveTasks->count(),
            'tasks' => $this->inactiveTasks->toArray(),
            'message' => "Tienes {$this->inactiveTasks->count()} tarea(s) sin avance en los últimos {$this->inactivityDays} días.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $taskList = $this->inactiveTasks->map(
            fn (array $t) => "- {$t['task_title']} ({$t['days_inactive']} días sin avance)"
        )->implode("\n");

        return app(NotificationSettingsService::class)->buildMailMessage(
            'inactivity_alert',
            [
                'user_name'       => $notifiable->name,
                'inactivity_days' => $this->inactivityDays,
                'task_list'       => $taskList,
            ],
            "Alerta: Tareas sin avance desde hace {$this->inactivityDays} días",
            "Tienes {$this->inactiveTasks->count()} tarea(s) sin avance en los últimos {$this->inactivityDays} días:\n\n{$taskList}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
