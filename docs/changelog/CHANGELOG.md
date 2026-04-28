# Changelog

Historial de cambios del sistema TAPE.

---

## 2026-04-09 — Íconos de Área

### Cambios
- Nuevo campo `icon_key` en áreas para personalización visual en el frontend

### Migraciones
- `2026_04_09_000000_add_icon_key_to_areas_table`

---

## 2026-04-07 — Cierre de Reuniones y Nuevos Roles

### Cierre de Reuniones
- Las reuniones ahora pueden cerrarse (`PATCH /meetings/{id}/close`)
- Una reunión cerrada no permite crear nuevas tareas
- Campos agregados: `is_closed` (boolean), `closed_at` (timestamp)

### Nuevos Roles
Sistema expandido de 3 a 8 roles:

| Rol | Nivel | Nuevo |
|-----|-------|-------|
| Super Administrador | Admin | — |
| Gerente | Admin | ✅ |
| Encargado de Área | Manager | — |
| Director | Manager | ✅ |
| Líder | Manager | ✅ |
| Coordinador | Manager | ✅ |
| Trabajador | Worker | — |
| Analista | Worker | ✅ |

### Roles Configurables
- Endpoint: `PATCH /api/roles/{role}/toggle-active` (solo SuperAdmin)
- Roles configurables: gerente, director, leader, coordinator, worker, analyst
- SuperAdmin y Area Manager siempre activos
- Listado: `GET /api/roles` (con conteo de usuarios)

### Membresía de Área
- Roles manager level y worker level pueden ser miembros de áreas
- Asignación directa al crear usuario con `area_id`
- SuperAdmin puede reasignar trabajadores entre áreas

### Migraciones
- `2026_04_07_100000_add_is_closed_to_meetings_table`
- `2026_04_07_100001_add_new_roles_and_is_active_to_roles_table`

---

## 2026-04-01 — Cambios de Admin y Comentarios

### Cambios de Administrador
- SuperAdmin puede cambiar la contraseña de otros usuarios (`PATCH /users/{id}/password`)
- SuperAdmin puede eliminar áreas (`DELETE /areas/{id}`)

### Comentarios Obligatorios
- Cancelar tarea requiere motivo obligatorio → tipo `cancellation_note`
- Reabrir tarea requiere motivo obligatorio → tipo `reopen_note`
- Nuevos tipos de comentario agregados al enum `CommentTypeEnum`

### Historial de Estados
- Los registros de `task_status_history` ahora incluyen `user_id` (responsable en ese momento)
- Permite ver quién era el responsable cuando se hizo el cambio

### Migraciones
- `2026_03_19_000000_add_user_id_to_task_status_history_table`

---

## 2026-03-30 — Adjuntos v2 (Supabase S3)

### Sistema de Adjuntos Renovado
- Nueva tabla `attachments` para almacenamiento en Supabase Storage (S3)
- Pipeline de procesamiento asíncrono con `ProcessAttachmentJob`
- Procesamiento de imágenes: redimensión, WebP, corrección EXIF
- URLs firmadas temporales (5 min lectura, 15 min descarga)
- Autorización basada en roles (`AttachmentAuthorizationService`)

### Nuevos Endpoints
- `POST /attachments` — Subir archivo
- `GET /tasks/{task}/attachments-v2` — Listar adjuntos de tarea
- `GET /areas/{area}/attachments` — Listar adjuntos de área
- `GET /attachments/{attachment}/signed-url` — Obtener URL temporal
- `DELETE /attachments/{attachment}` — Eliminar archivo

### Migraciones
- `2026_03_30_000001_create_attachments_table`

---

## 2026-03-25 — Sistema de Notificaciones

### Notificaciones Laravel
- 16 tipos de notificación implementados
- 3 canales: database, mail, broadcast
- Sistema basado en eventos y listeners
- Plantillas de mensajes configurables

### Nuevos Endpoints
- `GET /notifications` — Listar notificaciones
- `GET /notifications/unread-count` — Conteo de no leídas
- `PATCH /notifications/{id}/read` — Marcar como leída
- `POST /notifications/read-all` — Marcar todas como leídas

### Automatización
- `POST /automation/detect-overdue` — Detección de vencidas
- `POST /automation/send-summary` — Resumen diario
- `POST /automation/send-reminders` — Recordatorios
- `POST /automation/detect-inactivity` — Detección de inactividad

### Migraciones
- `2026_03_25_000001_create_notifications_table`

---

## 2026-03-17 — Índices y Tareas Externas

### Rendimiento
- Índices compuestos en tablas de tareas, historial y comentarios

### Tareas Externas
- Soporte para asignar tareas a emails externos (`external_email`, `external_name`)
- Envío de email al crear tarea externa (`ExternalTaskMail`)

### Migraciones
- `2026_03_17_000000_add_performance_indexes`
- `2026_03_17_000001_add_external_fields_to_tasks_table`

---

## 2026-03-16 — Release Inicial (MVP)

### Funcionalidades
- Autenticación con Laravel Sanctum
- CRUD de usuarios con roles (superadmin, area_manager, worker)
- CRUD de áreas con gestión de miembros
- CRUD de tareas con máquina de estados completa
- Sistema de reuniones con creación masiva de tareas
- Dashboard general, por área y personal
- Importación de tareas desde CSV
- Sistema de configuración del sistema
- Plantillas de mensajes
- Delegación de tareas
- Comentarios y adjuntos en tareas
- Actualizaciones de avance
- Historial de estados
- Registro de actividad (activity logs)

### Tablas Creadas
- users, roles, areas, area_members
- tasks, task_comments, task_attachments, task_delegations
- task_notifications, task_status_history, task_updates
- meetings, activity_logs
- system_settings, message_templates
- personal_access_tokens, cache, jobs
