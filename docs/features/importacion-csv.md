# Importación CSV de Tareas

## Endpoint

```
POST /api/import/tasks
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Acceso:** Solo Administradores (SuperAdmin, Gerente)
**Límite de archivo:** 5 MB máximo, formato CSV

---

## Formato del CSV

### Columnas

| Columna | Requerida | Descripción | Ejemplo |
|---------|-----------|-------------|---------|
| `titulo` | **Sí** | Título de la tarea | "Revisar informe" |
| `descripcion` | No | Descripción | "Revisar el informe mensual" |
| `responsable_email` | No | Email del responsable | "juan@empresa.com" |
| `area` | No | Nombre del área | "Recursos Humanos" |
| `prioridad` | No | Prioridad | "alta" |
| `estado` | No | Estado inicial | "pendiente" |
| `fecha_inicio` | No | Fecha de inicio | "2026-04-15" |
| `fecha_limite` | No | Fecha límite | "2026-05-01" |

### Mapeo de Prioridades

| Valor en CSV | Valor interno |
|-------------|---------------|
| baja, low | low |
| media, medium | medium |
| alta, high | high |
| urgente, critical | urgent |

### Mapeo de Estados

| Valor en CSV | Valor interno |
|-------------|---------------|
| pendiente, pending | pending |
| en progreso, en_progreso, in_progress | in_progress |
| completada, completed | completed |
| cancelada, cancelled | cancelled |
| borrador, draft | draft |
| vencida, overdue | overdue |

### Formatos de Fecha Aceptados

- `Y-m-d` → 2026-04-15
- `d/m/Y` → 15/04/2026
- `m/d/Y` → 04/15/2026
- `d-m-Y` → 15-04-2026

---

## Comportamiento

1. **Resolución de áreas:** Si el nombre del área no existe, se crea automáticamente
2. **Resolución de responsable:** Busca el usuario por email. Si no existe, ignora la asignación
3. **Valores por defecto:** Prioridad `medium`, estado `pending`
4. **Creador:** El usuario autenticado que ejecuta la importación
5. **Transacción:** Si ninguna fila se importa correctamente, se revierte todo

---

## Response

### Éxito (200)
```json
{
  "message": "Importación completada: 45 tarea(s) importadas.",
  "imported": 45,
  "errors": [
    "Fila 12: El título es obligatorio.",
    "Fila 28: Formato de fecha no reconocido."
  ]
}
```

### Error total (422)
```json
{
  "message": "No se pudo importar ninguna tarea.",
  "errors": ["Fila 1: ...", "Fila 2: ..."]
}
```

**Nota:** Se recopilan hasta 50 errores máximo.

---

## Ejemplo de CSV

```csv
titulo,descripcion,responsable_email,area,prioridad,estado,fecha_inicio,fecha_limite
Revisar informe mensual,Revisión del informe de ventas,ana@empresa.com,Ventas,alta,pendiente,2026-04-01,2026-04-30
Preparar presentación,,pedro@empresa.com,Marketing,media,,2026-04-15,2026-05-10
Actualizar base de datos,Migrar datos del sistema anterior,,TI,urgente,en progreso,,2026-04-20
```
