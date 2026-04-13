<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class SyncLicenseUsers extends Command
{
    protected $signature = 'license:sync-users';

    protected $description = 'Corregir el conteo de usuarios activos en el Management System';

    public function handle(LicenseService $licenseService): int
    {
        $dbCount = User::where('active', true)->count();

        $this->info("Usuarios activos en DB: {$dbCount}");

        $usage = $licenseService->getCurrentUsage();

        if ($usage === null) {
            $this->error('No se pudo obtener el conteo actual del Management System. Verifica la conexión.');
            return self::FAILURE;
        }

        $msCount = $usage['current'] ?? null;

        if ($msCount === null) {
            $this->error('El Management System no devolvió el campo "current". No es posible calcular la corrección.');
            return self::FAILURE;
        }

        $this->info("Conteo en Management System: {$msCount}");

        $delta = $dbCount - $msCount;

        if ($delta === 0) {
            $this->info('El conteo ya está sincronizado. No se requiere corrección.');
            return self::SUCCESS;
        }

        $sign = $delta > 0 ? "+{$delta}" : (string) $delta;
        $this->warn("Diferencia detectada: {$sign}. Enviando corrección al Management System...");

        $licenseService->reportUsageDelta($delta);

        $this->info("Corrección enviada: {$sign}. El MS ahora debería reflejar {$dbCount} usuarios activos.");

        return self::SUCCESS;
    }
}
