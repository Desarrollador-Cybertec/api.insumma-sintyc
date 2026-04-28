# Despliegue y Operaciones

## Infraestructura

| Componente | Tecnología | Detalle |
|------------|-----------|---------|
| VPS | Contabo | Servidor dedicado |
| Panel | cPanel | Gestión web |
| SSL/DNS | Cloudflare | Proxy y certificados |
| Base de datos | PostgreSQL | Supabase (remoto) |
| Almacenamiento | Supabase Storage | S3-compatible |
| Email | SMTP | Mail del VPS |

---

## Acceso SSH

```bash
ssh usuario@ip_servidor
cd /ruta/del/proyecto
```

---

## Despliegue

### Procedimiento estándar

```bash
# 1. Obtener últimos cambios
git pull origin main

# 2. Instalar dependencias
composer install --no-dev --optimize-autoloader

# 3. Ejecutar migraciones
php artisan migrate --force

# 4. Limpiar y cachear configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar queue worker
sudo systemctl restart tape-queue
# O si usa supervisor:
sudo supervisorctl restart tape-queue:*
```

### Rollback

```bash
# Revertir última migración
php artisan migrate:rollback --step=1

# Revertir código
git revert HEAD
# o
git checkout HEAD~1 -- .
```

---

## Cron Jobs

Agregar al crontab del servidor:

```cron
# Scheduler de Laravel (cada minuto)
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

### Tareas Programadas

| Comando | Frecuencia | Hora | Descripción |
|---------|-----------|------|-------------|
| `tasks:detect-overdue` | Cada hora | — | Marca tareas vencidas como OVERDUE |
| `tasks:send-daily-summary` | Diario | 7:00 AM | Envía resumen de tareas pendientes |
| `tasks:send-due-reminders` | Diario | 8:00 AM | Alerta de tareas próximas a vencer |
| `tasks:detect-inactivity` | Diario | 9:00 AM | Detecta tareas sin actividad |
| `license:sync-users` | Periódico | — | Sincroniza conteo de usuarios con licencia |
| `license:report-usage` | Periódico | — | Reporta uso de licencia |
| `attachments:retry-failed` | Periódico | — | Reintenta procesamiento de adjuntos fallidos |

---

## Queue Worker

### Opción 1: Systemd (recomendado)

```ini
# /etc/systemd/system/tape-queue.service
[Unit]
Description=TAPE Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/ruta/del/proyecto
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable tape-queue
sudo systemctl start tape-queue
sudo systemctl status tape-queue
```

### Opción 2: Cron watchdog

```cron
*/5 * * * * cd /ruta/del/proyecto && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

---

## Configuración de Email (Producción)

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.dominio.com
MAIL_PORT=465
MAIL_USERNAME=noreply@dominio.com
MAIL_PASSWORD=contraseña
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@dominio.com
MAIL_FROM_NAME="TAPE - Sistema de Tareas"
```

---

## Diagnósticos

### Verificar estado del sistema

```bash
# Estado de las colas
php artisan queue:monitor

# Listar jobs fallidos
php artisan queue:failed

# Reintentar job fallido
php artisan queue:retry {id}

# Reintentar todos los fallidos
php artisan queue:retry all

# Limpiar jobs fallidos
php artisan queue:flush
```

### Verificar crons

```bash
# Ver tareas programadas
php artisan schedule:list

# Ejecutar manualmente
php artisan tasks:detect-overdue
php artisan tasks:send-daily-summary
php artisan tasks:send-due-reminders
php artisan tasks:detect-inactivity
```

### Logs

```bash
# Ver logs recientes
tail -f storage/logs/laravel.log

# Limpiar logs
truncate -s 0 storage/logs/laravel.log
```

---

## Canales en Producción

| Canal | Estado | Notas |
|-------|--------|-------|
| Database | ✅ Activo | Siempre |
| Email (SMTP) | ✅ Activo | Configurar credenciales |
| Broadcast (WebSocket) | ⚠️ Opcional | Requiere Reverb/Pusher |

---

## Variables de Entorno Clave

```env
# Aplicación
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.dominio.com

# Base de datos (Supabase)
DB_CONNECTION=pgsql
DB_HOST=host.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=contraseña

# Supabase Storage (S3)
SUPABASE_URL=https://xxx.supabase.co
SUPABASE_KEY=eyJ...
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=attachments
AWS_ENDPOINT=https://xxx.supabase.co/storage/v1/s3

# Licencias
SUBSCRIPTION_SERVICE_URL=https://gestion.dominio.com/api
SUBSCRIPTION_API_KEY=clave-secreta

# Cola
QUEUE_CONNECTION=database

# Sanctum
SANCTUM_STATEFUL_DOMAINS=dominio.com
```

---

## Troubleshooting

### Emails no se envían
1. Verificar `MAIL_*` en `.env`
2. Verificar que el queue worker está corriendo
3. Verificar `emails_enabled` en `system_settings`
4. Revisar `php artisan queue:failed`

### Adjuntos no se procesan
1. Verificar queue worker
2. Verificar credenciales S3 en `.env`
3. Ejecutar `php artisan attachments:retry-failed`
4. Revisar logs: `storage/logs/laravel.log`

### Licencia bloqueada
1. Verificar `SUBSCRIPTION_SERVICE_URL` accesible
2. Verificar `SUBSCRIPTION_API_KEY` válida
3. Ejecutar `php artisan license:sync-users` para sincronizar

### Base de datos lenta
1. Verificar índices: migración `add_performance_indexes`
2. Verificar conexión a Supabase (latencia)
3. Considerar consultas N+1 con `php artisan telescope` (dev)
