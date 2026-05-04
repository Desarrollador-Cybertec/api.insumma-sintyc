<?php

namespace App\Notifications;

use App\Services\NotificationSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyTaskSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $summaryContent,
        public int $totalPending,
        public int $overdueCount,
        public int $dueSoonCount,
        public int $notStartedCount,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettingsService::class)->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_summary',
            'category' => 'summary',
            'total_pending' => $this->totalPending,
            'total_active' => $this->totalPending,
            'overdue_count' => $this->overdueCount,
            'due_soon_count' => $this->dueSoonCount,
            'not_started_count' => $this->notStartedCount,
            'summary_content' => $this->summaryContent,
            'message' => "Resumen diario: {$this->totalPending} activas, {$this->dueSoonCount} por vencer, {$this->notStartedCount} sin empezar, {$this->overdueCount} vencidas.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = now()->toDateString();

        return app(NotificationSettingsService::class)->buildMailMessage(
            'daily_summary',
            [
                'user_name'       => $notifiable->name,
                'date'            => $date,
                'summary_content' => $this->summaryContent,
            ],
            "Resumen diario de tareas — {$date}",
            "Hola {$notifiable->name},\n\n{$this->summaryContent}"
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
