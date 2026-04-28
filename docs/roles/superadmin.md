# Rol: Super Administrador

## Descripción
Acceso completo a toda la plataforma. Gestiona usuarios, áreas, configuración del sistema y tiene visibilidad global de todas las tareas y reuniones.

---

## Endpoints Disponibles

### Autenticación
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/login` | Iniciar sesión |
| POST | `/logout` | Cerrar sesión |
| GET | `/me` | Datos del usuario actual |

### Usuarios (acceso completo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/users` | Listar todos los usuarios |
| POST | `/users` | Crear usuario (con validación de licencia) |
| GET | `/users/{id}` | Ver detalle de usuario |
| PUT | `/users/{id}` | Editar usuario |
| PATCH | `/users/{id}/role` | Cambiar rol |
| PATCH | `/users/{id}/password` | Cambiar contraseña de otro usuario |
| PATCH | `/users/{id}/toggle-active` | Activar/desactivar |

### Áreas (acceso completo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/areas` | Listar todas las áreas |
| POST | `/areas` | Crear área (con validación de licencia) |
| GET | `/areas/{id}` | Ver detalle |
| PUT | `/areas/{id}` | Editar área |
| DELETE | `/areas/{id}` | Eliminar área (sin tareas) |
| PATCH | `/areas/{id}/manager` | Asignar encargado |
| POST | `/areas/claim-worker` | Asignar trabajador (puede reasignar) |
| GET | `/areas/{id}/available-workers` | Trabajadores disponibles |
| GET | `/areas/{id}/members` | Miembros del área |
| DELETE | `/areas/{id}/members/{user}` | Desasignar miembro |

### Reuniones (acceso completo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/meetings` | Listar todas las reuniones |
| POST | `/meetings` | Crear reunión |
| GET | `/meetings/{id}` | Ver detalle |
| PUT | `/meetings/{id}` | Editar reunión |
| DELETE | `/meetings/{id}` | Eliminar reunión (solo SuperAdmin) |
| PATCH | `/meetings/{id}/close` | Cerrar reunión |
| POST | `/meetings/{id}/tasks` | Crear tareas masivas |

### Tareas (acceso completo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/tasks` | Listar todas las tareas de la organización |
| POST | `/tasks` | Crear tarea |
| GET | `/tasks/{id}` | Ver detalle completo |
| PUT | `/tasks/{id}` | Editar tarea |
| DELETE | `/tasks/{id}` | Eliminar tarea |
| POST | `/tasks/{id}/claim` | Reclamar tarea |
| POST | `/tasks/{id}/delegate` | Delegar tarea |
| POST | `/tasks/{id}/start` | Iniciar tarea |
| POST | `/tasks/{id}/submit-review` | Enviar a revisión |
| POST | `/tasks/{id}/complete` | Completar |
| POST | `/tasks/{id}/approve` | Aprobar |
| POST | `/tasks/{id}/reject` | Rechazar |
| POST | `/tasks/{id}/cancel` | Cancelar |
| POST | `/tasks/{id}/reopen` | Reabrir |
| POST | `/tasks/{id}/comment` | Comentar |
| POST | `/tasks/{id}/attachments` | Adjuntar archivo |
| POST | `/tasks/{id}/updates` | Agregar actualización |

### Dashboard (acceso completo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/dashboard/general` | Dashboard global |
| GET | `/dashboard/area/{area}` | Dashboard por área |
| GET | `/dashboard/me` | Dashboard personal |
| GET | `/dashboard/consolidated` | Reporte consolidado |

### Configuración (exclusivo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| GET | `/roles` | Listar roles |
| PATCH | `/roles/{id}/toggle-active` | Activar/desactivar rol |
| GET | `/settings` | Ver configuraciones |
| POST | `/settings` | Crear configuración |
| PUT | `/settings` | Actualizar configuraciones |
| DELETE | `/settings/{id}` | Eliminar configuración |
| GET | `/message-templates` | Ver plantillas |
| POST | `/message-templates` | Crear plantilla |
| PUT | `/message-templates/{id}` | Editar plantilla |
| DELETE | `/message-templates/{id}` | Eliminar plantilla |

### Automatización
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/automation/detect-overdue` | Detectar vencidas (global) |
| POST | `/automation/send-summary` | Enviar resumen diario (global) |
| POST | `/automation/send-reminders` | Enviar recordatorios (global) |
| POST | `/automation/detect-inactivity` | Detectar inactividad (global) |

### Importación (exclusivo)
| Método | Endpoint | Acción |
|--------|----------|--------|
| POST | `/import/tasks` | Importar tareas desde CSV |

---

## Restricciones

- **No puede** eliminar su propia cuenta
- **No puede** cambiar su propio rol
- **No puede** cambiar su propia contraseña por esta vía
- **No puede** ver tareas personales de otros usuarios (sin área)
- El Gerente **no puede** ver/editar/eliminar al SuperAdmin
