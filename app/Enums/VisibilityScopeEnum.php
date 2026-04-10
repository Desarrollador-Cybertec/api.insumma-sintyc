<?php

namespace App\Enums;

enum VisibilityScopeEnum: string
{
    case USER = 'user';
    case AREA = 'area';
    case TASK = 'task';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Usuario',
            self::AREA => 'Área',
            self::TASK => 'Tarea',
            self::SYSTEM => 'Sistema',
        };
    }
}
