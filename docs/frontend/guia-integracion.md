# Guía de Integración Frontend

## Configuración Base

### Axios Setup

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json' },
});

// Agregar token a cada request
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
```

---

## Autenticación

### Login

```javascript
const { data } = await api.post('/login', { email, password });
localStorage.setItem('token', data.token);
// data.user contiene { id, name, email, role, active_areas }
```

### Logout

```javascript
await api.post('/logout');
localStorage.removeItem('token');
```

---

## Manejo de Errores

### Interceptor recomendado

```javascript
api.interceptors.response.use(
  response => response,
  error => {
    const { status, data } = error.response || {};
    
    switch (status) {
      case 401:
        localStorage.removeItem('token');
        window.location.href = '/login';
        break;
      case 403:
        if (data?.error_type?.startsWith('license_')) {
          handleLicenseError(data);
        } else {
          showToast('No tienes permiso para esta acción');
        }
        break;
      case 422:
        // Errores de validación
        return Promise.reject(error);
      case 429:
        showToast('Demasiados intentos. Espera un momento.');
        break;
      case 503:
        showToast('Servicio no disponible. Intenta más tarde.');
        break;
    }
    return Promise.reject(error);
  }
);
```

---

## Zona Horaria

Todas las fechas del backend están en UTC. El frontend debe convertir a la zona horaria local.

### Bogotá (America/Bogota)

```javascript
function formatDate(dateString) {
  return new Date(dateString).toLocaleString('es-CO', {
    timeZone: 'America/Bogota',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

// Para campos date-only (sin hora):
function formatDateOnly(dateString) {
  return new Date(dateString + 'T00:00:00').toLocaleDateString('es-CO', {
    timeZone: 'America/Bogota',
  });
}
```

---

## Notificaciones (Polling)

### Implementación recomendada

```javascript
// Polling cada 30 segundos
useEffect(() => {
  const interval = setInterval(async () => {
    const { data } = await api.get('/notifications/unread-count');
    setUnreadCount(data.unread_count);
  }, 30000);
  
  return () => clearInterval(interval);
}, []);
```

### Marcar como leídas

```javascript
// Individual
await api.patch(`/notifications/${id}/read`);

// Todas
await api.post('/notifications/read-all');
```

---

## Subida de Archivos

### Adjuntos v2 (Supabase S3)

```javascript
async function uploadFile(file, taskId = null, areaId = null) {
  const formData = new FormData();
  formData.append('file', file);
  if (taskId) formData.append('task_id', taskId);
  if (areaId) formData.append('area_id', areaId);
  
  const { data } = await api.post('/attachments', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  
  // data.data.processing_status será "pending"
  // Hacer polling hasta que sea "ready"
  return data.data;
}
```

### Obtener URL de descarga

```javascript
async function getDownloadUrl(attachmentId) {
  const { data } = await api.get(
    `/attachments/${attachmentId}/signed-url?download=true`
  );
  // data.url es una URL temporal (15 min)
  // data.expires_at indica cuándo expira
  window.open(data.url, '_blank');
}
```

---

## Paginación

Todos los endpoints de listado retornan paginación de 20 elementos:

```javascript
const { data } = await api.get('/tasks', {
  params: { page: 1, status: 'in_progress', sort: 'due_date' }
});

// data.data = [TaskResource, ...]
// data.meta = { current_page, last_page, per_page, total }
// data.links = { first, last, prev, next }
```

---

## Visibilidad por Rol

### Menú lateral según rol

```javascript
const menuItems = {
  admin: ['dashboard', 'users', 'areas', 'meetings', 'tasks', 'settings', 'automation', 'import'],
  manager: ['dashboard', 'areas', 'meetings', 'tasks', 'automation'],
  worker: ['dashboard', 'tasks'],
};

function getMenu(user) {
  if (user.role.slug === 'superadmin' || user.role.slug === 'gerente') {
    return menuItems.admin;
  }
  if (['area_manager', 'director', 'leader', 'coordinator'].includes(user.role.slug)) {
    return menuItems.manager;
  }
  return menuItems.worker;
}
```

---

## Fechas de Tareas

- `start_date` y `due_date` solo se establecen al **crear** la tarea
- No se pueden editar después
- El frontend debe mostrar ambas fechas pero no permitir su edición en el formulario de actualización

---

## Historial de Estados

Al mostrar el historial de una tarea, incluir:

```javascript
// GET /tasks/{id} incluye statusHistory
task.status_history.map(entry => ({
  from: entry.from_status,
  to: entry.to_status,
  changedBy: entry.changed_by_user?.name,
  note: entry.note,
  date: formatDate(entry.created_at),
}));
```

---

## Comentarios con Motivo Obligatorio

Las siguientes acciones requieren un comentario obligatorio (máximo 2000 caracteres):

- **Iniciar tarea** → tipo `progress`
- **Enviar a revisión** → tipo `completion_note`
- **Completar** → tipo `completion_note`
- **Cancelar** → tipo `cancellation_note`
- **Reabrir** → tipo `reopen_note`
- **Rechazar** → tipo `rejection_note`

El frontend debe mostrar un modal o textarea obligatorio antes de ejecutar estas acciones.

---

## Trabajadores Disponibles

Al reclamar un trabajador para un área, el endpoint filtra automáticamente:
- Solo roles worker level (worker, analyst)
- Solo usuarios activos
- Solo usuarios sin área activa asignada

```javascript
const { data } = await api.get(`/areas/${areaId}/available-workers`, {
  params: { search: 'juan' }
});
```
