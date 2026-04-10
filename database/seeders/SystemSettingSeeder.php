<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── notifications ──────────────────────────────────────────────
            [
                'key'         => 'emails_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Activar/desactivar envío de correos automáticos',
            ],
            [
                'key'         => 'daily_summary_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Activar/desactivar resumen diario de tareas',
            ],
            [
                'key'         => 'alert_days_before_due',
                'value'       => '3',
                'type'        => 'integer',
                'group'       => 'notifications',
                'description' => 'Días antes del vencimiento para enviar alertas',
            ],
            [
                'key'         => 'alert_on_due_date',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Enviar alerta el día de vencimiento',
            ],
            [
                'key'         => 'alert_overdue',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Enviar alerta cuando una tarea esté vencida',
            ],
            [
                'key'         => 'copy_to_manager',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Enviar copia de notificaciones al encargado de área',
            ],
            [
                'key'         => 'copy_to_superadmin',
                'value'       => '0',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Enviar copia de notificaciones al super administrador',
            ],
            [
                'key'         => 'broadcast_enabled',
                'value'       => '0',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Activar notificaciones en tiempo real (requiere Laravel Reverb + pusher/pusher-php-server)',
            ],

            // ── automation ─────────────────────────────────────────────────
            [
                'key'         => 'detect_overdue_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'automation',
                'description' => 'Activar detección automática de tareas vencidas',
            ],
            [
                'key'         => 'detect_overdue_time',
                'value'       => '06:00',
                'type'        => 'string',
                'group'       => 'automation',
                'description' => 'Hora de ejecución de detección de vencidas (HH:MM)',
            ],
            [
                'key'         => 'daily_summary_time',
                'value'       => '07:00',
                'type'        => 'string',
                'group'       => 'automation',
                'description' => 'Hora de envío del resumen diario (HH:MM)',
            ],
            [
                'key'         => 'send_reminders_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'automation',
                'description' => 'Activar envío automático de recordatorios',
            ],
            [
                'key'         => 'send_reminders_time',
                'value'       => '08:00',
                'type'        => 'string',
                'group'       => 'automation',
                'description' => 'Hora de envío de recordatorios (HH:MM)',
            ],
            [
                'key'         => 'inactivity_alert_enabled',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'automation',
                'description' => 'Activar alertas por inactividad (tareas sin avance)',
            ],
            [
                'key'         => 'inactivity_alert_days',
                'value'       => '7',
                'type'        => 'integer',
                'group'       => 'automation',
                'description' => 'Días sin avance para generar alerta de inactividad',
            ],
            [
                'key'         => 'inactivity_alert_time',
                'value'       => '09:00',
                'type'        => 'string',
                'group'       => 'automation',
                'description' => 'Hora de ejecución de detección de inactividad (HH:MM)',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value'       => $setting['value'],
                    'type'        => $setting['type'],
                    'group'       => $setting['group'],
                    'description' => $setting['description'],
                ],
            );
        }
    }
}
