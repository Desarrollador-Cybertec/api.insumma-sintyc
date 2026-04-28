# Modelo de Datos — Base de Datos y Modelos Eloquent

## Diagrama de Relaciones

```
┌──────────┐     ┌──────────────┐     ┌───────────┐
│  roles   │◄────│    users     │────▶│   areas   │
└──────────┘     └──────┬───────┘     └─────┬─────┘
                        │                   │
                 ┌──────┴───────┐    ┌──────┴───────┐
                 │ area_members │    │   meetings   │
                 └──────────────┘    └──────┬───────┘
                                           │
                                    ┌──────┴───────┐
                                    │    tasks     │
                                    └──────┬───────┘
                        ┌──────┬───────┬───┴───┬──────────┬──────────┐
                        ▼      ▼       ▼       ▼          ▼          ▼
                   comments  attach  deleg  status_hist  updates  attachments
                             ments   ations               (v2)
```

---

## Tablas y Modelos

### 1. `users` → `App\Models\User`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `name` | string | Nombre completo |
| `email` | string unique | Email de acceso |
| `password` | string (hashed) | Contraseña |
| `role_id` | FK → roles | Rol asignado |
| `active` | boolean | Estado activo/inactivo |
| `email_verified_at` | timestamp | — |
| `remember_token` | string | — |
| `created_at` / `updated_at` | timestamps | — |

**Traits:** `HasApiTokens`, `HasFactory`, `Notifiable`

**Relaciones:**
- `role()` → BelongsTo Role
- `areas()` → BelongsToMany Area (pivot: area_members)
- `activeAreas()` → BelongsToMany Area (filtrado is_active)
- `managedAreas()` → HasMany Area
- `createdTasks()` → HasMany Task
- `assignedTasks()` → HasMany Task
- `responsibleTasks()` → HasMany Task
- `taskComments()` → HasMany TaskComment
- `uploadedAttachments()` → HasMany TaskAttachment

---

### 2. `roles` → `App\Models\Role`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `name` | string | Nombre visible |
| `slug` | string unique | Identificador (superadmin, gerente, etc.) |
| `is_active` | boolean | ¿Rol disponible para asignar? |
| `created_at` / `updated_at` | timestamps | — |

**Relaciones:**
- `users()` → HasMany User

---

### 3. `areas` → `App\Models\Area`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `name` | string | Nombre del área |
| `description` | text nullable | Descripción |
| `icon_key` | string nullable | Ícono para el frontend |
| `process_identifier` | string nullable | Identificador de proceso |
| `manager_user_id` | FK → users | Encargado del área |
| `active` | boolean | Estado activo |
| `created_at` / `updated_at` | timestamps | — |

**Relaciones:**
- `manager()` → BelongsTo User
- `members()` → BelongsToMany User (pivot: area_members)
- `activeMembers()` → BelongsToMany User (filtrado is_active)
- `activeWorkers()` → BelongsToMany User (filtrado worker-level roles)
- `tasks()` → HasMany Task

---

### 4. `area_members` → `App\Models\AreaMember`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `area_id` | FK → areas | — |
| `user_id` | FK → users | — |
| `assigned_by` | FK → users nullable | Quién asignó |
| `claimed_by` | FK → users nullable | Quién reclamó |
| `joined_at` | datetime | Fecha de ingreso |
| `left_at` | datetime nullable | Fecha de baja |
| `is_active` | boolean | Membresía activa |
| `created_at` / `updated_at` | timestamps | — |

---

### 5. `tasks` → `App\Models\Task`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `title` | string | Título de la tarea |
| `description` | text nullable | Descripción |
| `created_by` | FK → users | Creador |
| `assigned_by` | FK → users nullable | Quién asignó |
| `assigned_to_user_id` | FK → users nullable | Asignado directo a usuario |
| `assigned_to_area_id` | FK → areas nullable | Asignado a área |
| `external_email` | string nullable | Email de responsable externo |
| `external_name` | string nullable | Nombre de responsable externo |
| `delegated_by` | FK → users nullable | Quién delegó |
| `current_responsible_user_id` | FK → users nullable | Responsable actual |
| `area_id` | FK → areas nullable | Área asociada |
| `priority` | enum(TaskPriority) | Prioridad |
| `status` | enum(TaskStatus) | Estado actual |
| `start_date` | date nullable | Fecha de inicio |
| `due_date` | date nullable | Fecha límite |
| `completed_at` | datetime nullable | Fecha de completado |
| `requires_attachment` | boolean | ¿Requiere adjunto? |
| `requires_completion_comment` | boolean | ¿Requiere comentario al cerrar? |
| `requires_manager_approval` | boolean | ¿Requiere aprobación? |
| `requires_completion_notification` | boolean | ¿Notificar al completar? |
| `requires_due_date` | boolean | ¿Requiere fecha límite? |
| `requires_progress_report` | boolean | ¿Requiere reportes de avance? |
| `notify_on_due` | boolean | Notificar al vencer |
| `notify_on_overdue` | boolean | Notificar si vence |
| `notify_on_completion` | boolean | Notificar al completar |
| `progress_percent` | integer | Porcentaje de avance (0-100) |
| `completion_notified_at` | datetime nullable | — |
| `closed_by` | FK → users nullable | Quién cerró |
| `cancelled_by` | FK → users nullable | Quién canceló |
| `meeting_id` | FK → meetings nullable | Reunión origen |
| `deleted_at` | timestamp nullable | Soft delete |
| `created_at` / `updated_at` | timestamps | — |

