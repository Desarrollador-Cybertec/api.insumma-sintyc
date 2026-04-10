<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsolidatedDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $worker;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = [
            'superadmin' => Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]),
            'area_manager' => Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]),
            'worker' => Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]),
        ];

        $this->admin = User::factory()->create([
            'role_id' => $roles['superadmin']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->manager = User::factory()->create([
            'role_id' => $roles['area_manager']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id' => $roles['worker']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->area = Area::create([
            'name' => 'Producción',
            'process_identifier' => 'PROD',
            'manager_user_id' => $this->manager->id,
        ]);
    }

    public function test_superadmin_can_access_consolidated_dashboard(): void
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
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'total_tasks',
                    'total_completed',
                    'total_active',
                    'total_overdue',
                    'global_completion_rate',
                ],
                'by_area',
            ]);

        $this->assertEquals(2, $response->json('summary.total_tasks'));
        $this->assertEquals(1, $response->json('summary.total_completed'));
    }

    public function test_consolidated_includes_area_details(): void
    {
        $task = Task::create([
            'title' => 'Pendiente',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);
        Task::where('id', $task->id)->update(['created_at' => now()->subDays(10)]);

        Task::create([
            'title' => 'Completada',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk();

        $areas = $response->json('by_area');
        $this->assertNotEmpty($areas);

        $prodArea = collect($areas)->firstWhere('area_name', 'Producción');
        $this->assertNotNull($prodArea);
        $this->assertEquals('PROD', $prodArea['process_identifier']);
        $this->assertEquals(2, $prodArea['total']);
        $this->assertEquals(50.0, $prodArea['completion_rate']);
        $this->assertNotNull($prodArea['oldest_pending_days']);
    }

    public function test_consolidated_shows_without_progress(): void
    {
        Task::create([
            'title' => 'Sin avance',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk();

        $areas = $response->json('by_area');
        $prodArea = collect($areas)->firstWhere('area_name', 'Producción');
        $this->assertEquals(1, $prodArea['without_progress']);
    }

    public function test_consolidated_shows_overdue_per_area(): void
    {
        Task::create([
            'title' => 'Vencida',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'due_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $areas = $response->json('by_area');
        $prodArea = collect($areas)->firstWhere('area_name', 'Producción');
        $this->assertEquals(1, $prodArea['overdue']);
    }

    public function test_worker_cannot_access_consolidated_dashboard(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertForbidden();
    }

    public function test_consolidated_handles_empty_areas(): void
    {
        // Area with no tasks
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk();

        $areas = $response->json('by_area');
        $prodArea = collect($areas)->firstWhere('area_name', 'Producción');
        $this->assertEquals(0, $prodArea['total']);
        $this->assertEquals(0, $prodArea['completion_rate']);
    }

    public function test_consolidated_multiple_areas(): void
    {
        $area2 = Area::create([
            'name' => 'Calidad',
            'process_identifier' => 'QA',
        ]);

        Task::create([
            'title' => 'Prod task',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        Task::create([
            'title' => 'QA task',
            'created_by' => $this->admin->id,
            'area_id' => $area2->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk();

        $areas = $response->json('by_area');
        $this->assertCount(2, $areas);
        $this->assertEquals(2, $response->json('summary.total_tasks'));
        $this->assertEquals(50.0, $response->json('summary.global_completion_rate'));
    }

    public function test_personal_tasks_excluded_from_consolidated_summary(): void
    {
        // Area task — must be counted
        Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        // Personal task (area_id = null) — must NOT appear in summary
        Task::create([
            'title' => 'Tarea personal',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->admin->id,
            'current_responsible_user_id' => $this->admin->id,
            'area_id' => null,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/consolidated');

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.total_tasks'), 'Personal tasks must not appear in consolidated summary');
        $this->assertEquals(1, $response->json('summary.total_active'));
    }
}
