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

class TaskUpdateTest extends TestCase
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

    public function test_worker_can_add_progress_update(): void
    {
        $task = Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'comment' => 'Avance del 50%',
                'progress_percent' => 50,
                'update_type' => 'progress',
            ]);

        $response->assertCreated()
            ->assertJsonPath('comment', 'Avance del 50%')
            ->assertJsonPath('progress_percent', 50);

        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id,
            'user_id' => $this->worker->id,
            'progress_percent' => 50,
        ]);

        $this->assertEquals(50, $task->fresh()->progress_percent);
    }

    public function test_update_requires_comment(): void
    {
        $task = Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'progress_percent' => 30,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_other_worker_cannot_add_update(): void
    {
        $otherWorker = User::factory()->create([
            'role_id' => $this->roles['worker']->id,
        ]);

        $task = Task::create([
            'title' => 'No mía',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($otherWorker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'comment' => 'Intento',
            ]);

        $response->assertForbidden();
    }

    public function test_manager_can_add_update_to_area_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea del área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'comment' => 'Revisé el avance',
                'update_type' => 'note',
            ]);

        $response->assertCreated();
    }

    public function test_progress_percent_must_be_valid(): void
    {
        $task = Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'comment' => 'Progreso inválido',
                'progress_percent' => 150,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['progress_percent']);
    }

    public function test_task_show_includes_updates(): void
    {
        $task = Task::create([
            'title' => 'Con avances',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'comment' => 'Primer avance',
                'progress_percent' => 25,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.progress_percent', 25)
            ->assertJsonCount(1, 'data.updates');
    }
}
