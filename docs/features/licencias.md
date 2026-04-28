# Sistema de Licencias

## Arquitectura

El `LicenseService` se comunica con un Sistema de Gestión externo para validar cuotas y estado de suscripción.

```
Backend TAPE ──HTTP POST──▶ Management System
                           ├── Verifica cuota
                           ├── Verifica estado
                           └── Retorna autorización
```

**Principio FAIL CLOSED:** Si el Sistema de Gestión no responde → la operación se bloquea (HTTP 503).

---

## Estados de Licencia

| Estado | Efecto en la API | Código HTTP |
|--------|-----------------|-------------|
| `active` | Operación permitida | 200/201 |
| `suspended` | Todas las operaciones con cuota bloqueadas | 403 |
| `expired` | Todas las operaciones con cuota bloqueadas | 403 |
| `unavailable` | Sistema de licencias caído | 503 |

---

## Endpoints Afectados

### Operaciones con verificación de cuota

| Endpoint | Acción de licencia | Descripción |
|----------|-------------------|-------------|
| `POST /api/login` | `checkSubscriptionActive()` | Verifica suscripción activa |
| `POST /api/users` | `authorize('create_user', 1)` | Verifica cuota de usuarios |
| `PATCH /api/users/{id}/toggle-active` (reactivar) | `authorize('create_user', 1)` | Verifica cuota al reactivar |
| `POST /api/areas` | `authorize('create_area', 1)` | Verifica cuota de áreas |

### Operaciones con reporte de uso

| Endpoint | Reporte | Descripción |
|----------|---------|-------------|
| `POST /api/users` (crear) | `reportUserActive()` | +1 usuario activo |
| `PATCH /toggle-active` (activar) | `reportUserActive()` | +1 usuario activo |
| `PATCH /toggle-active` (desactivar) | `reportUserDeactivated()` | -1 usuario activo |

### Endpoints NO afectados

Todas las demás operaciones (CRUD de tareas, reuniones, comentarios, adjuntos, etc.) funcionan normalmente sin verificación de licencia.

---

## Errores de Licencia

### Formato del error

```json
{
  "message": "Descripción del error",
  "error_type": "license_denied|license_expired|license_suspended|license_unavailable"
}
```

### Tipos de error

| error_type | Código | Cuándo ocurre |
|------------|--------|---------------|
| `license_denied` | 403 | Cuota agotada (ej: máximo de usuarios alcanzado) |
| `license_expired` | 403 | Suscripción expirada |
| `license_suspended` | 403 | Suscripción suspendida |
| `license_unavailable` | 503 | Sistema de gestión no disponible |

---

## Excepciones PHP

| Excepción | Cuándo se lanza |
|-----------|----------------|
| `LicenseDeniedException` | Cuota agotada |
| `LicenseExpiredException` | Suscripción expirada |
| `LicenseSuspendedException` | Suscripción suspendida |
| `LicenseSystemUnavailableException` | Timeout o error de conexión |

---

## Integración Frontend

### Interceptor HTTP recomendado

```javascript
axios.interceptors.response.use(
  response => response,
  error => {
    const { status, data } = error.response;
    
    if (data?.error_type?.startsWith('license_')) {
      switch (data.error_type) {
        case 'license_denied':
          showModal('Cuota agotada', data.message);
          break;
        case 'license_expired':
          showModal('Licencia expirada', data.message);
          redirectToRenewal();
          break;
        case 'license_suspended':
          showModal('Licencia suspendida', data.message);
          break;
        case 'license_unavailable':
          showToast('Sistema de licencias no disponible');
          break;
      }
    }
    return Promise.reject(error);
  }
);
```

### Reglas para el Frontend

1. **NUNCA** ocultar botones de crear usuarios/áreas preventivamente
2. **SIEMPRE** dejar que el backend valide y manejar el error
3. **NO** cachear el estado de la licencia por períodos largos
4. Mostrar modales informativos, no errores técnicos
