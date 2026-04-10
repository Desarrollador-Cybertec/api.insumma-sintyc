<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    /**
     * Import tasks from a CSV file.
     *
     * Expected columns: titulo, descripcion, responsable_email, area, prioridad, estado, fecha_inicio, fecha_limite
     */
    public function importTasks(Request $request): JsonResponse
    {
        if (!$request->user()->isAdminLevel()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return response()->json(['message' => 'No se pudo leer el archivo.'], 422);
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'Archivo vacío o formato inválido.'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $required = ['titulo'];
        $missing = array_diff($required, $header);
        if (!empty($missing)) {
            fclose($handle);
            return response()->json([
                'message' => 'Columnas requeridas faltantes: ' . implode(', ', $missing),
            ], 422);
        }

        $imported = 0;
        $errors = [];
        $row = 1;

        // Pre-fetch lookup caches to avoid per-row queries
        $areaCache = Area::all()->keyBy(fn ($a) => strtolower($a->name));
        $areaByProcess = Area::whereNotNull('process_identifier')->get()->keyBy(fn ($a) => strtolower($a->process_identifier));
        $userCache = User::pluck('id', 'email');

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $row++;

                if (count($data) !== count($header)) {
                    $errors[] = "Fila {$row}: número de columnas no coincide con el encabezado.";
                    continue;
                }

                $rowData = array_combine($header, $data);
                $result = $this->processRow($rowData, $request->user(), $row, $areaCache, $areaByProcess, $userCache);

                if ($result['success']) {
                    $imported++;
                } else {
                    $errors[] = $result['error'];
                }
            }

            fclose($handle);

            if ($imported === 0 && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se importó ninguna tarea.',
                    'errors' => array_slice($errors, 0, 50),
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => "Se importaron {$imported} tarea(s) correctamente.",
                'imported' => $imported,
                'errors' => array_slice($errors, 0, 50),
            ]);
        } catch (\Throwable $e) {
            fclose($handle);
            DB::rollBack();
            return response()->json([
                'message' => 'Error durante la importación: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function processRow(array $row, User $creator, int $rowNumber, &$areaCache, &$areaByProcess, $userCache): array
    {
        $title = trim($row['titulo'] ?? '');
        if (empty($title)) {
            return ['success' => false, 'error' => "Fila {$rowNumber}: título vacío."];
        }

        // Resolve area from cache instead of per-row query
        $areaId = null;
        $areaValue = trim($row['area'] ?? '');
        if (!empty($areaValue)) {
            $key = strtolower($areaValue);
            $area = $areaCache->get($key) ?? $areaByProcess->get($key);

            if (!$area) {
                $area = Area::create([
                    'name' => $areaValue,
                    'process_identifier' => $areaValue,
                    'active' => true,
                ]);
                $areaCache->put($key, $area);
                $areaByProcess->put($key, $area);
            }
            $areaId = $area->id;
        }

        // Resolve responsible user from cache instead of per-row query
        $responsibleId = null;
        $email = trim($row['responsable_email'] ?? '');
        if (!empty($email)) {
            $responsibleId = $userCache->get($email);
        }

        // Map priority
        $priorityMap = [
            'baja' => 'low', 'low' => 'low',
            'media' => 'medium', 'medium' => 'medium',
            'alta' => 'high', 'high' => 'high',
            'urgente' => 'urgent', 'urgent' => 'urgent',
            'critica' => 'urgent', 'critical' => 'urgent',
        ];
        $rawPriority = strtolower(trim($row['prioridad'] ?? 'medium'));
        $priority = $priorityMap[$rawPriority] ?? 'medium';

        // Map status
        $statusMap = [
            'pendiente' => 'pending', 'pending' => 'pending',
            'en progreso' => 'in_progress', 'in_progress' => 'in_progress',
            'completada' => 'completed', 'completed' => 'completed',
            'cancelada' => 'cancelled', 'cancelled' => 'cancelled',
            'borrador' => 'draft', 'draft' => 'draft',
            'vencida' => 'overdue', 'overdue' => 'overdue',
        ];
        $rawStatus = strtolower(trim($row['estado'] ?? 'pending'));
        $status = $statusMap[$rawStatus] ?? 'pending';

        // Parse dates
        $startDate = $this->parseDate($row['fecha_inicio'] ?? '');
        $dueDate = $this->parseDate($row['fecha_limite'] ?? '');

        Task::create([
            'title' => $title,
            'description' => trim($row['descripcion'] ?? '') ?: null,
            'created_by' => $creator->id,
            'assigned_to_user_id' => $responsibleId,
            'current_responsible_user_id' => $responsibleId,
            'area_id' => $areaId,
            'priority' => $priority,
            'status' => $status,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        return ['success' => true, 'error' => null];
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) {
            return null;
        }

        // Try common formats
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
