# Rol: Trabajador (Worker / Analyst)

## Descripción
Ejecuta tareas asignadas. Puede crear tareas personales, reportar avances, agregar comentarios y adjuntos. Visibilidad limitada a sus propias tareas y su área.

---

## Endpoints Disponibles

### Autenticación
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/login` | Iniciar sesión |
| POST | `/logout` | Cerrar sesión |
| GET | `/me` | Datos del usuario actual |

### Áreas (lectura limitada)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/areas` | Listar (solo áreas donde es miembro o manager) |
| GET | `/areas/{id}` | Ver detalle (solo su área) |

### Tareas (acceso personal)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/tasks` | Listar: tareas asignadas + personales |
| POST | `/tasks` | Crear tarea personal (auto-asignación) |
| GET | `/tasks/{id}` | Ver detalle (solo tareas donde es responsable) |
| POST | `/tasks/{id}/start` | Iniciar tarea asignada |
| POST | `/tasks/{id}/submit-review` | Enviar a revisión |
| POST | `/tasks/{id}/complete` | Completar (si no requiere aprobación) |
| POST | `/tasks/{id}/cancel` | Cancelar (si es responsable o creador) |
| POST | `/tasks/{id}/comment` | Agregar comentario |
| POST | `/tasks/{id}/attachments` | Adjuntar archivo |
| POST | `/tasks/{id}/updates` | Reportar avance |

### Dashboard
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/dashboard/me` | Dashboard personal |

### Notificaciones
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/notifications` | Listar notificaciones |
| GET | `/notifications/unread-count` | Conteo de no leídas |
| PATCH | `/notifications/{id}/read` | Marcar como leída |
| POST | `/notifications/read-all` | Marcar todas como leídas |

---

## Flujo de Trabajo Típico

```
1. Recibe notificación de tarea asignada
2. Ve la tarea en su dashboard (GET /dashboard/me)
3. Inicia la tarea (POST /tasks/{id}/start) con comentario
4. Reporta avances (POST /tasks/{id}/updates)
5. Adjunta evidencia (POST /tasks/{id}/attachments)
6. Envía a revisión (POST /tasks/{id}/submit-review) con comentario
7. Si rechazada → corrige y vuelve a enviar
8. Si aprobada → tarea completada
```

---

## Tareas Personales

El trabajador puede crear tareas auto-asignadas:

```json
POST /tasks
{
  "title": "Mi tarea personal",
  "assigned_to_user_id": "{mi_user_id}"
}
```

Estas tareas:
- **No tienen área asociada** (no aparecen en dashboards de área)
- **Solo las ve el creador**
- **No requieren aprobación del manager**
- El creador puede eliminarlas

---

## Restricciones

- **No puede** ver listado de usuarios
- **No puede** crear áreas ni usuarios
- **No puede** ver reuniones
- **No puede** delegar tareas
- **No puede** aprobar ni rechazar tareas
- **No puede** ver dashboards de área o generales
- **No puede** acceder a configuración del sistema
- **No puede** ejecutar automatizaciones
- **No puede** importar tareas desde CSV
- **No puede** ver tareas de otros usuarios
