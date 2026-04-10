<?php

namespace App\Exceptions;

class LicenseExpiredException extends LicenseException
{
    public function __construct(string $message = 'La suscripción ha vencido. No es posible realizar nuevas operaciones hasta que sea renovada.')
    {
        parent::__construct($message, 403);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'license_expired',
        ], 403);
    }
}
