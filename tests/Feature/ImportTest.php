<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;

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
    }

    private function createCsv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    public function test_superadmin_can_import_tasks_from_csv(): void
    {
        $csv = "titulo,descripcion,area,prioridad,estado,fecha_inicio,fecha_limite\n";
        $csv .= "Tarea importada,Descripción de prueba,Producción,alta,pendiente,2025-01-01,2025-06-30\n";
        $csv .= "Otra tarea,,Calidad,media,en progreso,,2025-12-31\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('imported'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea importada',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Otra tarea',
            'priority' => 'medium',
            'status' => 'in_progress',
        ]);
    }

    public function test_import_creates_areas_automatically(): void
    {
        $csv = "titulo,area\n";
        $csv .= "Mi tarea,NuevaÁrea\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('areas', [
            'name' => 'NuevaÁrea',
            'process_identifier' => 'NuevaÁrea',
        ]);
    }

    public function test_import_matches_existing_areas_by_name(): void
    {
        $area = Area::create([
            'name' => 'Producción',
            'process_identifier' => 'PROD',
        ]);

        $csv = "titulo,area\n";
        $csv .= "Tarea prod,Producción\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea prod',
            'area_id' => $area->id,
        ]);
    }

    public function test_import_matches_existing_areas_by_process_identifier(): void
    {
        $area = Area::create([
            'name' => 'Producción',
            'process_identifier' => 'PROD',
        ]);

        $csv = "titulo,area\n";
        $csv .= "Tarea prod,PROD\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea prod',
            'area_id' => $area->id,
        ]);
    }

    public function test_import_assigns_responsible_by_email(): void
    {
        $csv = "titulo,responsable_email\n";
        $csv .= "Tarea asignada,{$this->worker->email}\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Tarea asignada',
            'current_responsible_user_id' => $this->worker->id,
        ]);
    }

    public function test_import_skips_rows_with_empty_title(): void
    {
        $csv = "titulo,area\n";
        $csv .= ",Producción\n";
        $csv .= "Válida,Producción\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('imported'));
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_import_requires_titulo_column(): void
    {
        $csv = "nombre,area\n";
        $csv .= "Tarea,Producción\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Columnas requeridas faltantes: titulo']);
    }

    public function test_worker_cannot_import_tasks(): void
    {
        $csv = "titulo\nTarea\n";

        $response = $this->actingAs($this->worker, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertForbidden();
    }

    public function test_import_requires_file(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks');

        $response->assertUnprocessable();
    }

    public function test_import_handles_date_formats(): void
    {
        $csv = "titulo,fecha_inicio,fecha_limite\n";
        $csv .= "Tarea 1,2025-03-15,2025-06-30\n";
        $csv .= "Tarea 2,15/03/2025,30/06/2025\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('imported'));

        /** @var Task $task1 */
        $task1 = Task::where('title', 'Tarea 1')->first();
        $this->assertEquals('2025-03-15', $task1->start_date->toDateString());

        $task2 = Task::where('title', 'Tarea 2')->first();
        $this->assertEquals('2025-03-15', $task2->start_date->toDateString());
    }

    public function test_import_maps_status_correctly(): void
    {
        $csv = "titulo,estado\n";
        $csv .= "T1,completada\n";
        $csv .= "T2,en progreso\n";
        $csv .= "T3,cancelada\n";

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/import/tasks', [
                'file' => $this->createCsv($csv),
            ]);

        $response->assertOk();
        $this->assertEquals(3, $response->json('imported'));

        $this->assertDatabaseHas('tasks', ['title' => 'T1', 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['title' => 'T2', 'status' => 'in_progress']);
        $this->assertDatabaseHas('tasks', ['title' => 'T3', 'status' => 'cancelled']);
    }
}
