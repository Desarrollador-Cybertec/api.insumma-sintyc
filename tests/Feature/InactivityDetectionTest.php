<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InactivityDetectionTest extends TestCase
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
    }

    public function test_detect_inactive_alerts_tasks_without_updates(): void
    {
        $task = Task::create([
            'title' => 'Tarea sin avance',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(10)]);

        Artisan::call('tasks:detect-inactive');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_detect_inactive_ignores_tasks_with_recent_updates(): void
    {
        $task = Task::create([
            'title' => 'Tarea con avance reciente',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(10)]);

        TaskUpdate::create([
            'task_id' => $task->id,
            'user_id' => $this->worker->id,
            'update_type' => 'progress',
            'comment' => 'Avance reciente',
            'progress_percent' => 50,
            'created_at' => now()->subDay(),
        ]);

        Artisan::call('tasks:detect-inactive');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_detect_inactive_alerts_tasks_with_old_updates(): void
    {
        $task = Task::create([
            'title' => 'Tarea con avance viejo',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(20)]);

        $update = TaskUpdate::create([
            'task_id' => $task->id,
            'user_id' => $this->worker->id,
            'update_type' => 'progress',
            'comment' => 'Avance viejo',
            'progress_percent' => 30,
        ]);
        TaskUpdate::where('id', $update->id)->update(['created_at' => now()->subDays(10)]);

        Artisan::call('tasks:detect-inactive');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_detect_inactive_ignores_completed_tasks(): void
    {
        $task = Task::create([
            'title' => 'Completada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now()->subDay(),
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(20)]);

        Artisan::call('tasks:detect-inactive');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_detect_inactive_creates_consolidated_notification(): void
    {
        $t1 = Task::create([
            'title' => 'Inactiva 1',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $t1->id)->update(['created_at' => now()->subDays(10)]);

        $t2 = Task::create([
            'title' => 'Inactiva 2',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);
        Task::where('id', $t2->id)->update(['created_at' => now()->subDays(15)]);

        Artisan::call('tasks:detect-inactive');

        // Should create ONE notification for both tasks (consolidated per user)
        $notifications = $this->worker->notifications;
        $this->assertCount(1, $notifications);
        $data = $notifications->first()->data;
        $this->assertEquals(2, $data['task_count']);
    }

    public function test_detect_inactive_respects_enabled_setting(): void
    {
        SystemSetting::create([
            'key' => 'inactivity_alert_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'automation',
        ]);

        $task = Task::create([
            'title' => 'Debería ignorarse',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(10)]);

        $this->artisan('tasks:detect-inactive')
            ->expectsOutput('Alertas por inactividad desactivadas.')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_detect_inactive_respects_custom_days_setting(): void
    {
        SystemSetting::create([
            'key' => 'inactivity_alert_days',
            'value' => '15',
            'type' => 'integer',
            'group' => 'automation',
        ]);

        // Task is 10 days old — should NOT trigger with 15-day threshold
        $task = Task::create([
            'title' => 'No debería alertar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(10)]);

        Artisan::call('tasks:detect-inactive');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_superadmin_can_trigger_inactivity_detection(): void
    {
        SystemSetting::create([
            'key' => 'inactivity_alert_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'automation',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/detect-inactivity');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Detección de inactividad ejecutada correctamente']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'module' => 'automation',
            'action' => 'trigger_inactivity_detection',
        ]);
    }

    public function test_worker_cannot_trigger_inactivity_detection(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/automation/detect-inactivity');

        $response->assertForbidden();
    }

    public function test_trigger_inactivity_fails_when_disabled(): void
    {
        SystemSetting::create([
            'key' => 'inactivity_alert_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'automation',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/detect-inactivity');

        $response->assertUnprocessable();
    }
}
