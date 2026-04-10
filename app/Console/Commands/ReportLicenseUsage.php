<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class ReportLicenseUsage extends Command
{
    protected $signature = 'license:check';

    protected $description = 'Verificar que la conexión con el Management System está activa';

    public function handle(LicenseService $licenseService): int
    {
        try {
            // Prueba con create_area que no consume cupo pero verifica conexión y estado
            $licenseService->authorize('create_area');
            $this->info('Management System: conexión OK, suscripción activa.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Management System: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
