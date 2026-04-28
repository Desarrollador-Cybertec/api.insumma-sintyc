# Dashboards

El sistema proporciona 4 vistas de dashboard para diferentes niveles de acceso.

---

## 1. Dashboard General (`GET /dashboard/general`)

**Acceso:** Solo Admin (SuperAdmin, Gerente)

Visión global de toda la organización. Excluye tareas personales.

### Response

```json
{
  "tasks_by_status": {
    "pending": 12,
    "in_progress": 25,
    "in_review": 5,
    "completed": 80,
    "cancelled": 3,
    "overdue": 2
  },
  "tasks_by_area": [
    { "area_id": 1, "area_name": "Ventas", "total": 35 },
    { "area_id": 2, "area_name": "TI", "total": 22 }
  ],
  "overdue_tasks": 2,
  "due_soon": 8,
  "completed_this_month": 15,
  "total_active": 44,
  "total_completed": 80,
  "total_all": 127,
  "completion_rate": 63.0,
  "global_progress": 64.5,
  "pending_by_user": [
    { "user_id": 5, "user_name": "Ana García", "pending_tasks": 7 },
    { "user_id": 8, "user_name": "Pedro López", "pending_tasks": 3 }
  ],
  "my_tasks": [
    {
      "id": 99,
      "title": "Revisión mensual",
      "status": "in_progress",
      "priority": "high",
      "due_date": "2026-04-20",
      "is_overdue": false,
      "progress_percent": 50,
      "area_id": null
    }
  ]
}
```

### Métricas

| Métrica | Descripción |
|---------|-------------|
| `tasks_by_status` | Conteo de tareas por estado |
| `tasks_by_area` | Conteo de tareas por área |
| `overdue_tasks` | Tareas vencidas (fecha pasada, no completadas) |
| `due_soon` | Tareas que vencen en los próximos 3 días |
| `completed_this_month` | Completadas en el mes actual |
| `completion_rate` | % completadas / total |
| `global_progress` | % completadas / (total - canceladas) |
| `pending_by_user` | Ranking de tareas pendientes por usuario |
| `my_tasks` | Tareas personales del admin (sin área) |

---

## 2. Dashboard por Área (`GET /dashboard/area/{area}`)

**Acceso:** Admin o Manager del área

Métricas específicas de un área.

### Response

```json
{
  "area": { "id": 1, "name": "Ventas", "manager_user_id": 5 },
  "total_tasks": 35,
  "active_tasks": 18,
  "completed_tasks": 15,
  "overdue_tasks": 2,
  "due_soon": 4,
  "without_progress": 6,
  "pending_assignment_tasks": 3,
  "completion_rate": 42.8,
  "tasks_by_status": {
    "pending_assignment": 3,
    "pending": 5,
    "in_progress": 8,
    "in_review": 2,
    "completed": 15,
    "overdue": 2
  },
  "by_responsible": [
    { "user_id": 10, "user_name": "María Ruiz", "active_tasks": 5 },
    { "user_id": 12, "user_name": "Carlos Díaz", "active_tasks": 3 }
  ],
  "awaiting_claim": [
    {
      "id": 45,
      "title": "Tarea pendiente de reclamar",
      "priority": "high",
      "due_date": "2026-04-25",
      "created_at": "2026-04-10"
    }
  ]
}
```

### Métricas Específicas

| Métrica | Descripción |
|---------|-------------|
| `without_progress` | Tareas activas sin ninguna actualización |
| `pending_assignment_tasks` | Tareas asignadas al área pero sin responsable |
| `by_responsible` | Distribución de tareas activas por responsable |
| `awaiting_claim` | Lista de tareas esperando ser reclamadas |

---

## 3. Dashboard Personal (`GET /dashboard/me`)

**Acceso:** Todos los usuarios autenticados

Resumen de las tareas propias del usuario.

### Response

```json
{
  "active_tasks": 5,
  "overdue_tasks": 1,
  "due_soon": 2,
  "completed_tasks": 12,
  "tasks_by_status": {
    "pending": 1,
    "in_progress": 3,
    "in_review": 1,
    "completed": 12,
    "overdue": 1
  },
  "upcoming_tasks": [
    {
      "id": 78,
      "title": "Entregar reporte",
      "status": "in_progress",
      "priority": "high",
      "due_date": "2026-04-18",
      "is_overdue": false,
      "progress_percent": 60
    }
  ]
}
```

---

## 4. Reporte Consolidado (`GET /dashboard/consolidated`)

**Acceso:** Solo Admin

Vista tipo Excel con todas las áreas. Diseñado para replicar el formato del antiguo sistema basado en hojas de cálculo.

### Response

```json
{
  "summary": {
    "total_tasks": 250,
    "total_completed": 150,
    "total_active": 85,
    "total_overdue": 15,
    "global_completion_rate": 60.0
  },
  "by_area": [
    {
      "area_id": 1,
      "area_name": "Ventas",
      "process_identifier": "VEN-001",
      "manager": "Ana García",
      "total": 35,
      "by_status": {
        "pending": 5,
        "in_progress": 8,
        "completed": 15,
        "overdue": 2
      },
      "completion_rate": 42.8,
      "overdue": 2,
      "without_progress": 6,
      "oldest_pending_days": 15,
      "avg_days_without_update": 4.5
    }
  ]
}
```

### Métricas Avanzadas

| Métrica | Descripción |
|---------|-------------|
| `oldest_pending_days` | Días que lleva la tarea pendiente más antigua |
| `avg_days_without_update` | Promedio de días sin actualización en tareas activas |
| `without_progress` | Tareas activas sin actualizaciones de avance |

---

## Optimización de Consultas

Todos los dashboards están optimizados:
- **Dashboard General:** Consulta única con agregación condicional (single query)
- **Dashboard por Área:** Consulta única con JOIN
- **Dashboard Personal:** Consulta única con agregación
- **Consolidado:** Una consulta para todas las tareas, agrupación en PHP
