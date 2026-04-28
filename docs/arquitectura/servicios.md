# Servicios de Negocio

Capa de servicios que encapsula la lógica de negocio separada de los controladores HTTP.

---

## Servicios de Tareas

### TaskCreationService

Responsable de la creación de tareas con toda la lógica asociada.

**Método:** `create(array $data, User $creator): Task`

**Lógica de asignación:**

| Tipo | Condición | Estado Inicial | Responsable |
|------|-----------|----------------|-------------|
| **Auto-asignación** | Usuario se asigna a sí mismo | `PENDING` | El mismo usuario |
| **Directa a usuario** | `assigned_to_user_id` presente | `PENDING` | Usuario asignado |
| **A área** | `assigned_to_area_id` presente | `PENDING_ASSIGNMENT` | null (pendiente de claim) |
| **A manager** (no miembro) | Asignado es manager del área | `PENDING_ASSIGNMENT` | null (pendiente) |
| **Externa** | `external_email` presente | `PENDING` | null |

**Resolución de área:**
1. `area_id` explícito → usa ese valor
2. Sin `area_id` + asignado a usuario → usa el área activa del usuario
3. Auto-asignación → sin área (tarea personal)

**Efectos secundarios:**
- Crea registro en TaskStatusHistory
- Registra en ActivityLog
- Envía email externo si `external_email` presente
- Dispara notificaciones según tipo de asignación

---

### TaskStatusService

Gestiona las transiciones de estado con validación, historial y eventos.

**Métodos:**

| Método | Transición | Comentario requerido |
|--------|-----------|---------------------|
| `start(task, user, note)` | → IN_PROGRESS | Sí (tipo PROGRESS) |
| `cancel(task, user, reason)` | → CANCELLED | Sí (tipo CANCELLATION_NOTE) |
| `reopen(task, user, reason)` | CANCELLED → PENDING / COMPLETED → IN_PROGRESS | Sí (tipo REOPEN_NOTE) |
| `transition(task, status, user, note)` | Genérico | Opcional |

**Validaciones:**
- Verifica que la transición está permitida según `TaskStatusEnum::allowedTransitions()`
- Actualiza `progress_percent` al valor por defecto del nuevo estado
- Registra en TaskStatusHistory
- Dispara evento `TaskStatusChanged`

---

### TaskCompletionService

Gestiona el completado y aprobación de tareas con validación de requisitos.

**Métodos:**

| Método | Desde | Hacia | Uso |
|--------|-------|-------|-----|
| `submitForReview(task, user, note)` | IN_PROGRESS | IN_REVIEW o COMPLETED | Worker envía su trabajo |
| `complete(task, user, note)` | IN_PROGRESS | COMPLETED | Completado directo (sin aprobación) |
| `approve(task, approver, note)` | IN_REVIEW | COMPLETED | Manager aprueba |
| `reject(task, rejector, note)` | IN_REVIEW | REJECTED | Manager rechaza |

**Validación de requisitos (antes de completar):**
- `requires_attachment` → Debe tener al menos un adjunto
- `requires_completion_comment` → Debe tener al menos un comentario (completion_note, comment o progress)
- `requires_due_date` → Debe tener fecha límite establecida

---

### TaskDelegationService

Gestiona la delegación de tareas entre usuarios.

**Método:** `delegate(task, fromUser, toUserId, note): Task`

**Validaciones:**
- El usuario destino debe existir
- Si la tarea tiene área, el destino debe pertenecer a esa área
- El destino debe tener rol worker o manager level
- La tarea no puede estar COMPLETED ni CANCELLED

**Efectos:**
- Crea registro en TaskDelegation con áreas de origen y destino
- Cambia `current_responsible_user_id` al nuevo responsable
- Resetea el estado a PENDING
- Dispara evento `TaskDelegated`

---

## Servicios de Áreas

### AreaClaimService

Gestiona la asignación de trabajadores a áreas.

**Método:** `claimWorker(userId, areaId, claimedBy): AreaMember`

