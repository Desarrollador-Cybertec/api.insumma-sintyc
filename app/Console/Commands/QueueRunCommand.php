<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueRunCommand extends Command
{
    protected $signature = 'queue:run
                            {--queue=default : Nombre de la cola a procesar}
                            {--tries=3 : Número máximo de intentos por job}
                            {--timeout=60 : Segundos máximos de ejecución por job}';

    protected $description = 'Procesa la cola si no está pausada. Usar este comando en el crontab en lugar de queue:work.';

    public function handle(): int
    {
        if (Cache::get('queue_paused', false)) {
            $this->warn('Cola en pausa. No se procesaron trabajos.');
            $this->line('  Para reanudar: php artisan queue:toggle resume');
            return self::SUCCESS;
        }

        $queue   = $this->option('queue');
        $tries   = $this->option('tries');
        $timeout = $this->option('timeout');

        return $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--queue'           => $queue,
            '--tries'           => $tries,
            '--timeout'         => $timeout,
        ]);
    }
}
