<?php

namespace App\Enums;

enum UpdateTypeEnum: string
{
    case PROGRESS = 'progress';
    case EVIDENCE = 'evidence';
    case NOTE = 'note';

    public function label(): string
    {
        return match ($this) {
            self::PROGRESS => 'Avance',
            self::EVIDENCE => 'Evidencia',
            self::NOTE => 'Nota',
        };
    }
}
