# 📖 Documentación — TAPE API (S!NTyC)

**Sistema de Seguimiento de Tareas y Compromisos** — Backend API

> Reemplazo del sistema basado en Excel para la gestión organizacional de tareas, reuniones, áreas y equipos.

---

## Estructura de la Documentación

| Carpeta | Contenido |
|---------|-----------|
| [`arquitectura/`](arquitectura/) | Visión general del sistema, stack tecnológico, modelo de datos y despliegue |
| [`api/`](api/) | Referencia completa de endpoints, autenticación y manejo de errores |
| [`roles/`](roles/) | Guías por rol (Superadmin, Area Manager, Worker) |
| [`features/`](features/) | Funcionalidades principales: tareas, notificaciones, licencias, importación |
| [`frontend/`](frontend/) | Guías de integración para el frontend React |
| [`operaciones/`](operaciones/) | Despliegue, cron jobs, colas y troubleshooting |
| [`changelog/`](changelog/) | Historial de cambios del sistema |

---

## Inicio Rápido

```bash
# 1. Clonar e instalar
composer install
cp .env.example .env
php artisan key:generate

# 2. Base de datos
php artisan migrate
php artisan db:seed

# 3. Ejecutar en desarrollo
composer dev
# Equivale a: php artisan serve + queue:listen + vite (concurrente)
```

**URL base API:** `http://localhost:8000/api`
**Autenticación:** Bearer Token (Laravel Sanctum)

---

## Stack Tecnológico

| Capa | Tecnología |
|------|------------|
| Framework | Laravel 12 (PHP 8.4) |
| Base de Datos | MySQL(phpMyAdmin) |
| Autenticación | Laravel Sanctum (tokens API) |
| Almacenamiento | Supabase Storage (S3-compatible) |
| WebSocket | Laravel Reverb |
| Procesamiento de imágenes | Intervention Image (GD) |
| Cola de trabajos | Laravel Queue (database driver) |
| Frontend (separado) | React 19 + Vite |
| Hosting | Contabo VPS + cPanel |

---

## Arquitectura de Carpetas del Proyecto

```
app/
├── Console/Commands/     # 7 comandos artisan (cron, detección, reportes)
├── Enums/                # 10 enums (estados, roles, prioridades, tipos)
├── Events/               # 6 eventos de dominio (tareas)
├── Http/
│   ├── Controllers/      # 14 controladores
│   ├── Middleware/        # 1 middleware (CheckLicense)
│   ├── Requests/         # 22 form requests
│   └── Resources/        # 15 API resources
├── Jobs/                 # 1 job (procesamiento de archivos)
├── Listeners/            # 6 listeners de eventos
├── Mail/                 # 1 mailable (tareas externas)
├── Models/               # 16 modelos Eloquent
├── Notifications/        # 16 notificaciones
├── Policies/             # 7 políticas de autorización
├── Providers/            # Providers de Laravel
└── Services/             # 11 servicios de negocio

database/
├── migrations/           # 29 migraciones
└── seeders/              # 6 seeders

routes/
└── api.php               # Todas las rutas de la API

config/                   # 11 archivos de configuración
```

---

## Documentos Clave

- **Empezar aquí:** [Visión General del Sistema](arquitectura/sistema-overview.md)
- **Referencia API:** [Endpoints Completos](api/endpoints-referencia.md)
- **Modelo de datos:** [Base de Datos y Modelos](arquitectura/modelo-datos.md)
- **Servicios:** [Servicios de Negocio](arquitectura/servicios.md)
- **Autorización:** [Políticas y Permisos](arquitectura/politicas-autorizacion.md)
- **Tareas:** [Ciclo de Vida](features/ciclo-vida-tareas.md)
- **Reuniones:** [Sistema de Reuniones](features/reuniones.md)
- **Adjuntos:** [Sistema de Adjuntos (S3)](features/adjuntos.md)
- **Dashboards:** [Vistas de Dashboard](features/dashboards.md)
- **Notificaciones:** [Sistema de Notificaciones](features/notificaciones.md)
- **Licencias:** [Sistema de Licencias](features/licencias.md)
- **Importación:** [Importación CSV](features/importacion-csv.md)
- **Frontend:** [Guía de Integración](frontend/guia-integracion.md)
- **Despliegue:** [Guía de Producción](operaciones/despliegue.md)
- **Changelog:** [Historial de Cambios](changelog/CHANGELOG.md)
