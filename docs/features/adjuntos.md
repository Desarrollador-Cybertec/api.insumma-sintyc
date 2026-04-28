# Sistema de Adjuntos (Attachments)

## Dos Sistemas de Adjuntos

El proyecto tiene dos implementaciones de adjuntos:

| Sistema | Tabla | Almacenamiento | Uso |
|---------|-------|---------------|-----|
| v1 (legacy) | `task_attachments` | Disco local | `POST /tasks/{id}/attachments` |
| v2 (actual) | `attachments` | Supabase S3 | `POST /attachments` |

---

## v2 — Sistema Actual (Supabase S3)

### Pipeline de Procesamiento

```
1. Frontend sube archivo
   POST /attachments (multipart/form-data)
         │
2. Backend almacena temporalmente en disco local
   storage/app/tmp/{uuid}.{ext}
         │
3. Crea registro en BD con status: PENDING
         │
4. Despacha ProcessAttachmentJob (async)
         │
5. Job procesa el archivo:
   ├── Si es imagen >= 1MB:
   │   ├── Corrige orientación EXIF
   │   ├── Redimensiona a max 2048px ancho
   │   └── Convierte a WebP (calidad 80) o JPEG
   └── Si no es imagen o < 1MB:
       └── Sube el original sin cambios
         │
6. Sube a Supabase Storage (S3)
         │
7. Actualiza BD → status: READY + checksum SHA256
         │
8. Elimina archivo temporal local
```

### Estados de Procesamiento

| Estado | Descripción |
|--------|-------------|
| `pending` | Archivo recibido, esperando procesamiento |
| `processing` | Job en ejecución |
| `ready` | Procesado y disponible en S3 |
| `failed` | Error en procesamiento (ver metadata) |

### Rutas en S3

| Contexto | Ruta |
|----------|------|
| Tarea de área | `areas/{area_id}/tasks/{task_id}/{filename}` |
| Tarea sin área | `tasks/{task_id}/{filename}` |
| Documento de área | `areas/{area_id}/documents/{filename}` |
| Archivo personal | `users/{user_id}/private/{filename}` |

### Visibilidad

| Scope | Cuándo se asigna |
|-------|-----------------|
| `TASK` | Upload con `task_id` |
| `AREA` | Upload con `area_id` |
| `USER` | Upload sin contexto |

### URLs Firmadas

Los archivos en S3 no son públicos. Para acceder:

```
GET /attachments/{id}/signed-url
GET /attachments/{id}/signed-url?download=true
```

| Tipo | Duración | Uso |
|------|----------|-----|
| Lectura | 5 minutos | Vista previa en navegador |
| Descarga | 15 minutos | Descarga con header Content-Disposition |

### Autorización

| Rol | Ver | Eliminar |
|-----|-----|----------|
| Admin | Todos | Todos |
| Manager | De sus áreas + propios | De sus áreas + propios |
| Worker | Propios + de tareas asignadas | Solo propios |

---

## v1 — Sistema Legacy (Disco Local)

Almacena en `storage/app/tasks/{task_id}/`:

```
POST /tasks/{task}/attachments
Content-Type: multipart/form-data
Body: file, attachment_type (evidence|support|final_delivery)
```

### Tipos de Adjunto

| Tipo | Descripción |
|------|-------------|
| `evidence` | Evidencia de trabajo |
| `support` | Material de soporte |
| `final_delivery` | Entrega final |

---

## Procesamiento de Imágenes

### Configuración

| Parámetro | Valor |
|-----------|-------|
| Ancho máximo | 2048 px |
| Calidad WebP | 80% |
| Motor | GD Library |
| Umbral de procesamiento | >= 1 MB |

### Flujo de Imagen

```
Original JPEG/PNG (5 MB)
  │
  ├── Fix EXIF orientation (rotaciones 3, 6, 8)
  ├── Resize a max 2048px ancho (mantiene aspect ratio)
  ├── Intenta convertir a WebP (80% calidad)
  │   ├── Éxito → sube .webp
  │   └── Fallo → intenta JPEG
  │       ├── Éxito → sube .jpg
  │       └── Fallo → sube original sin cambios
  │
  └── Resultado: ~500 KB WebP
```

### Manejo de Errores

- Si falla la conversión de imagen → sube el original
- Si falla la subida a S3 → marca como `FAILED` con error en metadata
- Los archivos `FAILED` pueden reintentarse con: `php artisan attachments:retry-failed`
