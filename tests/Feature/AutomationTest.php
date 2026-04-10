<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;
    private User $manager;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $superadminRole = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $workerRole = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);
        $managerRole = Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);

        $this->admin = User::factory()->create([
            'role_id' => $superadminRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id' => $workerRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->manager = User::factory()->create([
            'role_id' => $managerRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->area = Area::create([
            'name' => 'Área Test',
            'process_identifier' => 'TEST',
            'manager_user_id' => $this->manager->id,
        ]);

        // Seed required settings
        SystemSetting::create([
            'key' => 'emails_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'notifications',
        ]);

        SystemSetting::create([
            'key' => 'daily_summary_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'notifications',
        ]);

        SystemSetting::create([
            'key' => 'detect_overdue_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'automation',
        ]);

        SystemSetting::create([
            'key' => 'alert_days_before_due',
            'value' => '3',
            'type' => 'integer',
            'group' => 'notifications',
        ]);

        SystemSetting::create([
            'key' => 'inactivity_alert_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'automation',
        ]);
    }

    // ── Trigger Overdue Detection ──

    public function test_superadmin_can_trigger_overdue_detection(): void
    {
        Task::create([
            'title' => 'Tarea vencida',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/detect-overdue');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Detección de tareas vencidas ejecutada correctamente']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'module' => 'automation',
            'action' => 'trigger_overdue_detection',
        ]);
    }

    public function test_worker_cannot_trigger_overdue_detection(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/automation/detect-overdue');

        $response->assertForbidden();
    }

    // ── Trigger Daily Summary ──

    public function test_superadmin_can_trigger_daily_summary(): void
    {
        Task::create([
            'title' => 'Pendiente',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/send-summary');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Resumen diario enviado correctamente']);
    }

    public function test_trigger_summary_fails_when_disabled(): void
    {
        SystemSetting::setValue('daily_summary_enabled', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/send-summary');

        $response->assertUnprocessable();
    }

    public function test_worker_cannot_trigger_daily_summary(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/automation/send-summary');

        $response->assertForbidden();
    }

    // ── Trigger Due Reminders ──

    public function test_superadmin_can_trigger_due_reminders(): void
    {
        Task::create([
            'title' => 'Vence pronto',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDay(),
            'notify_on_due' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/send-reminders');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Recordatorios enviados correctamente']);
    }

    public function test_trigger_reminders_fails_when_emails_disabled(): void
    {
        SystemSetting::setValue('emails_enabled', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/send-reminders');

        $response->assertUnprocessable();
    }

    public function test_worker_cannot_trigger_due_reminders(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/automation/send-reminders');

        $response->assertForbidden();
    }

    // ── Commands respect DB settings ──

    public function test_detect_overdue_respects_enabled_setting(): void
    {
        SystemSetting::setValue('detect_overdue_enabled', false);

        Task::create([
            'title' => 'Tarea vencida',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(3),
        ]);

        $this->artisan('tasks:detect-overdue')
            ->expectsOutput('Detección de tareas vencidas desactivada.')
            ->assertSuccessful();

        // Task should NOT be marked as overdue
        $this->assertDatabaseMissing('task_status_history', [
            'to_status' => TaskStatusEnum::OVERDUE->value,
        ]);
    }

    public function test_daily_summary_respects_enabled_setting(): void
    {
        SystemSetting::setValue('daily_summary_enabled', false);

        $this->artisan('tasks:send-daily-summary')
            ->expectsOutput('Resumen diario desactivado.')
            ->assertSuccessful();
    }

    public function test_due_reminders_runs_even_when_emails_disabled(): void
    {
        // When emails_enabled=false the command should still run and save notifications to DB.
        // resolveChannels() omits the mail channel — it does NOT abort the command.
        SystemSetting::setValue('emails_enabled', false);

        $this->artisan('tasks:send-due-reminders')
            ->assertSuccessful();
    }

    // ── Area Manager: scoped access ──

    public function test_area_manager_can_trigger_overdue_detection_for_their_area(): void
    {
        Task::create([
            'title' => 'Tarea vencida en área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(2),
            'area_id' => $this->area->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/automation/detect-overdue');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Detección de tareas vencidas ejecutada correctamente']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea vencida en área',
            'status' => TaskStatusEnum::OVERDUE->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->manager->id,
            'module' => 'automation',
            'action' => 'trigger_overdue_detection',
        ]);
    }

    public function test_area_manager_overdue_detection_does_not_affect_other_areas(): void
    {
        $otherArea = Area::create([
            'name' => 'Otra Área',
            'process_identifier' => 'OTHER',
            'manager_user_id' => $this->admin->id,
        ]);

        $taskInOwnArea = Task::create([
            'title' => 'Tarea en mi área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(2),
            'area_id' => $this->area->id,
        ]);

        $taskInOtherArea = Task::create([
            'title' => 'Tarea en otra área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(2),
            'area_id' => $otherArea->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson('/api/automation/detect-overdue')
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskInOwnArea->id,
            'status' => TaskStatusEnum::OVERDUE->value,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskInOtherArea->id,
            'status' => TaskStatusEnum::IN_PROGRESS->value,
        ]);
    }

    public function test_area_manager_can_trigger_daily_summary_for_their_area(): void
    {
        Task::create([
            'title' => 'Tarea en mi área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'area_id' => $this->area->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/automation/send-summary');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Resumen diario enviado correctamente']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_area_manager_can_trigger_due_reminders_for_their_area(): void
    {
        Task::create([
            'title' => 'Vence pronto en mi área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDay(),
            'notify_on_due' => true,
            'area_id' => $this->area->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/automation/send-reminders');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Recordatorios enviados correctamente']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_area_manager_due_reminders_do_not_affect_other_areas(): void
    {
        $otherArea = Area::create([
            'name' => 'Otra Área',
            'process_identifier' => 'OTHER2',
            'manager_user_id' => $this->admin->id,
        ]);

        Task::create([
            'title' => 'Vence pronto en otra área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDay(),
            'notify_on_due' => true,
            'area_id' => $otherArea->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson('/api/automation/send-reminders')
            ->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_area_manager_can_trigger_inactivity_detection_for_their_area(): void
    {
        $task = Task::create([
            'title' => 'Tarea inactiva en mi área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'area_id' => $this->area->id,
        ]);

        // Backdate task so it falls outside the inactivity window (default 7 days)
        \Illuminate\Support\Facades\DB::table('tasks')
            ->where('id', $task->id)
            ->update(['created_at' => now()->subDays(10)]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/automation/detect-inactivity');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Detección de inactividad ejecutada correctamente']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }
}