**Traits:** `SoftDeletes`

**Relaciones:**
- `creator()` → BelongsTo User
- `assigner()` → BelongsTo User
- `assignedUser()` → BelongsTo User
- `assignedArea()` → BelongsTo Area
- `delegator()` → BelongsTo User
- `currentResponsible()` → BelongsTo User
- `area()` → BelongsTo Area
- `closedByUser()` → BelongsTo User
- `cancelledByUser()` → BelongsTo User
- `comments()` → HasMany TaskComment
- `attachments()` → HasMany TaskAttachment
- `uploadedAttachments()` → HasMany Attachment
- `delegations()` → HasMany TaskDelegation
- `statusHistory()` → HasMany TaskStatusHistory
- `notifications()` → HasMany TaskNotification
- `updates()` → HasMany TaskUpdate
- `latestUpdate()` → HasOne TaskUpdate (más reciente)
- `meeting()` → BelongsTo Meeting

**Métodos:**
- `isAssignedToArea()` — ¿Asignada a un área (no a usuario)?
- `isOverdue()` — ¿Fecha vencida y no completada/cancelada?

---

### 6. `task_comments` → `App\Models\TaskComment`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `user_id` | FK → users | Autor |
| `comment` | text | Contenido |
| `type` | enum(CommentType) | Tipo de comentario |
| `created_at` / `updated_at` | timestamps | — |

**Tipos de comentario:** `comment`, `progress`, `completion_note`, `rejection_note`, `cancellation_note`, `reopen_note`, `system`

---

### 7. `task_attachments` → `App\Models\TaskAttachment`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `uploaded_by` | FK → users | — |
| `file_name` | string | Nombre original |
| `file_path` | string | Ruta en storage local |
| `mime_type` | string | Tipo MIME |
| `file_size` | integer | Tamaño en bytes |
| `attachment_type` | enum(AttachmentType) | evidence, support, final_delivery |
| `created_at` / `updated_at` | timestamps | — |

---

### 8. `attachments` → `App\Models\Attachment` (v2 — Supabase S3)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `uuid` | uuid unique | Identificador público |
| `task_id` | FK → tasks nullable | — |
| `area_id` | FK → areas nullable | — |
| `owner_user_id` | FK → users | Propietario |
| `uploaded_by` | FK → users | Quién subió |
| `disk` | string | Disco de storage (supabase) |
| `bucket` | string | Bucket S3 |
| `storage_path` | string nullable | Ruta en S3 |
| `original_name` | string | Nombre original |
| `stored_name` | string | Nombre almacenado |
| `mime_type` | string | Tipo MIME |
| `extension` | string | Extensión |
| `size_original` | integer | Tamaño original |
| `size_processed` | integer nullable | Tamaño procesado |
| `processing_status` | enum(ProcessingStatus) | pending → processing → ready / failed |
| `visibility_scope` | enum(VisibilityScope) | user, area, task, system |
| `checksum` | string nullable | SHA256 |
| `metadata` | json nullable | Datos adicionales |
| `processed_at` | datetime nullable | — |
| `uploaded_at` | datetime | — |
| `created_at` / `updated_at` | timestamps | — |

---

### 9. `task_delegations` → `App\Models\TaskDelegation`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `from_user_id` | FK → users | Delegador |
| `to_user_id` | FK → users | Destinatario |
| `from_area_id` | FK → areas nullable | Área origen |
| `to_area_id` | FK → areas nullable | Área destino |
| `note` | text nullable | Motivo |
| `delegated_at` | datetime | — |
| `created_at` / `updated_at` | timestamps | — |

