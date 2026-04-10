<?php

namespace App\Exceptions;

class LicenseSystemUnavailableException extends LicenseException
{
    public function __construct(string $message = 'El sistema de licencias no está disponible en este momento. La operación fue bloqueada por seguridad.')
    {
        parent::__construct($message, 503);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'license_unavailable',
        ], 503);
    }
}
