<?php

namespace App\Enums;

enum CommentTypeEnum: string
{
    case COMMENT = 'comment';
    case PROGRESS = 'progress';
    case COMPLETION_NOTE = 'completion_note';
    case REJECTION_NOTE = 'rejection_note';
    case CANCELLATION_NOTE = 'cancellation_note';
    case REOPEN_NOTE = 'reopen_note';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::COMMENT => 'Comentario',
            self::PROGRESS => 'Progreso',
            self::COMPLETION_NOTE => 'Nota de cierre',
            self::REJECTION_NOTE => 'Nota de rechazo',
            self::CANCELLATION_NOTE => 'Nota de cancelación',
            self::REOPEN_NOTE => 'Nota de reapertura',
            self::SYSTEM => 'Sistema',
        };
    }
}
