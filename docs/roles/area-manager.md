# Rol: Encargado de Área (Area Manager)

## Descripción
Gestiona un área específica: sus miembros, tareas y reuniones. Puede aprobar/rechazar tareas, delegar trabajo y ver dashboards de su área. Incluye también los roles Director, Líder y Coordinador.

---

## Endpoints Disponibles

### Autenticación
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/login` | Iniciar sesión |
| POST | `/logout` | Cerrar sesión |
| GET | `/me` | Datos del usuario actual |

### Usuarios (acceso limitado)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/users` | Listar usuarios (ve usuarios de sus áreas) |
| GET | `/users/{id}` | Ver detalle (solo usuarios de sus áreas o a sí mismo) |

### Áreas (acceso a sus áreas)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/areas` | Listar (ve áreas que administra y donde es miembro) |
| GET | `/areas/{id}` | Ver detalle de su área |
| POST | `/areas/claim-worker` | Reclamar trabajador para su área |
| GET | `/areas/{id}/available-workers` | Trabajadores disponibles |
| GET | `/areas/{id}/members` | Miembros de su área |
| DELETE | `/areas/{id}/members/{user}` | Desasignar miembro de su área |

### Reuniones (acceso a sus áreas)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/meetings` | Listar (ve las que creó + de áreas que administra) |
| POST | `/meetings` | Crear reunión |
| GET | `/meetings/{id}` | Ver detalle |
| PUT | `/meetings/{id}` | Editar (solo las que creó, si no están cerradas) |
| PATCH | `/meetings/{id}/close` | Cerrar reunión (solo las que creó) |
| POST | `/meetings/{id}/tasks` | Crear tareas masivas |

### Tareas (acceso por área)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/tasks` | Listar: tareas de sus áreas + personales |
| POST | `/tasks` | Crear tarea |
| GET | `/tasks/{id}` | Ver detalle (tareas de su área) |
| PUT | `/tasks/{id}` | Editar tarea de su área |
| POST | `/tasks/{id}/claim` | Reclamar tarea para asignar |
| POST | `/tasks/{id}/delegate` | Delegar tarea |
| POST | `/tasks/{id}/start` | Iniciar tarea |
| POST | `/tasks/{id}/submit-review` | Enviar a revisión |
| POST | `/tasks/{id}/complete` | Completar |
| POST | `/tasks/{id}/approve` | **Aprobar** tarea en revisión |
| POST | `/tasks/{id}/reject` | **Rechazar** tarea en revisión |
| POST | `/tasks/{id}/cancel` | Cancelar |
| POST | `/tasks/{id}/reopen` | Reabrir |
| POST | `/tasks/{id}/comment` | Comentar |
| POST | `/tasks/{id}/attachments` | Adjuntar archivo |
| POST | `/tasks/{id}/updates` | Agregar actualización |

### Dashboard
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/dashboard/area/{area}` | Dashboard de su área |
| GET | `/dashboard/me` | Dashboard personal |

### Automatización (alcance de área)
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/automation/detect-overdue` | Detectar vencidas (solo su área) |
| POST | `/automation/send-summary` | Enviar resumen (solo su área) |
| POST | `/automation/send-reminders` | Enviar recordatorios (solo su área) |
| POST | `/automation/detect-inactivity` | Detectar inactividad (solo su área) |

---

## Flujo de Estados para el Manager

```
PENDING_ASSIGNMENT → [Reclamar] → PENDING
PENDING → [Iniciar] → IN_PROGRESS
IN_PROGRESS → [Enviar a revisión] → IN_REVIEW
IN_REVIEW → [Aprobar] → COMPLETED
IN_REVIEW → [Rechazar] → REJECTED
REJECTED → [Iniciar] → IN_PROGRESS
Cualquier activo → [Cancelar] → CANCELLED
COMPLETED/CANCELLED → [Reabrir] → IN_PROGRESS/PENDING
```

### Botones visibles por estado

| Estado | Acciones disponibles |
|--------|---------------------|
| PENDING_ASSIGNMENT | Reclamar, Delegar, Cancelar |
| PENDING | Iniciar, Delegar, Cancelar |
| IN_PROGRESS | Enviar a Revisión, Cancelar, Delegar |
| IN_REVIEW | Aprobar, Rechazar, Cancelar |
| REJECTED | Iniciar, Cancelar |
| COMPLETED | Reabrir |
| CANCELLED | Reabrir |
| OVERDUE | Iniciar, Cancelar |

---

## Restricciones

- **No puede** crear usuarios ni áreas
- **No puede** ver dashboards de otras áreas
- **No puede** eliminar reuniones (solo SuperAdmin)
- **No puede** acceder a configuración del sistema
- **No puede** importar tareas desde CSV
- **No puede** reasignar trabajadores entre áreas (solo SuperAdmin)
- **No puede** ver tareas personales de otros usuarios