---

### 10. `task_status_history` → `App\Models\TaskStatusHistory`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `changed_by` | FK → users | Quién cambió el estado |
| `user_id` | FK → users nullable | Responsable en ese momento |
| `from_status` | enum(TaskStatus) nullable | Estado anterior |
| `to_status` | enum(TaskStatus) | Estado nuevo |
| `note` | text nullable | Nota del cambio |
| `created_at` | datetime | — |

---

### 11. `task_updates` → `App\Models\TaskUpdate`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `user_id` | FK → users | Autor |
| `update_type` | enum(UpdateType) | progress, evidence, note |
| `comment` | text nullable | Contenido |
| `progress_percent` | integer nullable | Porcentaje reportado |
| `created_at` / `updated_at` | timestamps | — |

---

### 12. `task_notifications` → `App\Models\TaskNotification`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `task_id` | FK → tasks | — |
| `triggered_by` | FK → users | — |
| `notify_to_user_id` | FK → users | — |
| `channel` | enum(NotificationChannel) | database, mail, broadcast |
| `message` | text | — |
| `sent_at` | datetime nullable | — |
| `status` | string | — |
| `created_at` / `updated_at` | timestamps | — |

---

### 13. `meetings` → `App\Models\Meeting`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `title` | string | Título |
| `meeting_date` | date | Fecha de la reunión |
| `area_id` | FK → areas | Área asociada |
| `classification` | enum(MeetingClassification) | strategic, operational, follow_up, review, other |
| `notes` | text nullable | Notas |
| `is_closed` | boolean | ¿Cerrada? |
| `closed_at` | datetime nullable | Fecha de cierre |
| `created_by` | FK → users | Creador |
| `created_at` / `updated_at` | timestamps | — |

---

### 14. `activity_logs` → `App\Models\ActivityLog`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `user_id` | FK → users | Actor |
| `module` | string | Módulo (tasks, areas, meetings, etc.) |
| `action` | string | Acción realizada |
| `subject_type` | string nullable | Tipo del recurso |
| `subject_id` | bigint nullable | ID del recurso |
| `description` | text nullable | Descripción |
| `metadata` | json nullable | Datos adicionales |
| `created_at` | datetime | — |

---

### 15. `system_settings` → `App\Models\SystemSetting`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `key` | string unique | Clave de configuración |
| `value` | text | Valor |
| `type` | string | Tipo: string, boolean, integer, json |
| `group` | string | Grupo de configuración |
| `description` | text nullable | Descripción |
| `created_at` / `updated_at` | timestamps | — |

**Métodos estáticos:**
- `SystemSetting::getValue($key, $default)` — Obtener valor con cache
- `SystemSetting::setValue($key, $value)` — Establecer valor
- `SystemSetting::getGroup($group)` — Obtener grupo completo

---

### 16. `message_templates` → `App\Models\MessageTemplate`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `slug` | string unique | Identificador |
| `name` | string | Nombre descriptivo |
| `subject` | string | Asunto del mensaje |
| `body` | text | Cuerpo con {placeholders} |
| `active` | boolean | ¿Template activo? |
| `created_at` / `updated_at` | timestamps | — |

---

## Enums

### TaskStatusEnum
`draft`, `pending_assignment`, `pending`, `in_progress`, `in_review`, `completed`, `rejected`, `cancelled`, `overdue`

### TaskPriorityEnum
`low`, `medium`, `high`, `urgent`

### RoleEnum
`superadmin`, `gerente`, `area_manager`, `director`, `leader`, `coordinator`, `worker`, `analyst`

### CommentTypeEnum
`comment`, `progress`, `completion_note`, `rejection_note`, `cancellation_note`, `reopen_note`, `system`

### AttachmentTypeEnum
`evidence`, `support`, `final_delivery`

### UpdateTypeEnum
`progress`, `evidence`, `note`

### MeetingClassificationEnum
`strategic`, `operational`, `follow_up`, `review`, `other`

### ProcessingStatusEnum
`pending`, `processing`, `ready`, `failed`

### VisibilityScopeEnum
`user`, `area`, `task`, `system`

### NotificationChannelEnum
`database`, `mail`, `broadcast`

---

## Índices de Rendimiento

Se aplican índices adicionales en la migración `add_performance_indexes`:
- Índices compuestos en `tasks` para consultas frecuentes de estado + área + responsable
- Índices en tablas de historial y comentarios para consultas por `task_id`
