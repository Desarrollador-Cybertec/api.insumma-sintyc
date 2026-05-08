<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ToggleQueueCommand extends Command
{
    protected $signature = 'queue:toggle
                            {action? : "pause" para pausar, "resume" para reanudar, omitir para alternar}';

    protected $description = 'Pausa o reanuda el procesamiento de todos los trabajos en cola de forma indefinida. El crontab debe llamar a queue:run en lugar de queue:work.';

    public function handle(): int
    {
        $action = $this->argument('action');

        if ($action && !in_array($action, ['pause', 'resume'])) {
            $this->error("Acción inválida. Use 'pause', 'resume', o no indique nada para alternar.");
            return self::FAILURE;
        }

        $isPaused = Cache::get('queue_paused', false);

        $shouldPause = match ($action) {
            'pause'  => true,
            'resume' => false,
            default  => !$isPaused,
        };

        if ($shouldPause === $isPaused) {
            $state = $isPaused ? 'pausada' : 'activa';
            $this->info("La cola ya se encuentra {$state}. No se realizaron cambios.");
            return self::SUCCESS;
        }

        if ($shouldPause) {
            Cache::forever('queue_paused', true);
            $this->warn('Cola PAUSADA. Los trabajos en cola quedarán en espera hasta que se reanude.');
            $this->line('  Para reanudar: php artisan queue:toggle resume');
        } else {
            Cache::forget('queue_paused');
            $this->info('Cola REANUDADA. Los trabajos en cola volverán a procesarse.');
        }

        return self::SUCCESS;
    }
}
