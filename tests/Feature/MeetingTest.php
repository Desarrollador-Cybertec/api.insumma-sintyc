<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];
    private User $admin;
    private User $manager;
    private User $worker;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles['superadmin'] = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $this->roles['area_manager'] = Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);
        $this->roles['worker'] = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);

        $this->admin = User::factory()->create([
            'role_id' => $this->roles['superadmin']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->manager = User::factory()->create([
            'role_id' => $this->roles['area_manager']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id' => $this->roles['worker']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->area = Area::create([
            'name' => 'Área Test',
            'manager_user_id' => $this->manager->id,
        ]);
    }

    public function test_superadmin_can_create_meeting(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/meetings', [
                'title' => 'Reunión de planificación',
                'meeting_date' => '2026-03-20',
                'area_id' => $this->area->id,
                'classification' => 'operational',
                'notes' => 'Discutir prioridades del trimestre',
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Reunión de planificación');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Reunión de planificación',
            'classification' => 'operational',
        ]);
    }

    public function test_manager_can_create_meeting(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/meetings', [
                'title' => 'Reunión de área',
                'meeting_date' => '2026-03-21',
                'area_id' => $this->area->id,
            ]);

        $response->assertCreated();
    }

    public function test_worker_cannot_create_meeting(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/meetings', [
                'title' => 'Reunión ilegal',
                'meeting_date' => '2026-03-21',
            ]);

        $response->assertForbidden();
    }

    public function test_meeting_requires_title_and_date(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/meetings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'meeting_date']);
    }

    public function test_superadmin_can_list_meetings(): void
    {
        Meeting::create([
            'title' => 'Reunión 1',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        Meeting::create([
            'title' => 'Reunión 2',
            'meeting_date' => '2026-03-21',
            'area_id' => $this->area->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/meetings');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_sees_only_own_area_meetings(): void
    {
        Meeting::create([
            'title' => 'Su reunión',
            'meeting_date' => '2026-03-20',
            'area_id' => $this->area->id,
            'created_by' => $this->manager->id,
        ]);

        $otherArea = Area::create([
            'name' => 'Otra Área',
            'manager_user_id' => $this->admin->id,
        ]);

        Meeting::create([
            'title' => 'Otra reunión',
            'meeting_date' => '2026-03-21',
            'area_id' => $otherArea->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/meetings');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_meeting_show_includes_tasks(): void
    {
        $meeting = Meeting::create([
            'title' => 'Reunión con tareas',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/meetings/{$meeting->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $meeting->id)
            ->assertJsonStructure(['data' => [
                'id', 'title', 'meeting_date', 'classification', 'notes', 'creator', 'tasks',
            ]]);
    }

    public function test_superadmin_can_update_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Original',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/meetings/{$meeting->id}", [
                'title' => 'Actualizada',
            ]);

        $response->assertOk();
        $this->assertEquals('Actualizada', $meeting->fresh()->title);
    }

    public function test_superadmin_can_delete_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Para borrar',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/meetings/{$meeting->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    }

    public function test_task_can_be_created_with_meeting_id(): void
    {
        $meeting = Meeting::create([
            'title' => 'Reunión origen',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Compromiso de reunión',
                'assigned_to_user_id' => $this->worker->id,
                'meeting_id' => $meeting->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Compromiso de reunión',
            'meeting_id' => $meeting->id,
        ]);
    }

    // ── Batch Task Creation ──

    public function test_superadmin_can_batch_create_tasks_for_meeting(): void
    {
        AreaMember::create([
            'area_id' => $this->area->id,
            'user_id' => $this->worker->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $meeting = Meeting::create([
            'title' => 'Reunión batch',
            'meeting_date' => '2026-03-20',
            'area_id' => $this->area->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    [
                        'title' => 'Tarea 1 de reunión',
                        'assigned_to_user_id' => $this->worker->id,
                        'priority' => 'high',
                    ],
                    [
                        'title' => 'Tarea 2 de reunión',
                        'assigned_to_area_id' => $this->area->id,
                    ],
                    [
                        'title' => 'Tarea 3 externa',
                        'external_email' => 'externo@test.com',
                        'external_name' => 'Externo',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonCount(3, 'tasks');

        $this->assertDatabaseCount('tasks', 3);

        // All linked to the meeting
        $this->assertDatabaseHas('tasks', ['title' => 'Tarea 1 de reunión', 'meeting_id' => $meeting->id, 'status' => 'pending']);
        $this->assertDatabaseHas('tasks', ['title' => 'Tarea 2 de reunión', 'meeting_id' => $meeting->id, 'status' => 'pending_assignment']);
        $this->assertDatabaseHas('tasks', ['title' => 'Tarea 3 externa', 'meeting_id' => $meeting->id]);
    }

    public function test_manager_can_batch_create_tasks_for_own_area_meeting(): void
    {
        AreaMember::create([
            'area_id' => $this->area->id,
            'user_id' => $this->worker->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $meeting = Meeting::create([
            'title' => 'Reunión del manager',
            'meeting_date' => '2026-03-20',
            'area_id' => $this->area->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    [
                        'title' => 'Tarea para worker',
                        'assigned_to_user_id' => $this->worker->id,
                    ],
                    [
                        'title' => 'Tarea para otra área',
                        'assigned_to_area_id' => $this->area->id,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'tasks');
    }

    public function test_manager_cannot_batch_assign_to_worker_outside_area(): void
    {
        $otherWorker = User::factory()->create([
            'role_id' => $this->roles['worker']->id,
        ]);

        $meeting = Meeting::create([
            'title' => 'Reunión',
            'meeting_date' => '2026-03-20',
            'area_id' => $this->area->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    [
                        'title' => 'Tarea inválida',
                        'assigned_to_user_id' => $otherWorker->id,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_worker_cannot_batch_create_meeting_tasks(): void
    {
        $meeting = Meeting::create([
            'title' => 'Reunión',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    ['title' => 'Intento', 'assigned_to_user_id' => $this->worker->id],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_batch_tasks_require_at_least_one_task(): void
    {
        $meeting = Meeting::create([
            'title' => 'Reunión',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tasks']);
    }

    public function test_batch_tasks_require_assignment_target(): void
    {
        $meeting = Meeting::create([
            'title' => 'Reunión',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    ['title' => 'Sin destino'],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_batch_tasks_generates_status_history(): void
    {
        AreaMember::create([
            'area_id' => $this->area->id,
            'user_id' => $this->worker->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $meeting = Meeting::create([
            'title' => 'Reunión historial',
            'meeting_date' => '2026-03-20',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/tasks", [
                'tasks' => [
                    ['title' => 'Tarea con historial', 'assigned_to_user_id' => $this->worker->id],
                ],
            ]);

        $this->assertDatabaseHas('task_status_history', [
            'note' => 'Tarea creada',
        ]);
    }
}
