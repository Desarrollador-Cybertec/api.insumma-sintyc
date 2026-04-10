<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserTest extends TestCase
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
                'allowed' => true, 'reason' => null, 'limit' => 10,
                'current' => 0, 'remaining' => 10, 'status' => 'active',
            ], 200),
            $licenseUrl . '/api/internal/usage' => Http::response(['ok' => true], 200),
        ]);

        $this->roles['superadmin'] = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $this->roles['manager'] = Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);
        $this->roles['worker'] = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);
    }

    private function createUser(RoleEnum $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => $this->roles[$role->value]->id,
            'password' => Hash::make('Password1'),
        ], $overrides));
    }

    public function test_superadmin_can_list_users(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_worker_cannot_list_users(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($worker, 'sanctum')
            ->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_superadmin_can_create_user(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'New Worker',
                'email' => 'newworker@test.com',
                'password' => 'Password1',
                'role_id' => $this->roles['worker']->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'New Worker');

        $this->assertDatabaseHas('users', ['email' => 'newworker@test.com']);
    }

    public function test_worker_cannot_create_user(): void
    {
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Another',
                'email' => 'another@test.com',
                'password' => 'Password1',
                'role_id' => $this->roles['worker']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_superadmin_can_update_user_role(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/role", [
                'role_id' => $this->roles['manager']->id,
            ]);

        $response->assertOk();
        $this->assertEquals($this->roles['manager']->id, $worker->fresh()->role_id);
    }

    public function test_superadmin_cannot_change_own_role(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$admin->id}/role", [
                'role_id' => $this->roles['worker']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_superadmin_can_toggle_user_active_status(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/toggle-active");

        $response->assertOk();
        $this->assertFalse($worker->fresh()->active);
    }

    public function test_create_user_validates_password_strength(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Weak Pass',
                'email' => 'weak@test.com',
                'password' => '123',
                'role_id' => $this->roles['worker']->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_create_user_validates_unique_email(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN, ['email' => 'taken@test.com']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Duplicate',
                'email' => 'taken@test.com',
                'password' => 'Password1',
                'role_id' => $this->roles['worker']->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_exclude_area_filter_hides_already_claimed_workers(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $area = Area::create(['name' => 'Área Test', 'manager_user_id' => $admin->id]);

        $inArea = $this->createUser(RoleEnum::WORKER);
        $notInArea = $this->createUser(RoleEnum::WORKER);

        AreaMember::create([
            'area_id' => $area->id,
            'user_id' => $inArea->id,
            'assigned_by' => $admin->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/users?role=worker&exclude_area={$area->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($notInArea->id, $ids->toArray());
        $this->assertNotContains($inArea->id, $ids->toArray());
    }

    public function test_exclude_area_filter_keeps_workers_from_other_areas(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $area1 = Area::create(['name' => 'Área 1']);
        $area2 = Area::create(['name' => 'Área 2']);

        $workerInArea1 = $this->createUser(RoleEnum::WORKER);

        AreaMember::create([
            'area_id' => $area1->id,
            'user_id' => $workerInArea1->id,
            'assigned_by' => $admin->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Worker is in area1, but when listing available for area2 they should appear
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/users?role=worker&exclude_area={$area2->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($workerInArea1->id, $ids->toArray());
    }

    // ── Update Password ──

    public function test_superadmin_can_update_user_password(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/password", [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada correctamente.');

        $this->assertTrue(Hash::check('NewPassword1', $worker->fresh()->password));
    }

    public function test_worker_cannot_update_another_users_password(): void
    {
        $worker1 = $this->createUser(RoleEnum::WORKER);
        $worker2 = $this->createUser(RoleEnum::WORKER);

        $this->actingAs($worker1, 'sanctum')
            ->patchJson("/api/users/{$worker2->id}/password", [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertForbidden();
    }

    public function test_superadmin_cannot_update_own_password_via_admin_endpoint(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$admin->id}/password", [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertForbidden();
    }

    public function test_update_password_validates_strength(): void
    {
        $admin = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/password", [
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertUnprocessable();
    }
}
