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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests that verify actual DB notification records to catch queue-level duplication.
 */
class NotificationRealDispatchTest extends TestCase
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

    public function test_admin_assigns_to_worker_exactly_one_db_notification(): void
    {
        // Fake only mail to avoid SMTP errors; DB notifications still go through
        Mail::fake();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Test duplicados',
                'assigned_to_user_id' => $this->worker->id,
            ])
            ->assertCreated();

        // Check actual database notification records
        $workerNotifs = $this->worker->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $managerNotifs = $this->manager->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $this->assertEquals(1, $workerNotifs, "Worker should have exactly 1 DB notification, got {$workerNotifs}");
        $this->assertEquals(1, $managerNotifs, "Manager should have exactly 1 DB notification (copy), got {$managerNotifs}");
    }

    public function test_manager_assigns_to_worker_exactly_one_db_notification(): void
    {
        Mail::fake();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Test manager asigna',
                'assigned_to_user_id' => $this->worker->id,
            ])
            ->assertCreated();

        $workerNotifs = $this->worker->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $managerNotifs = $this->manager->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $this->assertEquals(1, $workerNotifs, "Worker should have exactly 1 DB notification, got {$workerNotifs}");
        $this->assertEquals(0, $managerNotifs, "Manager (assigner + area manager) should NOT get a copy, got {$managerNotifs}");
    }

    public function test_admin_assigns_to_area_exactly_one_db_notification(): void
    {
        Mail::fake();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Test asignación a área',
                'assigned_to_area_id' => $this->area->id,
            ])
            ->assertCreated();

        $managerNotifs = $this->manager->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $workerNotifs = $this->worker->notifications()
            ->where('type', TaskAssignedNotification::class)
            ->count();

        $this->assertEquals(1, $managerNotifs, "Manager should have exactly 1 DB notification, got {$managerNotifs}");
        $this->assertEquals(0, $workerNotifs, "Worker should not be notified for area assignment, got {$workerNotifs}");
    }
}
