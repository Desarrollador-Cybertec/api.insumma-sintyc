<?php

namespace Tests\Feature;

use App\Enums\ProcessingStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Attachment;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $worker;
    private Area $area;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles['superadmin'] = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $this->roles['manager']    = Role::create(['name' => 'Encargado de Área', 'slug' => RoleEnum::AREA_MANAGER->value]);
        $this->roles['worker']     = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);

        $this->admin = User::factory()->create([
            'role_id'  => $this->roles['superadmin']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->manager = User::factory()->create([
            'role_id'  => $this->roles['manager']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id'  => $this->roles['worker']->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->area = Area::create([
            'name'            => 'Área de Prueba',
            'manager_user_id' => $this->manager->id,
        ]);

        AreaMember::create([
            'area_id'     => $this->area->id,
            'user_id'     => $this->worker->id,
            'assigned_by' => $this->admin->id,
            'joined_at'   => now(),
            'is_active'   => true,
        ]);
    }

    private function createAttachment(array $overrides = []): Attachment
    {
        return Attachment::create(array_merge([
            'uploaded_by'       => $this->worker->id,
            'owner_user_id'     => $this->worker->id,
            'disk'              => 'local',
            'original_name'     => 'test.pdf',
            'stored_name'       => 'test.pdf',
            'mime_type'         => 'application/pdf',
            'extension'         => 'pdf',
            'size_original'     => 1024,
            'processing_status' => ProcessingStatusEnum::READY,
        ], $overrides));
    }

    // ── Task Attachments ──

    public function test_responsible_worker_can_list_task_attachments(): void
    {
        $task = Task::create([
            'title'                      => 'Tarea con adjuntos',
            'created_by'                 => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id'                    => $this->area->id,
            'status'                     => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->createAttachment(['task_id' => $task->id]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/attachments-v2");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_superadmin_can_list_task_attachments(): void
    {
        $task = Task::create([
            'title'      => 'Tarea admin',
            'created_by' => $this->admin->id,
            'status'     => TaskStatusEnum::PENDING,
        ]);

        $this->createAttachment(['task_id' => $task->id]);
        $this->createAttachment(['task_id' => $task->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/attachments-v2");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_task_attachments_excludes_pending_files(): void
    {
        $task = Task::create([
            'title'                      => 'Tarea adjuntos pendientes',
            'created_by'                 => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id'                    => $this->area->id,
            'status'                     => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->createAttachment(['task_id' => $task->id, 'processing_status' => ProcessingStatusEnum::PENDING]);
        $this->createAttachment(['task_id' => $task->id, 'processing_status' => ProcessingStatusEnum::READY]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/attachments-v2");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Area Attachments ──

    public function test_manager_can_list_area_attachments(): void
    {
        $this->createAttachment(['area_id' => $this->area->id]);
        $this->createAttachment(['area_id' => $this->area->id]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/areas/{$this->area->id}/attachments");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_can_list_area_attachments(): void
    {
        $this->createAttachment(['area_id' => $this->area->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/areas/{$this->area->id}/attachments");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_worker_in_area_can_list_area_attachments(): void
    {
        $this->createAttachment(['area_id' => $this->area->id]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson("/api/areas/{$this->area->id}/attachments");

        $response->assertOk();
    }

    public function test_worker_outside_area_cannot_list_area_attachments(): void
    {
        $outsider = User::factory()->create(['role_id' => $this->roles['worker']->id]);
        $otherArea = Area::create(['name' => 'Otra Área']);

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/areas/{$otherArea->id}/attachments")
            ->assertForbidden();
    }

    // ── Destroy ──

    public function test_uploader_can_delete_their_attachment(): void
    {
        $attachment = $this->createAttachment([
            'uploaded_by'   => $this->worker->id,
            'owner_user_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Archivo eliminado correctamente.');

        $this->assertModelMissing($attachment);
    }

    public function test_superadmin_can_delete_any_attachment(): void
    {
        $attachment = $this->createAttachment([
            'uploaded_by'   => $this->worker->id,
            'owner_user_id' => $this->worker->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertOk();
        $this->assertModelMissing($attachment);
    }

    public function test_other_worker_cannot_delete_attachment(): void
    {
        $otherWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        $attachment = $this->createAttachment([
            'uploaded_by'   => $this->worker->id,
            'owner_user_id' => $this->worker->id,
        ]);

        $this->actingAs($otherWorker, 'sanctum')
            ->deleteJson("/api/attachments/{$attachment->id}")
            ->assertForbidden();
    }
}
