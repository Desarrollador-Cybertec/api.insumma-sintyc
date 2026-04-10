<?php

namespace App\Enums;

enum NotificationChannelEnum: string
{
    case DATABASE = 'database';
    case MAIL = 'mail';
    case BROADCAST = 'broadcast';

    public function label(): string
    {
        return match ($this) {
            self::DATABASE => 'Base de datos',
            self::MAIL => 'Correo',
            self::BROADCAST => 'Tiempo real',
        };
    }
}
