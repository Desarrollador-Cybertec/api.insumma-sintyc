# Sistema de Notificaciones

## Arquitectura

Las notificaciones se envían a través de 3 canales configurables:

| Canal | Descripción | Configurable |
|-------|-------------|-------------|
| `database` | Almacena en tabla `notifications` de Laravel | Siempre activo |
| `mail` | Envía email via SMTP | `emails_enabled` |
| `broadcast` | Tiempo real via WebSocket (Reverb/Pusher) | `broadcast_enabled` |

---

## 16 Tipos de Notificación

### Asignación y Delegación
| Clase | Cuándo se envía | Destinatario |
|-------|-----------------|-------------|
| `TaskAssignedNotification` | Tarea asignada a usuario | Usuario asignado |
| `TaskDelegatedNotification` | Tarea delegada | Nuevo responsable |

### Ciclo de Vida
| Clase | Cuándo se envía | Destinatario |
|-------|-----------------|-------------|
| `TaskStartedNotification` | Tarea iniciada | Manager del área |
| `TaskSubmittedForReviewNotification` | Enviada a revisión | Manager del área |
| `TaskCompletedNotification` | Tarea completada | Creador y manager |
| `TaskApprovedNotification` | Tarea aprobada | Responsable |
| `TaskRejectedNotification` | Tarea rechazada | Responsable |
| `TaskCancelledNotification` | Tarea cancelada | Responsable y creador |
| `TaskReopenedNotification` | Tarea reabierta | Responsable |

### Contenido
| Clase | Cuándo se envía | Destinatario |
|-------|-----------------|-------------|
| `TaskCommentAddedNotification` | Nuevo comentario | Involucrados en la tarea |
| `TaskAttachmentAddedNotification` | Nuevo adjunto | Involucrados en la tarea |
| `TaskUpdateAddedNotification` | Nueva actualización de avance | Manager del área |

### Alertas Automáticas
| Clase | Cuándo se envía | Destinatario |
|-------|-----------------|-------------|
| `TaskDueSoonNotification` | Próxima a vencer (N días) | Responsable |
| `TaskOverdueNotification` | Tarea vencida | Responsable |
| `TaskInactivityNotification` | Sin actividad por N días | Responsable |
| `DailyTaskSummaryNotification` | Resumen diario (cron) | Todos los usuarios con tareas |

---

## Eventos y Listeners

| Evento | Listener | Acción |
|--------|----------|--------|
| `TaskAssigned` | `SendTaskAssignedNotification` | Notifica al asignado |
| `TaskDelegated` | `SendTaskDelegatedNotification` | Notifica al nuevo responsable |
| `TaskStatusChanged` | `SendTaskStatusNotification` | Notifica según el cambio de estado |
| `TaskCommentAdded` | `SendTaskCommentNotification` | Notifica al responsable/creador |
| `TaskAttachmentAdded` | `SendTaskAttachmentNotification` | Notifica al responsable/creador |
| `TaskUpdateAdded` | `SendTaskUpdateNotification` | Notifica al manager |

---

## Plantillas de Mensajes

Las notificaciones usan plantillas almacenadas en `message_templates` con placeholders:

```
Slug: task_assigned
Subject: Se te ha asignado una nueva tarea
Body: Hola {user_name}, se te ha asignado la tarea "{task_title}" con prioridad {priority}.
```

**Variables disponibles:** `{user_name}`, `{task_title}`, `{task_id}`, `{priority}`, `{due_date}`, `{area_name}`, `{status}`, `{comment}`

Si no hay plantilla activa, se usa un texto fallback hardcodeado.

---

## Configuración

### Configuraciones de Notificaciones (system_settings)

| Clave | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `emails_enabled` | boolean | true | Activar envío de emails |
| `broadcast_enabled` | boolean | false | Activar broadcast WebSocket |
| `daily_summary_enabled` | boolean | true | Activar resumen diario |
| `detect_overdue_enabled` | boolean | true | Activar detección de vencidas |
| `send_reminders_enabled` | boolean | true | Activar recordatorios |
| `inactivity_alert_enabled` | boolean | true | Activar alertas de inactividad |
| `alert_days_before_due` | integer | 3 | Días antes del vencimiento para alertar |
| `inactivity_alert_days` | integer | 7 | Días sin actividad para alertar |
| `copy_to_manager` | boolean | true | Copiar notificaciones al manager |
| `copy_to_superadmin` | boolean | false | Copiar notificaciones al superadmin |

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
| `organizational` | Asignación, delegación, cambios de estado de área |
| `personal` | Vencimiento, inactividad, aprobación/rechazo personal |
| `summary` | Resumen diario |

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
