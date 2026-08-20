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
                'key'         => 'alert_on_due_date',
                'value'       => '1',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Enviar alerta el día de vencimiento',
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
