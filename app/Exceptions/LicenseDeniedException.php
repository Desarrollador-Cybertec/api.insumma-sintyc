<?php

namespace App\Exceptions;

class LicenseDeniedException extends LicenseException
{
    public function __construct(string $message = 'El límite del plan actual ha sido alcanzado. Para continuar, actualiza tu suscripción.')
    {
        parent::__construct($message, 403);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'license_denied',
        ], 403);
    }
}
