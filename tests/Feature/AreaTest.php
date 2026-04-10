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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Fake license API calls so tests are isolated from the real Management System
        $licenseUrl = rtrim(config('services.subscription.url', 'https://managed.cyberteconline.com'), '/');
        Http::fake([
            $licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => true, 'reason' => null, 'limit' => null,
                'current' => null, 'remaining' => null, 'status' => 'active',
            ], 200),
            $licenseUrl . '/api/internal/usage' => Http::response(['ok' => true], 200),
        ]);

        $this->roles['superadmin'] = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $this->roles['manager'] = Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);
        $this->roles['worker'] = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);
    }

    private function createUser(RoleEnum $role, array $overrides = []): User
    {
        $roleKey = match($role) {
            RoleEnum::SUPERADMIN => 'superadmin',
            RoleEnum::AREA_MANAGER => 'manager',
            RoleEnum::WORKER => 'worker',
        };

        return User::factory()->create(array_merge([
            'role_id' => $this->roles[$roleKey]->id,
            'password' => Hash::make('Password1'),
        ], $overrides));
    }

    public function test_superadmin_can_create_area(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/areas', [
                'name' => 'Área de Test',
                'description' => 'Descripción del área',
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Área de Test');
    }

    public function test_worker_cannot_create_area(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/areas', [
                'name' => 'Área Ilegal',
            ]);

        $response->assertForbidden();
    }

    public function test_superadmin_can_assign_manager(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área']);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/areas/{$area->id}/manager", [
                'manager_user_id' => $manager->id,
            ]);

        $response->assertOk();
        $this->assertEquals($manager->id, $area->fresh()->manager_user_id);
    }

    public function test_manager_can_claim_worker(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $worker = $this->createUser(RoleEnum::WORKER);
        $area = Area::create(['name' => 'Área', 'manager_user_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/areas/claim-worker', [
                'user_id' => $worker->id,
                'area_id' => $area->id,
            ]);

        $response->assertCreated();
        $this->assertTrue($worker->belongsToArea($area->id));
    }

    public function test_cannot_claim_worker_already_in_area(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $worker = $this->createUser(RoleEnum::WORKER);
        $area = Area::create(['name' => 'Área', 'manager_user_id' => $manager->id]);

        AreaMember::create([
            'area_id' => $area->id,
            'user_id' => $worker->id,
            'assigned_by' => $manager->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/areas/claim-worker', [
                'user_id' => $worker->id,
                'area_id' => $area->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_claim_non_worker_user(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $anotherManager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área', 'manager_user_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/areas/claim-worker', [
                'user_id' => $anotherManager->id,
                'area_id' => $area->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_superadmin_can_list_all_areas(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        Area::create(['name' => 'Área 1']);
        Area::create(['name' => 'Área 2']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/areas');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_area_manager_can_list_all_areas(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        Area::create(['name' => 'Área del Manager', 'manager_user_id' => $manager->id]);
        Area::create(['name' => 'Otra Área']);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/areas');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_can_update_area(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $area = Area::create(['name' => 'Old Name']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/areas/{$area->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk();
        $this->assertEquals('New Name', $area->fresh()->name);
    }

    public function test_area_manager_can_list_available_workers_excluding_area_members(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área Test', 'manager_user_id' => $manager->id]);

        $inArea = $this->createUser(RoleEnum::WORKER);
        $available = $this->createUser(RoleEnum::WORKER);

        AreaMember::create([
            'area_id' => $area->id,
            'user_id' => $inArea->id,
            'assigned_by' => $manager->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/areas/{$area->id}/available-workers");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($available->id, $ids->toArray());
        $this->assertNotContains($inArea->id, $ids->toArray());
    }

    public function test_available_workers_does_not_show_workers_from_other_areas(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area1 = Area::create(['name' => 'Área 1', 'manager_user_id' => $manager->id]);
        $area2 = Area::create(['name' => 'Área 2', 'manager_user_id' => $manager->id]);

        $workerInArea2 = $this->createUser(RoleEnum::WORKER);

        AreaMember::create([
            'area_id' => $area2->id,
            'user_id' => $workerInArea2->id,
            'assigned_by' => $manager->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Worker is in area2 (active), so should NOT appear as available for area1
        // because a worker can only belong to one active area at a time
        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/areas/{$area1->id}/available-workers");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($workerInArea2->id, $ids->toArray());
    }

    public function test_worker_cannot_list_available_workers(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);
        $area = Area::create(['name' => 'Área Test']);

        $this->actingAs($worker, 'sanctum')
            ->getJson("/api/areas/{$area->id}/available-workers")
            ->assertForbidden();
    }

    public function test_available_workers_supports_search_filter(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área Test', 'manager_user_id' => $manager->id]);

        $matching = $this->createUser(RoleEnum::WORKER, ['name' => 'Ana García']);
        $notMatching = $this->createUser(RoleEnum::WORKER, ['name' => 'Pedro López']);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/areas/{$area->id}/available-workers?search=Ana");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($matching->id, $ids->toArray());
        $this->assertNotContains($notMatching->id, $ids->toArray());
    }

    public function test_manager_can_list_area_members(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área Test', 'manager_user_id' => $manager->id]);

        $worker1 = $this->createUser(RoleEnum::WORKER);
        $worker2 = $this->createUser(RoleEnum::WORKER);
        $outsider = $this->createUser(RoleEnum::WORKER);

        AreaMember::create(['area_id' => $area->id, 'user_id' => $worker1->id, 'assigned_by' => $manager->id, 'joined_at' => now(), 'is_active' => true]);
        AreaMember::create(['area_id' => $area->id, 'user_id' => $worker2->id, 'assigned_by' => $manager->id, 'joined_at' => now(), 'is_active' => true]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/areas/{$area->id}/members");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($worker1->id, $ids->toArray());
        $this->assertContains($worker2->id, $ids->toArray());
        $this->assertNotContains($outsider->id, $ids->toArray());
    }

    public function test_members_excludes_inactive_memberships(): void
    {
        $manager = $this->createUser(RoleEnum::AREA_MANAGER);
        $area = Area::create(['name' => 'Área Test', 'manager_user_id' => $manager->id]);

        $active = $this->createUser(RoleEnum::WORKER);
        $inactive = $this->createUser(RoleEnum::WORKER);

        AreaMember::create(['area_id' => $area->id, 'user_id' => $active->id, 'assigned_by' => $manager->id, 'joined_at' => now(), 'is_active' => true]);
        AreaMember::create(['area_id' => $area->id, 'user_id' => $inactive->id, 'assigned_by' => $manager->id, 'joined_at' => now(), 'is_active' => false]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/areas/{$area->id}/members");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($active->id, $ids->toArray());
        $this->assertNotContains($inactive->id, $ids->toArray());
    }

    public function test_worker_cannot_list_area_members(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);
        $area = Area::create(['name' => 'Área Test']);

        $this->actingAs($worker, 'sanctum')
            ->getJson("/api/areas/{$area->id}/members")
            ->assertForbidden();
    }

    // ── Destroy ──

    public function test_superadmin_can_delete_area(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $area = Area::create(['name' => 'Área a Eliminar']);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/areas/{$area->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Área eliminada correctamente.');

        $this->assertModelMissing($area);
    }

    public function test_superadmin_cannot_delete_area_with_tasks(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $area = Area::create(['name' => 'Área con Tareas']);

        Task::create([
            'title' => 'Tarea del área',
            'created_by' => $admin->id,
            'area_id' => $area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/areas/{$area->id}");

        $response->assertUnprocessable();
        $this->assertModelExists($area);
    }

    public function test_worker_cannot_delete_area(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);
        $area = Area::create(['name' => 'Área Protegida']);

        $this->actingAs($worker, 'sanctum')
            ->deleteJson("/api/areas/{$area->id}")
            ->assertForbidden();
    }
}
