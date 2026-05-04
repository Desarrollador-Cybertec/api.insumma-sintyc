<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DetectInactiveTasks extends Command
{
    protected $signature = 'tasks:detect-inactive';

    protected $description = 'Retired legacy command kept as a no-op for backward compatibility';

    public function handle(): int
    {
        $this->info('La detección de inactividad fue retirada. Usa el resumen diario para revisar tareas sin empezar y vencidas.');

        return self::SUCCESS;
    }
}
