<?php

// Helpers globales de S!NTyC

use Carbon\CarbonInterface;
use Spatie\Holidays\Holidays;

if (! function_exists('is_business_day')) {
    /**
     * Determina si una fecha es día hábil en Colombia:
     * no es fin de semana ni festivo nacional.
     */
    function is_business_day(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! Holidays::for(country: 'co')->isHoliday($date);
    }
}