**Reglas:**
- El usuario debe tener rol worker o manager level
- Si el que reclama es SuperAdmin → puede reasignar (desactiva área anterior)
- Si es manager → no puede reasignar trabajadores ya asignados a otra área
- Un trabajador solo puede estar activo en un área a la vez

**Método:** `removeWorker(userId, areaId, removedBy): void`
- Marca `is_active = false` y `left_at = now()` en area_members

---

## Servicios de Archivos

### AttachmentUploadService

Pipeline de subida en 2 fases:

```
1. Upload → Almacena en disco local /tmp/{uuid}.{ext}
2. Crea registro Attachment (status: PENDING)
3. Despacha ProcessAttachmentJob (async)
```

**Visibilidad automática:**
- Con `task_id` → `VisibilityScope::TASK`
- Con `area_id` → `VisibilityScope::AREA`
- Sin contexto → `VisibilityScope::USER`

---

### AttachmentProcessingService

Procesamiento asíncrono ejecutado por `ProcessAttachmentJob`:

```
1. Marca como PROCESSING
2. Lee archivo temporal del disco local
3. Valida existencia y metadata
4. Si es imagen >= 1MB → procesa con GD:
   a. Corrige orientación EXIF (rotaciones 3, 6, 8)
   b. Redimensiona a máximo 2048px de ancho
   c. Convierte a WebP (calidad 80) o JPEG como fallback
5. Construye ruta final en S3
6. Sube a Supabase Storage
7. Actualiza BD → status: READY, checksum SHA256
8. Elimina archivo temporal
```

**Rutas en S3:**
| Contexto | Ruta |
|----------|------|
| Tarea de área | `areas/{area_id}/tasks/{task_id}/{filename}` |
| Tarea sin área | `tasks/{task_id}/{filename}` |
| Documento de área | `areas/{area_id}/documents/{filename}` |
| Archivo personal | `users/{user_id}/private/{filename}` |

---

### AttachmentUrlService

Genera URLs firmadas temporales para acceso a archivos en S3.

| Tipo | Duración | Headers |
|------|----------|---------|
| Lectura | 5 minutos | — |
| Descarga | 15 minutos | `Content-Disposition: attachment` |

---

### AttachmentAuthorizationService

Autorización de acceso a archivos basada en rol:

| Rol | Puede ver | Puede eliminar |
|-----|-----------|----------------|
| Admin | Todos los archivos | Todos los archivos |
| Manager | Archivos de sus áreas y tareas de sus áreas | Archivos de sus áreas + propios |
| Worker | Sus propios archivos + archivos de tareas asignadas | Solo sus propios archivos |

---

## Servicios de Licencia

### LicenseService

Comunicación con el Sistema de Gestión externo para control de cuotas.

**Principio:** FAIL CLOSED — Si el sistema no responde, la operación se bloquea.

**Métodos principales:**

| Método | Uso | Efecto |
|--------|-----|--------|
| `authorize(action, qty)` | Antes de crear usuario/área | Verifica cuota disponible |
| `checkSubscriptionActive()` | En login | Verifica suscripción activa |
| `reportUserActive()` | Tras crear/reactivar usuario | +1 usuario activo |
| `reportUserDeactivated()` | Tras desactivar usuario | -1 usuario activo |
| `getCurrentUsage()` | Monitoreo | Consulta sin bloquear |

**Timeout:** 10 segundos para todas las llamadas HTTP.

---

## Servicios de Notificaciones

### NotificationSettingsService

Centraliza la configuración de notificaciones leyendo de `system_settings`.

**Canales:**
- `database` — Siempre activo
- `mail` — Configurable (`emails_enabled`)
- `broadcast` — Configurable (`broadcast_enabled`)

**Feature toggles:**
- `daily_summary_enabled` — Resumen diario
- `detect_overdue_enabled` — Detección de vencidas
- `send_reminders_enabled` — Recordatorios
- `inactivity_alert_enabled` — Alertas de inactividad

**Templates:**
- Busca plantillas activas en `message_templates` por slug
- Renderiza placeholders `{variable}` con valores proporcionados
- Fallback a texto hardcodeado si no hay plantilla activa
