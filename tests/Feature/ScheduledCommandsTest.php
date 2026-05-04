<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyTaskSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $superadminRole = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $workerRole = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);

        $this->admin = User::factory()->create([
            'role_id' => $superadminRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id' => $workerRole->id,
            'password' => Hash::make('Password1'),
        ]);

        SystemSetting::create(['key' => 'emails_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications']);
        SystemSetting::create(['key' => 'daily_summary_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications']);
        SystemSetting::create(['key' => 'alert_days_before_due', 'value' => '3', 'type' => 'integer', 'group' => 'notifications']);
        SystemSetting::create(['key' => 'send_reminders_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'automation']);
    }

    public function test_daily_summary_includes_due_soon_not_started_and_overdue_counts(): void
    {
        Task::create([
            'title' => 'Sin empezar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
            'due_date' => now()->addDay(),
        ]);

        Task::create([
            'title' => 'En progreso',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDays(2),
        ]);

        Task::create([
            'title' => 'Vencida',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDay(),
        ]);

        Notification::fake();

        Artisan::call('tasks:send-daily-summary');

        Notification::assertSentTo(
            $this->worker,
            DailyTaskSummaryNotification::class,
            function (DailyTaskSummaryNotification $notification) {
                return $notification->totalPending === 3
                    && $notification->dueSoonCount === 2
                    && $notification->notStartedCount === 1
                    && $notification->overdueCount === 1
                    && str_contains($notification->summaryContent, 'Detalle de tareas por vencer:')
                    && str_contains($notification->summaryContent, 'Sin empezar')
                    && str_contains($notification->summaryContent, 'En progreso');
            }
        );
    }

    public function test_due_reminders_notify_only_tasks_due_today(): void
    {
        Task::create([
            'title' => 'Vence hoy',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
            'notify_on_due' => true,
        ]);

        Task::create([
            'title' => 'Vence mañana',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDay(),
            'notify_on_due' => true,
        ]);

        Artisan::call('tasks:send-due-reminders');

        $notifications = $this->worker->notifications()->get();

        $this->assertCount(1, $notifications);
        $this->assertSame('Vence hoy', $notifications->first()->data['task_title']);
        $this->assertSame(0, $notifications->first()->data['days_remaining']);
    }

    public function test_due_reminders_do_not_duplicate_same_day_notification(): void
    {
        Task::create([
            'title' => 'Única del día',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
            'notify_on_due' => true,
        ]);

        Artisan::call('tasks:send-due-reminders');
        Artisan::call('tasks:send-due-reminders');

        $this->assertCount(1, $this->worker->notifications()->get());
    }

    public function test_due_reminders_ignore_tasks_without_flag(): void
    {
        Task::create([
            'title' => 'Sin flag',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
            'notify_on_due' => false,
            'notify_on_overdue' => false,
        ]);

        Artisan::call('tasks:send-due-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }
}
