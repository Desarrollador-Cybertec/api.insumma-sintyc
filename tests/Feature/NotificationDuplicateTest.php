<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDuplicateTest extends TestCase
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

    public function test_admin_assigns_to_worker_sends_exactly_one_notification_to_worker(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para worker',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated();

        // Worker should receive EXACTLY 1 TaskAssignedNotification
        Notification::assertSentToTimes($this->worker, TaskAssignedNotification::class, 1);
    }

    public function test_admin_assigns_to_worker_manager_gets_at_most_one_copy(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para worker con copy a manager',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated();

        // Manager should receive at most 1 copy (if copy_to_manager is on)
        Notification::assertSentToTimes($this->manager, TaskAssignedNotification::class, 1);
    }

    public function test_manager_assigns_to_worker_no_self_notification(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea del manager para worker',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated();

        // Worker gets 1 notification
        Notification::assertSentToTimes($this->worker, TaskAssignedNotification::class, 1);

        // Manager should NOT get a copy (they are the assigner AND the area manager)
        Notification::assertNotSentTo($this->manager, TaskAssignedNotification::class);
    }

    public function test_admin_assigns_to_area_manager_gets_exactly_one(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para área',
                'assigned_to_area_id' => $this->area->id,
            ]);

        $response->assertCreated();

        // Manager should receive exactly 1 notification
        Notification::assertSentToTimes($this->manager, TaskAssignedNotification::class, 1);

        // Worker should NOT receive anything
        Notification::assertNotSentTo($this->worker, TaskAssignedNotification::class);
    }

    public function test_admin_assigns_to_manager_user_gets_exactly_one(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para manager directo',
                'assigned_to_user_id' => $this->manager->id,
            ]);

        $response->assertCreated();

        // Manager should receive exactly 1 notification
        Notification::assertSentToTimes($this->manager, TaskAssignedNotification::class, 1);
    }
}
