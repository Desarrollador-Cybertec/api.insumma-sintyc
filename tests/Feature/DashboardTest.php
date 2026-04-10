<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
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

        AreaMember::create([
            'area_id' => $this->area->id,
            'user_id' => $this->worker->id,
            'assigned_by' => $this->admin->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_access_general_dashboard(): void
    {
        Task::create([
            'title' => 'T1',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        Task::create([
            'title' => 'T2',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/general');

        $response->assertOk()
            ->assertJsonStructure([
                'tasks_by_status',
                'overdue_tasks',
                'due_soon',
                'total_active',
                'total_completed',
                'total_all',
                'completion_rate',
                'global_progress',
                'pending_by_user',
            ]);

        $this->assertEquals(2, $response->json('total_all'));
        $this->assertEquals(1, $response->json('total_completed'));
    }

    public function test_worker_cannot_access_general_dashboard(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/dashboard/general');

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_area_dashboard(): void
    {
        Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/dashboard/area/{$this->area->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'area',
                'total_tasks',
                'active_tasks',
                'completed_tasks',
                'overdue_tasks',
                'due_soon',
                'without_progress',
                'pending_assignment_tasks',
                'completion_rate',
                'tasks_by_status',
                'by_responsible',
                'awaiting_claim',
            ]);

        $this->assertEquals(1, $response->json('total_tasks'));
        $this->assertEquals(1, $response->json('active_tasks'));
        $this->assertEquals(0, $response->json('pending_assignment_tasks'));
    }

    public function test_area_dashboard_shows_pending_assignment_in_awaiting_claim(): void
    {
        // Task assigned to manager (pending_assignment, no responsible yet)
        Task::create([
            'title' => 'Tarea sin reclamar',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'assigned_to_user_id' => $this->manager->id,
            'current_responsible_user_id' => null,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/dashboard/area/{$this->area->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('pending_assignment_tasks'));
        $this->assertEquals(1, $response->json('total_tasks'));
        $this->assertCount(1, $response->json('awaiting_claim'));

        // Must appear in by_responsible as 'Sin responsable asignado'
        $byResponsible = collect($response->json('by_responsible'));
        $unassigned = $byResponsible->firstWhere('user_id', null);
        $this->assertNotNull($unassigned);
        $this->assertEquals('Sin responsable asignado', $unassigned['user_name']);
    }

    public function test_manager_can_access_own_area_dashboard(): void
    {
        Task::create([
            'title' => 'Tarea del manager',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/dashboard/area/{$this->area->id}");

        $response->assertOk();
    }

    public function test_worker_cannot_access_area_dashboard(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson("/api/dashboard/area/{$this->area->id}");

        $response->assertForbidden();
    }

    public function test_user_can_access_own_dashboard(): void
    {
        Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDay(),
        ]);

        Task::create([
            'title' => 'Vencida',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/dashboard/me');

        $response->assertOk()
            ->assertJsonStructure([
                'active_tasks',
                'overdue_tasks',
                'due_soon',
                'completed_tasks',
                'tasks_by_status',
                'upcoming_tasks',
            ]);

        $this->assertEquals(2, $response->json('active_tasks'));
        $this->assertEquals(1, $response->json('overdue_tasks'));
    }

    public function test_general_dashboard_shows_overdue_count(): void
    {
        Task::create([
            'title' => 'Vencida',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(5),
        ]);

        Task::create([
            'title' => 'A tiempo',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/general');

        $response->assertOk();
        $this->assertEquals(1, $response->json('overdue_tasks'));
    }

    public function test_personal_tasks_excluded_from_general_dashboard(): void
    {
        // Area task — should be counted
        Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        // Personal task (self-assigned, area_id = null) — should NOT be counted
        Task::create([
            'title' => 'Tarea personal Admin',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->admin->id,
            'current_responsible_user_id' => $this->admin->id,
            'area_id' => null,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/general');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total_all'), 'Personal tasks must not appear in total_all');
    }

    public function test_personal_tasks_excluded_from_my_dashboard(): void
    {
        // Area task assigned to worker — should be counted
        Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        // Personal task (self-assigned by worker, area_id = null) — should NOT be counted
        Task::create([
            'title' => 'Tarea personal Worker',
            'created_by' => $this->worker->id,
            'assigned_to_user_id' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => null,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/dashboard/me');

        $response->assertOk();
        $this->assertEquals(1, $response->json('active_tasks'), 'Personal tasks must not appear in active_tasks');
    }

    public function test_personal_task_visible_in_task_list(): void
    {
        // Personal task created via API (self-assigned)
        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Mi tarea personal',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Mi tarea personal',
            'area_id' => null,
        ]);

        // Must still appear in the worker's task list
        $listResponse = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/tasks');

        $listResponse->assertOk();
        $titles = collect($listResponse->json('data'))->pluck('title');
        $this->assertContains('Mi tarea personal', $titles);
    }

    public function test_general_dashboard_includes_superadmin_own_tasks(): void
    {
        // Task assigned to the superadmin themselves (personal, area_id = null)
        Task::create([
            'title' => 'Tarea propia del admin',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->admin->id,
            'current_responsible_user_id' => $this->admin->id,
            'area_id' => null,
            'status' => TaskStatusEnum::PENDING,
        ]);

        // Task assigned to the admin in an area
        Task::create([
            'title' => 'Tarea de area del admin',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        // Task assigned to someone else — must NOT appear in my_tasks
        Task::create([
            'title' => 'Tarea de otro',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/general');

        $response->assertOk()->assertJsonStructure(['my_tasks']);

        $myTasks = collect($response->json('my_tasks'));
        $this->assertCount(2, $myTasks, 'my_tasks debe incluir tareas propias del superadmin (con y sin area)');

        $titles = $myTasks->pluck('title');
        $this->assertContains('Tarea propia del admin', $titles);
        $this->assertContains('Tarea de area del admin', $titles);
        $this->assertNotContains('Tarea de otro', $titles);
    }
}
