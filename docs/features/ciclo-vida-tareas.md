# Ciclo de Vida de Tareas

## Máquina de Estados

```
                         ┌──────────┐
                         │  DRAFT   │
                         └────┬─────┘
                              │
                 ┌────────────┼────────────┐
                 ▼            │            ▼
        ┌────────────────┐    │    ┌───────────┐
        │ PENDING_ASSIGN │    │    │  PENDING  │
        └───────┬────────┘    │    └─────┬─────┘
                │ claim       │          │
                └─────────────┘          │
                      │                  │
                      ▼                  ▼
               ┌───────────┐      ┌───────────┐
               │  PENDING  │─────▶│IN_PROGRESS│◄───────────┐
               └───────────┘      └─────┬─────┘            │
                                        │                   │
                              ┌─────────┴────────┐         │
                              ▼                  ▼          │
                      ┌───────────┐      ┌───────────┐     │
                      │ COMPLETED │      │ IN_REVIEW │     │
                      └───────────┘      └─────┬─────┘     │
                                               │           │
                                    ┌──────────┼──────┐    │
                                    ▼          ▼      │    │
                             ┌───────────┐  ┌─────────┴──┐ │
                             │ COMPLETED │  │  REJECTED  │─┘
                             └───────────┘  └────────────┘

    IN_PROGRESS ──── (auto) ──── OVERDUE ──── IN_PROGRESS/CANCELLED

    Cualquier estado activo ──── CANCELLED
    COMPLETED/CANCELLED ──── Reabrir ──── IN_PROGRESS/PENDING
```

---

## Transiciones Permitidas

| Estado Actual | Puede ir a |
|---------------|-----------|
| DRAFT | PENDING_ASSIGNMENT, PENDING, CANCELLED |
| PENDING_ASSIGNMENT | PENDING, CANCELLED |
| PENDING | IN_PROGRESS, CANCELLED |
| IN_PROGRESS | IN_REVIEW, COMPLETED, CANCELLED, OVERDUE |
| IN_REVIEW | COMPLETED, REJECTED, CANCELLED |
| REJECTED | IN_PROGRESS, CANCELLED |
| COMPLETED | IN_PROGRESS (reopen) |
| CANCELLED | PENDING (reopen) |
| OVERDUE | IN_PROGRESS, CANCELLED |

---

## Progreso Automático por Estado

| Estado | Progreso |
|--------|----------|
| DRAFT | 0% |
| PENDING_ASSIGNMENT | 0% |
| PENDING | 0% |
| IN_PROGRESS | 25% |
| IN_REVIEW | 75% |
| COMPLETED | 100% |
| REJECTED | 25% |
| CANCELLED | 0% |
| OVERDUE | 25% |

---

## Acciones y Quién Puede Ejecutarlas

| Acción | Admin | Manager de Área | Responsable | Creador |
|--------|:-----:|:---------------:|:-----------:|:-------:|
| Crear | ✅ | ✅ | — | — |
| Reclamar (claim) | ✅ | ✅ | — | ✅ (si es manager) |
| Delegar | ✅ | ✅ | — | ✅ (si es manager) |
| Iniciar (start) | ✅ | ✅ | ✅ | ✅ (si es externa) |
| Enviar a revisión | ✅ | ✅ | ✅ | ✅ (si es externa) |
| Completar | ✅ | ✅ | ✅ | ✅ (si es externa) |
| Aprobar | ✅ | ✅ | — | — |
| Rechazar | ✅ | ✅ | — | — |
| Cancelar | ✅ | ✅ | ✅ | ✅ |
| Reabrir | ✅ | ✅ | ✅ | ✅ |
| Eliminar | ✅ | — | — | ✅ (solo personal) |
| Comentar | ✅ | ✅ | ✅ | ✅ |
| Adjuntar | ✅ | ✅ | ✅ | ✅ |

---

## Requisitos Configurables

Al crear una tarea se pueden activar requisitos:

| Requisito | Campo | Efecto |
|-----------|-------|--------|
| Requiere adjunto | `requires_attachment` | No se puede completar sin al menos un adjunto |
| Requiere comentario | `requires_completion_comment` | No se puede completar sin comentario de cierre |
| Requiere aprobación | `requires_manager_approval` | Envía a IN_REVIEW antes de COMPLETED |
| Requiere fecha límite | `requires_due_date` | No se puede completar sin fecha límite |
| Requiere reporte de avance | `requires_progress_report` | Indica que se esperan actualizaciones |

---

## Comentarios por Tipo

Los comentarios se clasifican automáticamente según la acción:

| Tipo | Cuándo se crea | Acción |
|------|---------------|--------|
| `comment` | Manual del usuario | POST /tasks/{id}/comment |
| `progress` | Al iniciar la tarea | POST /tasks/{id}/start |
| `completion_note` | Al completar/enviar a revisión/aprobar | Automático |
| `rejection_note` | Al rechazar | Automático |
| `cancellation_note` | Al cancelar | Automático |
| `reopen_note` | Al reabrir | Automático |
| `system` | Acciones automáticas | Sistema |

---

## Delegación

La delegación cambia el responsable de una tarea:

1. Valida que el destinatario pertenece al área de la tarea
2. Crea registro en `task_delegations`
3. Actualiza `current_responsible_user_id`
4. Resetea el estado a `PENDING`
5. Dispara notificación al nuevo responsable

---

## Tareas Personales

Las tareas auto-asignadas (donde `created_by == assigned_to_user_id`) son **tareas personales**:
- No tienen `area_id`
- No aparecen en dashboards de área
- Solo el creador las puede ver
- Ni siquiera el SuperAdmin puede ver tareas personales de otros
- El creador puede eliminarlas

---

## Historial de Estados

Cada cambio de estado genera un registro en `task_status_history`:

```json
{
  "task_id": 1,
  "changed_by": 5,
  "user_id": 3,
  "from_status": "pending",
  "to_status": "in_progress",
  "note": "Inicio del trabajo",
  "created_at": "2026-04-15 10:30:00"
}
```

Esto proporciona trazabilidad completa de quién cambió qué y cuándo.
