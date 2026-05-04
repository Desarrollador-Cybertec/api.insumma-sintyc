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
            'key' => 'alert_days_before_due',
            'value' => '3',
            'type' => 'integer',
            'group' => 'notifications',
        ]);

        SystemSetting::create([
            'key' => 'send_reminders_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'automation',
        ]);
    }

    public function test_legacy_automation_routes_are_not_available(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/automation/detect-overdue')
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->postJson('/api/automation/detect-inactivity')
            ->assertNotFound();
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
            'title' => 'Vence hoy',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
            'notify_on_due' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/automation/send-reminders');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Recordatorios enviados correctamente']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
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

        Task::create([
            'title' => 'Solo base de datos',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
            'notify_on_due' => true,
        ]);

        $this->artisan('tasks:send-due-reminders')
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->worker->id,
            'notifiable_type' => User::class,
        ]);
    }

    // ── Area Manager: scoped access ──

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
            'title' => 'Vence hoy en mi área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now(),
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
            'due_date' => now(),
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
}
