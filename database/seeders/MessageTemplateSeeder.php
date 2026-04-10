<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // La vista Blade (emails/tape-notification.blade.php) agrega automáticamente
        // el saludo "¡Hola, {primer_nombre}!", el botón y el cierre "Saludos, S!NTyC".
        // Los bodies aquí solo contienen el cuerpo del mensaje.

        $templates = [
            // Variables: {task_title}, {user_name}, {priority}, {due_date}
            [
                'slug'    => 'new_assignment',
                'name'    => 'Nueva asignación de tarea',
                'subject' => 'Se te ha asignado una nueva tarea: {task_title}',
                'body'    => "Se te ha asignado la tarea \"{task_title}\".\n\nPrioridad: {priority}\nFecha límite: {due_date}\n\nProcura realizarla antes de la fecha límite. Por favor revisa los detalles en la plataforma.",
            ],
            // Variables: {task_title}, {user_name}, {delegated_by}, {priority}, {due_date}
            [
                'slug'    => 'task_delegated',
                'name'    => 'Tarea delegada',
                'subject' => 'Tarea delegada: {task_title}',
                'body'    => "Se te ha delegado la tarea \"{task_title}\" por {delegated_by}.\n\nPrioridad: {priority}\nFecha límite: {due_date}",
            ],
            // Variables: {task_title}, {user_name}, {approved_by}
            [
                'slug'    => 'task_approved',
                'name'    => 'Tarea aprobada',
                'subject' => 'Tu tarea "{task_title}" ha sido aprobada',
                'body'    => "La tarea \"{task_title}\" ha sido aprobada.\n\n¡Buen trabajo!",
            ],
            // Variables: {task_title}, {user_name}, {rejected_by}, {rejection_reason}
            [
                'slug'    => 'task_rejected',
                'name'    => 'Tarea rechazada',
                'subject' => 'Tu tarea "{task_title}" necesita correcciones',
                'body'    => "La tarea \"{task_title}\" ha sido rechazada y requiere correcciones.\n\nMotivo: {rejection_reason}\n\nPor favor revisa y vuelve a enviar.",
            ],
            // Variables: {task_title}, {user_name}, {days_remaining}, {due_date}
            [
                'slug'    => 'task_reminder',
                'name'    => 'Recordatorio de tarea próxima a vencer',
                'subject' => 'Recordatorio: La tarea "{task_title}" vence pronto',
                'body'    => "La tarea \"{task_title}\" vence en {days_remaining} día(s).\n\nFecha límite: {due_date}\n\nAsegúrate de completarla a tiempo.",
            ],
            // Variables: {task_title}, {user_name}, {days_overdue}, {due_date}
            [
                'slug'    => 'task_overdue',
                'name'    => 'Tarea vencida',
                'subject' => 'Alerta: La tarea "{task_title}" está vencida',
                'body'    => "La tarea \"{task_title}\" está vencida desde hace {days_overdue} día(s).\n\nFecha límite original: {due_date}\n\nPor favor actualiza el estado o contacta a tu encargado.",
            ],
            // Variables: {task_title}, {user_name}, {cancelled_by}, {note}
            [
                'slug'    => 'task_cancelled',
                'name'    => 'Tarea cancelada',
                'subject' => 'Tarea cancelada: {task_title}',
                'body'    => "La tarea \"{task_title}\" ha sido cancelada por {cancelled_by}.\n\nMotivo: {note}",
            ],
            // Variables: {task_title}, {user_name}, {completed_by}
            [
                'slug'    => 'task_completed',
                'name'    => 'Tarea completada',
                'subject' => 'Tarea completada: {task_title}',
                'body'    => "{completed_by} completó la tarea \"{task_title}\".\n\n¡Buen trabajo al equipo!",
            ],
            // Variables: {task_title}, {user_name}, {submitted_by}
            [
                'slug'    => 'task_submitted_review',
                'name'    => 'Tarea enviada a revisión',
                'subject' => 'Revisión requerida: {task_title}',
                'body'    => "{submitted_by} envió la tarea \"{task_title}\" a revisión.\n\nRequiere tu aprobación en la plataforma.",
            ],
            // Variables: {task_title}, {user_name}, {reopened_by}, {note}
            [
                'slug'    => 'task_reopened',
                'name'    => 'Tarea reabierta',
                'subject' => 'Tarea reabierta: {task_title}',
                'body'    => "La tarea \"{task_title}\" ha sido reabierta por {reopened_by}.\n\nNota: {note}",
            ],
            // Variables: {user_name}, {date}, {summary_content}
            [
                'slug'    => 'daily_summary',
                'name'    => 'Resumen diario de tareas',
                'subject' => 'Resumen diario de tareas — {date}',
                'body'    => "{summary_content}",
            ],
            // Variables: {user_name}, {inactivity_days}, {task_list}
            [
                'slug'    => 'inactivity_alert',
                'name'    => 'Alerta por inactividad',
                'subject' => 'Alerta: Tareas sin avance desde hace {inactivity_days} días',
                'body'    => "Tienes tareas sin reportar avance en los últimos {inactivity_days} días:\n\n{task_list}\n\nPor favor actualiza el estado de estas tareas.",
            ],
        ];

        foreach ($templates as $template) {
            MessageTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name'    => $template['name'],
                    'subject' => $template['subject'],
                    'body'    => $template['body'],
                    'active'  => true,
                ],
            );
        }
    }
}
