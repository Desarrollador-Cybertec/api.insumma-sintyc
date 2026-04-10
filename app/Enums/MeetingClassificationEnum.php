<?php

namespace App\Enums;

enum MeetingClassificationEnum: string
{
    case STRATEGIC = 'strategic';
    case OPERATIONAL = 'operational';
    case FOLLOW_UP = 'follow_up';
    case REVIEW = 'review';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::STRATEGIC => 'Estratégica',
            self::OPERATIONAL => 'Operativa',
            self::FOLLOW_UP => 'Seguimiento',
            self::REVIEW => 'Revisión',
            self::OTHER => 'Otra',
        };
    }
}
