<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskTest extends TestCase
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

    // ── Creation ──

    public function test_superadmin_can_create_task_assigned_to_user(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea directa',
                'description' => 'Descripción',
                'assigned_to_user_id' => $this->worker->id,
                'priority' => 'high',
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Tarea directa')
            ->assertJsonPath('status', TaskStatusEnum::PENDING->value);
    }

    public function test_superadmin_can_create_task_assigned_to_area(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea de área',
                'assigned_to_area_id' => $this->area->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING_ASSIGNMENT->value);
    }

    public function test_worker_can_create_task_for_self(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Mi propia tarea',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Mi propia tarea')
            ->assertJsonPath('status', TaskStatusEnum::PENDING->value);
    }

    public function test_worker_cannot_assign_task_to_other_user(): void
    {
        $otherWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para otro',
                'assigned_to_user_id' => $otherWorker->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);
    }

    public function test_worker_cannot_assign_task_to_area(): void
    {
        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea de área',
                'assigned_to_area_id' => $this->area->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_area_id']);
    }

    public function test_worker_can_create_task_with_external_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea externa',
                'description' => 'Para proveedor',
                'external_email' => 'proveedor@example.com',
                'external_name' => 'Juan Proveedor',
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Tarea externa');

        $this->assertDatabaseHas('tasks', [
            'external_email' => 'proveedor@example.com',
            'external_name' => 'Juan Proveedor',
        ]);

        Mail::assertQueued(\App\Mail\ExternalTaskMail::class, function ($mail) {
            return $mail->hasTo('proveedor@example.com');
        });
    }

    public function test_superadmin_can_create_task_for_themselves(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea propia del admin',
                'assigned_to_user_id' => $this->admin->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING->value);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea propia del admin',
            'assigned_to_user_id' => $this->admin->id,
            'current_responsible_user_id' => $this->admin->id,
        ]);
    }

    public function test_manager_can_create_task_for_themselves(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea propia del manager',
                'assigned_to_user_id' => $this->manager->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING->value);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea propia del manager',
            'assigned_to_user_id' => $this->manager->id,
            'current_responsible_user_id' => $this->manager->id,
        ]);
    }

    public function test_manager_can_create_task_for_area_worker(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para trabajador',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING->value);
    }

    public function test_manager_can_create_task_for_other_area(): void
    {
        $otherArea = Area::create([
            'name' => 'Otra Área',
            'manager_user_id' => User::factory()->create(['role_id' => $this->roles['area_manager']->id])->id,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Requerimiento inter-área',
                'assigned_to_area_id' => $otherArea->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING_ASSIGNMENT->value);
    }

    public function test_manager_cannot_assign_to_worker_outside_their_area(): void
    {
        $outsideWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea fuera de área',
                'assigned_to_user_id' => $outsideWorker->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_user_id']);
    }

    public function test_manager_can_create_task_with_external_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para externo',
                'external_email' => 'externo@example.com',
            ]);

        $response->assertCreated();

        Mail::assertQueued(\App\Mail\ExternalTaskMail::class, function ($mail) {
            return $mail->hasTo('externo@example.com');
        });
    }

    public function test_cannot_combine_assignment_targets(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Doble asignación',
                'assigned_to_user_id' => $this->worker->id,
                'assigned_to_area_id' => $this->area->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_task_requires_title(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'assigned_to_user_id' => $this->worker->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_task_requires_assignee_or_area_or_external(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Sin asignar',
            ]);

        $response->assertUnprocessable();
    }

    // ── Delegation ──

    public function test_manager_can_delegate_area_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'assigned_to_area_id' => $this->area->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $this->worker->id,
                'note' => 'Por favor encárgate',
            ]);

        $response->assertOk();
        $this->assertEquals($this->worker->id, $task->fresh()->current_responsible_user_id);
        $this->assertEquals(TaskStatusEnum::PENDING, $task->fresh()->status);
    }

    public function test_cannot_delegate_to_user_outside_area(): void
    {
        $outsideWorker = User::factory()->create([
            'role_id' => $this->roles['worker']->id,
        ]);

        $task = Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'assigned_to_area_id' => $this->area->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $outsideWorker->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_delegate_completed_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea completada',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $this->worker->id,
            ]);

        $response->assertUnprocessable();
    }

    // ── Status Transitions ──

    public function test_worker_can_start_assigned_task(): void
    {
        $task = Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_worker_cannot_start_other_users_task(): void
    {
        $otherWorker = User::factory()->create([
            'role_id' => $this->roles['worker']->id,
        ]);

        $task = Task::create([
            'title' => 'No mía',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $otherWorker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        $response->assertForbidden();
    }

    public function test_worker_can_submit_for_review(): void
    {
        $task = Task::create([
            'title' => 'Para revisar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => true,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::IN_REVIEW, $task->fresh()->status);
    }

    public function test_task_completes_directly_without_approval_requirement(): void
    {
        $task = Task::create([
            'title' => 'Sin aprobación',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => false,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::COMPLETED, $task->fresh()->status);
    }

    public function test_manager_can_approve_task(): void
    {
        $task = Task::create([
            'title' => 'En revisión',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/approve", [
                'note' => 'Buen trabajo',
            ]);

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::COMPLETED, $task->fresh()->status);
    }

    public function test_manager_can_reject_task(): void
    {
        $task = Task::create([
            'title' => 'Rechazada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reject", [
                'note' => 'Necesita correcciones',
            ]);

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::REJECTED, $task->fresh()->status);
    }

    public function test_reject_requires_note(): void
    {
        $task = Task::create([
            'title' => 'Rechazada sin nota',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reject", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    }

    public function test_superadmin_can_cancel_task(): void
    {
        $task = Task::create([
            'title' => 'Cancelar',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/cancel", ['comment' => 'Se cancela la tarea.']);

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::CANCELLED, $task->fresh()->status);
    }

    public function test_superadmin_can_reopen_completed_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea completada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
            'closed_by' => $this->admin->id,
            'progress_percent' => 100,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Se requiere ajuste']);

        $response->assertOk();
        $fresh = $task->fresh();
        $this->assertEquals(TaskStatusEnum::IN_PROGRESS, $fresh->status);
        $this->assertEquals(25, $fresh->progress_percent);
        $this->assertNull($fresh->completed_at);
        $this->assertNull($fresh->closed_by);
    }

    public function test_manager_can_reopen_completed_task_in_their_area(): void
    {
        $task = Task::create([
            'title' => 'Tarea de área completada',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
            'closed_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Requiere revisión adicional.']);

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_superadmin_can_reopen_cancelled_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea cancelada',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::CANCELLED,
            'cancelled_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Se reabre la tarea cancelada.']);

        $response->assertOk();
        $fresh = $task->fresh();
        $this->assertEquals(TaskStatusEnum::PENDING, $fresh->status);
        $this->assertNull($fresh->cancelled_by);
    }

    public function test_worker_can_reopen_their_own_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea propia completada',
            'created_by' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
            'closed_by' => $this->admin->id,
            'progress_percent' => 100,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Necesito ajustes.']);

        $response->assertOk();
        $fresh = $task->fresh();
        $this->assertEquals(TaskStatusEnum::IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_worker_cannot_reopen_task_they_are_not_responsible_for(): void
    {
        $otherWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        $task = Task::create([
            'title' => 'Tarea ajena completada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $otherWorker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen");

        $response->assertForbidden();
    }

    // ── Requirements Validation ──

    public function test_cannot_submit_review_without_required_attachment(): void
    {
        $task = Task::create([
            'title' => 'Requiere adjunto',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_attachment' => true,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        $response->assertUnprocessable();
    }

    public function test_cannot_submit_review_without_required_completion_comment(): void
    {
        $task = Task::create([
            'title' => 'Requiere comentario',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_completion_comment' => true,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        $response->assertUnprocessable();
    }

    // ── Comments ──

    public function test_user_can_add_comment_to_visible_task(): void
    {
        $task = Task::create([
            'title' => 'Comentar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comment", [
                'comment' => 'Progreso reportado',
                'type' => 'progress',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'comment' => 'Progreso reportado',
        ]);
    }

    // ── Attachments ──

    public function test_user_can_upload_attachment(): void
    {
        Storage::fake('local');

        $task = Task::create([
            'title' => 'Adjuntar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/attachments", [
                'file' => $file,
                'attachment_type' => 'evidence',
            ]);

        $response->assertCreated()
            ->assertJsonPath('file_name', 'document.pdf');
    }

    // ── Listing & Viewing ──

    public function test_superadmin_sees_all_tasks(): void
    {
        Task::create(['title' => 'T1', 'created_by' => $this->admin->id, 'status' => TaskStatusEnum::PENDING]);
        Task::create(['title' => 'T2', 'created_by' => $this->admin->id, 'status' => TaskStatusEnum::IN_PROGRESS]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_worker_sees_only_own_tasks(): void
    {
        $otherWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        Task::create([
            'title' => 'Mi tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        Task::create([
            'title' => 'Otra tarea',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $otherWorker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_sees_area_tasks_assigned_by_admin(): void
    {
        // Task created by admin for the area → manager should see it
        Task::create([
            'title' => 'Tarea de admin para área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotEmpty($ids);
    }

    public function test_manager_cannot_see_worker_self_created_tasks(): void
    {
        // Worker creates a task for themselves → should NOT appear for manager
        Task::create([
            'title' => 'Tarea personal del trabajador',
            'created_by' => $this->worker->id,
            'assigned_to_user_id' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manager_cannot_view_worker_self_created_task_directly(): void
    {
        $task = Task::create([
            'title' => 'Tarea personal',
            'created_by' => $this->worker->id,
            'assigned_to_user_id' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertForbidden();
    }

    public function test_manager_can_view_admin_assigned_task_directly(): void
    {
        $task = Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk();
    }

    public function test_task_show_returns_full_details(): void
    {
        $task = Task::create([
            'title' => 'Detalle',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonStructure(['data' => [
                'id', 'title', 'status', 'priority',
                'creator', 'current_responsible',
                'comments', 'attachments', 'status_history',
            ]]);
    }

    // ── Status History ──

    public function test_status_transitions_are_logged(): void
    {
        $task = Task::create([
            'title' => 'Historial',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        $this->assertDatabaseHas('task_status_history', [
            'task_id'     => $task->id,
            'user_id'     => $this->worker->id,
            'from_status' => TaskStatusEnum::PENDING->value,
            'to_status'   => TaskStatusEnum::IN_PROGRESS->value,
        ]);
    }

    // ── Delegation History ──

    public function test_delegation_creates_history_record(): void
    {
        $task = Task::create([
            'title' => 'Delegar historial',
            'created_by' => $this->admin->id,
            'assigned_to_area_id' => $this->area->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $this->worker->id,
                'note' => 'Delegación de prueba',
            ]);

        $this->assertDatabaseHas('task_delegations', [
            'task_id' => $task->id,
            'from_user_id' => $this->manager->id,
            'to_user_id' => $this->worker->id,
        ]);
    }

    // ── Delete ──

    public function test_superadmin_can_delete_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea a eliminar',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Tarea eliminada correctamente.']);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_worker_cannot_delete_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea protegida',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_delete_cascades_related_records(): void
    {
        $task = Task::create([
            'title' => 'Tarea con comentario',
            'created_by' => $this->admin->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->admin->id,
            'comment' => 'Un comentario',
            'type' => 'comment',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertOk();

        $this->assertDatabaseMissing('task_comments', ['task_id' => $task->id]);
    }

    // ── Manager-user assignment (area task via manager) ──

    public function test_admin_assigning_to_manager_creates_area_task_pending_assignment(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea para encargado',
                'assigned_to_user_id' => $this->manager->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', TaskStatusEnum::PENDING_ASSIGNMENT->value);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea para encargado',
            'area_id' => $this->area->id,
            'assigned_to_user_id' => $this->manager->id,
            'current_responsible_user_id' => null, // must claim or delegate first
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT->value,
        ]);
    }

    public function test_manager_can_claim_area_task_assigned_to_them(): void
    {
        // Admin creates task directed to manager
        $task = Task::create([
            'title' => 'Tarea para reclamar',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->manager->id,
            'current_responsible_user_id' => null,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/claim");

        $response->assertOk();

        $fresh = $task->fresh();
        $this->assertEquals(TaskStatusEnum::PENDING, $fresh->status);
        $this->assertEquals($this->manager->id, $fresh->current_responsible_user_id);

        $this->assertDatabaseHas('task_status_history', [
            'task_id'     => $task->id,
            'user_id'     => $this->manager->id,
            'from_status' => TaskStatusEnum::PENDING_ASSIGNMENT->value,
            'to_status'   => TaskStatusEnum::PENDING->value,
        ]);
    }

    public function test_manager_can_delegate_pending_assignment_task_to_worker(): void
    {
        $task = Task::create([
            'title' => 'Tarea para delegar desde area',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->manager->id,
            'current_responsible_user_id' => $this->manager->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $this->worker->id,
            ]);

        $response->assertOk();

        $fresh = $task->fresh();
        $this->assertEquals(TaskStatusEnum::PENDING, $fresh->status);
        $this->assertEquals($this->worker->id, $fresh->current_responsible_user_id);
    }

    public function test_worker_cannot_claim_task(): void
    {
        $task = Task::create([
            'title' => 'Tarea sin responsable',
            'created_by' => $this->admin->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING_ASSIGNMENT,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/claim")
            ->assertForbidden();
    }

    public function test_claim_fails_when_task_not_pending_assignment(): void
    {
        $task = Task::create([
            'title' => 'Tarea activa',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->manager->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/claim")
            ->assertUnprocessable();
    }

    // ── External Task Status Management ──

    public function test_worker_can_start_external_task_they_created(): void
    {
        $task = Task::create([
            'title' => 'Tarea externa',
            'created_by' => $this->worker->id,
            'external_email' => 'proveedor@example.com',
            'external_name' => 'Juan Proveedor',
            'current_responsible_user_id' => null,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_worker_can_submit_external_task_for_review(): void
    {
        $task = Task::create([
            'title' => 'Tarea externa en progreso',
            'created_by' => $this->worker->id,
            'external_email' => 'proveedor@example.com',
            'current_responsible_user_id' => null,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => false,
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        $response->assertOk();
        $this->assertEquals(TaskStatusEnum::COMPLETED, $task->fresh()->status);
    }

    public function test_worker_cannot_start_external_task_of_another_user(): void
    {
        $otherWorker = User::factory()->create(['role_id' => $this->roles['worker']->id]);

        $task = Task::create([
            'title' => 'Tarea externa ajena',
            'created_by' => $otherWorker->id,
            'external_email' => 'proveedor@example.com',
            'current_responsible_user_id' => null,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start")
            ->assertForbidden();
    }
}
