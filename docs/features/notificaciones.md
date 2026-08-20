# Sistema de Notificaciones

## Arquitectura

Las notificaciones se envían a través de 3 canales configurables:

| Canal | Descripción | Configurable |
|-------|-------------|-------------|
| `database` | Almacena en tabla `notifications` de Laravel | Siempre activo |
| `mail` | Envía email via SMTP | `emails_enabled` |
| `broadcast` | Tiempo real via WebSocket (Reverb/Pusher) | `broadcast_enabled` |

Todas las notificaciones son disparadas por una **acción real de un usuario** sobre una tarea (asignación o cambio de estado). No existen correos periódicos ni recordatorios programados: el sistema ya no ejecuta ningún cron de notificaciones.

---

## Las 4 Notificaciones Activas

| Clase | Cuándo se envía | Destinatario |
|-------|-----------------|-------------|
| `TaskAssignedNotification` | Se asigna una tarea a un usuario | Usuario asignado (y manager del área si `copy_to_manager` está activo) |
| `TaskSubmittedForReviewNotification` | El responsable envía la tarea a revisión | Quien debe aprobarla: `delegated_by` → `assigned_by` → `created_by` (en ese orden de prioridad) |
| `TaskApprovedNotification` | El aprobador marca la tarea como completada (requería aprobación) | Responsable de la tarea |
| `TaskCompletedNotification` | El propio responsable completa la tarea sin requerir aprobación | Manager del área (tarea organizacional) o creador (tarea personal, sin área) |

Todas implementan `ShouldQueue` y usan `NotificationSettingsService::resolveChannels()` para decidir por qué canales enviarse.

---

## Eventos y Listeners

| Evento | Listener | Acción |
|--------|----------|--------|
| `TaskAssigned` | `App\Listeners\SendTaskAssignedNotification` | Notifica al usuario asignado (y opcionalmente al manager del área) con `TaskAssignedNotification`. No notifica autoasignación. |
| `TaskStatusChanged` | `App\Listeners\SendTaskStatusNotification` | Según el estado destino: `in_review` → `TaskSubmittedForReviewNotification`; `completed` → `TaskApprovedNotification` (si lo completó alguien distinto al responsable) o `TaskCompletedNotification` (si el propio responsable la autocompletó). |

Ambos listeners implementan `ShouldQueue` con `$afterCommit = true`, para que un fallo de envío de correo nunca revierta la transacción de la tarea.

Estos son los **únicos** listeners que disparan notificaciones automáticas del sistema. No hay comandos programados (`Schedule`) ni endpoints manuales de disparo — toda notificación nace de una acción real sobre una tarea.

---

## Plantillas de Mensajes

Las notificaciones usan plantillas almacenadas en `message_templates` con placeholders:

```
Slug: task_assigned
Subject: Se te ha asignado una nueva tarea
Body: Hola {user_name}, se te ha asignado la tarea "{task_title}" con prioridad {priority}.
```

Slugs relevantes: `new_assignment`, `task_submitted_review`, `task_approved`, `task_completed`.

**Variables disponibles:** `{user_name}`, `{task_title}`, `{task_id}`, `{priority}`, `{due_date}`, `{area_name}`, `{status}`, `{comment}`

Si no hay plantilla activa, se usa un texto fallback hardcodeado.

---

## Configuración

### Configuraciones de Notificaciones (system_settings)

| Clave | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `emails_enabled` | boolean | true | Activar envío de emails |
| `broadcast_enabled` | boolean | false | Activar broadcast WebSocket |
| `copy_to_manager` | boolean | true | Copiar notificaciones al manager |
| `copy_to_superadmin` | boolean | false | Copiar notificaciones al superadmin |

Las claves asociadas a los correos periódicos retirados (resumen diario, recordatorio de vencimiento, y las de detección de vencidas/inactividad retiradas previamente) están en `SystemSetting::RETIRED_KEYS` y ya no aparecen en la API de settings, aunque puedan seguir existiendo como filas históricas en la base de datos.

---

## API de Notificaciones

### Listar notificaciones
```
GET /api/notifications
Response: { data: [...], meta: { current_page, last_page, per_page, total } }
```

### Conteo de no leídas
```
GET /api/notifications/unread-count
Response: { unread_count: 5 }
```

### Marcar como leída
```
PATCH /api/notifications/{id}/read
Response: { message: "Notificación marcada como leída." }
```

### Marcar todas como leídas
```
POST /api/notifications/read-all
Response: { message: "Todas las notificaciones marcadas como leídas." }
```

---

## Categorías de Notificación

| Categoría | Tipos |
|-----------|-------|
| `organizational` | Asignación, cambios de estado de tareas de área |
| `personal` | Asignación, aprobación/finalización de tareas sin área |

---

## Producción

### SMTP (Contabo)
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.dominio.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@dominio.com
```

### Cola
Las notificaciones se procesan a través del queue worker para no bloquear las respuestas HTTP.

### Polling (Frontend)
Intervalo recomendado: **30 segundos** consultando `GET /notifications/unread-count`.
