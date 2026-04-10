<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedMessageTemplates();
    }

    private function seedSettings(): void
    {
        $settings = [
            // Notifications group
            [
                'key' => 'emails_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Activar/desactivar envío de correos automáticos',
            ],
            [
                'key' => 'daily_summary_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Activar/desactivar resumen diario de tareas',
            ],
            [
                'key' => 'alert_days_before_due',
                'value' => '3',
                'type' => 'integer',
                'group' => 'notifications',
                'description' => 'Días antes del vencimiento para enviar alertas',
            ],
            [
                'key' => 'alert_on_due_date',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enviar alerta el día de vencimiento',
            ],
            [
                'key' => 'alert_overdue',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enviar alerta cuando una tarea esté vencida',
            ],
            [
                'key' => 'copy_to_manager',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enviar copia de notificaciones al encargado de área',
            ],
            [
                'key' => 'copy_to_superadmin',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enviar copia de notificaciones al super administrador',
            ],
            [
                'key' => 'broadcast_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Activar notificaciones en tiempo real (requiere Laravel Reverb + pusher/pusher-php-server)',
            ],

            // Automation group
            [
                'key' => 'detect_overdue_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'automation',
                'description' => 'Activar detección automática de tareas vencidas',
            ],
            [
                'key' => 'detect_overdue_time',
                'value' => '06:00',
                'type' => 'string',
                'group' => 'automation',
                'description' => 'Hora de ejecución de detección de vencidas (HH:MM)',
            ],
            [
                'key' => 'daily_summary_time',
                'value' => '07:00',
                'type' => 'string',
                'group' => 'automation',
                'description' => 'Hora de envío del resumen diario (HH:MM)',
            ],
            [
                'key' => 'send_reminders_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'automation',
                'description' => 'Activar envío automático de recordatorios',
            ],
            [
                'key' => 'send_reminders_time',
                'value' => '08:00',
                'type' => 'string',
                'group' => 'automation',
                'description' => 'Hora de envío de recordatorios (HH:MM)',
            ],

            // Inactivity alerts
            [
                'key' => 'inactivity_alert_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'automation',
                'description' => 'Activar alertas por inactividad (tareas sin avance)',
            ],
            [
                'key' => 'inactivity_alert_days',
                'value' => '7',
                'type' => 'integer',
                'group' => 'automation',
                'description' => 'Días sin avance para generar alerta de inactividad',
            ],
            [
                'key' => 'inactivity_alert_time',
                'value' => '09:00',
                'type' => 'string',
                'group' => 'automation',
                'description' => 'Hora de ejecución de detección de inactividad (HH:MM)',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    private function seedMessageTemplates(): void
    {
        $templates = [
            [
                'slug' => 'new_assignment',
                'name' => 'Nueva asignación de tarea',
                'subject' => 'Se te ha asignado una nueva tarea: {task_title}',
                'body' => "Se te ha asignado la tarea \"{task_title}\".\n\nPrioridad: {priority}\nFecha límite: {due_date}\n\nProcura realizarla antes de la fecha límite. Por favor revisa los detalles en la plataforma.",
            ],
            [
                'slug' => 'task_reminder',
                'name' => 'Recordatorio de tarea próxima a vencer',
                'subject' => 'Recordatorio: La tarea "{task_title}" vence pronto',
                'body' => "La tarea \"{task_title}\" vence en {days_remaining} día(s).\n\nFecha límite: {due_date}\n\nAsegúrate de completarla a tiempo.",
            ],
            [
                'slug' => 'task_overdue',
                'name' => 'Tarea vencida',
                'subject' => 'Alerta: La tarea "{task_title}" está vencida',
                'body' => "La tarea \"{task_title}\" está vencida desde hace {days_overdue} día(s).\n\nFecha límite original: {due_date}\n\nPor favor actualiza el estado o contacta a tu encargado.",
            ],
            [
                'slug' => 'task_delegated',
                'name' => 'Tarea delegada',
                'subject' => 'Tarea delegada: {task_title}',
                'body' => "Se te ha delegado la tarea \"{task_title}\" por {delegated_by}.\n\nPrioridad: {priority}\nFecha límite: {due_date}",
            ],
            [
                'slug' => 'task_approved',
                'name' => 'Tarea aprobada',
                'subject' => 'Tu tarea "{task_title}" ha sido aprobada',
                'body' => "La tarea \"{task_title}\" ha sido aprobada.\n\n¡Buen trabajo!",
            ],
            [
                'slug' => 'task_rejected',
                'name' => 'Tarea rechazada',
                'subject' => 'Tu tarea "{task_title}" necesita correcciones',
                'body' => "La tarea \"{task_title}\" ha sido rechazada y requiere correcciones.\n\nMotivo: {rejection_reason}\n\nPor favor revisa y vuelve a enviar.",
            ],
            [
                'slug' => 'daily_summary',
                'name' => 'Resumen diario de tareas',
                'subject' => 'Resumen diario de tareas - {date}',
                'body' => "{summary_content}",
            ],
            [
                'slug' => 'inactivity_alert',
                'name' => 'Alerta por inactividad',
                'subject' => 'Alerta: Tareas sin avance desde hace {inactivity_days} días',
                'body' => "Tienes tareas sin reportar avance en los últimos {inactivity_days} días:\n\n{task_list}\n\nPor favor actualiza el estado de estas tareas.",
            ],
            [
                'slug' => 'task_cancelled',
                'name' => 'Tarea cancelada',
                'subject' => 'Tarea cancelada: {task_title}',
                'body' => "La tarea \"{task_title}\" ha sido cancelada por {cancelled_by}.\n\nMotivo: {note}",
            ],
            [
                'slug' => 'task_completed',
                'name' => 'Tarea completada',
                'subject' => 'Tarea completada: {task_title}',
                'body' => "{completed_by} completó la tarea \"{task_title}\".\n\n¡Buen trabajo al equipo!",
            ],
            [
                'slug' => 'task_submitted_review',
                'name' => 'Tarea enviada a revisión',
                'subject' => 'Revisión requerida: {task_title}',
                'body' => "{submitted_by} envió la tarea \"{task_title}\" a revisión.\n\nRequiere tu aprobación en la plataforma.",
            ],
            [
                'slug' => 'task_reopened',
                'name' => 'Tarea reabierta',
                'subject' => 'Tarea reabierta: {task_title}',
                'body' => "La tarea \"{task_title}\" ha sido reabierta por {reopened_by}.\n\nNota: {note}",
            ],
        ];

        foreach ($templates as $template) {
            MessageTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
