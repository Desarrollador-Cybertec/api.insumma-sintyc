<?php

namespace App\Enums;

enum ProcessingStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PROCESSING => 'Procesando',
            self::READY => 'Listo',
            self::FAILED => 'Fallido',
        };
    }
}
