<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createRoles(): void
    {
        // Fake license API — subscription active for all auth tests by default
        $licenseUrl = rtrim(config('services.subscription.url', 'https://managed.cyberteconline.com'), '/');
        Http::fake([
            $licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => true, 'reason' => null, 'limit' => null,
                'current' => null, 'remaining' => null, 'status' => 'active',
            ], 200),
        ]);

        Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);
        Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);
    }

    private function createUser(RoleEnum $role, array $overrides = []): User
    {
        $roleModel = Role::where('slug', $role->value)->first();

        return User::factory()->create(array_merge([
            'role_id' => $roleModel->id,
            'password' => Hash::make('Password1'),
        ], $overrides));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $this->createRoles();
        $user = $this->createUser(RoleEnum::SUPERADMIN, ['email' => 'admin@test.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $this->createRoles();
        $this->createUser(RoleEnum::SUPERADMIN, ['email' => 'admin@test.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertUnprocessable();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createRoles();
        $this->createUser(RoleEnum::SUPERADMIN, [
            'email' => 'admin@test.com',
            'active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'Password1',
        ]);

        $response->assertUnprocessable();
    }

    public function test_user_can_logout(): void
    {
        $this->createRoles();
        $user = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/logout');

        $response->assertOk();
    }

    public function test_user_can_get_own_profile(): void
    {
        $this->createRoles();
        $user = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
