# Cambios frontend: notificaciones de tareas en creacion y edicion

## Objetivo

El backend de tareas quedo reducido a 3 grupos de notificacion para tarea:

1. `notify_on_assignment_start`
   - Cubre cuando la tarea se asigna y cuando se inicia.
2. `notify_on_review_completion`
   - Cubre cuando se envia a revision y cuando se completa/aprueba.
3. `notify_on_due_overdue`
   - Cubre cuando la tarea esta por vencer y cuando ya vencio.

Las demas notificaciones de tarea ya no deben mostrarse en frontend ni considerarse configurables desde la UI.

## Estado del backend

### Edicion

Se corrigio el problema por el cual en edicion de tarea los cambios de notificaciones no persistian para superadmin o gerente.

### Campos nuevos para usar en frontend

Usar estos 3 campos en el formulario:

- `notify_on_assignment_start`
- `notify_on_review_completion`
- `notify_on_due_overdue`

### Campos legacy que ya no debe usar el frontend

Estos campos siguen existiendo por compatibilidad interna, pero la UI ya no deberia leerlos ni enviarlos como fuente principal:

- `requires_progress_report`
- `requires_completion_notification`
- `notify_on_completion`
- `notify_on_due`
- `notify_on_overdue`

## Cambios en la vista de creacion de tarea

### Reemplazar la seccion de notificaciones actual por 3 opciones

Sugerencia de labels:

- `Al asignar o iniciar la tarea`
- `Al enviar a revision o completar la tarea`
- `Al vencer o estar por vencer`

### Payload para crear

Enviar solo los 3 campos nuevos junto con el resto del formulario.

Ejemplo:

```json
{
  "title": "Actualizar informe",
  "assigned_to_user_id": 12,
  "notify_on_assignment_start": true,
  "notify_on_review_completion": true,
  "notify_on_due_overdue": true
}
```

### Recomendacion UX en creacion

- Si no hay `due_date`, se puede mantener visible `notify_on_due_overdue`, pero con ayuda visual aclarando que solo aplica cuando exista fecha limite.
- Si quieren evitar confusion, deshabiliten temporalmente esa opcion hasta que el usuario cargue `due_date`.
- Si la tarea requiere aprobacion, `notify_on_review_completion` cubre tanto `revision` como `aprobacion/completado`.
- Si la tarea no requiere aprobacion, ese mismo flag cubre el completado directo.

## Cambios en la vista de edicion de tarea

### Carga inicial del formulario

Para edicion, leer estos campos desde la respuesta de tarea:

- `data.notify_on_assignment_start`
- `data.notify_on_review_completion`
- `data.notify_on_due_overdue`

Nota: en `show` y `update` la tarea llega dentro de `data`.

### Payload para editar

Ejemplo de `PATCH /api/tasks/{id}`:

```json
{
  "notify_on_assignment_start": false,
  "notify_on_review_completion": true,
  "notify_on_due_overdue": true
}
```

## Opciones que hay que quitar del frontend

Quitar cualquier toggle, checkbox o selector asociado a:

- comentarios
- adjuntos
- avances o actualizaciones
- delegacion
- rechazo
- cancelacion
- reapertura
- separados de `por vencer` y `vencida`
- separados de `revision` y `completado`

## Comportamiento backend ya aplicado

El backend ya quedo alineado para que solo existan estas notificaciones de tarea:

- asignacion / inicio
- revision / completado
- por vencer / vencida

Ya no se envian notificaciones de tarea para:

- comentarios
- adjuntos
- avances
- delegacion
- rechazo
- cancelacion
- reapertura

## Checklist de frontend

- Reemplazar la UI de notificaciones por solo 3 opciones.
- En creacion, enviar `notify_on_assignment_start`, `notify_on_review_completion`, `notify_on_due_overdue`.
- En edicion, cargar esos 3 valores desde `data`.
- Dejar de depender de campos legacy.
- Eliminar textos, iconos o ayudas de opciones antiguas.
