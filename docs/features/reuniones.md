# Reuniones (Meetings)

## Concepto

Las reuniones son contenedores para la creación masiva de tareas. Simulan la dinámica de una reunión presencial donde se acuerdan compromisos y se asignan responsables.

---

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/meetings` | Listar reuniones (filtros: classification, area, cerrada) |
| `POST` | `/meetings` | Crear reunión |
| `GET` | `/meetings/{id}` | Ver detalle con tareas |
| `PUT` | `/meetings/{id}` | Editar reunión |
| `DELETE` | `/meetings/{id}` | Eliminar reunión (solo SuperAdmin) |
| `POST` | `/meetings/{id}/tasks/batch` | Crear tareas masivamente |
| `PATCH` | `/meetings/{id}/close` | Cerrar reunión |

---

## Clasificaciones

| Clasificación | Descripción |
|---------------|-------------|
| `weekly` | Reunión semanal |
| `monthly` | Reunión mensual |
| `directive` | Reunión directiva |
| `follow_up` | Reunión de seguimiento |
| `other` | Otra |

---

## Creación de Reunión

```json
POST /meetings
{
  "title": "Reunión semanal Ventas",
  "description": "Revisión de pendientes de la semana",
  "area_id": 1,
  "classification": "weekly",
  "meeting_date": "2026-04-15"
}
```

> `area_id` es opcional. Si no se envía, es una reunión general.

---

## Creación Masiva de Tareas

```json
POST /meetings/{id}/tasks/batch
{
  "tasks": [
    {
      "title": "Preparar reporte mensual",
      "description": "Incluir métricas de Q1",
      "assigned_to": 5,
      "area_id": 1,
      "due_date": "2026-04-20",
      "priority": "high"
    },
    {
      "title": "Actualizar inventario",
      "assigned_to": 8,
      "area_id": 1,
      "due_date": "2026-04-25",
      "priority": "medium"
    }
  ]
}
```

### Reglas

- Máximo **50 tareas** por batch
- Cada tarea se crea con `meeting_id` referenciando la reunión
- Las tareas heredan el `area_id` de la reunión si no se especifica
- Se ejecuta en transacción — si una falla, ninguna se crea
- Usa `TaskCreationService` internamente (misma lógica que creación individual)

---

## Cierre de Reunión

```json
PATCH /meetings/{id}/close
```

### Efectos del Cierre

- Campo `is_closed` → `true`
- Campo `closed_at` → timestamp actual
- **No se pueden crear nuevas tareas** en la reunión
- Las tareas existentes siguen su flujo normal
- No se puede reabrir

### Permisos para Cerrar

- SuperAdmin
- Creador de la reunión

---

## Permisos

| Acción | SuperAdmin | Gerente | Area Manager | Worker |
|--------|:----------:|:-------:|:------------:|:------:|
| Ver listado | ✅ | ✅ | ✅ | ❌ |
| Ver detalle | ✅ | ✅ | ✅ (su área) | ❌ |
| Crear | ✅ | ✅ | ✅ | ❌ |
| Editar | ✅ (no cerrada) | ❌ | ❌ | ❌ |
| Eliminar | ✅ | ❌ | ❌ | ❌ |
| Cerrar | ✅ | ❌ | ✅ (creador, no cerrada) | ❌ |
| Crear tareas | ✅ | ✅ | ✅ | ❌ |

---

## Estructura de Datos

```sql
meetings
├── id (PK)
├── title (string)
├── description (text, nullable)
├── area_id (FK → areas, nullable)
├── created_by (FK → users)
├── classification (enum)
├── meeting_date (date)
├── is_closed (boolean, default false)
├── closed_at (timestamp, nullable)
├── created_at
└── updated_at
```
