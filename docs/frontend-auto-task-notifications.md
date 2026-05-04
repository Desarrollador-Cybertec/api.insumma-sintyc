# Notificaciones automáticas en auto-tareas

## Contexto

Cuando un usuario crea una tarea y se la asigna a sí mismo (auto-asignación), el backend ahora activa automáticamente los recordatorios de vencimiento. El frontend **no necesita cambiar el flujo de creación**, pero debe presentar correctamente las notificaciones que llegan.

---

## Comportamiento del backend

### Al crear la tarea

Si `assigned_to_user_id === auth.user.id` y se envía `due_date`, el backend activa internamente:

```json
"notify_on_due": true,
"notify_on_overdue": true
```

El frontend puede seguir enviando estos campos explícitamente si quiere sobrescribir el comportamiento (por ejemplo, si el usuario los desmarca manualmente).

### Cuándo llegan las notificaciones (scheduler diario)

| Momento | Tipo | Mensaje |
|---|---|---|
| 3 días antes del vencimiento | `task_due_soon` | "La tarea X vence en 3 días." |
| 2 días antes | `task_due_soon` | "La tarea X vence en 2 días." |
| 1 día antes | `task_due_soon` | "La tarea X vence en 1 día." |
| El mismo día de vencimiento | `task_due_soon` | "La tarea X vence hoy." |
| Día 1 vencida | `task_overdue` | "La tarea X está vencida por 1 día." |
| Día 4 vencida | `task_overdue` | "La tarea X está vencida por 4 días." |
| Día 7, 10, 13... | `task_overdue` | Cada 3 días mientras siga vencida |

---

## Estructura de las notificaciones (canal `database`)

Las notificaciones llegan al endpoint estándar de notificaciones. El campo `data` tiene esta forma:

### `task_due_soon`

```json
{
  "type": "task_due_soon",
  "category": "personal",
  "task_id": 42,
  "task_title": "Revisar contrato",
  "days_remaining": 2,
  "due_date": "2026-05-01",
  "message": "La tarea \"Revisar contrato\" vence en 2 días."
}
```

- `category`: `"personal"` si la tarea no tiene área, `"organizational"` si tiene.
- `days_remaining`: `0` significa que vence hoy.

### `task_overdue`

```json
{
  "type": "task_overdue",
  "category": "personal",
  "task_id": 42,
  "task_title": "Revisar contrato",
  "days_overdue": 4,
  "due_date": "2026-04-25",
  "message": "La tarea \"Revisar contrato\" está vencida por 4 días."
}
```

---

## Recomendaciones para el frontend

### Íconos / colores sugeridos

| Tipo | Color | Ícono sugerido |
|---|---|---|
| `task_due_soon` con `days_remaining >= 2` | Amarillo / warning | `clock` |
| `task_due_soon` con `days_remaining <= 1` | Naranja / urgente | `clock-alert` |
| `task_overdue` | Rojo | `alert-circle` |

### Mostrar `days_remaining === 0`

Cuando `days_remaining` es `0`, el mensaje ya dice "vence hoy". No calcular diferencia de fechas en el frontend para este caso.

### Navegación al hacer clic

Al hacer clic en cualquiera de estas notificaciones, navegar a la vista de detalle de la tarea usando `task_id`.

```
/tasks/{task_id}
```

### Formulario de creación de tarea

- Si el usuario es el mismo que el asignado y se incluye `due_date`, los checkboxes de `notify_on_due` y `notify_on_overdue` pueden mostrarse **pre-marcados** como sugerencia visual (el backend los activa de todas formas si no se envían).
- Si el usuario los desmarca, enviar explícitamente `"notify_on_due": false` para que el backend respete la preferencia.

---

## Sin cambios requeridos en el frontend

- El endpoint de creación de tareas (`POST /tasks`) no cambia.
- El modelo de notificación en el canal `database` no cambia.
- La auto-asignación sin `due_date` **no activa** ningún recordatorio (no hay fecha que monitorear).
