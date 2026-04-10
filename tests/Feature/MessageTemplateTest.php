<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;
    private MessageTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $superadminRole = Role::create(['name' => 'Super Administrador', 'slug' => RoleEnum::SUPERADMIN->value]);
        $workerRole = Role::create(['name' => 'Trabajador', 'slug' => RoleEnum::WORKER->value]);

        $this->admin = User::factory()->create([
            'role_id' => $superadminRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->worker = User::factory()->create([
            'role_id' => $workerRole->id,
            'password' => Hash::make('Password1'),
        ]);

        $this->template = MessageTemplate::create([
            'slug' => 'new_assignment',
            'name' => 'Nueva asignación',
            'subject' => 'Tarea: {task_title}',
            'body' => 'Se te asignó la tarea {task_title}.',
            'active' => true,
        ]);
    }

    // ── Index ──

    public function test_superadmin_can_list_templates(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/message-templates');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['slug' => 'new_assignment']);
    }

    public function test_worker_cannot_list_templates(): void
    {
        $response = $this->actingAs($this->worker)->getJson('/api/message-templates');

        $response->assertForbidden();
    }

    // ── Show ──

    public function test_superadmin_can_view_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/message-templates/{$this->template->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'slug' => 'new_assignment',
                'subject' => 'Tarea: {task_title}',
            ]);
    }

    public function test_worker_cannot_view_template(): void
    {
        $response = $this->actingAs($this->worker)
            ->getJson("/api/message-templates/{$this->template->id}");

        $response->assertForbidden();
    }

    // ── Update ──

    public function test_superadmin_can_update_template_subject(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/message-templates/{$this->template->id}", [
                'subject' => 'Nuevo asunto: {task_title}',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['subject' => 'Nuevo asunto: {task_title}']);

        $this->assertEquals('Nuevo asunto: {task_title}', $this->template->fresh()->subject);
    }

    public function test_superadmin_can_update_template_body(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/message-templates/{$this->template->id}", [
                'body' => 'Cuerpo actualizado para {task_title}.',
            ]);

        $response->assertOk();
        $this->assertEquals('Cuerpo actualizado para {task_title}.', $this->template->fresh()->body);
    }

    public function test_superadmin_can_deactivate_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/message-templates/{$this->template->id}", [
                'active' => false,
            ]);

        $response->assertOk();
        $this->assertFalse($this->template->fresh()->active);
    }

    public function test_worker_cannot_update_template(): void
    {
        $response = $this->actingAs($this->worker)
            ->putJson("/api/message-templates/{$this->template->id}", [
                'subject' => 'Intento no autorizado',
            ]);

        $response->assertForbidden();
    }

    public function test_update_validates_subject_max_length(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/message-templates/{$this->template->id}", [
                'subject' => str_repeat('a', 256),
            ]);

        $response->assertUnprocessable();
    }

    // ── Store ──

    public function test_superadmin_can_create_template(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/message-templates', [
                'slug'    => 'custom_template',
                'name'    => 'Plantilla personalizada',
                'subject' => 'Asunto personalizado',
                'body'    => 'Cuerpo del mensaje personalizado.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'custom_template');

        $this->assertDatabaseHas('message_templates', ['slug' => 'custom_template']);
    }

    public function test_worker_cannot_create_template(): void
    {
        $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/message-templates', [
                'slug'    => 'worker_template',
                'name'    => 'Plantilla worker',
                'subject' => 'Asunto',
                'body'    => 'Cuerpo.',
            ])
            ->assertForbidden();
    }

    public function test_create_template_requires_unique_slug(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/message-templates', [
                'slug'    => 'new_assignment', // already exists in setUp
                'name'    => 'Duplicada',
                'subject' => 'Asunto',
                'body'    => 'Cuerpo.',
            ])
            ->assertUnprocessable();
    }

    // ── Destroy ──

    public function test_superadmin_can_delete_template(): void
    {
        $extra = MessageTemplate::create([
            'slug'    => 'to_delete',
            'name'    => 'A eliminar',
            'subject' => 'Asunto',
            'body'    => 'Cuerpo.',
            'active'  => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/message-templates/{$extra->id}");

        $response->assertNoContent();
        $this->assertModelMissing($extra);
    }

    public function test_worker_cannot_delete_template(): void
    {
        $this->actingAs($this->worker, 'sanctum')
            ->deleteJson("/api/message-templates/{$this->template->id}")
            ->assertForbidden();
    }
}
