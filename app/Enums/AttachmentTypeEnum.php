<?php

namespace App\Enums;

enum AttachmentTypeEnum: string
{
    case EVIDENCE = 'evidence';
    case SUPPORT = 'support';
    case FINAL_DELIVERY = 'final_delivery';

    public function label(): string
    {
        return match ($this) {
            self::EVIDENCE => 'Evidencia',
            self::SUPPORT => 'Soporte',
            self::FINAL_DELIVERY => 'Entrega final',
        };
    }
}
