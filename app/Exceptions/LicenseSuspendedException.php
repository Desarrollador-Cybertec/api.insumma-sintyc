<?php

namespace App\Exceptions;

class LicenseSuspendedException extends LicenseException
{
    public function __construct(string $message = 'La suscripción está suspendida. Contacta al administrador de tu cuenta para resolver el problema.')
    {
        parent::__construct($message, 403);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type'    => 'license_suspended',
        ], 403);
    }
}
