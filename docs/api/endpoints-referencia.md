# Referencia Completa de Endpoints API

**Base URL:** `http://localhost:8000/api`
**Autenticación:** `Authorization: Bearer {token}` (Laravel Sanctum)
**Content-Type:** `application/json` (excepto uploads: `multipart/form-data`)

---

## Resumen de Endpoints

| Recurso | Endpoints | Autenticación |
|---------|-----------|---------------|
| [Auth](#autenticación) | 3 | Público (login) / Protegido |
| [Users](#usuarios) | 7 | Protegido (Admin/Manager) |
| [Areas](#áreas) | 10 | Protegido |
| [Meetings](#reuniones) | 7 | Protegido (Admin/Manager) |
| [Tasks](#tareas) | 17 | Protegido |
| [Dashboard](#dashboard) | 4 | Protegido |
| [Roles](#roles) | 2 | Protegido |
| [Settings](#configuración-del-sistema) | 4 | Protegido (SuperAdmin) |
| [Message Templates](#plantillas-de-mensajes) | 5 | Protegido (SuperAdmin) |
| [Automation](#automatización) | 4 | Protegido (Admin/Manager) |
| [Import](#importación) | 1 | Protegido (Admin) |
| [Attachments](#adjuntos-v2) | 5 | Protegido |
| [Notifications](#notificaciones) | 4 | Protegido |

---

## Autenticación

### `POST /login`
**Rate limit:** 5 intentos / minuto
**Body:**
```json
{ "email": "string", "password": "string" }
```
**Response 200:**
```json
{
  "user": { "id", "name", "email", "role", "active_areas" },
  "token": "string"
}
```
**Errores:** 422 (credenciales inválidas, usuario inactivo), 403 (licencia suspendida/expirada), 503 (licencia no disponible)

---

### `POST /logout`
Revoca el token actual.
**Response 200:** `{ "message": "Sesión cerrada correctamente." }`

---

### `GET /me`
Retorna el usuario autenticado con `role` y `activeAreas`.
**Response 200:** `{ "user": UserResource }`

---

## Usuarios

### `GET /users`
Lista usuarios con filtros y paginación.
**Query params:** `search`, `role` (slug), `active` (boolean), `exclude_area` (int)
**Paginación:** 20 por página
**Visibilidad:** Managers no ven SuperAdmin. Workers no tienen acceso.

---

### `POST /users`
Crea un usuario. Requiere cuota de licencia (`create_user`).
**Body:**
```json
{
  "name": "string",
  "email": "string",
  "password": "string",
  "password_confirmation": "string",
  "role_id": "int",
  "area_id": "int (opcional)"
}
```
**Response 201:** UserResource

---

### `GET /users/{user}`
Detalle del usuario con `role` y `activeAreas`.

---

### `PUT /users/{user}`
Actualiza nombre, email y estado activo.
**Body:** `{ "name": "string", "email": "string", "active": "boolean" }`

---

### `PATCH /users/{user}/role`
Cambia el rol del usuario. Si se degrada de manager a worker, se elimina como encargado de áreas.
**Body:** `{ "role_id": "int" }`

---

### `PATCH /users/{user}/password`
Actualiza la contraseña (Admin cambia a otros).
**Body:** `{ "password": "string", "password_confirmation": "string" }`
**Reglas:** Mínimo 8 caracteres, mayúsculas, minúsculas y números.

---

### `PATCH /users/{user}/toggle-active`
Activa/desactiva usuario. Al reactivar, verifica cuota de licencia.

---

## Áreas

### `GET /areas`
Lista áreas. Workers ven solo áreas donde son miembros o managers.
**Query params:** `active` (boolean)

---

### `POST /areas`
Crea un área. Requiere cuota de licencia (`create_area`).
**Body:** `{ "name": "string", "description": "string", "process_identifier": "string" }`

---

### `GET /areas/{area}`
Detalle del área con `manager`, `activeMembers` y conteo de workers.

---

### `PUT /areas/{area}`
Actualiza nombre, descripción, process_identifier, estado activo.

---

### `DELETE /areas/{area}`
Elimina el área. **Precondición:** No debe tener tareas asociadas.

---

### `PATCH /areas/{area}/manager`
Asigna un encargado al área.
**Body:** `{ "manager_user_id": "int" }`

---

### `POST /areas/claim-worker`
Reclama un trabajador para el área (lo asigna como miembro).
**Body:** `{ "user_id": "int", "area_id": "int" }`

---

### `GET /areas/{area}/available-workers`
Lista trabajadores disponibles (sin área asignada).
**Query params:** `search`

---

### `GET /areas/{area}/members`
Lista miembros activos del área.
**Query params:** `search`

---

### `DELETE /areas/{area}/members/{user}`
Desactiva un miembro del área (no lo elimina, marca `is_active = false`).

---

## Reuniones

### `GET /meetings`
Lista reuniones. No-admins ven solo las que crearon o de áreas que administran.
**Query params:** `area_id`, `classification`

---

### `POST /meetings`
Crea una reunión.
**Body:**
```json
{
  "title": "string",
  "meeting_date": "Y-m-d",
  "area_id": "int",
  "classification": "strategic|operational|follow_up|review|other",
  "notes": "string (opcional)"
}
```

---

### `GET /meetings/{meeting}`
Detalle con tareas asociadas.

---

### `PUT /meetings/{meeting}`
Actualiza datos. **No se puede editar si está cerrada.**

---

### `DELETE /meetings/{meeting}`
Solo SuperAdmin.

---

### `PATCH /meetings/{meeting}/close`
Cierra la reunión. Impide crear nuevas tareas en ella.

---

### `POST /meetings/{meeting}/tasks`
Creación masiva de tareas para la reunión.
**Body:**
```json
{
  "tasks": [
    {
      "title": "string",
      "description": "string",
      "assigned_to_user_id": "int",
      "assigned_to_area_id": "int",
      "priority": "low|medium|high|urgent",
      "due_date": "Y-m-d"
    }
  ]
}
```
**Error 422:** Si la reunión está cerrada.

---

## Tareas

### `GET /tasks`
Lista tareas con visibilidad por rol.
**Query params:** `status`, `priority`, `area_id`, `sort` (oldest, due_date, priority, latest)

**Visibilidad:**
- **SuperAdmin/Gerente:** Todas las tareas de la organización + tareas personales propias
- **Manager:** Tareas de áreas que administra (creadas por no-workers) + tareas personales
- **Worker:** Tareas asignadas + tareas personales

---

### `POST /tasks`
Crea una tarea.
**Body:**
```json
{
  "title": "string (requerido)",
  "description": "string",
  "area_id": "int",
  "assigned_to_user_id": "int",
  "assigned_to_area_id": "int",
  "external_email": "string",
  "external_name": "string",
  "priority": "low|medium|high|urgent",
  "start_date": "Y-m-d",
  "due_date": "Y-m-d",
  "meeting_id": "int",
  "requires_attachment": "boolean",
  "requires_completion_comment": "boolean",
  "requires_manager_approval": "boolean",
  "requires_due_date": "boolean",
  "requires_progress_report": "boolean"
}
```

---

### `GET /tasks/{task}`
Detalle completo con: creador, asignados, delegaciones, comentarios, adjuntos, historial de estados, actualizaciones.

---

### `PUT /tasks/{task}`
Actualiza campos editables (título, descripción, prioridad, requisitos).

---

### `DELETE /tasks/{task}`
Eliminación permanente (force delete). Solo Admin o creador de tarea personal.

---

### `POST /tasks/{task}/claim`
Reclamar una tarea pendiente de asignación.
**Precondición:** Estado debe ser `PENDING_ASSIGNMENT`.

---

### `POST /tasks/{task}/delegate`
Delegar tarea a otro usuario.
**Body:** `{ "to_user_id": "int", "note": "string (opcional)" }`
**Validación:** El destinatario debe pertenecer al área de la tarea.

---

### `POST /tasks/{task}/start`
Iniciar trabajo en la tarea.
**Body:** `{ "comment": "string (requerido, max 2000)" }`
**Transición:** PENDING → IN_PROGRESS

---

### `POST /tasks/{task}/submit-review`
Enviar a revisión del manager.
**Body:** `{ "comment": "string (requerido, max 2000)" }`
**Transición:** IN_PROGRESS → IN_REVIEW (si requiere aprobación) o → COMPLETED

---

### `POST /tasks/{task}/complete`
Completar tarea directamente (sin aprobación).
**Body:** `{ "comment": "string (requerido, max 2000)" }`
**Error:** Si la tarea requiere aprobación del manager.

---

### `POST /tasks/{task}/approve`
Aprobar tarea en revisión (solo Admin/Manager).
**Body:** `{ "note": "string (opcional)" }`
**Transición:** IN_REVIEW → COMPLETED

---

### `POST /tasks/{task}/reject`
Rechazar tarea en revisión (devuelve al responsable).
**Body:** `{ "note": "string (requerido)" }`
**Transición:** IN_REVIEW → REJECTED

---

### `POST /tasks/{task}/cancel`
Cancelar tarea activa.
**Body:** `{ "comment": "string (requerido, max 2000)" }`

---

### `POST /tasks/{task}/reopen`
Reabrir tarea completada o cancelada.
**Body:** `{ "comment": "string (requerido, max 2000)" }`

---

### `POST /tasks/{task}/comment`
Agregar comentario a una tarea.
**Body:** `{ "comment": "string (requerido)", "type": "comment|progress (opcional, default: comment)" }`

---

### `POST /tasks/{task}/attachments`
Subir archivo adjunto (almacenamiento local).
**Body (multipart):** `file` (archivo), `attachment_type` (evidence|support|final_delivery)

---

### `POST /tasks/{task}/updates`
Agregar actualización de avance.
**Body:**
```json
{
  "update_type": "progress|evidence|note",
  "comment": "string",
  "progress_percent": "int (0-100, opcional)"
}
```

---

## Dashboard

### `GET /dashboard/general`
Dashboard global (solo Admin).
**Response:** Tareas por estado, por área, vencidas, próximas, completadas este mes, tasa de completado, progreso global, tareas pendientes por usuario.

---

### `GET /dashboard/area/{area}`
Dashboard por área (Admin o manager del área).
**Response:** Métricas del área, tareas por estado, por responsable, pendientes de asignación.

---

### `GET /dashboard/me`
Dashboard personal (todos los usuarios).
**Response:** Tareas activas, vencidas, próximas, completadas, por estado, próximas tareas.

---

### `GET /dashboard/consolidated`
Reporte consolidado (solo Admin). Vista tipo Excel con todas las áreas.
**Response:** Resumen global + detalle por área con tasas de completado, tareas vencidas, días sin actividad.

---

## Roles

### `GET /roles`
Lista todos los roles con conteo de usuarios.

---

### `PATCH /roles/{role}/toggle-active`
Activa/desactiva un rol configurable (solo SuperAdmin).
**Roles configurables:** gerente, director, leader, coordinator, worker, analyst

---

## Configuración del Sistema

### `GET /settings`
Lista configuraciones agrupadas. **Solo SuperAdmin.**
**Query params:** `group`

---

### `POST /settings`
Crea una configuración.
**Body:** `{ "key": "string", "value": "string", "type": "string|boolean|integer|json", "group": "string", "description": "string" }`

---

### `PUT /settings`
Actualización masiva de configuraciones.
**Body:** `{ "settings": [{ "key": "string", "value": "mixed" }] }`

---

### `DELETE /settings/{systemSetting}`
Elimina una configuración.

---

## Plantillas de Mensajes

### `GET /message-templates`
Lista plantillas. **Solo SuperAdmin.**

### `POST /message-templates`
Crea plantilla.
**Body:** `{ "name": "string", "slug": "string", "subject": "string", "body": "string", "active": "boolean" }`

### `GET /message-templates/{messageTemplate}`
Detalle de plantilla.

### `PUT /message-templates/{messageTemplate}`
Actualiza plantilla.

### `DELETE /message-templates/{messageTemplate}`
Elimina plantilla.

---

## Automatización

### `POST /automation/detect-overdue`
Ejecuta detección manual de tareas vencidas.
- **SuperAdmin:** Alcance global
- **Manager:** Alcance de sus áreas

---

### `POST /automation/send-summary`
Envía resumen diario de tareas manualmente.

---

### `POST /automation/send-reminders`
Envía recordatorios de próximo vencimiento manualmente.

---

### `POST /automation/detect-inactivity`
Detecta tareas sin actividad manualmente.

---

## Importación

### `POST /import/tasks`
Importa tareas desde archivo CSV. **Solo Admin.**
**Body (multipart):** `file` (CSV, max 5MB)

**Columnas CSV:**
| Columna | Requerida | Descripción |
|---------|-----------|-------------|
| `titulo` | Sí | Título de la tarea |
| `descripcion` | No | Descripción |
| `responsable_email` | No | Email del responsable |
| `area` | No | Nombre del área (crea si no existe) |
| `prioridad` | No | baja/media/alta/urgente |
| `estado` | No | pendiente/en progreso/completada/cancelada |
| `fecha_inicio` | No | Formatos: Y-m-d, d/m/Y, m/d/Y, d-m-Y |
| `fecha_limite` | No | Formatos: Y-m-d, d/m/Y, m/d/Y, d-m-Y |

---

## Adjuntos v2 (Supabase S3)

### `POST /attachments`
Sube un archivo con procesamiento asíncrono.
**Body (multipart):** `file`, `task_id` (opcional), `area_id` (opcional)
**Response 201:** AttachmentResource con `processing_status: "pending"`

**Pipeline de procesamiento:**
1. Almacena temporalmente en disco local
2. Despacha `ProcessAttachmentJob`
3. El job procesa imágenes (resize, WebP, EXIF fix) y sube a S3
4. Actualiza estado a `ready`

---

### `GET /tasks/{task}/attachments-v2`
Lista adjuntos de una tarea (solo status `ready`).

---

### `GET /areas/{area}/attachments`
Lista adjuntos de un área (solo status `ready`).

---

### `GET /attachments/{attachment}/signed-url`
Genera URL firmada temporal para descargar/visualizar.
**Query params:** `download` (boolean, default: false)
**Response:** `{ "url": "string", "expires_at": "ISO timestamp" }`
**Duración:** 5 min (lectura), 15 min (descarga)

---

### `DELETE /attachments/{attachment}`
Elimina archivo de S3 y registro de BD.

---

## Notificaciones

### `GET /notifications`
Lista notificaciones del usuario autenticado.
**Paginación:** 20 por página, más recientes primero.

---

### `GET /notifications/unread-count`
Conteo de notificaciones no leídas.
**Response:** `{ "unread_count": 5 }`

---

### `PATCH /notifications/{id}/read`
Marca una notificación como leída.

---

### `POST /notifications/read-all`
Marca todas las notificaciones no leídas como leídas.

---

## Códigos de Error Comunes

| Código | Significado |
|--------|-------------|
| 200 | Éxito |
| 201 | Recurso creado |
| 204 | Sin contenido (eliminación exitosa) |
| 401 | No autenticado |
| 403 | No autorizado / Licencia suspendida o expirada |
| 404 | Recurso no encontrado |
| 422 | Error de validación |
| 429 | Rate limit excedido |
| 500 | Error interno del servidor |
| 503 | Sistema de licencias no disponible |
