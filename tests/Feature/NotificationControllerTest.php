<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('Password1'),
        ]);
    }

    private function createNotification(array $overrides = []): DatabaseNotification
    {
        return DatabaseNotification::create(array_merge([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TaskAssignedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => ['type' => 'task_assigned', 'task_id' => 1, 'message' => 'Test'],
            'read_at' => null,
        ], $overrides));
    }

    // ── Index ──

    public function test_user_can_list_own_notifications(): void
    {
        $this->createNotification();
        $this->createNotification();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'type', 'data', 'read_at', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        $otherRole = Role::where('slug', RoleEnum::WORKER->value)->first();
        $otherUser = User::factory()->create(['role_id' => $otherRole->id]);

        // Create notification for another user
        DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TaskAssignedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => ['message' => 'Not mine'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_cannot_list_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    // ── Unread Count ──

    public function test_user_can_get_unread_count(): void
    {
        $this->createNotification();
        $this->createNotification();
        $this->createNotification(['read_at' => now()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_unread_count_is_zero_when_all_read(): void
    {
        $this->createNotification(['read_at' => now()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    // ── Mark as Read ──

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification = $this->createNotification();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Notificación marcada como leída.']);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_users_notification(): void
    {
        $otherRole = Role::where('slug', RoleEnum::WORKER->value)->first();
        $otherUser = User::factory()->create(['role_id' => $otherRole->id]);

        $notification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TaskAssignedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => ['message' => 'Other'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_nonexistent_notification_returns_404(): void
    {
        $fakeId = Str::uuid()->toString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/notifications/{$fakeId}/read");

        $response->assertNotFound();
    }

    // ── Mark All as Read ──

    public function test_user_can_mark_all_as_read(): void
    {
        $n1 = $this->createNotification();
        $n2 = $this->createNotification();
        $n3 = $this->createNotification(['read_at' => now()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/notifications/read-all');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Todas las notificaciones marcadas como leídas.']);

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
        $this->assertNotNull($n3->fresh()->read_at);
    }

    // ── Pagination ──

    public function test_notifications_are_paginated(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createNotification();
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonCount(20, 'data');
    }
}
