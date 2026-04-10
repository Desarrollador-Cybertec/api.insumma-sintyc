<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Area;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];
    private string $licenseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseUrl = rtrim(config('services.subscription.url', 'https://managed.cyberteconline.com'), '/');

        $this->roles['superadmin'] = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $this->roles['manager']    = Role::create(['name' => 'Encargado de Área',   'slug' => RoleEnum::AREA_MANAGER->value]);
        $this->roles['worker']     = Role::create(['name' => 'Trabajador',          'slug' => RoleEnum::WORKER->value]);
    }

    private function createUser(RoleEnum $role, array $overrides = []): User
    {
        $key = match ($role) {
            RoleEnum::SUPERADMIN   => 'superadmin',
            RoleEnum::AREA_MANAGER => 'manager',
            RoleEnum::WORKER       => 'worker',
            default                => 'worker',
        };

        return User::factory()->create(array_merge([
            'role_id'  => $this->roles[$key]->id,
            'password' => Hash::make('Password1'),
        ], $overrides));
    }

    private function fakeAuthorizeAllowed(?int $limit = 10, int $current = 3): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed'   => true,
                'reason'    => null,
                'limit'     => $limit,
                'current'   => $current,
                'remaining' => $limit !== null ? $limit - $current : null,
                'status'    => 'active',
            ], 200),
            $this->licenseUrl . '/api/internal/usage' => Http::response(['ok' => true], 200),
        ]);
    }

    private function fakeAuthorizeDenied(string $reason = 'Límite de usuarios activos alcanzado.'): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => $reason,
                'status'  => 'active',
            ], 200),
        ]);
    }

    private function fakeAuthorizeExpired(): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => null,
                'status'  => 'expired',
            ], 200),
        ]);
    }

    private function fakeAuthorizeSuspended(): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => null,
                'status'  => 'suspended',
            ], 200),
        ]);
    }

    private function fakeAuthorizeUnavailable(): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([], 503),
        ]);
    }

    // ─── POST /api/users ────────────────────────────────────────────────────

    public function test_create_user_succeeds_when_license_allows(): void
    {
        $this->fakeAuthorizeAllowed();
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name'     => 'New Worker',
                'email'    => 'newworker@test.com',
                'password' => 'Password1',
                'role_id'  => $this->roles['worker']->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'newworker@test.com']);
    }

    public function test_create_user_blocked_when_license_denied(): void
    {
        $this->fakeAuthorizeDenied();
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name'     => 'Blocked Worker',
                'email'    => 'blocked@test.com',
                'password' => 'Password1',
                'role_id'  => $this->roles['worker']->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_denied');

        $this->assertDatabaseMissing('users', ['email' => 'blocked@test.com']);
    }

    public function test_create_user_blocked_when_license_expired(): void
    {
        $this->fakeAuthorizeExpired();
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name'     => 'Expired Worker',
                'email'    => 'expired@test.com',
                'password' => 'Password1',
                'role_id'  => $this->roles['worker']->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_expired');
    }

    public function test_create_user_blocked_when_license_system_unavailable(): void
    {
        $this->fakeAuthorizeUnavailable();
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name'     => 'Unavailable Worker',
                'email'    => 'unavailable@test.com',
                'password' => 'Password1',
                'role_id'  => $this->roles['worker']->id,
            ]);

        $response->assertStatus(503)
            ->assertJsonPath('type', 'license_unavailable');
    }

    public function test_create_user_reports_usage_after_success(): void
    {
        $this->fakeAuthorizeAllowed();
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name'     => 'Worker',
                'email'    => 'worker@test.com',
                'password' => 'Password1',
                'role_id'  => $this->roles['worker']->id,
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/usage')
                && $request->data()['metric'] === 'user_active';
        });
    }

    // ─── PATCH /api/users/{id}/toggle-active ────────────────────────────────

    public function test_reactivate_user_succeeds_when_license_allows(): void
    {
        $this->fakeAuthorizeAllowed();
        $admin  = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER, ['active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/toggle-active");

        $response->assertOk();
        $this->assertTrue($worker->fresh()->active);
    }

    public function test_reactivate_user_blocked_when_license_denied(): void
    {
        $this->fakeAuthorizeDenied();
        $admin  = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER, ['active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/toggle-active");

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_denied');

        $this->assertFalse($worker->fresh()->active);
    }

    public function test_deactivate_user_does_not_call_license(): void
    {
        // Disabling a user (active → inactive) should NOT call the license API
        Http::fake(); // No stubs — if authorize() is called, Http::assertNothingSent() will catch it

        $admin  = $this->createUser(RoleEnum::SUPERADMIN);
        $worker = $this->createUser(RoleEnum::WORKER, ['active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$worker->id}/toggle-active");

        $response->assertOk();
        $this->assertFalse($worker->fresh()->active);

        Http::assertNothingSent();
    }

    // ─── POST /api/areas ────────────────────────────────────────────────────

    public function test_create_area_succeeds_when_license_allows(): void
    {
        $this->fakeAuthorizeAllowed(null, 0);
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/areas', [
                'name'        => 'Área de Test',
                'description' => 'Descripción',
            ]);

        $response->assertCreated()->assertJsonPath('name', 'Área de Test');
    }

    public function test_create_area_blocked_when_license_denied(): void
    {
        $this->fakeAuthorizeDenied('Suscripción suspendida.');
        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/areas', [
                'name' => 'Area Bloqueada',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_denied');
    }

    public function test_create_area_does_not_report_usage(): void
    {
        Http::fake([
            $this->licenseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => true,
                'reason'  => null,
                'status'  => 'active',
            ], 200),
        ]);

        $admin = $this->createUser(RoleEnum::SUPERADMIN);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/areas', [
                'name'        => 'Area Sin Uso',
                'description' => 'No debe reportar uso',
            ]);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/api/internal/usage');
        });
    }

    // ─── POST /api/login ────────────────────────────────────────────────────

    public function test_login_blocked_when_subscription_expired(): void
    {
        $this->fakeAuthorizeExpired();
        $user = $this->createUser(RoleEnum::SUPERADMIN, ['password' => Hash::make('Password1')]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_expired');
    }

    public function test_login_blocked_when_license_system_unavailable(): void
    {
        $this->fakeAuthorizeUnavailable();
        $user = $this->createUser(RoleEnum::SUPERADMIN, ['password' => Hash::make('Password1')]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('type', 'license_unavailable');
    }

    public function test_login_blocked_when_subscription_suspended(): void
    {
        $this->fakeAuthorizeSuspended();
        $user = $this->createUser(RoleEnum::SUPERADMIN, ['password' => Hash::make('Password1')]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('type', 'license_suspended');
    }
}
