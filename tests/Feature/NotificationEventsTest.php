<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskAssigned;
use App\Events\TaskCommentAdded;
use App\Events\TaskDelegated;
use App\Events\TaskStatusChanged;
use App\Models\Area;
use App\Models\AreaMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskApprovedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCancelledNotification;
use App\Notifications\TaskCompletedNotification;
use App\Notifications\TaskRejectedNotification;
use App\Notifications\TaskReopenedNotification;
use App\Notifications\TaskSubmittedForReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];
    private User $admin;
    private User $manager;
    private User $worker;
    private User $worker2;
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

        $this->worker2 = User::factory()->create([
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

        AreaMember::create([
            'area_id' => $this->area->id,
            'user_id' => $this->worker2->id,
            'assigned_by' => $this->admin->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);
    }

    // ── TaskAssigned Event ──

    public function test_task_creation_dispatches_task_assigned_event(): void
    {
        Event::fake([TaskAssigned::class]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Nueva tarea',
                'assigned_to_user_id' => $this->worker->id,
            ]);

        Event::assertDispatched(TaskAssigned::class, function ($event) {
            return $event->task->title === 'Nueva tarea'
                && $event->assignedBy->id === $this->admin->id;
        });
    }

    public function test_task_assigned_event_not_dispatched_for_area_assignment(): void
    {
        Event::fake([TaskAssigned::class]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tasks', [
                'title' => 'Tarea de área',
                'assigned_to_area_id' => $this->area->id,
            ]);

        // Area assignments have no current_responsible_user_id → no event
        Event::assertNotDispatched(TaskAssigned::class);
    }

    // ── TaskDelegated Event ──

    public function test_delegation_dispatches_task_delegated_event(): void
    {
        Event::fake([TaskDelegated::class]);

        $task = Task::create([
            'title' => 'Tarea a delegar',
            'created_by' => $this->admin->id,
            'assigned_to_user_id' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/delegate", [
                'to_user_id' => $this->worker2->id,
            ]);

        Event::assertDispatched(TaskDelegated::class, function ($event) {
            return $event->delegatedBy->id === $this->manager->id
                && $event->delegatedTo->id === $this->worker2->id;
        });
    }

    // ── TaskStatusChanged Event ──

    public function test_starting_task_dispatches_status_changed_event(): void
    {
        Event::fake([TaskStatusChanged::class]);

        $task = Task::create([
            'title' => 'Tarea pendiente',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        Event::assertDispatched(TaskStatusChanged::class, function ($event) {
            return $event->fromStatus === TaskStatusEnum::PENDING->value
                && $event->toStatus === TaskStatusEnum::IN_PROGRESS->value;
        });
    }

    public function test_approving_task_dispatches_status_changed_event(): void
    {
        Event::fake([TaskStatusChanged::class]);

        $task = Task::create([
            'title' => 'Tarea en revisión',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/approve");

        Event::assertDispatched(TaskStatusChanged::class, function ($event) {
            return $event->toStatus === TaskStatusEnum::COMPLETED->value;
        });
    }

    public function test_rejecting_task_dispatches_status_changed_event(): void
    {
        Event::fake([TaskStatusChanged::class]);

        $task = Task::create([
            'title' => 'Tarea en revisión',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reject", [
                'note' => 'Falta evidencia',
            ]);

        Event::assertDispatched(TaskStatusChanged::class, function ($event) {
            return $event->toStatus === TaskStatusEnum::REJECTED->value
                && $event->note === 'Falta evidencia';
        });
    }

    // ── TaskCommentAdded Event ──

    public function test_adding_comment_dispatches_comment_added_event(): void
    {
        Event::fake([TaskCommentAdded::class]);

        $task = Task::create([
            'title' => 'Tarea con comentario',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comment", [
                'comment' => 'Esto es un avance',
            ]);

        Event::assertDispatched(TaskCommentAdded::class, function ($event) {
            return $event->comment->comment === 'Esto es un avance'
                && $event->commentBy->id === $this->worker->id;
        });
    }

    // ── Submit for Review → Smart Approver Routing ──

    public function test_submit_for_review_notifies_assigner_area_manager(): void
    {
        Notification::fake();

        // Manager assigned the task → manager should be notified (not all superadmins)
        $task = Task::create([
            'title' => 'Tarea asignada por manager',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->manager->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        Notification::assertSentTo($this->manager, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->admin, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->worker, TaskSubmittedForReviewNotification::class);
    }

    public function test_submit_for_review_notifies_assigner_superadmin_for_personal_task(): void
    {
        Notification::fake();

        // Superadmin assigned the task directly (personal task) → superadmin notified
        $task = Task::create([
            'title' => 'Tarea personal asignada por superadmin',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => null,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        Notification::assertSentTo($this->admin, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->worker, TaskSubmittedForReviewNotification::class);
    }

    public function test_submit_for_review_notifies_delegator_over_assigner(): void
    {
        Notification::fake();

        // Task was assigned by superadmin but then delegated by manager → delegator takes priority
        $task = Task::create([
            'title' => 'Tarea delegada',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'delegated_by' => $this->manager->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        Notification::assertSentTo($this->manager, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->admin, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->worker, TaskSubmittedForReviewNotification::class);
    }

    public function test_submit_for_review_falls_back_to_creator(): void
    {
        Notification::fake();

        // No assigned_by, no delegated_by → fall back to created_by
        $task = Task::create([
            'title' => 'Tarea sin asignador explícito',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        Notification::assertSentTo($this->admin, TaskSubmittedForReviewNotification::class);
        Notification::assertNotSentTo($this->worker, TaskSubmittedForReviewNotification::class);
    }

    // ── Worker Completes (no approval) → Manager Notification ──

    public function test_worker_completes_task_notifies_area_manager(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea sin aprobación',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
            'requires_manager_approval' => false,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/submit-review");

        // Worker completed → manager gets TaskCompletedNotification
        Notification::assertSentTo($this->manager, TaskCompletedNotification::class);
    }

    // ── Manager Approves → Worker Gets Approved Notification ──

    public function test_manager_approves_notifies_worker(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea en revisión',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/approve");

        Notification::assertSentTo($this->worker, TaskApprovedNotification::class);
        Notification::assertNotSentTo($this->manager, TaskApprovedNotification::class);
    }

    // ── Manager Rejects → Worker Gets Rejected Notification ──

    public function test_manager_rejects_notifies_worker(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea en revisión',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reject", [
                'note' => 'Falta evidencia',
            ]);

        Notification::assertSentTo($this->worker, TaskRejectedNotification::class, function ($notification) {
            return $notification->reason === 'Falta evidencia';
        });
    }

    // ── Cancel → Responsible Notification ──

    public function test_cancel_notifies_responsible_user(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea a cancelar',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/cancel", ['comment' => 'Se cancela la tarea.']);

        Notification::assertSentTo($this->worker, TaskCancelledNotification::class);
    }

    public function test_cancel_does_not_notify_self(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea propia',
            'created_by' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/cancel", ['comment' => 'Cancelo mi propia tarea.']);

        Notification::assertNotSentTo($this->worker, TaskCancelledNotification::class);
    }

    // ── Reopen → Responsible Notification ──

    public function test_reopen_completed_task_notifies_responsible(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea completada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
            'closed_by' => $this->manager->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Requiere ajustes']);

        Notification::assertSentTo($this->worker, TaskReopenedNotification::class, function ($notification) {
            return $notification->note === 'Requiere ajustes';
        });
    }

    public function test_reopen_cancelled_task_notifies_responsible(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea cancelada',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::CANCELLED,
            'cancelled_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Se reabre la tarea.']);

        Notification::assertSentTo($this->worker, TaskReopenedNotification::class);
    }

    public function test_normal_start_does_not_send_reopen_notification(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea pendiente',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        // PENDING → IN_PROGRESS is a normal start, not a reopen
        Notification::assertNotSentTo($this->worker, TaskReopenedNotification::class);
        Notification::assertNotSentTo($this->manager, TaskReopenedNotification::class);
    }

    // ── Category field ──

    public function test_notification_includes_organizational_category(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea de área',
            'created_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_REVIEW,
            'requires_manager_approval' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/approve");

        Notification::assertSentTo($this->worker, TaskApprovedNotification::class, function ($notification) use ($task) {
            $data = $notification->toArray($this->worker);
            return $data['category'] === 'organizational';
        });
    }

    public function test_notification_includes_personal_category(): void
    {
        $notification = new TaskAssignedNotification(
            Task::create([
                'title' => 'Tarea personal',
                'created_by' => $this->worker->id,
                'current_responsible_user_id' => $this->worker->id,
                'area_id' => null,
                'status' => TaskStatusEnum::PENDING,
            ]),
            $this->admin,
        );

        $data = $notification->toArray($this->worker);

        $this->assertEquals('personal', $data['category']);
    }

    // ── TaskStarted ──

    public function test_start_task_notifies_assigner(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea por iniciar',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        Notification::assertSentTo($this->admin, \App\Notifications\TaskStartedNotification::class);
        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskStartedNotification::class);
    }

    public function test_start_task_does_not_notify_self_starter(): void
    {
        Notification::fake();

        // Worker creates and self-assigns task, then starts it → no notification
        $task = Task::create([
            'title' => 'Tarea propia',
            'created_by' => $this->worker->id,
            'assigned_by' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::PENDING,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/start");

        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskStartedNotification::class);
    }

    public function test_reopen_from_completed_still_sends_reopen_not_started(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea completada',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::COMPLETED,
            'completed_at' => now(),
            'closed_by' => $this->manager->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/reopen", ['comment' => 'Requiere ajustes']);

        Notification::assertSentTo($this->worker, \App\Notifications\TaskReopenedNotification::class);
        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskStartedNotification::class);
    }

    // ── TaskUpdateAdded ──

    public function test_adding_update_notifies_creator(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea con avances',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'update_type' => 'progress',
                'comment' => 'Avanzando bien',
            ]);

        Notification::assertSentTo($this->admin, \App\Notifications\TaskUpdateAddedNotification::class);
        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskUpdateAddedNotification::class);
    }

    public function test_adding_update_does_not_notify_self(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea propia',
            'created_by' => $this->worker->id,
            'current_responsible_user_id' => $this->worker->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/updates", [
                'update_type' => 'progress',
                'comment' => 'Yo mismo trabajo en esto',
            ]);

        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskUpdateAddedNotification::class);
    }

    // ── TaskAttachmentAdded ──

    public function test_adding_attachment_notifies_creator(): void
    {
        Notification::fake();

        $task = Task::create([
            'title' => 'Tarea con adjuntos',
            'created_by' => $this->admin->id,
            'assigned_by' => $this->admin->id,
            'current_responsible_user_id' => $this->worker->id,
            'area_id' => $this->area->id,
            'status' => TaskStatusEnum::IN_PROGRESS,
        ]);

        // Simulate fake file upload (bypasses actual storage)
        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf');

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/attachments", [
                'file' => $file,
                'attachment_type' => 'evidence',
            ]);

        Notification::assertSentTo($this->admin, \App\Notifications\TaskAttachmentAddedNotification::class);
        Notification::assertNotSentTo($this->worker, \App\Notifications\TaskAttachmentAddedNotification::class);
    }
}
