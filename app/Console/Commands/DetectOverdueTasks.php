<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DetectOverdueTasks extends Command
{
    protected $signature = 'tasks:detect-overdue';

    protected $description = 'Retired legacy command kept as a no-op for backward compatibility';

    public function handle(): int
    {
        $this->info('La detección automática de tareas vencidas fue retirada. Usa el resumen diario para monitorear tareas vencidas.');

        return self::SUCCESS;
    }
}
