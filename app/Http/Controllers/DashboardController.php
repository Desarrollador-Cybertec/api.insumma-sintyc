<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatusEnum;
use App\Models\Area;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function general(Request $request): JsonResponse
    {
        if (!$request->user()->isAdminLevel()) {
            abort(403);
        }

        $completed = TaskStatusEnum::COMPLETED->value;
        $cancelled = TaskStatusEnum::CANCELLED->value;

        // Personal tasks (area_id IS NULL) are excluded from all dashboard metrics.
        // Single query for all aggregate counts (replaces 6 separate COUNT queries)
        $stats = Task::whereNotNull('area_id')->toBase()->selectRaw("
            COUNT(*) as total_all,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_completed,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_cancelled,
            SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN due_date < CURRENT_DATE AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue_tasks,
            SUM(CASE WHEN due_date >= CURRENT_DATE AND due_date <= ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as due_soon,
            SUM(CASE WHEN status = ? AND completed_at >= ? THEN 1 ELSE 0 END) as completed_this_month
        ", [
            $completed,
            $cancelled,
            $completed, $cancelled,
            $completed, $cancelled,
            now()->addDays(3)->toDateString(), $completed, $cancelled,
            $completed, now()->startOfMonth(),
        ])->first();

        $tasksByStatus = Task::whereNotNull('area_id')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // JOIN to resolve area names in one query (eliminates N+1)
        $tasksByArea = Task::select('tasks.area_id', 'areas.name as area_name', DB::raw('count(*) as total'))
            ->join('areas', 'tasks.area_id', '=', 'areas.id')
            ->whereNotNull('tasks.area_id')
            ->groupBy('tasks.area_id', 'areas.name')
            ->get()
            ->map(fn ($item) => [
                'area_id' => $item->area_id,
                'area_name' => $item->area_name,
                'total' => $item->total,
            ]);

        $totalAll = (int) $stats->total_all;
        $totalCompleted = (int) $stats->total_completed;
        $totalActive = (int) $stats->total_active;
        $completionRate = $totalAll > 0 ? round(($totalCompleted / $totalAll) * 100, 1) : 0;

        // Global progress: completed / (total - cancelled)
        $totalCancelled = (int) $stats->total_cancelled;
        $totalExcludingCancelled = $totalAll - $totalCancelled;
        $globalProgress = $totalExcludingCancelled > 0
            ? round(($totalCompleted / $totalExcludingCancelled) * 100, 1)
            : 0;

        // Personas con tareas pendientes (ordered by most pending)
        $pendingByUser = Task::whereNotNull('tasks.area_id')
            ->select('tasks.current_responsible_user_id', 'users.name as user_name', DB::raw('count(*) as total'))
            ->join('users', 'tasks.current_responsible_user_id', '=', 'users.id')
            ->whereNotIn('tasks.status', [$completed, $cancelled])
            ->groupBy('tasks.current_responsible_user_id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'user_id' => $item->current_responsible_user_id,
                'user_name' => $item->user_name,
                'pending_tasks' => $item->total,
            ]);

        // Superadmin's own active tasks (personal + area), ordered by due date
        $myTasks = Task::where('current_responsible_user_id', $request->user()->id)
            ->whereNotIn('status', [$completed, $cancelled])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->select(['id', 'title', 'status', 'priority', 'due_date', 'progress_percent', 'area_id'])
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'is_overdue' => $task->isOverdue(),
                'progress_percent' => $task->progress_percent,
                'area_id' => $task->area_id,
            ]);

        return response()->json([
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_area' => $tasksByArea,
            'overdue_tasks' => (int) $stats->overdue_tasks,
            'due_soon' => (int) $stats->due_soon,
            'completed_this_month' => (int) $stats->completed_this_month,
            'total_active' => $totalActive,
            'total_completed' => $totalCompleted,
            'total_all' => $totalAll,
            'completion_rate' => $completionRate,
            'global_progress' => $globalProgress,
            'pending_by_user' => $pendingByUser,
            'my_tasks' => $myTasks,
        ]);
    }

    public function byArea(Request $request, Area $area): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminLevel() && !$user->isManagerOfArea($area->id)) {
            abort(403);
        }

        $completed = TaskStatusEnum::COMPLETED->value;
        $cancelled = TaskStatusEnum::CANCELLED->value;
        $pendingAssignment = TaskStatusEnum::PENDING_ASSIGNMENT->value;

        // Single query for all aggregate counts
        $stats = Task::where('area_id', $area->id)->toBase()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_assignment,
            SUM(CASE WHEN due_date < CURRENT_DATE AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN due_date >= CURRENT_DATE AND due_date <= ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as due_soon,
            SUM(CASE WHEN status NOT IN (?, ?) AND NOT EXISTS (SELECT 1 FROM task_updates WHERE task_updates.task_id = tasks.id) THEN 1 ELSE 0 END) as without_progress
        ", [
            $completed,
            $completed, $cancelled,
            $pendingAssignment,
            $completed, $cancelled,
            now()->addDays(3)->toDateString(), $completed, $cancelled,
            $completed, $cancelled,
        ])->first();

        $byStatus = Task::where('area_id', $area->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // LEFT JOIN to include tasks without a responsible (pending_assignment)
        $byResponsible = Task::select(
                'tasks.current_responsible_user_id',
                'users.name as user_name',
                DB::raw('count(*) as total')
            )
            ->leftJoin('users', 'tasks.current_responsible_user_id', '=', 'users.id')
            ->where('tasks.area_id', $area->id)
            ->whereNotIn('tasks.status', [$completed, $cancelled])
            ->groupBy('tasks.current_responsible_user_id', 'users.name')
            ->get()
            ->map(fn ($item) => [
                'user_id' => $item->current_responsible_user_id,
                'user_name' => $item->user_name ?? 'Sin responsable asignado',
                'active_tasks' => $item->total,
            ]);

        // Pending assignment tasks (need claim or delegate)
        $pendingAssignmentTasks = Task::where('area_id', $area->id)
            ->where('status', TaskStatusEnum::PENDING_ASSIGNMENT)
            ->select(['id', 'title', 'priority', 'due_date', 'assigned_to_user_id', 'created_at'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'created_at' => $task->created_at,
            ]);

        $total = (int) $stats->total;
        $completedCount = (int) $stats->completed;
        $completionRate = $total > 0 ? round(($completedCount / $total) * 100, 1) : 0;

        return response()->json([
            'area' => ['id' => $area->id, 'name' => $area->name, 'manager_user_id' => $area->manager_user_id],
            'total_tasks' => $total,
            'active_tasks' => (int) $stats->active,
            'completed_tasks' => $completedCount,
            'overdue_tasks' => (int) $stats->overdue,
            'due_soon' => (int) $stats->due_soon,
            'without_progress' => (int) $stats->without_progress,
            'pending_assignment_tasks' => (int) $stats->pending_assignment,
            'completion_rate' => $completionRate,
            'tasks_by_status' => $byStatus,
            'by_responsible' => $byResponsible,
            'awaiting_claim' => $pendingAssignmentTasks,
        ]);
    }

    public function myDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $completed = TaskStatusEnum::COMPLETED->value;
        $cancelled = TaskStatusEnum::CANCELLED->value;

        // Personal tasks (area_id IS NULL) are excluded from dashboard metrics.
        // Single query for all counts (replaces 4 separate COUNT queries)
        $stats = Task::where('current_responsible_user_id', $user->id)
            ->whereNotNull('area_id')->toBase()->selectRaw("
            SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN due_date < CURRENT_DATE AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN due_date >= CURRENT_DATE AND due_date <= ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as due_soon,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed
        ", [
            $completed, $cancelled,
            $completed, $cancelled,
            now()->addDays(3)->toDateString(), $completed, $cancelled,
            $completed,
        ])->first();

        $byStatus = Task::where('current_responsible_user_id', $user->id)
            ->whereNotNull('area_id')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentTasks = Task::where('current_responsible_user_id', $user->id)
            ->whereNotNull('area_id')
            ->whereNotIn('status', [$completed, $cancelled])
            ->orderBy('due_date')
            ->limit(10)
            ->select(['id', 'title', 'status', 'priority', 'due_date', 'progress_percent'])
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'is_overdue' => $task->isOverdue(),
                'progress_percent' => $task->progress_percent,
            ]);

        return response()->json([
            'active_tasks' => (int) ($stats->active ?? 0),
            'overdue_tasks' => (int) ($stats->overdue ?? 0),
            'due_soon' => (int) ($stats->due_soon ?? 0),
            'completed_tasks' => (int) ($stats->completed ?? 0),
            'tasks_by_status' => $byStatus,
            'upcoming_tasks' => $recentTasks,
        ]);
    }

    /**
     * Consolidated report by area/process — mirrors the legacy Excel view.
     */
    public function consolidated(Request $request): JsonResponse
    {
        if (!$request->user()->isAdminLevel()) {
            abort(403);
        }

        $completed = TaskStatusEnum::COMPLETED->value;
        $cancelled = TaskStatusEnum::CANCELLED->value;

        $areas = Area::with('manager')->where('active', true)->get();
        $areaIds = $areas->pluck('id');

        // Fetch ALL tasks for active areas in ONE query (only needed columns)
        $allTasks = Task::whereIn('area_id', $areaIds)
            ->select(['id', 'area_id', 'status', 'due_date', 'created_at'])
            ->get();

        $activeTaskIds = $allTasks
            ->filter(fn ($t) => !in_array($t->status, [$completed, $cancelled]))
            ->pluck('id');

        // Get latest update date per active task in ONE query (eliminates N×M N+1)
        $latestUpdates = [];
        $taskIdsWithUpdates = [];
        if ($activeTaskIds->isNotEmpty()) {
            $latestUpdates = DB::table('task_updates')
                ->select('task_id', DB::raw('MAX(created_at) as last_update_at'))
                ->whereIn('task_id', $activeTaskIds)
                ->groupBy('task_id')
                ->pluck('last_update_at', 'task_id')
                ->toArray();
            $taskIdsWithUpdates = array_keys($latestUpdates);
        }

        // Group tasks by area in PHP (no per-area queries)
        $tasksByArea = $allTasks->groupBy('area_id');

        $consolidated = $areas->map(function (Area $area) use ($tasksByArea, $latestUpdates, $taskIdsWithUpdates, $completed, $cancelled) {
            $areaTasks = $tasksByArea->get($area->id, collect());
            $total = $areaTasks->count();

            if ($total === 0) {
                return [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'process_identifier' => $area->process_identifier,
                    'manager' => $area->manager?->name,
                    'total' => 0,
                    'by_status' => [],
                    'completion_rate' => 0,
                    'overdue' => 0,
                    'without_progress' => 0,
                    'oldest_pending_days' => null,
                    'avg_days_without_update' => null,
                ];
            }

            $byStatus = $areaTasks->groupBy('status')->map->count();
            $completedCount = $byStatus->get($completed, 0);
            $completionRate = round(($completedCount / $total) * 100, 1);

            $activeTasks = $areaTasks->filter(fn ($t) => !in_array($t->status, [$completed, $cancelled]));

            $overdue = $activeTasks->filter(fn ($t) => $t->due_date && $t->due_date < now())->count();
            $withoutProgress = $activeTasks->filter(fn ($t) => !in_array($t->id, $taskIdsWithUpdates))->count();

            $oldestPending = $activeTasks->min('created_at');
            $oldestPendingDays = $oldestPending ? (int) $oldestPending->diffInDays(now()) : null;

            $avgDays = null;
            if ($activeTasks->isNotEmpty()) {
                $totalDays = $activeTasks->sum(function ($task) use ($latestUpdates) {
                    $lastUpdateAt = $latestUpdates[$task->id] ?? null;
                    if ($lastUpdateAt) {
                        return (int) Carbon::parse($lastUpdateAt)->diffInDays(now());
                    }
                    return (int) $task->created_at->diffInDays(now());
                });
                $avgDays = round($totalDays / $activeTasks->count(), 1);
            }

            return [
                'area_id' => $area->id,
                'area_name' => $area->name,
                'process_identifier' => $area->process_identifier,
                'manager' => $area->manager?->name,
                'total' => $total,
                'by_status' => $byStatus,
                'completion_rate' => $completionRate,
                'overdue' => $overdue,
                'without_progress' => $withoutProgress,
                'oldest_pending_days' => $oldestPendingDays,
                'avg_days_without_update' => $avgDays,
            ];
        });

        // Global summary — single query with conditional aggregation (replaces 4 queries)
        $summary = Task::whereNotNull('area_id')->toBase()->selectRaw("
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_completed,
            SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN due_date < CURRENT_DATE AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as total_overdue
        ", [$completed, $completed, $cancelled, $completed, $cancelled])->first();

        return response()->json([
            'summary' => [
                'total_tasks' => (int) $summary->total_tasks,
                'total_completed' => (int) $summary->total_completed,
                'total_active' => (int) $summary->total_active,
                'total_overdue' => (int) $summary->total_overdue,
                'global_completion_rate' => $summary->total_tasks > 0
                    ? round(($summary->total_completed / $summary->total_tasks) * 100, 1)
                    : 0,
            ],
            'by_area' => $consolidated,
        ]);
    }
}
