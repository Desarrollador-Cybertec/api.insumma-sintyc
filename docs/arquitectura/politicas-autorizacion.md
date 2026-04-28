# Políticas de Autorización

Cada recurso tiene una Policy que define quién puede hacer qué.

---

## UserPolicy

| Acción | SuperAdmin | Gerente | Manager | Worker |
|--------|:----------:|:-------:|:-------:|:------:|
| Ver listado | ✅ | ✅ | ✅ | ❌ |
| Ver detalle | ✅ | ✅ (no SuperAdmin) | ✅ (su área) | ✅ (sí mismo) |
| Crear | ✅ | ✅ | ❌ | ❌ |
| Editar | ✅ | ✅ (no SuperAdmin) | ❌ | ✅ (sí mismo) |
| Cambiar rol | ✅ | ✅ (no SuperAdmin, no propio) | ❌ | ❌ |
| Cambiar contraseña | ✅ | ✅ (no SuperAdmin, no propia) | ❌ | ❌ |
| Eliminar | ✅ (no propio) | ❌ | ❌ | ❌ |

---

## AreaPolicy

| Acción | Admin | Manager de Área | Worker |
|--------|:-----:|:---------------:|:------:|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ (su área) | ✅ (su área) |
| Crear | ✅ | ❌ | ❌ |
| Editar | ✅ | ❌ | ❌ |
| Eliminar | ✅ | ❌ | ❌ |
| Asignar manager | ✅ | ❌ | ❌ |
| Reclamar worker | ✅ | ✅ (su área) | ❌ |
| Ver disponibles | ✅ | ✅ (su área) | ❌ |
| Desasignar miembro | ✅ | ✅ (su área) | ❌ |

---

## TaskPolicy

| Acción | Admin | Manager de Área | Responsable | Creador |
|--------|:-----:|:---------------:|:-----------:|:-------:|
| Ver | ✅* | ✅ (su área) | ✅ | ✅ (personal) |
| Crear | ✅ | ✅ | — | — |
| Editar | ✅* | ✅ (su área) | ❌ | ✅ (personal) |
| Reclamar | ✅* | ✅ | — | ✅ (manager) |
| Delegar | ✅* | ✅ | — | ✅ (manager) |
| Iniciar | ✅* | ✅ | ✅ | ✅ (externa) |
| Enviar revisión | ✅* | ✅ | ✅ | ✅ (externa) |
| Completar | ✅* | ✅ | ✅ | ✅ (externa) |
| Aprobar | ✅* | ✅ | ❌ | ❌ |
| Rechazar | ✅* | ✅ | ❌ | ❌ |
| Cancelar | ✅* | ✅ | ✅ | ✅ |
| Reabrir | ✅* | ✅ | ✅ | ✅ |
| Eliminar | ✅* | ❌ | ❌ | ✅ (personal) |
| Comentar | ✅* | ✅ | ✅ | ✅ |
| Adjuntar | ✅* | ✅ | ✅ | ✅ |

> *\* Excepto tareas personales ajenas (sin área, creadas por otro usuario)*

### Concepto: Tarea Personal Ajena

Una tarea es "personal ajena" si:
- No tiene `area_id` (no pertenece a ningún área)
- Fue creada por otro usuario

Ni siquiera el SuperAdmin puede ver/editar/eliminar tareas personales de otros usuarios.

---

## MeetingPolicy

| Acción | Admin | Manager de Área | Creador | Worker |
|--------|:-----:|:---------------:|:-------:|:------:|
| Ver listado | ✅ | ✅ | ✅ | ❌ |
| Ver detalle | ✅ | ✅ (su área) | ✅ | ❌ |
| Crear | ✅ | ✅ | — | ❌ |
| Editar | ✅ (no cerrada) | ❌ | ✅ (no cerrada) | ❌ |
| Eliminar | ✅ (SuperAdmin) | ❌ | ❌ | ❌ |
| Cerrar | ✅ (no cerrada) | ❌ | ✅ (no cerrada) | ❌ |

---

## AttachmentPolicy

Basada en `AttachmentAuthorizationService`:

| Acción | Admin | Manager | Worker |
|--------|:-----:|:-------:|:------:|
| Ver | ✅ todos | ✅ de sus áreas | ✅ propios + de tareas asignadas |
| Crear | ✅ | ✅ | ✅ |
| Eliminar | ✅ todos | ✅ de sus áreas + propios | ✅ solo propios |

---

## SystemSettingPolicy

| Acción | SuperAdmin | Otros |
|--------|:----------:|:-----:|
| Ver | ✅ | ❌ |
| Crear | ✅ | ❌ |
| Editar | ✅ | ❌ |
| Eliminar | ✅ | ❌ |

---

## MessageTemplatePolicy

| Acción | SuperAdmin | Otros |
|--------|:----------:|:-----:|
| Ver | ✅ | ❌ |
| Crear | ✅ | ❌ |
| Editar | ✅ | ❌ |
| Eliminar | ✅ | ❌ |
