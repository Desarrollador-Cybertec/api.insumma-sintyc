# Stack Tecnológico y Autenticación

## Stack

### Backend
| Componente | Tecnología | Versión |
|------------|-----------|---------|
| Framework | Laravel | 12.0 |
| Lenguaje | PHP | 8.4 |
| Base de datos | MySql | PHPMyAdmin |
| Autenticación | Laravel Sanctum | 4.0 |
| WebSocket | Laravel Reverb | — |
| Procesamiento de imágenes | Intervention Image | — |
| Almacenamiento de archivos | Supabase Storage (S3) | league/flysystem-aws-s3-v3 |
| Cola de trabajos | Laravel Queue | Database driver |

### Frontend (repositorio separado)
| Componente | Tecnología | Versión |
|------------|-----------|---------|
| Framework | React | 19.2.4 |
| Bundler | Vite | — |
| HTTP Client | Axios | — |

### Infraestructura
| Componente | Tecnología |
|------------|-----------|
| VPS | Contabo |
| Panel | cPanel |
| DNS/SSL | Cloudflare |
| Modelo | Single-tenant (una instancia por empresa) |

---

## Autenticación (Sanctum)

### Flujo de Login

```
POST /api/login
  ├── Validar credenciales (email + password)
  ├── Verificar usuario activo
  ├── Verificar licencia activa (LicenseService::checkSubscriptionActive)
  ├── Crear token Sanctum
  └── Retornar { user: UserResource, token: string }
```

### Uso del Token

```http
Authorization: Bearer {token}
```

Todas las rutas protegidas usan el middleware `auth:sanctum`.

### Logout

```
POST /api/logout
  └── Revocar token actual del usuario
```

---

## Sistema de Roles

### 8 Roles en 3 Niveles

```
Admin Level (acceso global)
├── SUPERADMIN  — "Super Administrador"
└── GERENTE     — "Gerente"

Manager Level (acceso por área)
├── AREA_MANAGER  — "Encargado de Área"
├── DIRECTOR      — "Director"
├── LEADER        — "Líder"
└── COORDINATOR   — "Coordinador"

Worker Level (acceso personal)
├── WORKER   — "Trabajador"
└── ANALYST  — "Analista"
```

### Roles Configurables

Los siguientes roles pueden activarse/desactivarse por el Superadmin:
- Gerente, Director, Líder, Coordinador, Trabajador, Analista

Los roles **SUPERADMIN** y **AREA_MANAGER** siempre están activos.

### Helpers del Modelo User

```php
$user->isSuperAdmin();      // ¿Es Super Admin?
$user->isGerente();          // ¿Es Gerente?
$user->isAreaManager();      // ¿Es Encargado de Área?
$user->isAdminLevel();       // ¿Es SuperAdmin o Gerente?
$user->isManagerLevel();     // ¿Es Area Manager, Director, Leader o Coordinator?
$user->isWorkerLevel();      // ¿Es Worker o Analyst?
$user->hasRole(RoleEnum::X); // ¿Tiene un rol específico?
$user->belongsToArea($id);   // ¿Pertenece a un área activa?
$user->isManagerOfArea($id); // ¿Es encargado de un área?
```

---

## Autorización por Capas

```
1. Middleware auth:sanctum     → ¿Está autenticado?
2. Middleware CheckLicense     → ¿Licencia válida? (solo ciertas rutas)
3. Policies                   → ¿Tiene permiso sobre el recurso?
4. Lógica en controladores    → Filtrado adicional por rol
5. Lógica en servicios        → Validaciones de negocio
```

### Policies Registradas

| Recurso | Policy | Alcance |
|---------|--------|---------|
| User | UserPolicy | Admin: CRUD global. Manager: ver su área. Worker: verse a sí mismo |
| Area | AreaPolicy | Admin: CRUD. Manager: ver y gestionar su área. Worker: ver su área |
| Task | TaskPolicy | Compleja (ver sección de tareas) |
| Meeting | MeetingPolicy | Admin/Manager: CRUD. Worker: sin acceso |
| Attachment | AttachmentPolicy | Basada en AttachmentAuthorizationService |
| SystemSetting | SystemSettingPolicy | Solo SuperAdmin |
| MessageTemplate | MessageTemplatePolicy | Solo SuperAdmin |

---

## Sistema de Licencias

### Arquitectura Fail-Closed

El `LicenseService` consulta un Sistema de Gestión externo para validar operaciones con cuota:

```
LicenseService → HTTP POST → Management System
  ├── create_user  → ¿Hay cuota para crear usuarios?
  ├── create_area  → ¿Hay cuota para crear áreas?
  └── login check  → ¿Suscripción activa?
```

**Principio:** Si el sistema de licencias no responde → la operación se bloquea.

### Estados de Licencia

| Estado | Efecto |
|--------|--------|
| `active` | Operación permitida |
| `suspended` | HTTP 403 — Licencia suspendida |
| `expired` | HTTP 403 — Licencia expirada |
| `unavailable` | HTTP 503 — Sistema no disponible |

### Endpoints Afectados

- `POST /api/login` — Verifica suscripción activa
- `POST /api/users` — Verifica cuota de usuarios
- `PATCH /api/users/{id}/toggle-active` — Verifica cuota al reactivar
- `POST /api/areas` — Verifica cuota de áreas

---

## Modelo Single-Tenant

Cada empresa tiene su propia instancia del backend con:
- Su propia base de datos PostgreSQL
- Su propio dominio/subdominio
- Su propia configuración de licencia

**Riesgos a bloquear:**
- No exponer endpoints de creación masiva sin protección de licencia
- No permitir acceso cruzado entre instancias
