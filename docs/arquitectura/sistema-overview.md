# Visión General del Sistema — TAPE

## ¿Qué es TAPE?

**TAPE** (Task & Process Engine) es un sistema de gestión de tareas y compromisos que reemplaza el modelo basado en hojas de cálculo Excel utilizado previamente por la organización. Permite asignar, dar seguimiento y reportar tareas originadas en reuniones de trabajo, con control de roles, notificaciones automáticas y dashboards en tiempo real.

---

## Problema que Resuelve

El modelo anterior basado en Excel presentaba:

- **Falta de individualidad:** Todas las tareas se mezclaban en una sola hoja sin separación por área o responsable
- **Sin trazabilidad:** No había historial de cambios ni registro de quién hizo qué
- **Sin automatización:** Las alertas de vencimiento y reportes se hacían manualmente
- **Sin dashboards:** La información consolidada requería procesamiento manual
- **Problemas de concurrencia:** Múltiples personas editando el mismo archivo simultáneamente

---

## Entidades Principales

### Usuarios y Roles

El sistema tiene **8 roles** organizados en **3 niveles jerárquicos:**

| Nivel | Roles | Alcance |
|-------|-------|---------|
| **Admin** | Super Administrador, Gerente | Acceso global a toda la organización |
| **Manager** | Encargado de Área, Director, Líder, Coordinador | Acceso a sus áreas asignadas |
| **Worker** | Trabajador, Analista | Acceso solo a sus tareas y área |

### Áreas

Unidades organizacionales que agrupan usuarios y tareas. Cada área tiene un **encargado** (manager) y **miembros** activos.

### Tareas

Unidad central del sistema. Cada tarea tiene:
- Creador, asignado y responsable actual
- Área asociada (opcional para tareas personales)
- Estado, prioridad y fechas
- Requisitos configurables (adjuntos, comentarios, aprobación)
- Historial completo de cambios de estado

### Reuniones

Agrupan tareas creadas durante sesiones de trabajo. Tienen clasificación (estratégica, operativa, seguimiento, revisión) y pueden cerrarse para impedir nuevas tareas.

### Notificaciones

Sistema de 3 canales (base de datos, email, broadcast) con 16 tipos de notificación automática.

---

## Flujo de Trabajo Principal

```
Reunión → Crear Tareas → Asignar → Reclamar → Iniciar → Completar/Revisar → Aprobar → Cerrar
```

### Máquina de Estados de Tareas

```
                    ┌──────────────┐
                    │    DRAFT     │
                    └──────┬───────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
    ┌─────────────────┐        ┌──────────────┐
    │ PENDING_ASSIGN  │        │   PENDING    │
    └────────┬────────┘        └──────┬───────┘
             │ claim                  │
             └────────┬───────────────┘
                      ▼
             ┌──────────────┐
             │ IN_PROGRESS  │◄─────────┐
             └──────┬───────┘          │
                    │                  │ reject
          ┌─────────┴─────────┐        │
          ▼                   ▼        │
   ┌────────────┐    ┌──────────────┐  │
   │ COMPLETED  │    │  IN_REVIEW   │──┘
   └────────────┘    └──────┬───────┘
                            │ approve
                            ▼
                     ┌────────────┐
                     │ COMPLETED  │
                     └────────────┘

Estados terminales: COMPLETED, CANCELLED
Cualquier estado activo → CANCELLED (con motivo obligatorio)
COMPLETED/CANCELLED → Reabrir (con motivo obligatorio)
IN_PROGRESS → OVERDUE (detección automática)
```

### Progreso por Estado

| Estado | Progreso |
|--------|----------|
| Draft / Pendiente Asignación / Pendiente / Cancelada | 0% |
| En Progreso / Rechazada / Vencida | 25% |
| En Revisión | 75% |
| Completada | 100% |

---

## Tipos de Asignación de Tareas

1. **Directa a usuario:** Se asigna a un trabajador específico → Estado: `PENDING`
2. **A área:** Se asigna al área, un miembro debe reclamarla → Estado: `PENDING_ASSIGNMENT`
3. **A manager (no miembro del área):** Similar a área, queda pendiente de asignación
4. **Externa:** Se envía por email a alguien externo → Estado: `PENDING`
5. **Personal:** Auto-asignación, sin área → Invisible en dashboards de área

---

## Arquitectura Técnica

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│   Frontend   │────▶│  Laravel API  │────▶│  PostgreSQL  │
│  React + Vite│◀────│  (Sanctum)    │◀────│  (Supabase)  │
└─────────────┘     └──────┬───────┘     └──────────────┘
                           │
                    ┌──────┴───────┐
                    │   Supabase   │
                    │   Storage    │
                    │   (S3)       │
                    └──────────────┘
```

### Capas de la Aplicación

```
Routes (api.php)
  └── Controllers (validación, respuestas HTTP)
        └── Services (lógica de negocio)
              └── Models (Eloquent ORM)
                    └── Policies (autorización)
```

---

## Seguridad y Autorización

- **Autenticación:** Laravel Sanctum (tokens API por sesión)
- **Autorización:** Policies por recurso + verificación de roles en controladores
- **Licenciamiento:** Middleware `CheckLicense` + `LicenseService` (fail-closed)
- **Rate Limiting:** Login throttled a 5 intentos por minuto
- **Archivos:** URLs firmadas temporales (5 min lectura, 15 min descarga)

---

## Automatizaciones (Cron)

| Comando | Frecuencia | Descripción |
|---------|------------|-------------|
| `tasks:detect-overdue` | Cada hora | Detecta tareas vencidas |
| `tasks:send-daily-summary` | Diario (7:00 AM) | Resumen de tareas por usuario |
| `tasks:send-due-reminders` | Diario (8:00 AM) | Alertas de próximo vencimiento |
| `tasks:detect-inactivity` | Diario (9:00 AM) | Detecta tareas sin actividad |

---

## Configuración del Sistema

El sistema tiene configuraciones dinámicas almacenadas en `system_settings`:

| Grupo | Configuraciones |
|-------|----------------|
| **notifications** | `emails_enabled`, `broadcast_enabled`, `daily_summary_enabled`, `copy_to_manager`, `copy_to_superadmin` |
| **alerts** | `detect_overdue_enabled`, `send_reminders_enabled`, `inactivity_alert_enabled`, `alert_days_before_due`, `inactivity_alert_days` |
